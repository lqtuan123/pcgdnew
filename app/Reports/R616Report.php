<?php
namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Calculation\Calculation;
use App\Services\GeoService;
use App\Services\ReportFormatService;
use PhpOffice\PhpSpreadsheet\Style\Border;
class R616Report extends BaseReport
{
      public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    public function export(Request $req)
    {
        // ===== 1) Input
        $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        $scheme   = (string)$req->input('scheme','old'); // old/new
        $geo      = (array)$req->input('geo', []);

        if (method_exists(Calculation::getInstance(), 'setCalculationEnabled')) {
            Calculation::getInstance()->setCalculationEnabled(false);
        }

        // ===== 2) Template (chỉnh lại path/ô nếu khác)
        $cfg = [
            'path'      => resource_path('excel/6.16.report.xlsx'),
            'header'    => ['province' => 'C1', 'ward' => 'C2'],
            'startRow'  => 8,        // dòng đầu tiên của dữ liệu (sau hàng tiêu đề con)
            'lastCol'   => 'Z',      // A..Z (khối cuối cùng là Y,Z)
            'footerDateRange' => 'U16:Z16', // "Cư Huê, ngày ..." (chỉnh theo file)
        ];

        $wb = IOFactory::load($cfg['path']);
        /** @var Worksheet $sh */
        $sh = $wb->getActiveSheet();

        // Header
        $sh->setCellValue($cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        $sh->setCellValue($cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));

        // ===== 3) Dữ liệu
        $rows = $this->queryIlliteracyM2ByEthnicity($academic, $scheme, $geo);

        // Chèn thêm dòng nếu cần (nhân bản định dạng hàng mẫu)
        $r0   = $cfg['startRow'];
        $need = max(0, count($rows) - 1);
        if ($need > 0) {
            $sh->insertNewRowBefore($r0 + 1, $need);
            for ($i = 1; $i <= $need; $i++) {
                $this->replicateRowFormatMerged($sh, $r0, $r0 + $i, $cfg['lastCol']);
            }
        }

        // Bản đồ cột cho 4 nhóm
        $map = [
            '15_25' => ['tot' => ['C','D'], 'ill' => ['E','F'], 'rate' => ['G','H']],
            '15_35' => ['tot' => ['I','J'], 'ill' => ['K','L'], 'rate' => ['M','N']],
            '15_60' => ['tot' => ['O','P'], 'ill' => ['Q','R'], 'rate' => ['S','T']],
            'gt_60' => ['tot' => ['U','V'], 'ill' => ['W','X'], 'rate' => ['Y','Z']],
        ];

        // ===== 4) Ghi dữ liệu
        $r = $r0; $stt = 1;
        foreach ($rows as $it) {
            $this->setCellValue($sh, "A{$r}", $stt);
            $this->setCellValue($sh, "B{$r}", $it['ethnicity']);

            // 15–25
            $this->setCellValue($sh, $map['15_25']['tot'][0].$r, $it['pop_15_25']);
            $this->setCellValue($sh, $map['15_25']['tot'][1].$r, $it['pop_15_25_f']);
            $this->setCellValue($sh, $map['15_25']['ill'][0].$r, $it['ill_15_25']);
            $this->setCellValue($sh, $map['15_25']['ill'][1].$r, $it['ill_15_25_f']);
            $sh->setCellValue("G{$r}", '=IF(C'.$r.'=0,"",ROUND(E'.$r.'/C'.$r.'*100,2))');
            $sh->setCellValue("H{$r}", '=IF(D'.$r.'=0,"",ROUND(F'.$r.'/D'.$r.'*100,2))');

            // 15–35
            $this->setCellValue($sh, $map['15_35']['tot'][0].$r, $it['pop_15_35']);
            $this->setCellValue($sh, $map['15_35']['tot'][1].$r, $it['pop_15_35_f']);
            $this->setCellValue($sh, $map['15_35']['ill'][0].$r, $it['ill_15_35']);
            $this->setCellValue($sh, $map['15_35']['ill'][1].$r, $it['ill_15_35_f']);
            $sh->setCellValue("M{$r}", '=IF(I'.$r.'=0,"",ROUND(K'.$r.'/I'.$r.'*100,2))');
            $sh->setCellValue("N{$r}", '=IF(J'.$r.'=0,"",ROUND(L'.$r.'/J'.$r.'*100,2))');

            // 15–60
            $this->setCellValue($sh, $map['15_60']['tot'][0].$r, $it['pop_15_60']);
            $this->setCellValue($sh, $map['15_60']['tot'][1].$r, $it['pop_15_60_f']);
            $this->setCellValue($sh, $map['15_60']['ill'][0].$r, $it['ill_15_60']);
            $this->setCellValue($sh, $map['15_60']['ill'][1].$r, $it['ill_15_60_f']);
            $sh->setCellValue("S{$r}", '=IF(O'.$r.'=0,"",ROUND(Q'.$r.'/O'.$r.'*100,2))');
            $sh->setCellValue("T{$r}", '=IF(P'.$r.'=0,"",ROUND(R'.$r.'/P'.$r.'*100,2))');

            // >60
            $this->setCellValue($sh, $map['gt_60']['tot'][0].$r, $it['pop_gt_60']);
            $this->setCellValue($sh, $map['gt_60']['tot'][1].$r, $it['pop_gt_60_f']);
            $this->setCellValue($sh, $map['gt_60']['ill'][0].$r, $it['ill_gt_60']);
            $this->setCellValue($sh, $map['gt_60']['ill'][1].$r, $it['ill_gt_60_f']);
            $sh->setCellValue("Y{$r}", '=IF(U'.$r.'=0,"",ROUND(W'.$r.'/U'.$r.'*100,2))');
            $sh->setCellValue("Z{$r}", '=IF(V'.$r.'=0,"",ROUND(X'.$r.'/V'.$r.'*100,2))');

            $r++; $stt++;
        }

        // ===== 5) Dòng Tổng cộng
        $sumRow = $r;
        $this->setCellValue($sh, "A{$sumRow}", '');
        $this->setCellValue($sh, "B{$sumRow}", 'Tổng cộng');

        // Cộng cho tất cả cột số (trừ cột tỷ lệ)
        $sumCols = ['C','D','E','F','I','J','K','L','O','P','Q','R','U','V','W','X'];
        foreach ($sumCols as $col) {
            $this->setCellValue($sh, "{$col}{$sumRow}", sprintf('=SUM(%1$s%2$d:%1$s%3$d)', $col, $r0, $r-1));
        }

        // Công thức tỷ lệ ở dòng Tổng cộng
        $rateMap = [
            ['num'=>'E', 'den'=>'C', 'out'=>'G'],
            ['num'=>'F', 'den'=>'D', 'out'=>'H'],
            ['num'=>'K', 'den'=>'I', 'out'=>'M'],
            ['num'=>'L', 'den'=>'J', 'out'=>'N'],
            ['num'=>'Q', 'den'=>'O', 'out'=>'S'],
            ['num'=>'R', 'den'=>'P', 'out'=>'T'],
            ['num'=>'W', 'den'=>'U', 'out'=>'Y'],
            ['num'=>'X', 'den'=>'V', 'out'=>'Z'],
        ];
        foreach ($rateMap as $m) {
            $this->setCellValue(
                $sh, "{$m['out']}{$sumRow}",
                sprintf('=IF(%1$s%3$d=0,"",ROUND(%2$s%3$d/%1$s%3$d*100,2))', $m['den'], $m['num'], $sumRow)
            );
        }

        // ===== 6) Định dạng
        $this->drawThinBorders($sh, $r0, $sumRow, 'A', $cfg['lastCol']);
        // Căn lề
        $sh->getStyle("A{$r0}:{$cfg['lastCol']}{$sumRow}")
           ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sh->getStyle("A{$r0}:A{$sumRow}")
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("C{$r0}:F{$sumRow}")
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("I{$r0}:L{$sumRow}")
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("O{$r0}:R{$sumRow}")
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("U{$r0}:X{$sumRow}")
           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        // Tỷ lệ 2 chữ số thập phân
         $sh->getStyle("C{$r0}:Z".($sumRow-1))
           ->getNumberFormat()->setFormatCode('0;-0;;@');

        foreach (['G','H','M','N','S','T','Y','Z'] as $col) {
            $sh->getStyle("{$col}{$r0}:{$col}{$sumRow}")
               ->getNumberFormat()  ->setFormatCode('0.00;-0.00;;@');   // zero-section rỗng
        }
        // Ẩn số 0 cho các cột đếm (để trống khi = 0)
       
        // Footer ngày tháng
       // Footer ngày tháng
        $footerRow = $sumRow + 2;
        $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
        $this->safeMerge($sh, "S{$footerRow}:W{$footerRow}");
        $this->setCellValue($sh, "S{$footerRow}", $this->formatService->end_date($wardName));
        $sh->getStyle("S{$footerRow}:W{$footerRow}")
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
     * Gom theo dân tộc (loại Kinh), tính đếm & mù chữ mức 2 cho 4 nhóm tuổi.
     * Chỉ dùng v_survey_detail_flat.
     */
    protected function queryIlliteracyM2ByEthnicity(string $academic, string $scheme, array $geo): array
    {
        [$y1] = array_map('intval', explode('-', $academic));

        $yrs_15_25 = range($y1-25, $y1-15);
        $yrs_15_35 = range($y1-35, $y1-15);
        $yrs_15_60 = range($y1-60, $y1-15);
        $y_ge_60   = $y1 - 60;

        // Cột địa bàn theo scheme
        $colProvince = $scheme === 'old' ? 'v.old_province_id' : 'v.new_province_id';
        $colWard     = $scheme === 'old' ? 'v.old_ward_id'     : 'v.new_ward_id';

        // Điều kiện mù chữ mức 2
        $ill = "("
             . "v.education_block_code IS NULL and UPPER(COALESCE(v.graduation_level_name,'')) NOT IN ('TH','THCS','THPT')"
             . " OR (NULLIF(v.bo_hoc_lop,'') IS NOT NULL AND CAST(v.bo_hoc_lop AS UNSIGNED) <= 5)"
             . " OR COALESCE(v.lit_tai_mu_muc,0) = 2"
             . ")";

        $q = DB::table('v_survey_detail_flat as v')
            ->where('v.academic_year', $academic)
            ->whereRaw('v.ethnicity IS NOT NULL')
            ->whereRaw("UPPER(COALESCE(v.ethnicity,'')) <> 'KINH'");

        if (!empty($geo['province_id'])) $q->where($colProvince, $geo['province_id']);
        if (!empty($geo['ward_id']))     $q->where($colWard,     $geo['ward_id']);

        $rows = $q->selectRaw("
           COALESCE(v.ethnicity,'Không rõ') AS ethnicity,

            -- 15-25
            SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_25).") THEN 1 ELSE 0 END) AS pop_15_25,
            SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_25).") AND v.is_female=1 THEN 1 ELSE 0 END) AS pop_15_25_f,
            SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_25).") AND ($ill) THEN 1 ELSE 0 END) AS ill_15_25,
            SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_25).") AND v.is_female=1 AND ($ill) THEN 1 ELSE 0 END) AS ill_15_25_f,

            -- 15-35
            SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_35).") THEN 1 ELSE 0 END) AS pop_15_35,
            SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_35).") AND v.is_female=1 THEN 1 ELSE 0 END) AS pop_15_35_f,
            SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_35).") AND ($ill) THEN 1 ELSE 0 END) AS ill_15_35,
            SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_35).") AND v.is_female=1 AND ($ill) THEN 1 ELSE 0 END) AS ill_15_35_f,

            -- 15-60
            SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_60).") THEN 1 ELSE 0 END) AS pop_15_60,
            SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_60).") AND v.is_female=1 THEN 1 ELSE 0 END) AS pop_15_60_f,
            SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_60).") AND ($ill) THEN 1 ELSE 0 END) AS ill_15_60,
            SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_60).") AND v.is_female=1 AND ($ill) THEN 1 ELSE 0 END) AS ill_15_60_f,

            -- >60
            SUM(CASE WHEN v.dob_year < {$y_ge_60} THEN 1 ELSE 0 END) AS pop_gt_60,
            SUM(CASE WHEN v.dob_year < {$y_ge_60} AND v.is_female=1 THEN 1 ELSE 0 END) AS pop_gt_60_f,
            SUM(CASE WHEN v.dob_year < {$y_ge_60} AND ($ill) THEN 1 ELSE 0 END) AS ill_gt_60,
            SUM(CASE WHEN v.dob_year < {$y_ge_60} AND v.is_female=1 AND ($ill) THEN 1 ELSE 0 END) AS ill_gt_60_f
        ")
        ->groupBy('ethnicity')
        ->orderBy('ethnicity')
        ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'ethnicity'     => $r->ethnicity,

                'pop_15_25'     => (int)$r->pop_15_25,
                'pop_15_25_f'   => (int)$r->pop_15_25_f,
                'ill_15_25'     => (int)$r->ill_15_25,
                'ill_15_25_f'   => (int)$r->ill_15_25_f,

                'pop_15_35'     => (int)$r->pop_15_35,
                'pop_15_35_f'   => (int)$r->pop_15_35_f,
                'ill_15_35'     => (int)$r->ill_15_35,
                'ill_15_35_f'   => (int)$r->ill_15_35_f,

                'pop_15_60'     => (int)$r->pop_15_60,
                'pop_15_60_f'   => (int)$r->pop_15_60_f,
                'ill_15_60'     => (int)$r->ill_15_60,
                'ill_15_60_f'   => (int)$r->ill_15_60_f,

                'pop_gt_60'     => (int)$r->pop_gt_60,
                'pop_gt_60_f'   => (int)$r->pop_gt_60_f,
                'ill_gt_60'     => (int)$r->ill_gt_60,
                'ill_gt_60_f'   => (int)$r->ill_gt_60_f,
            ];
        }
        return $out;
    }
}