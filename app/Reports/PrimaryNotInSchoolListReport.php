<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Services\GeoService;
use App\Services\ReportFormatService;

class PrimaryNotInSchoolListReport extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    public function export(Request $req)
    {
        // ===== 1) Đầu vào =====
        $academic = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1]     = array_map('intval', explode('-', $academic));
        $scheme   = (string) $req->input('scheme', 'old');
        $geo      = (array)  $req->input('geo', []);
        // (Báo cáo này theo chuẩn là KHÔNG lọc theo trường — trẻ chưa đi học
        //  nhưng nếu bạn muốn có, có thể lấy:
        //  $schoolId = (int) ($req->input('education.school_id') ?? $req->input('school_id') ?? 0);
        //  và truyền vào query nếu cần)

        // Dải năm sinh 6..14
        $years = [];
        for ($a = 6; $a <= 14; $a++) $years[] = $y1 - $a;

        // ===== 2) Lấy dữ liệu =====
        $rows = $this->queryNotInSchoolPrimary($academic, $scheme, $geo, $years);

        // ===== 3) Nạp template & header =====
        $cfg = $this->templateCfg();
        $wb  = IOFactory::load($cfg['path']);
        $sh  = $wb->getActiveSheet();

        $this->setCellValue($sh, $cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        $this->setCellValue($sh, $cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
        if (!empty($cfg['dateTop'])) {
            $this->setCellValue($sh, $cfg['dateTop'], $this->formatService->top_date());
        }

        // ===== 4) Chuẩn bị vùng dữ liệu =====
        $startRow = $cfg['startRow'];   // 8
        $lastCol  = $cfg['lastCol'];    // 'P'
        $need     = max(0, count($rows) - 1);
        if ($need > 0) {
            $sh->insertNewRowBefore($startRow + 1, $need);
            for ($i = 1; $i <= $need; $i++) {
                $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $lastCol);
            }
        }

        // ===== 5) Ghi dữ liệu =====
        $r = $startRow; $stt = 1;
        foreach ($rows as $it) {
            // STT
            $this->setCellValue($sh, 'A'.$r, $stt, ['align' => 'center']);

            // Họ tên: merge B:C theo template
            $this->safeMerge($sh, "B{$r}:C{$r}");
            $this->setCellValue($sh, 'B'.$r, $it['full_name'], ['align' => 'left']);

            // Ngày sinh
            $this->setCellValue($sh, 'D'.$r, $this->fmtDob($it['dob_day'],$it['dob_month'],$it['dob_year']), ['align' => 'center']);

            // Nữ (đặt 1 ở giữa nếu là nữ)
            $this->setCellValue($sh, 'E'.$r, $it['is_female'] ? 'x' : null, ['align' => 'center']);

            // Dân tộc, Tôn giáo
            $this->setCellValue($sh, 'F'.$r, $it['ethnicity'] ?: null, ['align'=>'left']);
            $this->setCellValue($sh, 'G'.$r, $it['religion']  ?: null, ['align'=>'left']);

            // Họ tên chủ hộ
            $this->setCellValue($sh, 'H'.$r, $it['chu_ho'] ?: null, ['align'=>'left']);

            // Địa chỉ (cho phép xuống dòng)
            $this->setCellValue($sh, 'I'.$r, $it['dia_chi'] ?: null, ['wrap'=>true, 'align'=>'left']);

            // Số phiếu
            $this->setCellValue($sh, 'J'.$r, $it['so_phieu'] ?: null, ['align'=>'center']);

            // Bỏ học L1..L5 (đánh dấu 1 đúng cột, nếu không có dữ liệu thì trống)
            for ($g = 1; $g <= 5; $g++) {
                $col = chr(ord('K') + $g - 1); // K,L,M,N,O
                $this->setCellValue($sh, $col.$r, ($it['quit_grade'] == $g) ? 'x' : null, ['align'=>'center']);
            }

            // Lý do bỏ học
            $this->setCellValue($sh, 'P'.$r, $it['note'] ?: null, ['wrap'=>true, 'align'=>'left']);

            $r++; $stt++;
        }

        $firstDataRow = $startRow;
        $lastDataRow  = max($startRow, $r - 1);

        // Cho cột địa chỉ / lý do wrap và hàng tự giãn
        foreach (['I','P'] as $c) {
            $sh->getStyle(sprintf('%s%d:%s%d', $c,$firstDataRow,$c,$lastDataRow))
               ->getAlignment()->setWrapText(true)
               ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
               ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
        }
        for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
            $sh->getRowDimension($row)->setRowHeight(-1);
        }

        // ===== 6) Viền + footer ngày tháng năm =====
        $this->drawThinBorders($sh, $firstDataRow, $lastDataRow, 'A', $lastCol);
       
            $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
            $this->setCellValue($sh,'J'.($lastDataRow +2)  , $this->formatService->end_date($wardName));
        

        // ===== 7) Xuất file =====
        return response()->streamDownload(function () use ($wb) {
            $w = IOFactory::createWriter($wb, 'Xlsx');
            $w->setPreCalculateFormulas(false);
            $w->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Lấy danh sách trẻ 6..14 TUỔI hiện KHÔNG học TiH (L1..L5) trong năm học,
     * kèm thông tin chủ hộ/địa chỉ và (nếu có) khối bỏ học.
     */
  protected function queryNotInSchoolPrimary(string $academic, string $scheme, array $geo, array $years): array
{
    // person_educations mới nhất (trong năm học)
    $peLatest = DB::table('person_educations as pe1')
        ->select('pe1.*', DB::raw('ROW_NUMBER() OVER(PARTITION BY pe1.person_id ORDER BY pe1.id DESC) AS rn'))
        ->where('pe1.academic_year', $academic);

    // person_disabilities mới nhất (để loại các em khuyết tật)
    $pdLatest = DB::table('person_disabilities as t')
        ->select('t.*', DB::raw('ROW_NUMBER() OVER(PARTITION BY t.person_id ORDER BY t.id DESC) AS rn'))
        ->where('t.is_deleted', false);

    $qb = DB::table('survey_form_details as d')
        ->join('survey_forms as f', fn($j) => $j->on('f.id', '=', 'd.survey_form_id')->where('f.is_deleted', false))
        ->join('people as p', 'p.id', '=', 'd.person_id')
        ->leftJoinSub($peLatest, 'pe', fn($j) => $j->on('pe.person_id', '=', 'p.id')->where('pe.rn', 1))
        ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id')
        ->leftJoin('graduation_levels as gl', 'gl.id', '=', 'pe.graduation_level_id')
        ->leftJoin('ethnicities as et', 'et.id', '=', 'p.ethnicity_id')
        ->leftJoin('religions  as rg', 'rg.id', '=', 'p.religion_id')

        // Chủ hộ & hộ theo SỐ PHIẾU
        ->leftJoin('people as head', 'head.id', '=', 'f.head_person_id')
        ->leftJoin('households as h', function($j){
            $j->on('h.head_person_id', '=', 'f.head_person_id')
              ->where('h.is_deleted', false);
        })
        ->leftJoin('household_members as hm', function($j){
            $j->on('hm.person_id', '=', 'p.id')
              ->on('hm.household_id', '=', 'h.id');
        })

        // Join khuyết tật mới nhất
        ->leftJoinSub($pdLatest, 'pd', function($j){
            $j->on('pd.person_id', '=', 'p.id')->where('pd.rn', 1);
        })

        ->where('d.is_deleted', false)
        ->where('d.academic_year', $academic)
        ->whereIn('p.dob_year', $years);

    // Lọc địa bàn cư trú theo scheme
    $this->applyGeoFilter($qb, $scheme, $geo);

    // ===== Điều kiện "chưa đi học" =====
    $noBlockAndNoClass = "(pe.education_block_id IS NULL AND (pe.class_label IS NULL OR TRIM(pe.class_label)=''))";
    $preOrMonth        = "(pe.education_block_id IS NOT NULL AND CAST(eb.name AS UNSIGNED) NOT BETWEEN 1 AND 12)";
    $hasDropout        = "(pe.bo_hoc_lop IS NOT NULL AND TRIM(pe.bo_hoc_lop) <> '')";
    $chuatotnghiep     = "(pe.graduation_level_id IS NULL OR (pe.graduation_level_id IS NOT NULL AND pe.graduation_level_id < 2))";
    $notInSchool       = "($noBlockAndNoClass OR $preOrMonth OR $hasDropout) AND $chuatotnghiep";
    $qb->whereRaw($notInSchool);

    // ===== Loại các em KHuyết tật =====
    // Nếu có bản ghi pd và mảng disability_ids khác rỗng -> loại.
    $notDisabled = "(pd.person_id IS NULL OR COALESCE(JSON_LENGTH(pd.disability_ids),0) = 0)";
    $qb->whereRaw($notDisabled);

    // Lớp bỏ học: chỉ giữ 1..5
    $quitGradeExpr = DB::raw("
        CASE 
            WHEN pe.bo_hoc_lop IS NULL OR TRIM(pe.bo_hoc_lop)='' THEN NULL
            WHEN CAST(pe.bo_hoc_lop AS UNSIGNED) BETWEEN 1 AND 5 THEN CAST(pe.bo_hoc_lop AS UNSIGNED)
            ELSE NULL
        END AS quit_grade
    ");

    $rows = $qb->select([
            'p.note','p.ho_dem','p.ten','p.dob_day','p.dob_month','p.dob_year','p.is_female',
            'et.name as ethnicity',
            'rg.name as religion',
            'p.addressline as p_addr',
            'p.old_ward_id as p_old_ward_id',
            'p.new_ward_id as p_new_ward_id',
            'p.old_province_id as p_old_province_id',
            'p.new_province_id as p_new_province_id',
            'p.so_phieu',

            'head.ho_dem as head_ho_dem',
            'head.ten    as head_ten',
            'hm.role     as relation',
            'h.addressline as hh_addr',
            'h.old_ward_id as hh_old_ward_id',
            'h.new_ward_id as hh_new_ward_id',
            'h.old_province_id as hh_old_province_id',
            'h.new_province_id as hh_new_province_id',

            $quitGradeExpr,
        ])
        ->orderBy('p.dob_year')->orderBy('p.ten')
        ->get();

    // Map ra output
    $out = [];
    foreach ($rows as $r) {
        $addr = $this->composeAddress(
            $scheme,
            $r->p_addr, $r->p_old_ward_id, $r->p_new_ward_id,
            $r->p_old_province_id, $r->p_new_province_id,
            $r->hh_addr, $r->hh_old_ward_id, $r->hh_new_ward_id,
            $r->hh_old_province_id, $r->hh_new_province_id
        );

        $out[] = [
            'full_name'  => trim(($r->ho_dem ? $r->ho_dem.' ' : '').($r->ten ?? '')),
            'dob_day'    => (int)($r->dob_day ?? 0),
            'dob_month'  => (int)($r->dob_month ?? 0),
            'dob_year'   => (int)($r->dob_year ?? 0),
            'is_female'  => (bool)$r->is_female,
            'ethnicity'  => $r->ethnicity,
            'religion'   => $r->religion,
            'chu_ho'     => trim(($r->head_ho_dem ? $r->head_ho_dem.' ' : '').($r->head_ten ?? '')) ?: null,
            'quan_he'    => $r->relation ?: null,
            'dia_chi'    => $addr ?: null,
            'so_phieu'   => $r->so_phieu,
            'quit_grade' => $r->quit_grade ? (int)$r->quit_grade : null,
            'note'       => $r->note,
        ];
    }
    return $out;
}

    /** Ưu tiên địa chỉ Person; thiếu thì lấy Household; gắn tên xã/phường theo scheme */
    protected function composeAddress(
        string $scheme,
        ?string $pAddr, ?int $pOldWard, ?int $pNewWard, ?int $pOldProv, ?int $pNewProv,
        ?string $hAddr, ?int $hOldWard, ?int $hNewWard, ?int $hOldProv, ?int $hNewProv
    ): ?string {
        $addrline = trim((string)($pAddr ?: $hAddr ?: ''));
        if ($scheme === 'old') {
            $wardName = $this->geoService->geoNameFromId($pOldWard ?? $hOldWard);
            $provName = $this->geoService->geoNameFromId($pOldProv ?? $hOldProv);
        } else {
            $wardName = $this->geoService->geoNameFromId($pNewWard ?? $hNewWard);
            $provName = $this->geoService->geoNameFromId($pNewProv ?? $hNewProv);
        }
        $parts = [];
        if ($addrline !== '') $parts[] = $addrline;
        if ($wardName) $parts[] = $wardName;
        if ($provName) $parts[] = $provName;
        return $parts ? implode(' - ', $parts) : null;
    }
protected function fmtDob(?int $d, ?int $m, ?int $y): ?string
    {
        $d = (int)$d; $m = (int)$m; $y = (int)$y;
        if ($y <= 0) return null;
        if ($d <= 0 || $m <= 0) return sprintf('%04d', $y);
        return sprintf('%02d/%02d/%04d', $d, $m, $y);
    }

    /** Template config (theo ảnh bạn gửi) */
    protected function templateCfg(): array
    {
        return [
            'path' => resource_path('excel/2.9.TiH.DSChuaDiHoc.xlsx'), // đặt đúng tên file của bạn

            'header' => [
                'province'  => 'C1',
                'ward'      => 'C2',
            ],
            'dateTop'     => 'I4',   // dòng “Ngày … tháng … năm …”
            'dateBottom'  => 'J10',  // footer dưới (tùy template)

            'startRow'    => 8,
            'lastCol'     => 'P',
        ];
    }
protected function drawThinBorders(Worksheet $sh, int $rowFrom, int $rowTo, string $colFrom, string $colTo): void
    {
        $range = sprintf('%s%d:%s%d', $colFrom, $rowFrom, $colTo, $rowTo);
        $sh->getStyle($range)->getBorders()->getAllBorders()
           ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    }
    /** căn lề bổ sung cho setCellValue: align/wrap */
    // protected function setCellValue(Worksheet $sh, string $addr, $value, array $opt = []): void
    // {
    //     parent::setCellValue($sh, $addr, $value, $opt);

    //     if (!empty($opt['align'])) {
    //         $align = $sh->getStyle($addr)->getAlignment();
    //         switch (strtolower($opt['align'])) {
    //             case 'center':
    //                 $align->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    //                 break;
    //             case 'right':
    //                 $align->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
    //                 break;
    //             default:
    //                 $align->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
    //         }
    //     }
    //     if (!empty($opt['wrap'])) {
    //         $sh->getStyle($addr)->getAlignment()->setWrapText(true);
    //     }
    // }
}
