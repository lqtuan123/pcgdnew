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

class R41Report extends BaseReport
{
      public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}
public function export(Request $req)
{
    $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear());
    [$y1]     = array_map('intval', explode('-', $academic));
    $scheme   = (string)$req->input('scheme', 'old');
    $geo      = (array)$req->input('geo', []);

    if (method_exists(\PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance(), 'setCalculationEnabled')) {
        \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance()->setCalculationEnabled(false);
    }

    $cfg = $this->templateCfg(); // lastCol = 'AO'
    $wb  = \PhpOffice\PhpSpreadsheet\IOFactory::load($cfg['path']);
    $sh  = $wb->getActiveSheet();

    // Header
    $sh->setCellValue($cfg['header']['province'],  $this->geoService->geoNameFromId($geo['province_id'] ?? null));
    $sh->setCellValue($cfg['header']['ward'],      $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
    if (!empty($cfg['dateTop'])) {
        $sh->setCellValue('G4', $this->formatService->top_date());
    }

    // Dữ liệu
    $data    = $this->queryThptKetQuaHuyDong($academic, $scheme, $geo);
    $byAge   = $data['by_age'];      // 14..21
    $sum1517 = $data['sum_15_17'];
    $sum1821 = $data['sum_18_21'];

    $startRow = $cfg['startRow']; // 14
    $tplRow   = $startRow;
    $r        = $startRow;
    $tong_12 = 0;
    $tong_tn_thcs = 0;
    $tong_hoc_10 = 0;
    $tong_tn_12 = 0;
    $tong_tnthpt = 0;
    $tong_pccc = 0;
    $writeRow = function (int $row, ?int $age, array $v, bool $isSum=false) use ($sh, $y1)
    {
        // A,B
        if ($isSum) {
            $sh->setCellValue("A{$row}", $v['_label'] ?? '');
        } else {
            $sh->setCellValue("A{$row}", $age);
            $sh->setCellValue("B{$row}", $y1 - $age);
        }
//  'total','female_total','disabled_total','moved_out_total','moved_in_total','must_pc',
        // Tính PPC tại export để đảm bảo đúng yêu cầu
        $total      = (int)($v['total']      ?? 0);
        $female     = (int)($v['female_total']     ?? 0);
        $disabled   = (int)($v['disabled_total']   ?? 0);
        // $moved_out  = (int)($v['moved_out_total']  ?? 0);
        // $deceased   = (int)($v['deceased']   ?? 0);
        $moved_in   = (int)($v['moved_in_total']   ?? 0);
        $moved_od   = (int)($v['moved_out_total']   ?? 0);
        $must_pc    = $total - ($disabled + $moved_od);
       
        
        // --- C..AO ---
        $map = [
            // Nhân khẩu
            'C'  => $total,
            'D'  => $female,
            'E'  => $disabled,
            'F'  => $moved_od,      // chuyển đi + chết
            'G'  => $moved_in,
            'H'  => $must_pc,

            // THCS (I–J)
            'I'  => $v['tn_thcs_last'] ?? 0,
            'J'  => $v['tn_thcs_prev1']-$v['tn_thcs_last'] ?? 0,

            // THPT thường (K–P)
            'K'  => $v['thpt_g10_last']    ?? 0,
            'L'  => $v['thpt_g10_prev']    ?? 0,
            'M'  => $v['thpt_g11']         ?? 0,
            'N'  => $v['thpt_g12']         ?? 0,
            'O'  => $v['thpt_grad_last']   ?? 0,
            'P'  => $v['thpt_grad_prev']   ?? 0,

            // GDTX/BT (Q–V)
            'Q'  => $v['gdtx_g10_last']    ?? 0,
            'R'  => $v['gdtx_g10_prev']    ?? 0,
            'S'  => $v['gdtx_g11']         ?? 0,
            'T'  => $v['gdtx_g12']         ?? 0,
            'U'  => $v['gdtx_grad_last']   ?? 0,
            'V'  => $v['gdtx_grad_prev']   ?? 0,

            // THCN/TDN (W–AC)
            'W'  => $v['voc_grad_last']    ?? 0,
            'X'  => $v['voc_grad_prev']    ?? 0,
            'Y'  => $v['voc_y1']           ?? 0,
            'Z'  => $v['voc_y2']           ?? 0,
            'AA' => $v['voc_y3']           ?? 0,
            'AB' => $v['voc_y35']          ?? 0,
            'AC' => $v['voc_y4']           ?? 0,

            // Bỏ học (AD–AF)
            'AD' => $v['drop_th']          ?? 0,
            'AE' => $v['drop_thcs']        ?? 0,
            'AF' => $v['drop_thpt']        ?? 0,

            // Đang học TH/THCS (2 hệ) (AG)
            'AG' => $v['studying_th_thcs'] ?? 0,

            // Học xong (AH–AJ)
            'AH' => $v['hx_1_5']           ?? 0,
            'AI' => $v['hx_6_9']           ?? 0,
            'AJ' => $v['hx_10_11']         ?? 0,

            // Trượt TN (AK–AN)
            'AK' =>  $v['fail_th']         ?? 0,
            'AL' => $v['fail_thcs']        ?? 0,
            'AM' => $v['fail_thpt']        ?? 0,
            'AN' => $v['fail_voc']         ?? 0,

            // Chưa đi học (AO)
            'AO' => $v['never_studied']    ?? 0,
        ];

        foreach ($map as $col => $val) {
            $sh->setCellValue($col.$row, $val);
        }
    };

    // Nhân bản style khối
    $endRow = $startRow + 9;
    $sh->duplicateStyle(
        $sh->getStyle("A{$tplRow}:{$cfg['lastCol']}{$tplRow}"),
        "A{$startRow}:{$cfg['lastCol']}{$endRow}"
    );

    // 14..17
    foreach ([14,15,16,17] as $age) {
        $writeRow($r, $age, $byAge[$age] ?? [], false);
        $r++;
    }
    // Tổng 15–17
    $sum1517['_label'] = 'Tổng số 15-17';
    $writeRow($r, null, $sum1517, true); $r++;

    // 18..21
    foreach ([18,19,20,21] as $age) {
        $writeRow($r, $age, $byAge[$age] ?? [], false);
        $r++;
    }
    // Tổng 18–21
    $sum1821['_label'] = 'Tổng số 18-21';
    $writeRow($r, null, $sum1821, true); $r++;

    // Viền + căn lề
    $this->drawThinBorders($sh, $startRow, $r-1, 'A', $cfg['lastCol']);

    $sh->getStyle("C{$startRow}:AO".($r-1))
   ->getNumberFormat()->setFormatCode('0;-0;;@'); // zero section rỗng

    // Ẩn số 0 cho 3 ô phần trăm (hiển thị 0.00% nếu muốn rỗng thì giữ null)
    $sh->getStyle('M23:M25')
    ->getNumberFormat()->setFormatCode('0.00%;-0.00%;;@'); // zero section rỗng

    
    $val = function(string $addr) use ($sh): float {
        $v = $sh->getCell($addr)->getCalculatedValue();
        return is_numeric($v) ? (float)$v : 0.0;
    };

    // helper: chia an toàn, trả về null nếu mẫu số = 0 (để ô trống)
    $ratio = function(float $num, float $den): ?float {
        return ($den > 0.0) ? ($num / $den) : null;
    };

    // --- Tổng hợp số liệu ---
    $tong_hoc_10   = $val('K15') + $val('L15') + $val('K20') + $val('L20');
    $tong_tn_thcs  = $val('I15') + $val('I20');

    $tong_tn_12    = $val('O15') + $val('O20');
    $tong_tnthpt   = $val('P20') + $val('O20');   // theo công thức bạn đang dùng
    $tong_pccc     = $val('H20');

    // nếu có dữ liệu sum_15_17 / sum_18_21
    $sum1517 = $data['sum_15_17'] ?? [];
    $sum1821 = $data['sum_18_21'] ?? [];
    $tong_12 = (int)($sum1517['last_12'] ?? 0) + (int)($sum1821['last_12'] ?? 0);
    if ($tong_12 === 0) {
        $tong_12 = (int)$tong_tn_12;  // fallback như bạn yêu cầu
    }

    // --- Ghi các ô dạng “a/b” ---
    $sh->setCellValue('J23', ((int)$tong_hoc_10)  . '/' . ((int)$tong_tn_thcs));
    $sh->setCellValue('J24', ((int)$tong_tn_12)   . '/' . ((int)$tong_12));
    $sh->setCellValue('J25', ((int)$tong_tnthpt)  . '/' . ((int)$tong_pccc));

    // --- Ghi các ô % (chia an toàn, ô trống nếu mẫu số = 0) ---
    $sh->setCellValue('M23', $ratio($tong_hoc_10,  $tong_tn_thcs));
    $sh->setCellValue('M24', $ratio($tong_tn_12,   $tong_12));
    $sh->setCellValue('M25', $ratio($tong_tnthpt,  $tong_pccc));

    // Định dạng % với 2 số thập phân cho các ô cột M
    $sh->getStyle('M23:M25')->getNumberFormat()->setFormatCode('0.00%');

    // Footer
    if (!empty($cfg['footerDate'])) {
        $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
        $sh->setCellValue('AC22', $this->formatService->end_date($wardName));
    }

    return response()->streamDownload(function () use ($wb) {
        $w = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($wb, 'Xlsx');
        $w->setPreCalculateFormulas(false);
        $w->save('php://output');
    }, $this->getExportFilename($cfg['path']), [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);
}


protected function queryThptKetQuaHuyDong(string $academic, string $scheme, array $geo): array
{
    [$y1] = array_map('intval', explode('-', $academic));
    $lastAcademic = ($y1 - 1) . '-' . $y1;       // năm học trước
    $prevAcademic = ($y1 - 2) . '-' . ($y1 - 1); // năm học trước nữa

    // Tuổi 14..21 (mỗi dòng là một cohort theo năm sinh)
    $ages  = range(14, 21);
    $years = array_map(fn($a) => $y1 - $a, $ages);

    // Phân loại trường
    $TYPE_GDTX = [3, 11];
    $TYPE_VOC  = [1]; // Trường dạy nghề
    $inGdtx = implode(',', $TYPE_GDTX);
    $inVoc  = implode(',', $TYPE_VOC);

    // Tên/mã văn bằng
    $GL_TH   = "'TH'";
    $GL_THCS = "'THCS'";
    $GL_THPT = "'THPT'";

    // Map các “khối nghề” (nếu dùng)
    $vocMap = [
        'TDN1'  => "TDN1",
        'TDN2'  => "TDN2",
        'TDN3'  => "TDN3",
        'TDN35' => "TDN3.5",
        'TDN4'  => "TDN4",
        'TC1'   => "THCN1",
        'TC2'   => "THCN2",
    ];

    /* =========================
     * 1) Subquery “năm các năm học có năm trước”
     * ========================= */
    $lastYear = DB::table('v_survey_detail_flat as v')
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
            // học cuối cấp năm trước
            DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=5  THEN 1 ELSE 0 END) AS had_g5"),
            DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=9  THEN 1 ELSE 0 END) AS had_g9"),
            DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=12 THEN 1 ELSE 0 END) AS had_g12"),
            DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=12 AND s.school_type_id IN ($inGdtx) THEN 1 ELSE 0 END) AS had_g12_gdtx"),
            DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=12 AND (s.school_type_id IS NULL OR s.school_type_id NOT IN ($inGdtx)) THEN 1 ELSE 0 END) AS had_g12_thpt"),

            // TN THCS năm qua & năm trước (gồm cả THPT vì có 4 thì đã có 3)
            DB::raw("MAX(CASE WHEN (UPPER(v.graduation_level_name) IN ($GL_THCS)) AND v.graduation_year = '$lastAcademic' THEN 1 ELSE 0 END) AS tn_thcs_last"),
            DB::raw("MAX(CASE WHEN (UPPER(v.graduation_level_name) IN ($GL_THCS)) AND v.graduation_year < '$lastAcademic' THEN 0 ELSE 0 END) AS tn_thcs_prev"),
            DB::raw("MAX(CASE WHEN (UPPER(v.graduation_level_name) IN ($GL_THCS, $GL_THPT)) and v.academic_year = '$academic' THEN 1 ELSE 0 END) AS tn_thcs_prev1"),

            
            DB::raw("MAX(CAST(v.education_block_code AS UNSIGNED)) AS max_grade_upto_last"),
            // TN THPT năm qua / các năm trước – hệ THPT thường
            DB::raw("MAX(CASE WHEN UPPER(v.graduation_level_name)='THPT'
                               AND v.graduation_year = '$lastAcademic'
                               AND (v.is_bo_tuc=0 OR v.is_bo_tuc IS NULL)
                               THEN 1 ELSE 0 END) AS tn_thpt_last"),
            DB::raw("MAX(CASE WHEN UPPER(v.graduation_level_name)='THPT'
                               AND v.graduation_year < '$lastAcademic'
                               AND (v.is_bo_tuc=0 OR v.is_bo_tuc IS NULL)
                               THEN 1 ELSE 0 END) AS tn_thpt_prev"),

            // TN THPT năm qua – hệ GDTX/BT
            DB::raw("MAX(CASE WHEN UPPER(v.graduation_level_name)='THPT'
                               AND v.graduation_year = '$lastAcademic'
                               AND v.is_bo_tuc=1 
                               THEN 1 ELSE 0 END) AS tn_gdtx_last"),

            DB::raw("MAX(CASE WHEN (UPPER(v.graduation_level_name) IN ($GL_TH,$GL_THCS,$GL_THPT))  THEN 1 ELSE 0 END) AS tn_th_m"),
            DB::raw("MAX(CASE WHEN (UPPER(v.graduation_level_name) IN ($GL_THCS,$GL_THPT))  THEN 1 ELSE 0 END) AS tn_thcs_m"),
            DB::raw("MAX(CASE WHEN (UPPER(v.graduation_level_name) IN ($GL_THPT))  THEN 1 ELSE 0 END) AS tn_thpt_m"),
        )
        // ->where('v.academic_year', $lastAcademic)
        ->groupBy('v.person_id');

    /* =========================
     * 2) Subquery “đã từng có hồ sơ giáo dục” (để tính ‘chưa đi học’)
     * ========================= */
    $hasEduAny = DB::table('person_educations')
        ->select('person_id', DB::raw('1 AS has_edu'))
        ->groupBy('person_id');

    /* =========================
     * 3) Dataset năm học hiện tại
     * ========================= */
    $cur = DB::table('v_survey_detail_flat as v')
        ->leftJoin('schools as s', function($j) {
            $j->on('s.code', '=', 'v.school_code')
              ->where(function($w) {
                  $w->whereNull('s.is_dissolved')
                    ->orWhere('s.is_dissolved', false)
                    ->orWhere('s.is_dissolved', 0);
              });
        })
        ->leftJoinSub($lastYear,  'ly',  fn($j) => $j->on('ly.person_id','=','v.person_id'))
        ->leftJoinSub($hasEduAny, 'pe',  fn($j) => $j->on('pe.person_id','=','v.person_id'))
        ->where('v.academic_year', $academic)
        ->whereIn('v.dob_year', $years);

    // Lọc địa bàn theo scheme
    if (($geo['province_id'] ?? null) || ($geo['ward_id'] ?? null)) {
        if ($scheme === 'old') {
            if (!empty($geo['province_id'])) $cur->where('v.old_province_id', $geo['province_id']);
            if (!empty($geo['ward_id']))     $cur->where('v.old_ward_id',     $geo['ward_id']);
        } else {
            if (!empty($geo['province_id'])) $cur->where('v.new_province_id', $geo['province_id']);
            if (!empty($geo['ward_id']))     $cur->where('v.new_ward_id',     $geo['ward_id']);
        }
    }

    // Điều kiện tái sử dụng
    $grade    = "CAST(v.education_block_code AS UNSIGNED)";
    $isGdtx   = " (v.is_bo_tuc = 1 )";
    $notGdtx  = "( (s.school_type_id IS NULL OR s.school_type_id NOT IN ($inGdtx)) AND (v.is_bo_tuc=0 OR v.is_bo_tuc IS NULL) )";

    // “Có bất kỳ dạng khuyết tật nào”
    $disAny = "(
        COALESCE(v.dis_VAN_DONG,0) +
        COALESCE(v.dis_NGHE_NOI,0) +
        COALESCE(v.dis_NHIN,0) +
        COALESCE(v.dis_THAN_KINH,0) +
        COALESCE(v.dis_TRI_TUE,0) +
        COALESCE(v.dis_HOC_TAP,0) +
        COALESCE(v.dis_TU_KY,0) +
        COALESCE(v.dis_KHAC,0)
    ) > 0";

    // Chuyển đi/Chết và Chuyển đến
    $movedOutOrDead = "v.sc_name IN ('Chuyển đi','MOVED_OUT','Chết','DECEASED')";
    $movedIn        = "v.sc_name IN ('Chuyển đến','MOVED_IN')";

    /* =========================
     * 4) Tổng hợp theo năm sinh
     * ========================= */
    $rows = $cur->selectRaw("
            v.dob_year AS year_of_birth,

            /* Tổng số trong độ tuổi (đếm người duy nhất) */
            COUNT(DISTINCT v.person_id) AS total,

            /* D: Nữ */
            COUNT(DISTINCT CASE WHEN CAST(v.is_female AS UNSIGNED)=1 THEN v.person_id END) AS female_total,

            /* E: Khuyết tật (có ít nhất 1 cờ) */
            COUNT(DISTINCT CASE WHEN $disAny THEN v.person_id END) AS disabled_total,

            /* F: Chuyển đi/Chết */
            COUNT(DISTINCT CASE WHEN $movedOutOrDead THEN v.person_id END) AS moved_out_total,

            /* G: Chuyển đến */
            COUNT(DISTINCT CASE WHEN $movedIn THEN v.person_id END) AS moved_in_total,

            /* H: Phải phổ cập = Tổng – Khuyết tật – (Chuyển đi/Chết) */
            (
                COUNT(DISTINCT v.person_id)
                - COUNT(DISTINCT CASE WHEN $disAny THEN v.person_id END)
                - COUNT(DISTINCT CASE WHEN $movedOutOrDead THEN v.person_id END)
            ) AS must_pc,

            /* I–J: TN THCS (kể cả THPT) năm qua / năm trước */
            SUM(COALESCE(ly.tn_thcs_last,0)) AS tn_thcs_last,
            SUM(COALESCE(ly.tn_thcs_prev,0))  AS tn_thcs_prev,
            SUM(COALESCE(ly.tn_thcs_prev1,0)) AS tn_thcs_prev1,
            /* K–P: THPT thường */
            SUM(CASE WHEN $grade=10 AND v.is_bo_tuc = 0 and UPPER(v.graduation_level_name) IN ($GL_THCS) AND v.graduation_year = '$lastAcademic'  THEN 1 ELSE 0 END) AS thpt_g10_last,
            SUM(CASE WHEN $grade=10 AND v.is_bo_tuc = 0  and UPPER(v.graduation_level_name) IN ($GL_THCS) AND v.graduation_year <> '$lastAcademic' AND v.graduation_year <> '$academic'  THEN 1 ELSE 0 END) AS thpt_g10_prev,
            SUM(CASE WHEN $grade=11 AND v.is_bo_tuc = 0  AND v.academic_year = '$academic'   THEN 1 ELSE 0 END) AS thpt_g11,
            SUM(CASE WHEN $grade=12 AND v.is_bo_tuc = 0 AND v.academic_year = '$academic'   THEN 1 ELSE 0 END) AS thpt_g12,
            SUM(COALESCE(ly.tn_thpt_last,0)) AS thpt_grad_last,
            SUM(COALESCE(ly.tn_thpt_prev,0)) AS thpt_grad_prev,

            /* Q–V: Bổ túc/GDTX */

            SUM(CASE WHEN $grade=10 AND v.is_bo_tuc = 1 and UPPER(v.graduation_level_name) IN ($GL_THCS) AND v.graduation_year = '$lastAcademic'  THEN 1 ELSE 0 END) AS gdtx_g10_last,
            SUM(CASE WHEN $grade=10 AND v.is_bo_tuc = 1  and UPPER(v.graduation_level_name) IN ($GL_THCS) AND v.graduation_year <> '$lastAcademic' AND v.graduation_year <> '$academic'  THEN 1 ELSE 0 END) AS gdtx_g10_prev,
            SUM(CASE WHEN $grade=11 AND v.is_bo_tuc = 1  AND v.academic_year = '$academic'   THEN 1 ELSE 0 END) AS gdtx_g11,
            SUM(CASE WHEN $grade=12 AND v.is_bo_tuc = 1 AND v.academic_year = '$academic'   THEN 1 ELSE 0 END) AS gdtx_g12,
            SUM(COALESCE(ly.tn_gdtx_last,0)) AS gdtx_grad_last,
            SUM(CASE WHEN UPPER(v.graduation_level_name)='THPT' AND v.graduation_year < '$lastAcademic'   AND v.is_bo_tuc = 1 THEN 1 ELSE 0 END) AS gdtx_grad_prev,

             

            /* W–X: Tốt nghiệp nghề (TC/TDN) năm qua / trước */
            SUM(CASE WHEN v.vocational_level_name IS NOT NULL AND v.vocational_grad_year = '$lastAcademic' THEN 1 ELSE 0 END) AS voc_grad_last,
            SUM(CASE WHEN v.vocational_level_name IS NOT NULL AND v.vocational_grad_year <  '$lastAcademic' THEN 1 ELSE 0 END) AS voc_grad_prev,

            /* Y–AC: Đang học nghề theo 'khối' */
            SUM(CASE WHEN v.education_block_code='{$vocMap['TDN1']}'  THEN 1 ELSE 0 END) AS voc_y1,
            SUM(CASE WHEN v.education_block_code='{$vocMap['TDN2']}'  THEN 1 ELSE 0 END) AS voc_y2,
            SUM(CASE WHEN v.education_block_code='{$vocMap['TDN3']}'  THEN 1 ELSE 0 END) AS voc_y3,
            SUM(CASE WHEN v.education_block_code='{$vocMap['TDN35']}' THEN 1 ELSE 0 END) AS voc_y35,
            SUM(CASE WHEN v.education_block_code='{$vocMap['TDN4']}'  THEN 1 ELSE 0 END) AS voc_y4,

            /* AD–AF: Bỏ học theo cấp */
            SUM(CASE WHEN v.bo_hoc_lop BETWEEN 1 AND 5  THEN 1 ELSE 0 END)  AS drop_th,
            SUM(CASE WHEN v.bo_hoc_lop BETWEEN 6 AND 9  THEN 1 ELSE 0 END)  AS drop_thcs,
            SUM(CASE WHEN v.bo_hoc_lop BETWEEN 10 AND 12 THEN 1 ELSE 0 END) AS drop_thpt,

            /* AG: Đang học TH/THCS */
            SUM(CASE WHEN $grade BETWEEN 1 AND 9 THEN 1 ELSE 0 END) AS studying_th_thcs,

            /* AH–AJ: Học xong lớp */
            SUM(CASE WHEN v.hoc_xong_lop BETWEEN 1 AND 5  THEN 1 ELSE 0 END) AS hx_1_5,
            SUM(CASE WHEN v.hoc_xong_lop BETWEEN 6 AND 9  THEN 1 ELSE 0 END) AS hx_6_9,
            SUM(CASE WHEN v.hoc_xong_lop IN (10,11)      THEN 1 ELSE 0 END) AS hx_10_11,


              SUM(CASE WHEN $grade =5 and v.academic_year <  '$lastAcademic'  THEN 1 ELSE 0 END) AS fail_th,
                SUM(CASE WHEN $grade =9 and v.academic_year <  '$lastAcademic'  THEN 1 ELSE 0 END) AS fail_thcs,
                  SUM(CASE WHEN $grade =12 and v.academic_year =  '$prevAcademic'  THEN 1 ELSE 0 END) AS fail_thpt,
               SUM(CASE WHEN $grade =12 and v.academic_year <  '$prevAcademic'  THEN 1 ELSE 0 END) AS fail_voc,
                  SUM(CASE WHEN $grade =12 and v.academic_year =  '$lastAcademic'  THEN 1 ELSE 0 END) AS last_12,
            
            
                   
            /* AK–AM: Không đậu tốt nghiệp (rớt TN) */
           
           

            /* AN: rớt nghề – tạm 0 */
            0 AS fail_voc,

            /* AO: Chưa đi học (không có bất kỳ person_educations) */
            SUM(CASE WHEN pe.has_edu IS NULL THEN 1 ELSE 0 END) AS never_studied
        ")
        ->groupBy('year_of_birth')
        ->orderBy('year_of_birth')
        ->get();

    /* =========================
     * 5) Map về dạng theo tuổi
     * ========================= */
    $rowKeys = [
        'total','female_total','disabled_total','moved_out_total','moved_in_total','must_pc',
        'tn_thcs_last','tn_thcs_prev','tn_thcs_prev1',
        'thpt_g10_last','thpt_g10_prev','thpt_g11','thpt_g12','thpt_grad_last','thpt_grad_prev',
        'gdtx_g10_last','gdtx_g10_prev','gdtx_g11','gdtx_g12','gdtx_grad_last','gdtx_grad_prev',
        'voc_grad_last','voc_grad_prev','voc_y1','voc_y2','voc_y3','voc_y35','voc_y4',
        'drop_th','drop_thcs','drop_thpt','studying_th_thcs',
        'hx_1_5','hx_6_9','hx_10_11',
        'fail_th', 'fail_thcs','fail_thpt','fail_voc','last_12',
        'never_studied',
    ];

    $byAge = [];
    foreach ($rows as $r) {
        $age = $y1 - (int)$r->year_of_birth;
        $byAge[$age] = [];
        foreach ($rowKeys as $k) {
            $byAge[$age][$k] = (int)($r->$k ?? 0);
        }
    }

    // Điền 0 cho tuổi thiếu
    foreach ($ages as $a) {
        if (!isset($byAge[$a])) {
            $byAge[$a] = array_fill_keys($rowKeys, 0);
        }
    }
    ksort($byAge);

    // Gộp nhóm 15–17 và 18–21
    $sum1517 = array_fill_keys($rowKeys, 0);
    $sum1821 = array_fill_keys($rowKeys, 0);
    foreach ($byAge as $age => $vals) {
        if ($age >= 15 && $age <= 17) {
            foreach ($rowKeys as $k) $sum1517[$k] += $vals[$k];
        }
        if ($age >= 18 && $age <= 21) {
            foreach ($rowKeys as $k) $sum1821[$k] += $vals[$k];
        }
    }

    return [
        'by_age'    => $byAge,   // 14..21
        'sum_15_17' => $sum1517,
        'sum_18_21' => $sum1821,
    ];
}


protected function templateCfg(): array
{
    return [
        'path'       => resource_path('excel/4.1.THPT.14.21Tuoi.xlsx'),
        'header'     => ['province' => 'C2', 'ward' => 'C3'],
        'dateTop'    => 'N5',
        'footerDate' => 'AI27',
        'startRow'   => 11,
        'lastCol'    => 'AO',   // vì đã thêm D,E,F,G trước H
    ];
}
}