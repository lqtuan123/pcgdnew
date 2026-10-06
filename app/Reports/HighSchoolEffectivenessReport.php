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

class HighSchoolEffectivenessReport extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

  public function export(Request $req)
{
    // ===== 1) Input
    $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear());
    [$y1]     = array_map('intval', explode('-', $academic));
    $scheme   = (string)$req->input('scheme', 'old');
    $geo      = (array)$req->input('geo', []);

    // Tăng tốc ghi
    if (method_exists(\PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance(), 'setCalculationEnabled')) {
        \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance()->setCalculationEnabled(false);
    }

    // ===== 2) Template
    $cfg = $this->templateCfg();              // đảm bảo lastCol = 'S'
    $wb  = \PhpOffice\PhpSpreadsheet\IOFactory::load($cfg['path']);
    $sh  = $wb->getActiveSheet();

    // Header
    $sh->setCellValue($cfg['header']['province'],  $this->geoService->geoNameFromId($geo['province_id'] ?? null));
    $sh->setCellValue($cfg['header']['ward'],      $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
    $sh->setCellValue($cfg['dateTop'],             $this->formatService->top_date());

    // ===== 3) Dữ liệu
    $rows = $this->queryThptEffectByVillage($academic, $scheme, $geo);

    // Chuẩn bị vùng dữ liệu
    $startRow = 9;   // ví dụ 8
    $sumRow   = 10;     // dòng “Tổng cộng” có sẵn trong template
    $need     = max(0, count($rows) - 1);
    if ($need > 0) {
        $sh->insertNewRowBefore($sumRow, $need);
        for ($i = 1; $i <= $need; $i++) {
            // $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $cfg['lastCol']);
             $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $cfg['lastCol']);
        }
        $sumRow += $need ;
    }

    // ===== 4) Ghi từng dòng
    $r = $startRow; $stt = 1;
    foreach ($rows as $it) {
        // A: STT
        $this->setCellValue($sh, 'A'.$r, $stt);

        // B–C: Đơn vị (template gộp 2 cột)
        $this->safeMerge($sh, "B{$r}:C{$r}");
        $this->setCellValue($sh, 'B'.$r, $it['unit_name']);

        // D..H: số liệu gốc
        $this->setCellValue($sh, 'D'.$r, $it['total_18_21']);   // Tổng số đối tượng 18–21
        $this->setCellValue($sh, 'E'.$r, $it['must_pc']);       // Tổng số phải phổ cập
        $this->setCellValue($sh, 'F'.$r, $it['tn_thpt']);       // TN THPT
        $this->setCellValue($sh, 'G'.$r, $it['tn_bt_thpt']);    // TN BT THPT
        $this->setCellValue($sh, 'H'.$r, $it['tn_thcn_tdn']);   // TN THCN, TDN

        // I = (F+G+H)/D
        $this->setCellValue(
            $sh, 'I'.$r,
            sprintf('=IF(D%d=0,"",ROUND((F%d+G%d+H%d)/D%d*100,2))', $r,$r,$r,$r,$r)
        );

        // J..L: Năm vừa qua – khối THPT thường
        $this->setCellValue($sh, 'J'.$r, $it['hs12_thpt']);           // HS lớp 12 THPT
        $this->setCellValue($sh, 'K'.$r, $it['grad_thpt_last']);      // TN THPT năm vừa qua
        $this->setCellValue(
            $sh, 'L'.$r,
            sprintf('=IF(J%d=0,"",ROUND(K%d/J%d*100,2))', $r,$r,$r)
        );

        // M..O: Năm vừa qua – GDTX/BT
        $this->setCellValue($sh, 'M'.$r, $it['hs12_gdtx']);            // HS lớp 12 GDTX/BT
        $this->setCellValue($sh, 'N'.$r, $it['grad_gdtx_thpt_last']);  // TN GDTX THPT năm vừa qua
        $this->setCellValue(
            $sh, 'O'.$r,
            sprintf('=IF(M%d=0,"",ROUND(N%d/M%d*100,2))', $r,$r,$r)
        );

        // P..R: Đào tạo nghề
        $this->setCellValue($sh, 'P'.$r, $it['voc3_total']);    // Tổng số học nghề từ 3 năm
        $this->setCellValue($sh, 'Q'.$r, $it['degree_total']);  // Tổng số có bằng (TC/ĐH)
        $this->setCellValue(
            $sh, 'R'.$r,
            sprintf('=IF(P%d=0,"",ROUND(Q%d/P%d*100,2))', $r,$r,$r)
        );

        // S: Đánh giá (để trống)
        $this->setCellValue($sh, 'S'.$r, null);

        $r++; $stt++;
    }

    // ===== 5) Dòng “Tổng cộng”
    // $this->safeMerge($sh, "A{$sumRow}:B{$sumRow}");
    // $this->setCellValue($sh, "A{$sumRow}", "Tổng cộng");

    // Cộng các cột số lượng
    foreach (['D','E','F','G','H','J','K','M','N','P','Q'] as $col) {
        $this->setCellValue(
            $sh, $col.$sumRow,
            sprintf('=SUM(%s%d:%s%d)', $col, $startRow, $col, $sumRow-1)
        );
    }

    // Tỷ lệ tổng hợp
    $this->setCellValue(
        $sh, 'I'.$sumRow,
        sprintf('=IF(D%d=0,"",ROUND((F%d+G%d+H%d)/D%d*100,2))', $sumRow,$sumRow,$sumRow,$sumRow,$sumRow)
    );
    $this->setCellValue(
        $sh, 'L'.$sumRow,
        sprintf('=IF(J%d=0,"",ROUND(K%d/J%d*100,2))', $sumRow,$sumRow,$sumRow)
    );
    $this->setCellValue(
        $sh, 'O'.$sumRow,
        sprintf('=IF(M%d=0,"",ROUND(N%d/M%d*100,2))', $sumRow,$sumRow,$sumRow)
    );
    $this->setCellValue(
        $sh, 'R'.$sumRow,
        sprintf('=IF(P%d=0,"",ROUND(Q%d/P%d*100,2))', $sumRow,$sumRow,$sumRow)
    );

    // ===== 6) Định dạng & viền
    $this->drawThinBorders($sh, $startRow, $sumRow, 'A', $cfg['lastCol']);

    $sh->getStyle("D{$startRow}:R".($sumRow-1))
         ->getNumberFormat()->setFormatCode('0;-0;;@'); // zero section rỗng

    $sh->getStyle("A{$startRow}:{$cfg['lastCol']}{$sumRow}")
       ->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sh->getStyle("A{$startRow}:A{$sumRow}")
       ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sh->getStyle("D{$startRow}:H{$sumRow}")
       ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sh->getStyle("J{$startRow}:R{$sumRow}")
       ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sh->getStyle("B{$startRow}:B{$sumRow}")
       ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

    foreach (['I','L','O','R'] as $colPct) {
        $sh->getStyle("{$colPct}{$startRow}:{$colPct}{$sumRow}")
           ->getNumberFormat()->setFormatCode('0.00');
    }

    // Footer ngày tháng năm
    $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
    $lastrow  = $sumRow + 2;
     $this->safeMerge($sh, "O{$lastrow}:S{$lastrow}");
        $this->setCellValue($sh,'O'.$lastrow , $this->formatService->end_date($wardName)); // ô tùy template // ô tùy template
       $sh->getStyle("O{$lastrow}:S{$lastrow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    // ===== 7) Xuất file
    return response()->streamDownload(function () use ($wb) {
        $w = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($wb, 'Xlsx');
        $w->setPreCalculateFormulas(false);
        $w->save('php://output');
    }, $this->getExportFilename($cfg['path']), [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);
}

protected function queryThptEffectByVillage(string $academic, string $scheme, array $geo): array
{
    [$y1]   = array_map('intval', explode('-', $academic));
    $years  = []; for ($a = 18; $a <= 21; $a++) $years[] = $y1 - $a;
    $lastYr = $y1 - 1;
    $lastAcademic = ($y1 - 1) . '-' . $y1;

    // Loại trường
    $TYPE_GDTX = [3, 11];
    $TYPE_VOC  = [1];

    $inGdtx = implode(',', $TYPE_GDTX);
    $inVoc  = implode(',', $TYPE_VOC);

    // Tên văn bằng đồng nghĩa
    $GL_TC = "'THCN','TDN','TRUNG CAP','TRUNG CẤP','TRUNGCAP'";
    $GL_CD = "'CAO DANG','CAO ĐẲNG','CD'";
    $GL_DH = "'DAI HOC','ĐẠI HỌC','DH'";

    // Lấy "năm bắt đầu" của graduation_year (2023 hoặc 2023-2024 => 2023)
    $gradStartYearSql = "CAST(SUBSTRING_INDEX(TRIM(v.graduation_year), '-', 1) AS UNSIGNED)";
    $gradNormSql  = "REPLACE(TRIM(v.graduation_year), '/', '-')"; // đổi '2024/2025' -> '2024-2025'
    $gradStartSql = "CAST(SUBSTRING_INDEX($gradNormSql, '-', 1) AS UNSIGNED)";
    $gradEndSql   = "CAST(SUBSTRING_INDEX($gradNormSql, '-', -1) AS UNSIGNED)";
    $condLastYear = "($gradStartSql = {$y1}-1 OR $gradEndSql = {$y1})"; // 2024-2025 -> 2024 hoặc 2025 đều match

    /* ---------- 1) Tổng hợp theo người trên TOÀN BỘ view ---------- */
    $agg = DB::table('v_survey_detail_flat as v')
        ->leftJoin('schools as s', function($j) {
            $j->on('s.code', '=', 'v.school_code')
              ->where(function($w) {
                  $w->whereNull('s.is_dissolved')
                    ->orWhere('s.is_dissolved', false)
                    ->orWhere('s.is_dissolved', 0);
              });
        })
        ->select(
            'v.person_id',
            // TN THPT (không bổ túc)
            DB::raw("MAX(CASE WHEN UPPER(v.graduation_level_name)='THPT' AND COALESCE(v.is_bo_tuc,0)=0 THEN 1 ELSE 0 END) AS tn_thpt"),
            // TN BT THPT
            DB::raw("MAX(CASE WHEN UPPER(v.graduation_level_name)='THPT' AND COALESCE(v.is_bo_tuc,0)=1 THEN 1 ELSE 0 END) AS tn_bt_thpt"),
            // Có bằng Trung cấp
            DB::raw("MAX(CASE WHEN UPPER(v.graduation_level_name) IN ($GL_TC) THEN 1 ELSE 0 END) AS tn_thcn_tdn"),
            // Từng học trường dạy nghề
            DB::raw("MAX(CASE WHEN s.school_type_id IN ($inVoc) THEN 1 ELSE 0 END) AS studied_voc"),
            // Có bằng TC / CĐ / ĐH
            DB::raw("MAX(CASE WHEN UPPER(v.graduation_level_name) IN ($GL_TC,$GL_CD,$GL_DH) THEN 1 ELSE 0 END) AS has_tc_dh"),

          
        )
        ->groupBy('v.person_id');

    /* ---------- 2) Năm vừa qua: học lớp 12 theo loại trường ---------- */
  $lastYear = DB::table('v_survey_detail_flat as v')
    // loại trường tương ứng với record lớp 12 ở NĂM HỌC TRƯỚC
   

    // thông tin tốt nghiệp (bất kỳ năm nào) để lọc ra "tốt nghiệp năm học trước"
    ->leftJoin('person_educations as pet', 'pet.person_id', '=', 'v.person_id')
     ->leftJoin('schools as s', function($j) {
         $j->on('s.code', '=', 'v.school_code')
           ->where(function($w) {
               $w->whereNull('s.is_dissolved')
                 ->orWhere('s.is_dissolved', false)
                 ->orWhere('s.is_dissolved', 0);
           });
     })
    // ->leftJoin('schools as s2', 's2.id', '=', 'pet.school_id')

    ->where('v.academic_year', $lastAcademic)
    ->select(
        'v.person_id',

        // Học lớp 12 THPT THƯỜNG trong năm học trước
        // DB::raw("MAX(CASE
        //            WHEN CAST(v.education_block_code AS UNSIGNED)=12
        //             AND (s.school_type_id IS NULL OR s.school_type_id NOT IN ($inGdtx))
        //            THEN 1 ELSE 0 END) AS is_thpt12"),
          DB::raw("MAX(CASE
                   WHEN CAST(v.education_block_code AS UNSIGNED)=12
                     
                   THEN 1 ELSE 0 END) AS is_thpt12"),

        // Học lớp 12 GDTX/BT trong năm học trước
        DB::raw("MAX(CASE
                   WHEN CAST(v.education_block_code AS UNSIGNED)=12
                    AND s.school_type_id IN ($inGdtx)
                   THEN 1 ELSE 0 END) AS is_gdtx12"),

        // TỐT NGHIỆP THPT năm học trước ở THPT thường
        // DB::raw("MAX(CASE
        //            WHEN pet.graduation_level_id = 4
        //             AND pet.graduation_year = '$lastAcademic'
        //             AND (s.school_type_id IS NULL OR s.school_type_id NOT IN ($inGdtx))
        //            THEN 1 ELSE 0 END) AS grad_thpt_last"),
  DB::raw("MAX(CASE
                   WHEN pet.graduation_level_id = 4
                    AND pet.graduation_year = '$lastAcademic'
                   
                   THEN 1 ELSE 0 END) AS grad_thpt_last"),
        // TỐT NGHIỆP THPT năm học trước ở GDTX/BT
        DB::raw("MAX(CASE
                   WHEN pet.graduation_level_id = 4
                    AND pet.graduation_year = '$lastAcademic'
                    AND s.school_type_id IN ($inGdtx)
                   THEN 1 ELSE 0 END) AS grad_gdtx_thpt_last")
    )
    ->groupBy('v.person_id');

    
    $movedOutCond = "UPPER(TRIM(v.sc_name)) IN ('CHẾT', 'CHUYỂN ĐI','CHUYEN DI','MOVED_OUT')";
    // điều kiện “khuyết tật” (bất kỳ cột nào = 1)
    $disabledCond = "("
        . "COALESCE(v.dis_VAN_DONG,0)+COALESCE(v.dis_NGHE_NOI,0)+COALESCE(v.dis_NHIN,0)"
        . "+COALESCE(v.dis_THAN_KINH,0)+COALESCE(v.dis_TRI_TUE,0)+COALESCE(v.dis_HOC_TAP,0)"
        . "+COALESCE(v.dis_TU_KY,0)+COALESCE(v.dis_KHAC,0)"
        . ") > 0";

    /* ---------- 3) Tập đối tượng 18–21 trong năm học hiện tại ---------- */
    $cur = DB::table('v_survey_detail_flat as v')
        ->leftJoin('geo_units as g', 'g.id', '=', 'v.village_id')
        ->leftJoinSub($agg,      'a',   fn($j) => $j->on('a.person_id','=','v.person_id'))
        ->leftJoinSub($lastYear, 'ly',  fn($j) => $j->on('ly.person_id','=','v.person_id'))
          
        
        ->where('v.academic_year', $academic)
        ->whereIn('v.dob_year', $years);

    // Lọc địa bàn theo old/new
    if (($geo['province_id'] ?? null) || ($geo['ward_id'] ?? null)) {
        if ($scheme === 'old') {
            if (!empty($geo['province_id'])) $cur->where('v.old_province_id', $geo['province_id']);
            if (!empty($geo['ward_id']))     $cur->where('v.old_ward_id',     $geo['ward_id']);
        } else {
            if (!empty($geo['province_id'])) $cur->where('v.new_province_id', $geo['province_id']);
            if (!empty($geo['ward_id']))     $cur->where('v.new_ward_id',     $geo['ward_id']);
        }
    }

    // Gom theo thôn/tổ (village)
    $rows = $cur->selectRaw("
                v.village_id            AS unit_id,
                COALESCE(v.village_name,'Không rõ') AS unit_name,

                COUNT(DISTINCT v.person_id)             AS total_18_21,   -- cột D
                COUNT(DISTINCT CASE
                    WHEN  ($movedOutCond) or  ($disabledCond)
                    THEN v.person_id END) AS must_pc,                      -- E

                SUM(COALESCE(a.tn_thpt,0))              AS tn_thpt,       -- F
                SUM(COALESCE(a.tn_bt_thpt,0))           AS tn_bt_thpt,    -- G
                SUM(COALESCE(a.tn_thcn_tdn,0))          AS tn_thcn_tdn,   -- H

                SUM(COALESCE(ly.is_thpt12,0))           AS hs12_thpt,     -- J
                SUM(COALESCE(ly.grad_thpt_last,0))       AS grad_thpt_last,-- K

                SUM(COALESCE(ly.is_gdtx12,0))           AS hs12_gdtx,     -- M
                SUM(COALESCE(ly.grad_gdtx_thpt_last,0))  AS grad_gdtx_thpt_last, -- N

                -- Đào tạo nghề ≥3 năm và có bằng
                SUM(CASE WHEN COALESCE(a.studied_voc,0)=1 OR COALESCE(a.has_tc_dh,0)=1 THEN 1 ELSE 0 END) AS voc3_total,  -- P
                SUM(COALESCE(a.has_tc_dh,0))            AS degree_total   -- Q
            ")
            ->groupBy('unit_id','unit_name')
            ->orderBy('unit_name')
            ->get();

    // Map kết quả
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'unit_id'               => (int) $r->unit_id,
            'unit_name'             => $r->unit_name,
            'total_18_21'           => (int) $r->total_18_21,
            'must_pc'             => (int) $r->total_18_21 - (int) $r->must_pc,

            'tn_thpt'               => (int) $r->tn_thpt,
            'tn_bt_thpt'            => (int) $r->tn_bt_thpt,
            'tn_thcn_tdn'           => (int) $r->tn_thcn_tdn,

            'hs12_thpt'             => (int) $r->hs12_thpt,
            'grad_thpt_last'        => (int) $r->grad_thpt_last,

            'hs12_gdtx'             => (int) $r->hs12_gdtx,
            'grad_gdtx_thpt_last'   => (int) $r->grad_gdtx_thpt_last,

            'voc3_total'            => (int) $r->voc3_total,
            'degree_total'          => (int) $r->degree_total,
        ];
    }
    return $out;
}

    /**
     * Gom theo thôn/tổ (village) cho tuổi 18–21.
     */
   
// protected function queryThptEffectByVillage(string $academic, string $scheme, array $geo): array
// {
//     // Năm học & cohort tuổi
//     [$y1] = array_map('intval', explode('-', $academic)); // vd '2024-2025' => 2024
//     $y2   = $y1 + 1;
//     $prevAcademic = ($y1 - 1) . '-' . $y1;

//     $years = [];
//     for ($a = 18; $a <= 21; $a++) $years[] = $y1 - $a;

//     // Phân loại trường
//     $TYPE_GDTX = [3, 11];      // GDTX/BT
//     $TYPE_BT   = [11];         // BT-THPT (nếu cần tách riêng)
//     $TYPE_VOC  = [1];          // dạy nghề
//     $inGdtx    = implode(',', $TYPE_GDTX);
//     $inBt      = implode(',', $TYPE_BT);
//     $inVoc     = implode(',', $TYPE_VOC);

//     // Nhãn tốt nghiệp TC/ĐH (tuỳ DB)
//     $GL_TC = "'THCN','TDN','TRUNG CAP','TRUNG CẤP','TRUNGCAP'";
//     $GL_DH = "'CAO DANG','CAO ĐẲNG','DAI HOC','ĐẠI HỌC','CD','DH'";

//     // Không phải GDTX/BT
//     $notGdtxBt = "(s.school_type_id IS NULL OR s.school_type_id NOT IN ($inGdtx))";

//     // Bộ điều kiện "năm tốt nghiệp NĂM NAY" (y1/y2/chuỗi academic)
//     $condGradThis = "
//       (
//         (pe.graduation_year REGEXP '^[0-9]{4}$' AND CAST(pe.graduation_year AS UNSIGNED) IN ($y1,$y2))
//         OR pe.graduation_year = '{$academic}'
//       )
//     ";

//     // Bộ điều kiện "năm tốt nghiệp NĂM TRƯỚC" (y1-1/y1/chuỗi prevAcademic)
//     $ly1 = $y1 - 1; $ly2 = $y1;
//     $condGradLast = "
//       (
//         (pe.graduation_year REGEXP '^[0-9]{4}$' AND CAST(pe.graduation_year AS UNSIGNED) IN ($ly1,$ly2))
//         OR pe.graduation_year = '{$prevAcademic}'
//       )
//     ";

//     // --- Tổng hợp theo người (EVER) + cờ tốt nghiệp NĂM NAY / NĂM TRƯỚC ---
//     $peAgg = DB::table('person_educations as pe')
//         ->leftJoin('schools as s', 's.id', '=', 'pe.school_id')
//         ->leftJoin('graduation_levels as gl', 'gl.id', '=', 'pe.graduation_level_id')
//         ->select(
//             'pe.person_id',

//             // ĐÃ TỪNG TN THPT (thường / BT)
//             DB::raw("MAX(CASE WHEN UPPER(gl.name)='THPT' AND $notGdtxBt THEN 1 ELSE 0 END) AS tn_thpt"),
//             DB::raw("MAX(CASE WHEN UPPER(gl.name)='THPT' AND s.school_type_id IN ($inBt) THEN 1 ELSE 0 END) AS tn_bt_thpt"),

//             // ĐÃ TỪNG TN TC/TDN
//             DB::raw("MAX(CASE WHEN UPPER(gl.name) IN ($GL_TC) THEN 1 ELSE 0 END) AS tn_thcn_tdn"),

//             // HỌC NGHỀ/TC/ĐH (ever)
//             DB::raw("MAX(CASE WHEN s.educational_level_id IN ($inVoc) THEN 1 ELSE 0 END) AS studied_voc"),
//             DB::raw("MAX(CASE WHEN UPPER(gl.name) IN ($GL_TC, $GL_DH) THEN 1 ELSE 0 END) AS has_tc_dh"),

//             // >>> Tốt nghiệp THPT NĂM TRƯỚC (thường/GDTX)
//             DB::raw("MAX(CASE WHEN UPPER(gl.name)='THPT' AND $notGdtxBt AND $condGradLast THEN 1 ELSE 0 END) AS grad_thpt_last"),
//             DB::raw("MAX(CASE WHEN UPPER(gl.name)='THPT' AND s.school_type_id IN ($inGdtx) AND $condGradLast THEN 1 ELSE 0 END) AS grad_gdtx_thpt_last"),

//             // >>> Tốt nghiệp THPT NĂM NAY (thường/GDTX) — mới thêm
//             DB::raw("MAX(CASE WHEN UPPER(gl.name)='THPT' AND $notGdtxBt AND $condGradThis THEN 1 ELSE 0 END) AS grad_thpt_this_regular"),
//             DB::raw("MAX(CASE WHEN UPPER(gl.name)='THPT' AND s.school_type_id IN ($inGdtx) AND $condGradThis THEN 1 ELSE 0 END) AS grad_thpt_this_gdtx")
//         )
//         ->groupBy('pe.person_id');

//     // --- HS LỚP 12 của NĂM HỌC TRƯỚC (J) ---
//     $peNowPrev = DB::table('person_educations as pe1')
//         ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe1.education_block_id')
//         ->leftJoin('schools as s1', 's1.id', '=', 'pe1.school_id')
//         ->select(
//             'pe1.person_id',
//             DB::raw("MAX(CASE WHEN CAST(eb.name AS UNSIGNED)=12 AND (s1.school_type_id IS NULL OR s1.school_type_id NOT IN ($inGdtx)) THEN 1 ELSE 0 END) AS is_thpt12"), // J (THPT thường)
//             DB::raw("MAX(CASE WHEN CAST(eb.name AS UNSIGNED)=12 AND s1.school_type_id IN ($inGdtx) THEN 1 ELSE 0 END) AS is_gdtx12") // nếu cần hiển thị
//         )
//         ->where('pe1.academic_year', $prevAcademic)
//         ->groupBy('pe1.person_id');

//     $unitIdExpr = "COALESCE(p.village_id, h.village_id)";

//     $qb = DB::table('survey_form_details as d')
//         ->join('survey_forms as f', fn($j) => $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false))
//         ->join('people as p', 'p.id', '=', 'd.person_id')
//         ->leftJoin('household_members as hm', 'hm.person_id', '=', 'p.id')
//         ->leftJoin('households as h', 'h.id', '=', 'hm.household_id')
//         ->leftJoinSub($peAgg, 'pea', fn($j) => $j->on('pea.person_id','=','p.id'))
//         ->leftJoinSub($peNowPrev, 'pen', fn($j) => $j->on('pen.person_id','=','p.id'))
//         ->leftJoin('geo_units as v', fn($j) => $j->on('v.id', '=', DB::raw($unitIdExpr)))
//         ->where('d.is_deleted', false)
//         // Ưu tiên lọc theo survey_forms (ổn định index hơn). Nếu d.academic_year có, giữ thêm 1 dòng dưới.
//         ->where('f.academic_year', $academic)
//         // ->where('d.academic_year', $academic)
//         ->whereIn('p.dob_year', $years);

//     $this->applyGeoFilter($qb, $scheme, $geo);

//     $rows = $qb->selectRaw("
//             {$unitIdExpr} AS unit_id,
//             COALESCE(v.name, 'Không rõ') AS unit_name,

//             COUNT(*) AS total_18_21,
//             COUNT(*) AS must_pc,

//             -- ĐÃ TỪNG TN (THPT/BT/TC-TDN)
//             SUM(COALESCE(pea.tn_thpt,0))     AS tn_thpt,
//             SUM(COALESCE(pea.tn_bt_thpt,0))  AS tn_bt_thpt,
//             SUM(COALESCE(pea.tn_thcn_tdn,0)) AS tn_thcn_tdn,

//             -- Tỉ lệ “đã từng TN” trên tổng
//             ROUND(
//               (SUM(COALESCE(pea.tn_thpt,0) + COALESCE(pea.tn_bt_thpt,0) + COALESCE(pea.tn_thcn_tdn,0))
//                / NULLIF(COUNT(*),0) * 100), 2
//             ) AS ratio_all,

//             -- TN năm TRƯỚC (thường + %)
//             SUM(COALESCE(pea.grad_thpt_last,0)) AS grad_thpt_last,
//             ROUND(SUM(COALESCE(pea.grad_thpt_last,0)) / NULLIF(COUNT(*),0) * 100, 2) AS ratio_last,

//             -- J: lớp 12 THPT năm học TRƯỚC
//             SUM(COALESCE(pen.is_thpt12,0)) AS hs12_thpt,
//             -- nếu muốn thêm: lớp 12 GDTX năm học trước
//             SUM(COALESCE(pen.is_gdtx12,0)) AS hs12_gdtx,

//             -- K: TN THPT NĂM NAY (tách thường/GDTX + tổng) — mới
//             SUM(COALESCE(pea.grad_thpt_this_regular,0)) AS grad_thpt_this_regular,
//             SUM(COALESCE(pea.grad_thpt_this_gdtx,0))    AS grad_thpt_this_gdtx,
//             SUM(COALESCE(pea.grad_thpt_this_regular,0) + COALESCE(pea.grad_thpt_this_gdtx,0)) AS grad_thpt_this_total,

//             -- Tỉ lệ J/K và K/Population — mới
//             ROUND(
//               SUM(COALESCE(pen.is_thpt12,0))
//               / NULLIF(SUM(COALESCE(pea.grad_thpt_this_regular,0) + COALESCE(pea.grad_thpt_this_gdtx,0)), 0) * 100
//             , 2) AS ratio_hs12_over_grad,

//             ROUND(
//               SUM(COALESCE(pea.grad_thpt_this_regular,0) + COALESCE(pea.grad_thpt_this_gdtx,0))
//               / NULLIF(COUNT(*), 0) * 100
//             , 2) AS ratio_grad_this_over_pop,

//             -- Hướng nghiệp/đại học (ever)
//             SUM(CASE WHEN COALESCE(pea.studied_voc,0)=1 OR COALESCE(pea.has_tc_dh,0)=1 THEN 1 ELSE 0 END) AS voc3_total,
//             SUM(COALESCE(pea.has_tc_dh,0)) AS degree_total
//         ")
//         ->groupBy('unit_id','unit_name')
//         ->orderBy('unit_name')
//         ->get();

//     // Đóng gói kết quả
//     $out = [];
//     foreach ($rows as $r) {
//         $out[] = [
//             'unit_id'                    => (int) $r->unit_id,
//             'unit_name'                  => (string) $r->unit_name,

//             'total_18_21'                => (int) $r->total_18_21,
//             'must_pc'                    => (int) $r->must_pc,

//             'tn_thpt'                    => (int) $r->tn_thpt,
//             'tn_bt_thpt'                 => (int) $r->tn_bt_thpt,
//             'tn_thcn_tdn'                => (int) $r->tn_thcn_tdn,
//             'ratio_all'                  => (float) $r->ratio_all,

//             'grad_thpt_last'             => (int) $r->grad_thpt_last,
//             'ratio_last'                 => (float) $r->ratio_last,

//             'hs12_thpt'                  => (int) $r->hs12_thpt,        // J
//             'hs12_gdtx'                  => (int) $r->hs12_gdtx,

//             'grad_thpt_this_regular'     => (int) $r->grad_thpt_this_regular,
//             'grad_thpt_this_gdtx'        => (int) $r->grad_thpt_this_gdtx,
//             'grad_thpt_this_total'       => (int) $r->grad_thpt_this_total,    // K

//             'ratio_hs12_over_grad'       => (float) $r->ratio_hs12_over_grad,  // J/K
//             'ratio_grad_this_over_pop'   => (float) $r->ratio_grad_this_over_pop, // K/total

//             'voc3_total'                 => (int) $r->voc3_total,
//             'degree_total'               => (int) $r->degree_total,
//         ];
//     }
//     return $out;
// }
// protected function queryThptEffectByVillage(string $academic, string $scheme, array $geo): array
// {
//     [$y1]   = array_map('intval', explode('-', $academic));
//     $years  = []; for ($a = 18; $a <= 21; $a++) $years[] = $y1 - $a;
//     $lastYr = $y1 - 1;
//     $lastacademic = ($y1 -1).'-'.$y1;
//     $TYPE_GDTX = [3, 11];
//     $TYPE_BT   = [11];
//     $TYPE_VOC  = [1];
//     $inGdtx    = implode(',', $TYPE_GDTX);
//     $inBt      = implode(',', $TYPE_BT);
//     $inVoc  = implode(',', $TYPE_VOC);
//     $GL_TC = "'THCN','TDN','TRUNG CAP','TRUNG CẤP','TRUNGCAP'";
//     $GL_DH = "'CAO DANG','CAO ĐẲNG','DAI HOC','ĐẠI HỌC','CD','DH'";

//     $notGdtxBt = "(s.school_type_id IS NULL OR s.school_type_id NOT IN ($inGdtx))";

//     // --- Tổng hợp theo người từ toàn bộ lịch sử ---
//     $peAgg = DB::table('person_educations as pe')
//         ->leftJoin('schools as s', 's.id', '=', 'pe.school_id')
//         ->leftJoin('graduation_levels as gl', 'gl.id', '=', 'pe.graduation_level_id')
//         ->select(
//             'pe.person_id',
//             DB::raw("MAX(CASE WHEN UPPER(gl.name)='THPT' AND $notGdtxBt THEN 1 ELSE 0 END) AS tn_thpt"),
//             DB::raw("MAX(CASE WHEN UPPER(gl.name)='THPT' AND s.school_type_id IN ($inBt) THEN 1 ELSE 0 END) AS tn_bt_thpt"),
//             DB::raw("MAX(CASE WHEN UPPER(gl.name) IN ($GL_TC) THEN 1 ELSE 0 END) AS tn_thcn_tdn"),
//             DB::raw("MAX(CASE WHEN s.school_type_id IN ( $inVoc ) THEN 1 ELSE 0 END) AS studied_voc"),
//             DB::raw("MAX(CASE WHEN UPPER(gl.name) IN ($GL_TC, $GL_DH) THEN 1 ELSE 0 END) AS has_tc_dh"),
//             DB::raw("MAX(CASE WHEN UPPER(gl.name)='THPT' AND $notGdtxBt AND (pe.graduation_year = '{$lastYr}' or pe.graduation_year = '{$lastacademic}' ) THEN 1 ELSE 0 END) AS grad_thpt_last"),
//             DB::raw("MAX(CASE WHEN UPPER(gl.name)='THPT' AND s.school_type_id IN ($inGdtx) AND ( pe.graduation_year = '{$lastYr}' or pe.graduation_year = '{$lastacademic}') THEN 1 ELSE 0 END) AS grad_gdtx_thpt_last")
//         )
//         ->groupBy('pe.person_id')
       
//         // ->get()
//         ;
//     // dd($y1, $academic,$peAgg[0]);
//     // --- Năm học hiện tại: HS lớp 12 tại THPT và tại GDTX/BT ---
//     $peNow = DB::table('person_educations as pe1')
//         ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe1.education_block_id')
//         ->leftJoin('schools as s1', 's1.id', '=', 'pe1.school_id')
//         ->select(
//             'pe1.person_id',
//             DB::raw("MAX(CASE WHEN CAST(eb.name AS UNSIGNED)=12 AND s1.school_type_id IN ($inGdtx) THEN 1 ELSE 0 END) AS is_gdtx12"),
//             DB::raw("MAX(CASE WHEN CAST(eb.name AS UNSIGNED)=12 AND (s1.school_type_id IS NULL OR s1.school_type_id NOT IN ($inGdtx)) THEN 1 ELSE 0 END) AS is_thpt12")
//         )
//         ->whereRaw('pe1.academic_year = ?', [$lastacademic])
//         ->groupBy('pe1.person_id')
//         // ->get() 
//         ;
//     // dd($lastacademic,$peNow);
//     $unitIdExpr = "COALESCE(p.village_id, h.village_id)";

//     $qb = DB::table('survey_form_details as d')
//         ->join('survey_forms as f', fn($j) => $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false))
//         ->join('people as p', 'p.id', '=', 'd.person_id')
//         ->leftJoin('household_members as hm', 'hm.person_id', '=', 'd.person_id ')
//         ->leftJoin('households as h', 'h.id', '=', 'hm.household_id')
//         ->leftJoinSub($peAgg, 'pea', fn($j) => $j->on('pea.person_id','=','d.person_id'))
//         ->leftJoinSub($peNow, 'pen', fn($j) => $j->on('pen.person_id','=','d.person_id'))
//         ->leftJoin('geo_units as v', fn($j) => $j->on('v.id', '=', DB::raw($unitIdExpr)))
//         ->where('d.is_deleted', false)
//         ->whereRaw('d.academic_year = ?', [$academic])
//         ->whereIn('p.dob_year', $years)
//         // ->get()
//         ;
//     // dd($academic,$qb);

//     $this->applyGeoFilter($qb, $scheme, $geo);

//     $rows = $qb->selectRaw("
//             {$unitIdExpr} AS unit_id,
//             COALESCE(v.name, 'Không rõ') AS unit_name,

//             COUNT(*) AS total_18_21,
//             COUNT(*) AS must_pc,

//             SUM(COALESCE(pea.tn_thpt,0))     AS tn_thpt,
//             SUM(COALESCE(pea.tn_bt_thpt,0))  AS tn_bt_thpt,
//             SUM(COALESCE(pea.tn_thcn_tdn,0)) AS tn_thcn_tdn,

//             ROUND(
//               (SUM(COALESCE(pea.tn_thpt,0) + COALESCE(pea.tn_bt_thpt,0) + COALESCE(pea.tn_thcn_tdn,0))
//                / NULLIF(COUNT(*),0) * 100), 2
//             ) AS ratio_all,

//             SUM(COALESCE(pea.grad_thpt_last,0))        AS grad_thpt_last,
//             ROUND( SUM(COALESCE(pea.grad_thpt_last,0)) / NULLIF(COUNT(*),0) * 100 , 2) AS ratio_last,

//             SUM(COALESCE(pen.is_thpt12,0))             AS hs12_thpt,   -- <== mới
//             SUM(COALESCE(pen.is_gdtx12,0))             AS hs12_gdtx,
//             SUM(COALESCE(pea.grad_gdtx_thpt_last,0))   AS grad_gdtx_thpt_last,

//             SUM(CASE WHEN COALESCE(pea.studied_voc,0)=1 OR COALESCE(pea.has_tc_dh,0)=1 THEN 1 ELSE 0 END) AS voc3_total,
//             SUM(COALESCE(pea.has_tc_dh,0)) AS degree_total
//         ")
//         ->groupBy('unit_id','unit_name')
//         ->orderBy('unit_name')
//         ->get();

//     $out = [];
//     foreach ($rows as $r) {
//         $out[] = [
//             'unit_id'             => (int) $r->unit_id,
//             'unit_name'           => (string) $r->unit_name,
//             'total_18_21'         => (int) $r->total_18_21,
//             'must_pc'             => (int) $r->must_pc,
//             'tn_thpt'             => (int) $r->tn_thpt,
//             'tn_bt_thpt'          => (int) $r->tn_bt_thpt,
//             'tn_thcn_tdn'         => (int) $r->tn_thcn_tdn,
//             'ratio_all'           => (float) $r->ratio_all,
//             'grad_thpt_last'      => (int) $r->grad_thpt_last,
//             'ratio_last'          => (float) $r->ratio_last,
//             'hs12_thpt'           => (int) $r->hs12_thpt,   // <== mới
//             'hs12_gdtx'           => (int) $r->hs12_gdtx,
//             'grad_gdtx_thpt_last' => (int) $r->grad_gdtx_thpt_last,
//             'voc3_total'          => (int) $r->voc3_total,
//             'degree_total'        => (int) $r->degree_total,
//         ];
//     }
//     return $out;
// }


    protected function templateCfg(): array
    {
        return [
            'path' => resource_path('excel/4.3.THPT.HieuQuaPhoCap.xlsx'),
            'header' => [
                'province' => 'C2',
                'ward'     => 'C3',
            ],
            'dateTop'    => 'G4',
            'footerDate' => 'N26',   // đúng ô dưới góc phải trong template của bạn
            'startRow'   => 8,
            'sumRow'     => 9,      // dòng “Tổng cộng” trong template trắng
            'lastCol'    => 'R',
        ];
    }
}
