<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PreschoolDisabilityReport extends BaseReport
{
    public function __construct(
        protected \App\Services\GeoService $geoService,
        protected \App\Services\ReportFormatService $formatService,
    ) {}

    public function export(Request $req)
    {
        // ===== 1) Input
        $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1]     = array_map('intval', explode('-', $academic)); // ví dụ '2024-2025' => 2024
        $scheme   = (string)$req->input('scheme', 'old');
        $geo      = (array)$req->input('geo', []);

        // ===== 2) Template
        $cfg = $this->templateCfg(); // nhớ để đúng lastCol
        $wb  = IOFactory::load($cfg['path']);
        $sh  = $wb->getActiveSheet();

        // Header
        $sh->setCellValue($cfg['header']['province'],  $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        // $sh->setCellValue($cfg['header']['district'],  $this->geoService->geoNameFromId($geo['district_id'] ?? null));
        $sh->setCellValue($cfg['header']['ward'],      $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
        $sh->setCellValue($cfg['dateTop'],             $this->formatService->top_date());

        // ===== 3) Dữ liệu
        $rows = $this->queryPreschoolDisabilityByBirthYear($academic, $scheme, $geo);

        // Chuẩn bị vùng dữ liệu (mặc định 6 dòng tuổi 0..5)
        $startRow = $cfg['startRow'];                 // vd: 8
        $sumRow   = $cfg['sumRow'];                   // dòng "Tổng" có sẵn
        $need     = max(0, count($rows) - 1);
        // if ($need > 0) {
        //     $sh->insertNewRowBefore($sumRow, $need);
        //     for ($i = 1; $i <= $need; $i++) {
        //         $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $cfg['lastCol']);
        //     }
        //     $sumRow += $need;
        // }

        // ===== 4) Ghi từng dòng
        $r = $startRow; $stt = 1;
        foreach ($rows as $it) {
            // A: STT
            $this->setCellValue($sh, 'A'.$r, $stt);

            // B: Năm sinh
            $this->setCellValue($sh, 'B'.$r, $it['birth_year']);

            // C: Độ tuổi (theo năm)
            $this->setCellValue($sh, 'C'.$r, $it['age']);

            // D: Tổng số người khuyết tật
            $this->setCellValue($sh, 'D'.$r, $it['total_disabled']);

            // E..L: 8 dạng tật
            $this->setCellValue($sh, 'E'.$r, $it['van_dong']);
            $this->setCellValue($sh, 'F'.$r, $it['nghe_noi']);
            $this->setCellValue($sh, 'G'.$r, $it['nhin']);
            $this->setCellValue($sh, 'H'.$r, $it['than_kinh']);
            $this->setCellValue($sh, 'I'.$r, $it['tri_tue']);
            $this->setCellValue($sh, 'J'.$r, $it['tu_ky']);
            $this->setCellValue($sh, 'K'.$r, $it['hoc_tap']);
            $this->setCellValue($sh, 'L'.$r, $it['khac']);

            // M: Có khả năng học tập
            $this->setCellValue($sh, 'M'.$r, $it['can_learn']);

            // N: Số HS khuyết tật tiếp cận giáo dục (MN)
            $this->setCellValue($sh, 'N'.$r, $it['access_total']);

            // O: Tỷ lệ = N / D
            $this->setCellValue(
                $sh, 'O'.$r,
                sprintf('=IF(D%d=0,"",ROUND(N%d/D%d*100,2))', $r,$r,$r)
            );

            $r++; $stt++;
        }

        // ===== 5) Dòng “Tổng”
        foreach (['D','E','F','G','H','I','J','K','L','M','N'] as $col) {
            $this->setCellValue(
                $sh, $col.$sumRow,
                sprintf('=SUM(%s%d:%s%d)', $col, $startRow, $col, $sumRow-1)
            );
        }
        $this->setCellValue(
            $sh, 'O'.$sumRow,
            sprintf('=IF(D%d=0,"",ROUND(N%d/D%d*100,2))', $sumRow,$sumRow,$sumRow)
        );

        // ===== 6) Định dạng & viền
        $this->drawThinBorders($sh, $startRow, $sumRow, 'A', $cfg['lastCol']);
        $sh->getStyle("A{$startRow}:{$cfg['lastCol']}{$sumRow}")
           ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sh->getStyle("A{$startRow}:A{$sumRow}")
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("B{$startRow}:B{$sumRow}")
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("C{$startRow}:O{$sumRow}")
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("O{$startRow}:O{$sumRow}")
           ->getNumberFormat()->setFormatCode('0.00');

        // Footer ngày tháng năm (tuỳ template của bạn)
        $lastrow  = $sumRow + 2;
        $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
        $this->safeMerge($sh, "K{$lastrow}:O{$lastrow}");
        $this->setCellValue($sh, 'K'.$lastrow, $this->formatService->end_date($wardName));
        $sh->getStyle("K{$lastrow}:O{$lastrow}")
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ===== 7) Xuất file
        return response()->streamDownload(function () use ($wb) {
            $w = IOFactory::createWriter($wb, 'Xlsx');
            $w->setPreCalculateFormulas(false);
            $w->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Lấy số liệu khuyết tật mầm non (tuổi 0..5) theo năm sinh, dựa trên view v_survey_detail_flat.
     */
protected function queryPreschoolDisabilityByBirthYear(string $academic, string $scheme, array $geo): array
{
    [$y1] = array_map('intval', explode('-', $academic));

    // 6 năm sinh: y1..y1-5 (tuổi = y1 - dob_year)
    $birthYears = [];
    for ($a = 0; $a <= 5; $a++) $birthYears[] = $y1 - $a;

    // Ít nhất 1 trong 8 dạng tật
    $disabledCond = "("
        ."COALESCE(v.dis_VAN_DONG,0)+COALESCE(v.dis_NGHE_NOI,0)+COALESCE(v.dis_NHIN,0)"
        ."+COALESCE(v.dis_THAN_KINH,0)+COALESCE(v.dis_TRI_TUE,0)+COALESCE(v.dis_HOC_TAP,0)"
        ."+COALESCE(v.dis_TU_KY,0)+COALESCE(v.dis_KHAC,0)"
        .") > 0";

    // Trường MN / nhóm trẻ / mẫu giáo
    $PRE_TYPES = '4,5,6,7';

    $qb = DB::table('v_survey_detail_flat as v')
        // CẦN join lại để lấy school_type_id
        ->leftJoin('schools as s', function($j) {
            $j->on('s.code', '=', 'v.school_code')
              ->where(function($w) {
                  $w->whereNull('s.is_dissolved')
                    ->orWhere('s.is_dissolved', false)
                    ->orWhere('s.is_dissolved', 0);
              });
        })
        ->where('v.academic_year', $academic)
        ->whereIn('v.dob_year', $birthYears);

    // Lọc địa bàn theo old/new
    if (!empty($geo)) {
        if (($geo['province_id'] ?? null) || ($geo['district_id'] ?? null) || ($geo['ward_id'] ?? null)) {
            if ($scheme === 'old') {
                if (!empty($geo['province_id'])) $qb->where('v.old_province_id', $geo['province_id']);
                if (!empty($geo['district_id'])) $qb->where('v.old_district_id', $geo['district_id']);
                if (!empty($geo['ward_id']))     $qb->where('v.old_ward_id',     $geo['ward_id']);
            } else {
                if (!empty($geo['province_id'])) $qb->where('v.new_province_id', $geo['province_id']);
                if (!empty($geo['district_id'])) $qb->where('v.new_district_id', $geo['district_id'] ?? null);
                if (!empty($geo['ward_id']))     $qb->where('v.new_ward_id',     $geo['ward_id']);
            }
        }
        if (!empty($geo['village_id'])) $qb->where('v.village_id', $geo['village_id']);
    }

    $rows = $qb->selectRaw("
            v.dob_year AS birth_year,
            (CAST($y1 AS SIGNED) - CAST(v.dob_year AS SIGNED)) AS age,

            -- Tổng số người khuyết tật (đếm theo người)
            COUNT(DISTINCT IF($disabledCond, v.person_id, NULL)) AS total_disabled,

            -- 8 dạng tật (đếm theo người; mỗi cột là 1 cờ)
            SUM(COALESCE(v.dis_VAN_DONG,0)) AS van_dong,
            SUM(COALESCE(v.dis_NGHE_NOI,0)) AS nghe_noi,
            SUM(COALESCE(v.dis_NHIN,0))     AS nhin,
            SUM(COALESCE(v.dis_THAN_KINH,0)) AS than_kinh,
            SUM(COALESCE(v.dis_TRI_TUE,0))   AS tri_tue,
            SUM(COALESCE(v.dis_TU_KY,0))     AS tu_ky,
            SUM(COALESCE(v.dis_HOC_TAP,0))   AS hoc_tap,
            SUM(COALESCE(v.dis_KHAC,0))      AS khac,

            -- Có khả năng học tập (trong số người khuyết tật)
            COUNT(DISTINCT IF($disabledCond AND COALESCE(v.dis_can_learn,0)=1, v.person_id, NULL)) AS can_learn,

            -- Tiếp cận GD mầm non: disabled & đang học trường loại 4/5/6/7
            COUNT(DISTINCT IF($disabledCond AND s.school_type_id IN ($PRE_TYPES), v.person_id, NULL)) AS access_total
        ")
        ->groupBy('birth_year')
        ->orderBy('birth_year', 'desc')
        ->get();

    // Đảm bảo đủ 6 dòng y1..y1-5
    $byYear = collect();
    foreach ($rows as $r) $byYear[$r->birth_year] = $r;

    $out = [];
    foreach ($birthYears as $by) {
        $age = $y1 - $by;
        $row = $byYear->get($by);
        $out[] = [
            'birth_year'     => $by,
            'age'            => $age,
            'total_disabled' => (int)($row->total_disabled ?? 0),

            'van_dong'       => (int)($row->van_dong ?? 0),
            'nghe_noi'       => (int)($row->nghe_noi ?? 0),
            'nhin'           => (int)($row->nhin ?? 0),
            'than_kinh'      => (int)($row->than_kinh ?? 0),
            'tri_tue'        => (int)($row->tri_tue ?? 0),
            'tu_ky'          => (int)($row->tu_ky ?? 0),
            'hoc_tap'        => (int)($row->hoc_tap ?? 0),
            'khac'           => (int)($row->khac ?? 0),

            'can_learn'      => (int)($row->can_learn ?? 0),
            'access_total'   => (int)($row->access_total ?? 0),
        ];
    }
    return $out;
}


    protected function templateCfg(): array
    {
        return [
            'path' => resource_path('excel/1.6.MN.TK.KhuyetTat.xlsx'),
            'header' => [
                'province' => 'C1',
                'ward' => 'C2',
               
            ],
            'dateTop'  => 'E3',   // chỉnh theo template của bạn
            'startRow' => 8,      // dòng dữ liệu đầu (STT=1)
            'sumRow'   => 14,     // dòng “Tổng” có sẵn trong template trống
            'lastCol'  => 'P',    // cột cuối cùng (Tỷ lệ)
        ];
    }
}
