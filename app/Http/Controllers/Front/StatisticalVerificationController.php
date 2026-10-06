<?php

namespace App\Http\Controllers\Front;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;

class StatisticalVerificationController extends Controller
{
    public function index(Request $request)
    {
        // Parse parameters - mặc định năm học 2024-2025
        $academic = (string)($request->input('school_year') ?: '2024-2025');
        $scheme = (string)$request->input('scheme', 'old');
        $geo = (array)$request->input('geo', []);
        
        // Tạo cache key dựa trên các tham số
        $cacheKey = 'stat_verify_' . md5($academic . $scheme . json_encode($geo));
        
        // Cache kết quả trong 1 giờ (3600 giây)
        $data = Cache::remember($cacheKey, 3600, function () use ($academic, $scheme, $geo) {
            return $this->calculateStatistics($academic, $scheme, $geo);
        });
        
        return view('front.reports.statistical-verification.index', $data);
    }
    
    /**
     * Tính toán tất cả thống kê
     */
    protected function calculateStatistics($academic, $scheme, $geo)
    {
        [$y1] = array_map('intval', explode('-', $academic));

        // Years of birth for ages 6..14 at year y1 (desc)
        $years = [];
        for ($age = 6; $age <= 14; $age++) { $years[] = $y1 - $age; }
        rsort($years);

        // Sử dụng view v_survey_detail_flat giống TiH614Report và StatisticalCheckController
        $qb = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->where('v.academic_year', $academic)
            ->whereIn('v.dob_year', $years);

        // Geo filter (old/new)
        if ($scheme === 'old') {
            if (!empty($geo['province_id'])) $qb->where('v.old_province_id', (int)$geo['province_id']);
            if (!empty($geo['district_id'])) $qb->where('v.old_district_id', (int)$geo['district_id']);
            if (!empty($geo['ward_id']))     $qb->where('v.old_ward_id',     (int)$geo['ward_id']);
        } else {
            if (!empty($geo['province_id'])) $qb->where('v.new_province_id', (int)$geo['province_id']);
            if (!empty($geo['ward_id']))     $qb->where('v.new_ward_id',     (int)$geo['ward_id']);
        }

        // Điều kiện khuyết tật và khối lớp giống TiH614Report
        $disabledExpr = "(v.dis_VAN_DONG=1 OR v.dis_NGHE_NOI=1 OR v.dis_NHIN=1 OR v.dis_THAN_KINH=1 OR v.dis_TRI_TUE=1 OR v.dis_HOC_TAP=1 OR v.dis_TU_KY=1 OR v.dis_KHAC=1)";
        $notDisabledExpr = "NOT({$disabledExpr})";
        $enrolledBlockExpr = "(v.education_block_code REGEXP '^[0-9]+$' AND CAST(v.education_block_code AS UNSIGNED) BETWEEN 1 AND 12)";
        $notEnrolledByBlock = "((v.education_block_code IS NULL OR v.education_block_code = '') AND (v.graduation_level_name IS NULL OR v.graduation_level_name=''))";

        // Aggregate minimal series needed - đếm NGƯỜI (DISTINCT person_id) để tránh trùng lặp
        $rows = $qb->selectRaw("
                v.dob_year as y,
                COUNT(DISTINCT v.person_id) as total,
                COUNT(DISTINCT CASE WHEN {$disabledExpr} THEN v.person_id END) as disabled,
                COUNT(DISTINCT CASE WHEN {$notEnrolledByBlock} AND {$notDisabledExpr} THEN v.person_id END) as non_dis_not_enrolled,
                COUNT(DISTINCT CASE WHEN {$notEnrolledByBlock} THEN v.person_id END) as not_enrolled_all,
                COUNT(DISTINCT CASE WHEN CAST(v.education_block_code AS UNSIGNED) = 1 THEN v.person_id END) as grade1_all,
                COUNT(DISTINCT CASE WHEN UPPER(TRIM(v.graduation_level_name)) IN ('TH','THCS','THPT') THEN v.person_id END) as th_graduated_all
            ")
            ->groupBy('v.dob_year')
            ->get();

        $series = [
            'total' => array_fill_keys($years, 0),
            'disabled' => array_fill_keys($years, 0),
            'non_dis_not_enrolled' => array_fill_keys($years, 0),
            'not_enrolled_all' => array_fill_keys($years, 0),
            'grade1_all' => array_fill_keys($years, 0),
            'th_graduated_all' => array_fill_keys($years, 0),
        ];
        foreach ($rows as $r) {
            $y = (int)$r->y;
            if (!isset($series['total'][$y])) continue;
            $series['total'][$y] = (int)$r->total;
            $series['disabled'][$y] = (int)$r->disabled;
            $series['non_dis_not_enrolled'][$y] = (int)$r->non_dis_not_enrolled;
            $series['not_enrolled_all'][$y] = (int)$r->not_enrolled_all;
            $series['grade1_all'][$y] = (int)$r->grade1_all;
            $series['th_graduated_all'][$y] = (int)$r->th_graduated_all;
        }

        // Convenience PPC per year (không khuyết tật)
        $ppc = [];
        foreach ($years as $y) {
            $ppc[$y] = ($series['total'][$y] ?? 0) - ($series['disabled'][$y] ?? 0);
        }

        // Map tiêu chí 1..9 cho cột Tiểu học (2.1)
        $y_6  = $y1 - 6;
        $y_11 = $y1 - 11;
        $y_12 = $y1 - 12;
        $y_13 = $y1 - 13;
        $y_14 = $y1 - 14;

        $pcgd21 = [
            1 => $series['total'][$y_6] ?? 0,                                           // Trẻ 6 tuổi (TẤT CẢ, bao gồm khuyết tật)
            2 => $series['grade1_all'][$y_6] ?? 0,                                      // Trẻ 6 tuổi học lớp 1 (education_block_code=1, bao gồm khuyết tật)
            3 => $ppc[$y_11] ?? 0,                                                      // PPC 11 tuổi
            4 => $ppc[$y_12] ?? 0,                                                      // PPC 12 tuổi
            5 => $ppc[$y_13] ?? 0,                                                      // PPC 13 tuổi
            6 => $ppc[$y_14] ?? 0,                                                      // PPC 14 tuổi
            7 => ($ppc[$y_11] ?? 0) + ($ppc[$y_12] ?? 0) + ($ppc[$y_13] ?? 0) + ($ppc[$y_14] ?? 0), // Tổng PPC 11-14
            8 => $series['th_graduated_all'][$y_11] ?? 0,                               // 11 tuổi hoàn thành TH (bao gồm cả THCS, THPT, khuyết tật)
            9 => ($series['th_graduated_all'][$y_11] ?? 0)
                + ($series['th_graduated_all'][$y_12] ?? 0)
                + ($series['th_graduated_all'][$y_13] ?? 0)
                + ($series['th_graduated_all'][$y_14] ?? 0),                            // 11-14 tuổi hoàn thành TH (giống THCS 3.2 tiêu chí 9)
        ];

        // Pass to view; fill defaults for 10..21 as 0 (các tiêu chí 10 trở đi sẽ là null/bôi xám cho Tiểu học 2.1)
        for ($i = 10; $i <= 21; $i++) { if (!isset($pcgd21[$i])) $pcgd21[$i] = 0; }

        /* ===================== THCS (3.1) từ R31Report ===================== */
        $yearsThcs = range($y1-11, $y1-18); // 11..18

        // dùng view phẳng giống R31 để tính nhanh
        $flat = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->where('v.academic_year', $academic)
            ->whereIn('v.dob_year', $yearsThcs);

        if ($scheme === 'old') {
            if (!empty($geo['province_id'])) $flat->where('v.old_province_id', $geo['province_id']);
            if (!empty($geo['ward_id']))     $flat->where('v.old_ward_id',     $geo['ward_id']);
        } else {
            if (!empty($geo['province_id'])) $flat->where('v.new_province_id', $geo['province_id']);
            if (!empty($geo['ward_id']))     $flat->where('v.new_ward_id',     $geo['ward_id']);
        }

        $TYPE_GDTX = [3,11];
        $TYPE_VOC  = [1];
        $inGdtx = implode(',', $TYPE_GDTX);
        $inVoc  = implode(',', $TYPE_VOC);
        $grade = "CAST(v.education_block_code AS UNSIGNED)";
        $isPT  = "( (s.school_type_id IS NULL OR s.school_type_id NOT IN ($inGdtx,$inVoc)) )";
        $isTX  = "( v.is_bo_tuc=1 OR s.school_type_id IN ($inGdtx) )";
        $isVOC = "( s.school_type_id IN ($inVoc) OR v.vocational_level_name ='Trung cấp' )";
        $disAny = "(COALESCE(v.dis_VAN_DONG,0)+COALESCE(v.dis_NGHE_NOI,0)+COALESCE(v.dis_NHIN,0)+COALESCE(v.dis_THAN_KINH,0)+COALESCE(v.dis_TRI_TUE,0)+COALESCE(v.dis_HOC_TAP,0)+COALESCE(v.dis_TU_KY,0)+COALESCE(v.dis_KHAC,0)) > 0 OR v.dis_has_certificate=1";
        $movedOutOrDead = "UPPER(TRIM(v.sc_name)) IN ('CHẾT','DECEASED','CHUYỂN ĐI','CHUYEN DI','MOVED_OUT')";
        
        // Điều kiện "tại chỗ" (local): trường học cùng địa bàn với người học
        $wardCol = $scheme === 'old' ? 'v.old_ward_id' : 'v.new_ward_id';
        $schWard = $scheme === 'old' ? 's.old_ward_id' : 's.new_ward_id';
        $local = "($schWard = $wardCol)";

        // head: must_pc theo yob
        $head = (clone $flat)->selectRaw("
                v.dob_year AS yob,
                COUNT(DISTINCT v.person_id) AS total,
                COUNT(DISTINCT CASE WHEN ($disAny) THEN v.person_id END) AS dis_total,
                (
                    COUNT(DISTINCT v.person_id)
                    - COUNT(DISTINCT CASE WHEN ($disAny) THEN v.person_id END)
                    - COUNT(DISTINCT CASE WHEN ($movedOutOrDead) THEN v.person_id END)
                ) AS must_pc
            ")
            ->groupBy('yob')
            ->get()
            ->keyBy('yob');

        // tốt nghiệp THCS (PT/BT) 15..18 - bao gồm cả người tốt nghiệp THPT (vì đã qua THCS)
        $tn = (clone $flat)
            ->whereBetween('v.dob_year', [$y1-18, $y1-15])
            ->selectRaw("
                v.dob_year AS yob,
                COUNT(DISTINCT CASE WHEN (UPPER(TRIM(v.graduation_level_name))='THCS' OR UPPER(TRIM(v.graduation_level_name))='THPT') AND $isPT THEN v.person_id END) AS tn_pt,
                COUNT(DISTINCT CASE WHEN (UPPER(TRIM(v.graduation_level_name))='THCS' OR UPPER(TRIM(v.graduation_level_name))='THPT') AND $isTX THEN v.person_id END) AS tn_bt
            ")
            ->groupBy('yob')
            ->get()
            ->keyBy('yob');

        // sau THCS (đang/đã học THPT/GDTX/GDNN) 15..18
        // Lưu ý: TN THPT (bất kể local/away) + đang học cấp THPT/GDNN "tại chỗ"
        $pt_gdnn = "((v.education_block_code IS NOT NULL and v.graduation_level_name ='THCS') )";
        $tnpt = "(v.graduation_level_name ='THPT')";
        
        $aft = (clone $flat)
            ->whereBetween('v.dob_year', [$y1-18, $y1-15])
            ->selectRaw("
                v.dob_year AS yob,
                SUM(CASE WHEN $tnpt OR ( $pt_gdnn AND $local AND $isPT) THEN 1 ELSE 0 END) AS aft_pt,
                SUM(CASE WHEN $pt_gdnn AND $isTX AND $local THEN 1 ELSE 0 END) AS aft_tx,
                SUM(CASE WHEN $isVOC AND $local THEN 1 ELSE 0 END) AS aft_voc
            ")
            ->groupBy('yob')
            ->get()
            ->keyBy('yob');

        $thcs31 = [];
        // 3..7: PPC 11..14
        $thcs31[3] = (int)($head[$y1-11]->must_pc ?? 0);
        $thcs31[4] = (int)($head[$y1-12]->must_pc ?? 0);
        $thcs31[5] = (int)($head[$y1-13]->must_pc ?? 0);
        $thcs31[6] = (int)($head[$y1-14]->must_pc ?? 0);
        $thcs31[7] = $thcs31[3] + $thcs31[4] + $thcs31[5] + $thcs31[6];

        // 10..14: 15–18
        $must1518 = 0; $tn_pt = 0; $tn_bt = 0; $aft_total = 0;
        for ($age=15; $age<=18; $age++) {
            $yob = $y1 - $age;
            $must1518 += (int)($head[$yob]->must_pc ?? 0);
            $tn_pt    += (int)($tn[$yob]->tn_pt ?? 0);
            $tn_bt    += (int)($tn[$yob]->tn_bt ?? 0);
            $aft_total += (int)($aft[$yob]->aft_pt ?? 0) + (int)($aft[$yob]->aft_tx ?? 0) + (int)($aft[$yob]->aft_voc ?? 0);
        }
        $thcs31[10] = $must1518;
        $thcs31[11] = $tn_pt + $tn_bt; // TT 11 (cũ: 13) - Tổng số 15-18 tuổi tốt nghiệp THCS

        /* ===================== THCS (3.2) từ R32Report ===================== */
        $lastAcademic = ($y1 - 1).'-'.$y1;
        $TYPE_GDTX = [3,11];
        $inGdtx    = implode(',', $TYPE_GDTX);

        $last = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->select(
                'v.person_id',
                DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=9 THEN 1 ELSE 0 END) AS g9_last"),
                DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=9  AND (s.school_type_id NOT IN ($inGdtx)) THEN 1 ELSE 0 END) AS g9_pt_last"),
                DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=9  AND (s.school_type_id IN ($inGdtx)) THEN 1 ELSE 0 END) AS g9_gdtx_last")
            )
            ->where('v.academic_year', $lastAcademic)
            ->groupBy('v.person_id');

        $now = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->select(
                'v.person_id',
                DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=1 THEN 1 ELSE 0 END) AS now_grade1"),
                DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=6 AND (s.school_type_id NOT IN ($inGdtx)) THEN 1 ELSE 0 END) AS now_thcs6_pt"),
                DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=6 AND (s.school_type_id IN ($inGdtx)) THEN 1 ELSE 0 END) AS now_thcs6_gdtx"),
                DB::raw("MAX(CASE WHEN UPPER(v.graduation_level_name)='TH' AND v.graduation_year='$lastAcademic' THEN 1 ELSE 0 END) AS grad_th_last"),
                DB::raw("MAX(CASE WHEN UPPER(v.graduation_level_name)='THCS' AND v.graduation_year='$lastAcademic' THEN 1 ELSE 0 END) AS grad_thcs_last_pt"),
                DB::raw("MAX(CASE WHEN UPPER(v.graduation_level_name)='THCS' AND v.graduation_year='$lastAcademic' THEN 1 ELSE 0 END) AS grad_thcs_last_gdtx")
            )
            ->where('v.academic_year', $academic)
            ->groupBy('v.person_id');

        $cur = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->leftJoinSub($last, 'ly', fn($j) => $j->on('ly.person_id', '=', 'v.person_id'))
            ->leftJoinSub($now,  'nw', fn($j) => $j->on('nw.person_id', '=', 'v.person_id'))
            ->where('v.academic_year', $academic);

        if ($scheme === 'old') {
            if (!empty($geo['province_id'])) $cur->where('v.old_province_id', $geo['province_id']);
            if (!empty($geo['ward_id']))     $cur->where('v.old_ward_id',     $geo['ward_id']);
        } else {
            if (!empty($geo['province_id'])) $cur->where('v.new_province_id', $geo['province_id']);
            if (!empty($geo['ward_id']))     $cur->where('v.new_ward_id',     $geo['ward_id']);
        }

        $yrs_11_14 = implode(',', range($y1-14, $y1-11));
        $yrs_15_18 = implode(',', range($y1-18, $y1-15));
        $y_age6    = $y1 - 6;

        $agg = $cur->selectRaw("
                SUM(CASE WHEN v.dob_year = {$y_age6} THEN 1 ELSE 0 END) AS age6_total,
                SUM(CASE WHEN v.dob_year = {$y_age6} AND COALESCE(nw.now_grade1,0)=1 THEN 1 ELSE 0 END) AS age6_grade1,

                SUM(CASE WHEN v.dob_year IN ({$yrs_11_14}) THEN 1 ELSE 0 END) AS age11_14_total,
                SUM(CASE WHEN v.dob_year IN ({$yrs_11_14}) AND UPPER(v.graduation_level_name) IN ('TH','THCS','THPT') THEN 1 ELSE 0 END) AS age11_14_htct_th,

                SUM(CASE WHEN COALESCE(ly.g9_last,0)=1 THEN 1 ELSE 0 END) AS g9_last_total,
                SUM(CASE WHEN COALESCE(nw.grad_thcs_last_pt,0)=1 AND COALESCE(ly.g9_pt_last,0)=1 THEN 1 ELSE 0 END) AS grad_thcs_last_pt,
                SUM(CASE WHEN COALESCE(nw.grad_thcs_last_gdtx,0)=1 AND COALESCE(ly.g9_gdtx_last,0)=1 THEN 1 ELSE 0 END) AS grad_thcs_last_gdtx,

                SUM(CASE WHEN v.dob_year IN ({$yrs_15_18}) THEN 1 ELSE 0 END) AS age15_18_total,
                SUM(CASE WHEN v.dob_year IN ({$yrs_15_18}) AND (UPPER(v.graduation_level_name)='THCS' OR UPPER(v.graduation_level_name)='THPT') THEN 1 ELSE 0 END) AS age15_18_thcs_pt,
                SUM(CASE WHEN v.dob_year IN ({$yrs_15_18}) AND (UPPER(v.graduation_level_name)='THCS' AND (s.school_type_id IN ($inGdtx))) THEN 0 ELSE 0 END) AS age15_18_thcs_gdtx
            ")->first();

        $thcs32 = [];
        if ($agg) {
            $thcs32[1]  = (int)$agg->age6_total;
            $thcs32[2]  = (int)$agg->age6_grade1;
            // $thcs32[7]  = (int)$agg->age11_14_total; // Không hiển thị - bôi xám
            $thcs32[9]  = (int)$agg->age11_14_htct_th;
            // $thcs32[10] = (int)$agg->age15_18_total; // Không hiển thị - bôi xám
            $thcs32[11] = (int)$agg->age15_18_thcs_pt + (int)$agg->age15_18_thcs_gdtx; // TT 11 (cũ: 13) - Tổng số 15-18 tuổi tốt nghiệp THCS
            $thcs32[21] = (int)$agg->g9_last_total; // TT 21 (cũ: 26) - Học sinh lớp 9 năm qua (2 hệ)
        }

        /* ===================== THPT (4.1) từ R41Report ===================== */
        $cfgInGdtx = implode(',', [3,11]);
        $GL_TH = "'TH'"; $GL_THCS = "'THCS'"; $GL_THPT = "'THPT'";

        $lastAcademicR41 = ($y1 - 1).'-'.$y1; $prevAcademicR41 = ($y1 - 2).'-'.($y1 - 1);

        $lastYear = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->select(
                'v.person_id',
                DB::raw("MAX(CASE WHEN (UPPER(v.graduation_level_name) IN ($GL_THCS)) AND v.graduation_year = '$lastAcademicR41' THEN 1 ELSE 0 END) AS tn_thcs_last"),
                DB::raw("MAX(CASE WHEN (UPPER(v.graduation_level_name) IN ($GL_THPT)) AND v.graduation_year = '$lastAcademicR41' AND (v.is_bo_tuc=0 OR v.is_bo_tuc IS NULL) THEN 1 ELSE 0 END) AS tn_thpt_last"),
                DB::raw("MAX(CASE WHEN (UPPER(v.graduation_level_name) IN ($GL_THPT)) AND v.graduation_year = '$lastAcademicR41' AND v.is_bo_tuc=1 THEN 1 ELSE 0 END) AS tn_gdtx_last")
            )
            ->groupBy('v.person_id');

        $hasEduAny = DB::table('person_educations')->select('person_id', DB::raw('1 AS has_edu'))->groupBy('person_id');

        $ages  = range(14, 21);
        $years41 = array_map(fn($a) => $y1 - $a, $ages);

        $cur41 = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->leftJoinSub($lastYear, 'ly', fn($j) => $j->on('ly.person_id','=','v.person_id'))
            ->leftJoinSub($hasEduAny, 'pe', fn($j) => $j->on('pe.person_id','=','v.person_id'))
            ->where('v.academic_year', $academic)
            ->whereIn('v.dob_year', $years41);

        if ($scheme === 'old') {
            if (!empty($geo['province_id'])) $cur41->where('v.old_province_id', $geo['province_id']);
            if (!empty($geo['ward_id']))     $cur41->where('v.old_ward_id',     $geo['ward_id']);
        } else {
            if (!empty($geo['province_id'])) $cur41->where('v.new_province_id', $geo['province_id']);
            if (!empty($geo['ward_id']))     $cur41->where('v.new_ward_id',     $geo['ward_id']);
        }

        $grade41   = "CAST(v.education_block_code AS UNSIGNED)";
        $disAny41  = "(COALESCE(v.dis_VAN_DONG,0)+COALESCE(v.dis_NGHE_NOI,0)+COALESCE(v.dis_NHIN,0)+COALESCE(v.dis_THAN_KINH,0)+COALESCE(v.dis_TRI_TUE,0)+COALESCE(v.dis_HOC_TAP,0)+COALESCE(v.dis_TU_KY,0)+COALESCE(v.dis_KHAC,0)) > 0";
        $movedOut41= "v.sc_name IN ('Chuyển đi','MOVED_OUT','Chết','DECEASED')";

        $rows41 = $cur41->selectRaw("
                v.dob_year AS year_of_birth,
                COUNT(DISTINCT v.person_id) AS total,
                COUNT(DISTINCT CASE WHEN $disAny41 THEN v.person_id END) AS disabled_total,
                COUNT(DISTINCT CASE WHEN $movedOut41 THEN v.person_id END) AS moved_out_total,
                (
                    COUNT(DISTINCT v.person_id)
                    - COUNT(DISTINCT CASE WHEN $disAny41 THEN v.person_id END)
                    - COUNT(DISTINCT CASE WHEN $movedOut41 THEN v.person_id END)
                ) AS must_pc,

                SUM(CASE WHEN $grade41=10 AND v.is_bo_tuc = 0 AND UPPER(v.graduation_level_name) IN ($GL_THCS) AND v.graduation_year = '$lastAcademicR41' THEN 1 ELSE 0 END) AS thpt_g10_last,
                SUM(CASE WHEN $grade41=10 AND v.is_bo_tuc = 1 AND UPPER(v.graduation_level_name) IN ($GL_THCS) AND v.graduation_year = '$lastAcademicR41' THEN 1 ELSE 0 END) AS gdtx_g10_last,
                SUM(CASE WHEN UPPER(v.graduation_level_name)='THPT' AND v.graduation_year = '$lastAcademicR41' AND (v.is_bo_tuc=0 OR v.is_bo_tuc IS NULL) THEN 1 ELSE 0 END) AS thpt_grad_last,
                SUM(CASE WHEN UPPER(v.graduation_level_name)='THPT' AND v.graduation_year <  '$lastAcademicR41' AND (v.is_bo_tuc=0 OR v.is_bo_tuc IS NULL) THEN 1 ELSE 0 END) AS thpt_grad_prev,
                SUM(CASE WHEN UPPER(v.graduation_level_name)='THPT' AND v.graduation_year = '$lastAcademicR41' AND v.is_bo_tuc=1 THEN 1 ELSE 0 END) AS gdtx_grad_last,
                SUM(CASE WHEN UPPER(v.graduation_level_name)='THPT' AND v.graduation_year <  '$lastAcademicR41' AND v.is_bo_tuc=1 THEN 1 ELSE 0 END) AS gdtx_grad_prev,
                SUM(CASE WHEN v.vocational_level_name IS NOT NULL AND v.vocational_grad_year = '$lastAcademicR41' THEN 1 ELSE 0 END) AS voc_grad_last,
                SUM(CASE WHEN v.vocational_level_name IS NOT NULL AND v.vocational_grad_year <  '$lastAcademicR41' THEN 1 ELSE 0 END) AS voc_grad_prev
            ")
            ->groupBy('year_of_birth')
            ->orderBy('year_of_birth')
            ->get();

        $byAge41 = [];
        foreach ($rows41 as $r) {
            $age = $y1 - (int)$r->year_of_birth;
            $byAge41[$age] = [
                'must_pc' => (int)$r->must_pc,
                'thpt_g10_last' => (int)$r->thpt_g10_last,
                'gdtx_g10_last' => (int)$r->gdtx_g10_last,
                'thpt_grad_last' => (int)$r->thpt_grad_last,
                'thpt_grad_prev' => (int)$r->thpt_grad_prev,
                'gdtx_grad_last' => (int)$r->gdtx_grad_last,
                'gdtx_grad_prev' => (int)$r->gdtx_grad_prev,
                'voc_grad_last'  => (int)$r->voc_grad_last,
                'voc_grad_prev'  => (int)$r->voc_grad_prev,
            ];
        }

        $sumAges = function(array $ages, string $key) use ($byAge41): int {
            $t = 0; foreach ($ages as $a) { $t += (int)($byAge41[$a][$key] ?? 0); } return $t;
        };

        $thpt41 = [];
        $thpt41[6]  = (int)($byAge41[14]['must_pc'] ?? 0);
        $thpt41[10] = $sumAges([15,16,17,18], 'must_pc');
        $thpt41[12] = $sumAges([15,16,17,18,19,20,21], 'must_pc'); // TT 12 (cũ: 17) - Số đối tượng PPC 15-21 tuổi
        $thpt41[13] = $sumAges([18,19,20,21], 'must_pc'); // TT 13 (cũ: 18) - Số đối tượng PPC 18-21 tuổi
        $thpt41[14] = $sumAges([15,16,17,18], 'thpt_g10_last'); // TT 14 (cũ: 19) - 15-18 tuổi tốt nghiệp THCS năm qua vào lớp 10 PT
        $thpt41[15] = $sumAges([15,16,17,18], 'gdtx_g10_last'); // TT 15 (cũ: 20) - 15-18 tuổi tốt nghiệp THCS năm qua vào lớp 10 BT
        // $thpt41[16] = $sumAges([18,19,20,21], 'thpt_grad_last') + $sumAges([18,19,20,21], 'thpt_grad_prev'); // TT 16 (cũ: 21) - 18-21 tuổi tốt nghiệp THPT PT
        $thpt41[17] = $sumAges([18,19,20,21], 'gdtx_grad_last') + $sumAges([18,19,20,21], 'gdtx_grad_prev'); // TT 17 (cũ: 22) - 18-21 tuổi tốt nghiệp THPT BT
        $thpt41[18] = $sumAges([18,19,20,21], 'thpt_grad_last') + $sumAges([18,19,20,21], 'gdtx_grad_last'); // TT 18 (cũ: 23) - Tốt nghiệp THPT năm qua
        $thpt41[19] = $sumAges([18,19,20,21], 'gdtx_grad_last'); // TT 19 (cũ: 24) - Tốt nghiệp THPT BT năm qua
        $thpt41[20] = $sumAges([18,19,20,21], 'voc_grad_last') + $sumAges([18,19,20,21], 'voc_grad_prev'); // TT 20 (cũ: 25) - 18-21 tuổi có bằng đào tạo nghề

        /* ===================== THPT (4.2) từ R42Report ===================== */
        $yrs_15_21 = range($y1-21, $y1-15);
        $yrs_15_18 = range($y1-18, $y1-15);
        $inTN  = "'THCS','THPT'";
        $TYPE_GDTX_R42 = [3,11];
        $TYPE_VOC_R42  = [1];
        $TYPE_TCCN_R42 = [12];
        $inGdtxR42 = implode(',', $TYPE_GDTX_R42);
        $inVocR42  = implode(',', $TYPE_VOC_R42);
        $inTccnR42 = implode(',', $TYPE_TCCN_R42);

        $lastAcademic42 = ($y1 - 1).'-'.$y1;

        $last42 = DB::table('v_survey_detail_flat as v')
            ->leftJoin('person_educations as pet', 'pet.person_id', '=', 'v.person_id')
            ->where('v.academic_year', $lastAcademic42)
            ->select(
                'v.person_id',
                DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=9 THEN 1 ELSE 0 END) AS is_g9"),
                DB::raw("MAX(CASE WHEN pet.graduation_level_id < 4 AND pet.graduation_year = '$lastAcademic42' THEN 1 ELSE 0 END) AS grad_thcs_last")
            )
            ->groupBy('v.person_id');

        $nowDest42 = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->select(
                'v.person_id',
                DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=10 AND (s.school_type_id IS NULL OR s.school_type_id NOT IN ($inGdtxR42)) THEN 1 ELSE 0 END) AS now_thpt10"),
                DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=10 AND s.school_type_id IN ($inGdtxR42) THEN 1 ELSE 0 END) AS now_gdtx10"),
                DB::raw("MAX(CASE WHEN s.school_type_id IN ($inTccnR42) THEN 1 ELSE 0 END) AS now_tccn"),
                DB::raw("MAX(CASE WHEN s.school_type_id IN ($inVocR42) THEN 1 ELSE 0 END) AS now_voc")
            )
            ->where('v.academic_year', $academic)
            ->groupBy('v.person_id');

        $cur42 = DB::table('v_survey_detail_flat as v')
            ->leftJoinSub($last42,    'ly', fn($j) => $j->on('ly.person_id','=','v.person_id'))
            ->leftJoinSub($nowDest42, 'dn', fn($j) => $j->on('dn.person_id','=','v.person_id'))
            ->where('v.academic_year', $academic);

        if ($scheme === 'old') {
            if (!empty($geo['province_id'])) $cur42->where('v.old_province_id', $geo['province_id']);
            if (!empty($geo['ward_id']))     $cur42->where('v.old_ward_id',     $geo['ward_id']);
        } else {
            if (!empty($geo['province_id'])) $cur42->where('v.new_province_id', $geo['province_id']);
            if (!empty($geo['ward_id']))     $cur42->where('v.new_ward_id',     $geo['ward_id']);
        }

        // Điều kiện khuyết tật và chuyển đi/chết
        $disAny42 = "(COALESCE(v.dis_VAN_DONG,0)+COALESCE(v.dis_NGHE_NOI,0)+COALESCE(v.dis_NHIN,0)+COALESCE(v.dis_THAN_KINH,0)+COALESCE(v.dis_TRI_TUE,0)+COALESCE(v.dis_HOC_TAP,0)+COALESCE(v.dis_TU_KY,0)+COALESCE(v.dis_KHAC,0)) > 0 OR v.dis_has_certificate=1";
        $movedOut42 = "UPPER(TRIM(v.sc_name)) IN ('CHẾT','DECEASED','CHUYỂN ĐI','CHUYEN DI','MOVED_OUT')";
        
        $agg42 = $cur42->selectRaw("
                -- Tổng 15-18
                COUNT(DISTINCT CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).") THEN v.person_id END) AS total_15_18,
                COUNT(DISTINCT CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).") AND ($disAny42) THEN v.person_id END) AS dis_15_18,
                COUNT(DISTINCT CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).") AND ($movedOut42) THEN v.person_id END) AS moved_15_18,
                
                -- Tổng 15-21
                COUNT(DISTINCT CASE WHEN v.dob_year IN (".implode(',', $yrs_15_21).") THEN v.person_id END) AS total_15_21,
                COUNT(DISTINCT CASE WHEN v.dob_year IN (".implode(',', $yrs_15_21).") AND ($disAny42) THEN v.person_id END) AS dis_15_21,
                COUNT(DISTINCT CASE WHEN v.dob_year IN (".implode(',', $yrs_15_21).") AND ($movedOut42) THEN v.person_id END) AS moved_15_21,
                
                SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).") AND UPPER(v.graduation_level_name) in ($inTN) THEN 1 ELSE 0 END) AS tn_thcs_15_18,
                SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).") AND COALESCE(ly.is_g9,0)=1 THEN 1 ELSE 0 END) AS g9_last,
                SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).") AND COALESCE(ly.grad_thcs_last,0)=1 THEN 1 ELSE 0 END) AS grad_thcs_last,
                SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).") AND COALESCE(dn.now_thpt10,0)=1 THEN 1 ELSE 0 END) AS now_thpt10,
                SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).") AND COALESCE(dn.now_gdtx10,0)=1 THEN 1 ELSE 0 END) AS now_gdtx10,
                SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).") AND COALESCE(dn.now_tccn,0)=1 THEN 1 ELSE 0 END) AS now_tccn,
                SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).") AND COALESCE(dn.now_voc,0)=1 THEN 1 ELSE 0 END) AS now_voc
            ")->first();

        $thpt42 = [];
        if ($agg42) {
            // Tính PPC (phải phổ cập) = tổng - khuyết tật - chuyển đi/chết
            $thpt42[10] = (int)$agg42->total_15_18 - (int)$agg42->dis_15_18 - (int)$agg42->moved_15_18;
            // Tính PPC 15-21
            $thpt42[12] = (int)$agg42->total_15_21 - (int)$agg42->dis_15_21 - (int)$agg42->moved_15_21; // TT 12 (cũ: 17) - Số đối tượng PPC 15-21 tuổi
            // 13: 18–21 hiện không tách được từ dataset => để null
            $thpt42[14] = (int)$agg42->now_thpt10; // TT 14 (cũ: 19) - vào lớp 10 PT
            $thpt42[21] = (int)$agg42->g9_last;    // TT 21 (cũ: 26) - HS lớp 9 năm trước
        }

        /* ===================== THPT (4.3) từ HighSchoolEffectivenessReport ===================== */
        $years43 = []; for ($a=18;$a<=21;$a++) $years43[] = $y1 - $a;
        $TYPE_GDTX43 = [3,11];
        $TYPE_VOC43  = [1];
        $inGdtx43 = implode(',', $TYPE_GDTX43);
        // Subqueries giống báo cáo 4.3
        $agg43 = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->select(
                'v.person_id',
                DB::raw("MAX(CASE WHEN UPPER(v.graduation_level_name)='THPT' AND COALESCE(v.is_bo_tuc,0)=0 THEN 1 ELSE 0 END) AS tn_thpt"),
                DB::raw("MAX(CASE WHEN UPPER(v.graduation_level_name)='THPT' AND COALESCE(v.is_bo_tuc,0)=1 THEN 1 ELSE 0 END) AS tn_bt_thpt"),
                DB::raw("MAX(CASE WHEN v.vocational_level_name IS NOT NULL THEN 1 ELSE 0 END) AS has_voc"),
                DB::raw("MAX(CASE WHEN UPPER(v.graduation_level_name) IN ('THCN','TDN','TRUNG CAP','TRUNG CẤP','TRUNGCAP','CAO DANG','CAO ĐẲNG','DAI HOC','ĐẠI HỌC','CD','DH') THEN 1 ELSE 0 END) AS has_tc_dh")
            )
            ->groupBy('v.person_id');

        $lastAcademic43 = ($y1 - 1).'-'.$y1;
        $lastYear43 = DB::table('v_survey_detail_flat as v')
            ->leftJoin('person_educations as pet', 'pet.person_id', '=', 'v.person_id')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->where('v.academic_year', $lastAcademic43)
            ->select(
                'v.person_id',
                DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=12 THEN 1 ELSE 0 END) AS is_thpt12"),
                DB::raw("MAX(CASE WHEN pet.graduation_level_id = 4 AND pet.graduation_year = '$lastAcademic43' THEN 1 ELSE 0 END) AS grad_thpt_last"),
                DB::raw("MAX(CASE WHEN pet.graduation_level_id = 4 AND pet.graduation_year = '$lastAcademic43' AND s.school_type_id IN ($inGdtx43) THEN 1 ELSE 0 END) AS grad_gdtx_thpt_last")
            )
            ->groupBy('v.person_id');

        $cur43 = DB::table('v_survey_detail_flat as v')
            ->leftJoinSub($agg43,      'a',  fn($j)=>$j->on('a.person_id','=','v.person_id'))
            ->leftJoinSub($lastYear43, 'ly', fn($j)=>$j->on('ly.person_id','=','v.person_id'))
            ->where('v.academic_year', $academic)
            ->whereIn('v.dob_year', $years43);

        if ($scheme === 'old') {
            if (!empty($geo['province_id'])) $cur43->where('v.old_province_id', $geo['province_id']);
            if (!empty($geo['ward_id']))     $cur43->where('v.old_ward_id',     $geo['ward_id']);
        } else {
            if (!empty($geo['province_id'])) $cur43->where('v.new_province_id', $geo['province_id']);
            if (!empty($geo['ward_id']))     $cur43->where('v.new_ward_id',     $geo['ward_id']);
        }

        $movedOut43 = "UPPER(TRIM(v.sc_name)) IN ('CHẾT','DECEASED','CHUYỂN ĐI','CHUYEN DI','MOVED_OUT')";
        $disabled43 = "(COALESCE(v.dis_VAN_DONG,0)+COALESCE(v.dis_NGHE_NOI,0)+COALESCE(v.dis_NHIN,0)+COALESCE(v.dis_THAN_KINH,0)+COALESCE(v.dis_TRI_TUE,0)+COALESCE(v.dis_HOC_TAP,0)+COALESCE(v.dis_TU_KY,0)+COALESCE(v.dis_KHAC,0)) > 0";

        $sum43 = $cur43->selectRaw("
                COUNT(DISTINCT v.person_id) AS total_18_21,
                COUNT(DISTINCT CASE WHEN ($movedOut43) OR ($disabled43) THEN v.person_id END) AS not_pc,
                SUM(COALESCE(a.tn_thpt,0)) AS tn_thpt,
                SUM(COALESCE(a.tn_bt_thpt,0)) AS tn_bt_thpt,
                SUM(COALESCE(ly.grad_thpt_last,0)) AS grad_thpt_last,
                SUM(COALESCE(ly.grad_gdtx_thpt_last,0)) AS grad_gdtx_thpt_last,
                SUM(COALESCE(a.has_tc_dh,0)) AS degree_total
            ")->first();

        $thpt43 = [];
        if ($sum43) {
            $thpt43[13] = (int)$sum43->total_18_21 - (int)$sum43->not_pc; // TT 13 (cũ: 18) - PPC 18-21
            $thpt43[16] = (int)$sum43->tn_thpt;                           // TT 16 (cũ: 21) - TN THPT PT (ever)
            $thpt43[17] = (int)$sum43->tn_bt_thpt;                        // TT 17 (cũ: 22) - TN THPT BT (ever)
            $thpt43[18] = (int)$sum43->grad_thpt_last + (int)$sum43->grad_gdtx_thpt_last; // TT 18 (cũ: 23) - TN THPT năm qua (PT+BT)
            $thpt43[19] = (int)$sum43->grad_gdtx_thpt_last;               // TT 19 (cũ: 24) - TN BT THPT năm qua
            $thpt43[20] = (int)$sum43->degree_total;                      // TT 20 (cũ: 25) - Có bằng TC/ĐH (đào tạo nghề)
        }

        /* ===================== Xóa mù chữ (5.1) theo AntiIlliteracyReport ===================== */
        $buildXmcRange = function(int $yMin, int $yMax) use ($academic, $scheme, $geo) {
            // latest PE
            $peLatest = DB::table('person_educations as pe1')
                ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) rn'))
                ->where('pe1.academic_year', $academic);

            // latest XMC
            $xmcLatest = DB::table('person_literacy as x')
                ->select('x.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY x.person_id ORDER BY x.id DESC) rn'));

            $qb = DB::table('survey_form_details as d')
                ->join('survey_forms as f', function($j){
                    $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
                })
                ->join('people as p', 'p.id', '=', 'd.person_id')
                ->leftJoinSub($peLatest, 'pe', function($j){
                    $j->on('pe.person_id','=','p.id')->where('pe.rn', 1);
                })
                ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id')
                ->leftJoinSub($xmcLatest, 'xmc', function($j){
                    $j->on('xmc.person_id','=','p.id')->where('xmc.rn', 1);
                })
                ->leftJoin('ethnicities as e', 'e.id', '=', 'p.ethnicity_id')
                ->where('d.is_deleted', false)
                ->where('d.academic_year', $academic);

            // geo filter
            if ($scheme === 'old') {
                if (!empty($geo['province_id'])) $qb->where('f.old_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $qb->where('f.old_ward_id',     $geo['ward_id']);
            } else {
                if (!empty($geo['province_id'])) $qb->where('f.new_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $qb->where('f.new_ward_id',     $geo['ward_id']);
            }

            $ageY  = "(p.dob_year BETWEEN {$yMin} AND {$yMax})";
            $ebn   = "COALESCE(CAST(NULLIF(TRIM(pe.bo_hoc_lop), '') AS UNSIGNED), 0)";
            $grad  = "(COALESCE(pe.graduation_level_id, 0) < 2)"; // chưa TN PT

            // lv1
            $lv1 = "( {$ageY} AND ( ({$grad} AND ({$ebn} <= 3)) OR (xmc.hoan_thanh_lop = 3 OR xmc.tai_mu_chu_muc = 1) ) )";
            // lv2: theo cấu hình công thức (giống report)
            $form = app(\App\Services\ReportFormatService::class)->cong_thuc_baocao(5,37);
            if ($form == 1) {
                $lv2 = "( {$ageY} AND ( ({$grad} AND ({$ebn} <= 5)) OR (xmc.hoan_thanh_lop = 5 OR xmc.tai_mu_chu_muc = 2) ) )";
            } else {
                $lv2 = "( {$ageY} AND ( ({$grad} AND ({$ebn} > 3 AND {$ebn} <= 5)) OR (xmc.hoan_thanh_lop = 5 OR xmc.tai_mu_chu_muc = 2) ) )";
            }

            $row = $qb->selectRaw("
                    SUM(CASE WHEN {$ageY} THEN 1 ELSE 0 END) AS y_tot,
                    SUM(CASE WHEN {$lv1} THEN 1 ELSE 0 END) AS lv1_tot,
                    SUM(CASE WHEN {$lv2} THEN 1 ELSE 0 END) AS lv2_tot
                ")->first();

            return [
                'y_tot'  => (int)($row->y_tot  ?? 0),
                'lv1_tot'=> (int)($row->lv1_tot?? 0),
                'lv2_tot'=> (int)($row->lv2_tot?? 0),
            ];
        };

        $rangeA = $buildXmcRange($y1-25, $y1-15); // 15–25
        $rangeB = $buildXmcRange($y1-35, $y1-15); // 15–35
        $rangeC = $buildXmcRange($y1-60, $y1-15); // 15–60

        $xmc51 = [];
        $xmc51[1] = $rangeA['y_tot'];
        $xmc51[2] = $rangeB['y_tot'];
        $xmc51[3] = $rangeC['y_tot'];
        $xmc51[4] = $rangeA['lv1_tot'];
        $xmc51[5] = $rangeB['lv1_tot'];
        $xmc51[6] = $rangeC['lv1_tot'];
        $xmc51[7] = $rangeA['lv2_tot'];
        $xmc51[8] = $rangeB['lv2_tot'];
        $xmc51[9] = $rangeC['lv2_tot'];

        /* ===================== Xóa mù chữ (5.2) theo LiteracyOutcomeReport ===================== */
        // Hàm lấy dữ liệu XMC theo dải tuổi (từ LiteracyOutcomeReport logic)
        $buildXmcOutcome = function(array $yearsRange) use ($academic, $scheme, $geo) {
            // latest PE
            $peLatest = DB::table('person_educations as pe1')
                ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) rn'))
                ->where('pe1.academic_year', $academic);
            
            // latest XMC
            $xmcLatest = DB::table('person_literacy as x')
                ->select('x.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY x.person_id ORDER BY x.id DESC) rn'));

            $qb = DB::table('survey_form_details as d')
                ->join('survey_forms as f', function($j){
                    $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
                })
                ->join('people as p', 'p.id', '=', 'd.person_id')
                ->leftJoinSub($peLatest, 'pe', function($j){
                    $j->on('pe.person_id','=','p.id')->where('pe.rn', 1);
                })
                ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id')
                ->leftJoinSub($xmcLatest, 'xmc', function($j){
                    $j->on('xmc.person_id','=','p.id')->where('xmc.rn', 1);
                })
                ->leftJoin('ethnicities as e', 'e.id', '=', 'p.ethnicity_id')
                ->where('d.is_deleted', false)
                ->where('d.academic_year', $academic)
                ->whereIn('p.dob_year', $yearsRange);

            // geo filter
            if ($scheme === 'old') {
                if (!empty($geo['province_id'])) $qb->where('f.old_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $qb->where('f.old_ward_id',     $geo['ward_id']);
            } else {
                if (!empty($geo['province_id'])) $qb->where('f.new_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $qb->where('f.new_ward_id',     $geo['ward_id']);
            }

            $isF   = '(p.is_female = 1)';
            $isEth = '(COALESCE(e.is_minority,0) = 1)';
            $ebn   = "COALESCE(CAST(NULLIF(TRIM(pe.bo_hoc_lop),'') AS UNSIGNED),0)";
            $grad  = "(COALESCE(pe.graduation_level_id,0) < 2)";
            
            // Lấy config từ ReportFormatService
            $rule1 = app(\App\Services\ReportFormatService::class)->cong_thuc_baocao(5,37) == 1;
            
            // Logic mù chữ mức 1 & 2 giống LiteracyOutcomeReport
            $lv2Drop = $rule1 ? "({$ebn} <= 5)" : "({$ebn} > 3 AND {$ebn} <= 5)";
            $lv1 = "( ({$grad} AND {$ebn} <= 3) OR (xmc.hoan_thanh_lop = 3 OR xmc.tai_mu_chu_muc = 1) )";
            $lv2 = "( ({$grad} AND {$lv2Drop})  OR (xmc.hoan_thanh_lop = 5 OR xmc.tai_mu_chu_muc = 2) )";

            $row = $qb->selectRaw("
                    COUNT(*) AS pop_tot,
                    SUM(CASE WHEN {$lv1} THEN 1 ELSE 0 END) AS lv1_tot,
                    SUM(CASE WHEN {$lv2} THEN 1 ELSE 0 END) AS lv2_tot
                ")->first();

            return [
                'pop_tot' => (int)($row->pop_tot ?? 0),
                'lv1_tot' => (int)($row->lv1_tot ?? 0),
                'lv2_tot' => (int)($row->lv2_tot ?? 0),
            ];
        };

        // 3 dải tuổi: 15-25, 15-35, 15-60
        $yearsA = range($y1-25, $y1-15); // 15-25 tuổi
        $yearsB = range($y1-35, $y1-15); // 15-35 tuổi
        $yearsC = range($y1-60, $y1-15); // 15-60 tuổi
        
        $outA = $buildXmcOutcome($yearsA);
        $outB = $buildXmcOutcome($yearsB);
        $outC = $buildXmcOutcome($yearsC);

        $xmc52 = [];
        // 1-3: Tổng dân số theo 3 dải tuổi
        $xmc52[1] = $outA['pop_tot'];
        $xmc52[2] = $outB['pop_tot'];
        $xmc52[3] = $outC['pop_tot'];
        // 4-6: Mù chữ mức 1 theo 3 dải tuổi
        $xmc52[4] = $outA['lv1_tot'];
        $xmc52[5] = $outB['lv1_tot'];
        $xmc52[6] = $outC['lv1_tot'];
        // 7-9: Mù chữ mức 2 theo 3 dải tuổi
        $xmc52[7] = $outA['lv2_tot'];
        $xmc52[8] = $outB['lv2_tot'];
        $xmc52[9] = $outC['lv2_tot'];

        /* ===================== Xóa mù chữ (5.3) theo IlliteracyByAgeBandsReport ===================== */
        // Hàm lấy dữ liệu XMC theo 3 dải tuổi (15-25, 26-35, 36-60) với phân loại dân tộc, tôn giáo
        $buildXmcByBands = function(array $bands) use ($academic, $scheme, $geo) {
            // latest PE
            $peLatest = DB::table('person_educations as pe1')
                ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) rn'))
                ->where('pe1.academic_year', $academic);
            
            // latest XMC
            $xmcLatest = DB::table('person_literacy as x')
                ->select('x.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY x.person_id ORDER BY x.id DESC) rn'));

            $qb = DB::table('survey_form_details as d')
                ->join('survey_forms as f', function($j){
                    $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
                })
                ->join('people as p', 'p.id', '=', 'd.person_id')
                ->leftJoinSub($peLatest, 'pe', function($j){
                    $j->on('pe.person_id','=','p.id')->where('pe.rn', 1);
                })
                ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id')
                ->leftJoinSub($xmcLatest, 'xmc', function($j){
                    $j->on('xmc.person_id','=','p.id')->where('xmc.rn', 1);
                })
                ->leftJoin('ethnicities as e', 'e.id', '=', 'p.ethnicity_id')
                ->where('d.is_deleted', false)
                ->where('d.academic_year', $academic);

            // geo filter
            if ($scheme === 'old') {
                if (!empty($geo['province_id'])) $qb->where('f.old_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $qb->where('f.old_ward_id',     $geo['ward_id']);
            } else {
                if (!empty($geo['province_id'])) $qb->where('f.new_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $qb->where('f.new_ward_id',     $geo['ward_id']);
            }

            $isF   = '(p.is_female = 1)';
            $isEth = '(COALESCE(e.is_minority,0) = 1)';
            $isRel = '(p.religion_id IS NOT NULL)';
            $ebn   = "COALESCE(CAST(NULLIF(TRIM(pe.bo_hoc_lop),'') AS UNSIGNED),0)";
            $gradLT2 = "(COALESCE(pe.graduation_level_id,0) < 2)";
            
            // Logic mù chữ
            $rule1 = app(\App\Services\ReportFormatService::class)->cong_thuc_baocao(5,37) == 1;
            $lv2Drop = $rule1 ? "({$ebn} <= 5)" : "({$ebn} > 3 AND {$ebn} <= 5)";
            $lv1 = "( ({$gradLT2} AND {$ebn} <= 3) OR (xmc.hoan_thanh_lop = 3 OR xmc.tai_mu_chu_muc = 1) )";
            $lv2 = "( ({$gradLT2} AND {$lv2Drop})  OR (xmc.hoan_thanh_lop = 5 OR xmc.tai_mu_chu_muc = 2) )";
            $relapse = "(COALESCE(xmc.tai_mu_chu_muc,0) IN (1,2))";

            // Tạo CASE cho 3 dải tuổi
            $cases = [];
            foreach ($bands as $i => $b) {
                $i1 = $i+1;
                $cases[] = "
                    SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} THEN 1 ELSE 0 END) as pop{$i1}_tot,
                    SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$isF} THEN 1 ELSE 0 END) as pop{$i1}_f,
                    SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$isEth} THEN 1 ELSE 0 END) as pop{$i1}_eth,
                    SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$isRel} THEN 1 ELSE 0 END) as pop{$i1}_rel,
                    
                    SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$lv1} THEN 1 ELSE 0 END) as lv1_{$i1}_tot,
                    SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$lv1} AND {$isF} THEN 1 ELSE 0 END) as lv1_{$i1}_f,
                    SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$lv1} AND {$isEth} THEN 1 ELSE 0 END) as lv1_{$i1}_eth,
                    SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$lv1} AND {$isRel} THEN 1 ELSE 0 END) as lv1_{$i1}_rel,
                    
                    SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$lv2} THEN 1 ELSE 0 END) as lv2_{$i1}_tot,
                    SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$lv2} AND {$isF} THEN 1 ELSE 0 END) as lv2_{$i1}_f,
                    SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$lv2} AND {$isEth} THEN 1 ELSE 0 END) as lv2_{$i1}_eth,
                    SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$lv2} AND {$isRel} THEN 1 ELSE 0 END) as lv2_{$i1}_rel,
                    
                    SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$relapse} THEN 1 ELSE 0 END) as re_{$i1}_tot,
                    SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$relapse} AND {$isF} THEN 1 ELSE 0 END) as re_{$i1}_f,
                    SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$relapse} AND {$isEth} THEN 1 ELSE 0 END) as re_{$i1}_eth,
                    SUM(CASE WHEN p.dob_year BETWEEN {$b['ymin']} AND {$b['ymax']} AND {$relapse} AND {$isRel} THEN 1 ELSE 0 END) as re_{$i1}_rel
                ";
            }

            $row = $qb->selectRaw(implode(",\n", $cases))->first();

            // Trả về mảng 3 dải
            $out = [];
            foreach ($bands as $i => $b) {
                $i1 = $i+1;
                $out[] = [
                    'label'   => $b['label'],
                    'pop_tot' => (int)($row->{"pop{$i1}_tot"} ?? 0),
                    'pop_f'   => (int)($row->{"pop{$i1}_f"} ?? 0),
                    'pop_eth' => (int)($row->{"pop{$i1}_eth"} ?? 0),
                    'pop_rel' => (int)($row->{"pop{$i1}_rel"} ?? 0),
                    'lv1_tot' => (int)($row->{"lv1_{$i1}_tot"} ?? 0),
                    'lv1_f'   => (int)($row->{"lv1_{$i1}_f"} ?? 0),
                    'lv1_eth' => (int)($row->{"lv1_{$i1}_eth"} ?? 0),
                    'lv1_rel' => (int)($row->{"lv1_{$i1}_rel"} ?? 0),
                    'lv2_tot' => (int)($row->{"lv2_{$i1}_tot"} ?? 0),
                    'lv2_f'   => (int)($row->{"lv2_{$i1}_f"} ?? 0),
                    'lv2_eth' => (int)($row->{"lv2_{$i1}_eth"} ?? 0),
                    'lv2_rel' => (int)($row->{"lv2_{$i1}_rel"} ?? 0),
                    're_tot'  => (int)($row->{"re_{$i1}_tot"} ?? 0),
                    're_f'    => (int)($row->{"re_{$i1}_f"} ?? 0),
                    're_eth'  => (int)($row->{"re_{$i1}_eth"} ?? 0),
                    're_rel'  => (int)($row->{"re_{$i1}_rel"} ?? 0),
                ];
            }
            return $out;
        };

        // 3 dải tuổi rời rạc theo IlliteracyByAgeBandsReport
        $bands53 = [
            ['label' => '15 - 25 tuổi', 'ymin' => $y1-25, 'ymax' => $y1-15],
            ['label' => '26 - 35 tuổi', 'ymin' => $y1-35, 'ymax' => $y1-26],
            ['label' => '36 - 60 tuổi', 'ymin' => $y1-60, 'ymax' => $y1-36],
        ];
        
        $rows53 = $buildXmcByBands($bands53);

        $xmc53 = [];
        // TT 1-3: Tổng dân số - cộng dồn các dải tuổi
        $xmc53[1] = $rows53[0]['pop_tot']; // 15-25
        $xmc53[2] = $rows53[0]['pop_tot'] + $rows53[1]['pop_tot']; // 15-35 = (15-25) + (26-35)
        $xmc53[3] = $rows53[0]['pop_tot'] + $rows53[1]['pop_tot'] + $rows53[2]['pop_tot']; // 15-60 = (15-25) + (26-35) + (36-60)
        
        // TT 4-6: Mù chữ mức 1 - cộng dồn
        $xmc53[4] = $rows53[0]['lv1_tot']; // 15-25
        $xmc53[5] = $rows53[0]['lv1_tot'] + $rows53[1]['lv1_tot']; // 15-35
        $xmc53[6] = $rows53[0]['lv1_tot'] + $rows53[1]['lv1_tot'] + $rows53[2]['lv1_tot']; // 15-60
        
        // TT 7-9: Mù chữ mức 2 - cộng dồn
        $xmc53[7] = $rows53[0]['lv2_tot']; // 15-25
        $xmc53[8] = $rows53[0]['lv2_tot'] + $rows53[1]['lv2_tot']; // 15-35
        $xmc53[9] = $rows53[0]['lv2_tot'] + $rows53[1]['lv2_tot'] + $rows53[2]['lv2_tot']; // 15-60

        /* ===================== Xóa mù chữ (5.5) theo AntiIlliteracyLevel1Report ===================== */
        // Hàm lấy dữ liệu mù chữ MỨC 1 theo dải tuổi
        $buildAntiIlliteracyLv1 = function(int $yMin, int $yMax) use ($academic, $scheme, $geo) {
            // latest PE
            $peLatest = DB::table('person_educations as pe1')
                ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) rn'))
                ->where('pe1.academic_year', $academic);
            
            // latest XMC
            $xmcLatest = DB::table('person_literacy as x')
                ->select('x.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY x.person_id ORDER BY x.id DESC) rn'));

            $qb = DB::table('survey_form_details as d')
                ->join('survey_forms as f', function($j){
                    $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
                })
                ->join('people as p', 'p.id', '=', 'd.person_id')
                ->leftJoinSub($peLatest, 'pe', function($j){
                    $j->on('pe.person_id','=','p.id')->where('pe.rn', 1);
                })
                ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id')
                ->leftJoinSub($xmcLatest, 'xmc', function($j){
                    $j->on('xmc.person_id','=','p.id')->where('xmc.rn', 1);
                })
                ->leftJoin('ethnicities as e', 'e.id', '=', 'p.ethnicity_id')
                ->where('d.is_deleted', false)
                ->where('d.academic_year', $academic);

            // geo filter
            if ($scheme === 'old') {
                if (!empty($geo['province_id'])) $qb->where('f.old_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $qb->where('f.old_ward_id',     $geo['ward_id']);
            } else {
                if (!empty($geo['province_id'])) $qb->where('f.new_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $qb->where('f.new_ward_id',     $geo['ward_id']);
            }

            $ageY  = "(p.dob_year BETWEEN {$yMin} AND {$yMax})";
            $isF   = "(p.is_female = 1)";
            $isEth = "(COALESCE(e.is_minority,0) = 1)";
            $ebn   = "COALESCE(CAST(NULLIF(TRIM(pe.bo_hoc_lop),'') AS UNSIGNED),0)";
            $grad  = "(COALESCE(pe.graduation_level_id,0) < 2)";
            
            // MÙ CHỮ MỨC 1 (chỉ mức 1, không bao gồm mức 2)
            $lv1 = "( ({$grad} AND {$ebn} <= 3) OR (xmc.hoan_thanh_lop = 3 OR xmc.tai_mu_chu_muc = 1) )";

            $row = $qb->selectRaw("
                    SUM(CASE WHEN {$ageY} THEN 1 ELSE 0 END) AS pop_tot,
                    SUM(CASE WHEN {$ageY} AND {$isF} THEN 1 ELSE 0 END) AS pop_f,
                    SUM(CASE WHEN {$ageY} AND {$isEth} THEN 1 ELSE 0 END) AS pop_eth,
                    SUM(CASE WHEN {$ageY} AND {$isF} AND {$isEth} THEN 1 ELSE 0 END) AS pop_feth,
                    
                    SUM(CASE WHEN {$ageY} AND {$lv1} THEN 1 ELSE 0 END) AS lv1_tot,
                    SUM(CASE WHEN {$ageY} AND {$lv1} AND {$isF} THEN 1 ELSE 0 END) AS lv1_f,
                    SUM(CASE WHEN {$ageY} AND {$lv1} AND {$isEth} THEN 1 ELSE 0 END) AS lv1_eth,
                    SUM(CASE WHEN {$ageY} AND {$lv1} AND {$isF} AND {$isEth} THEN 1 ELSE 0 END) AS lv1_feth
                ")->first();

            return [
                'pop_tot'  => (int)($row->pop_tot ?? 0),
                'pop_f'    => (int)($row->pop_f ?? 0),
                'pop_eth'  => (int)($row->pop_eth ?? 0),
                'pop_feth' => (int)($row->pop_feth ?? 0),
                'lv1_tot'  => (int)($row->lv1_tot ?? 0),
                'lv1_f'    => (int)($row->lv1_f ?? 0),
                'lv1_eth'  => (int)($row->lv1_eth ?? 0),
                'lv1_feth' => (int)($row->lv1_feth ?? 0),
            ];
        };

        // 3 dải tuổi cho báo cáo XMC 5.5
        $data55_A = $buildAntiIlliteracyLv1($y1-25, $y1-15); // 15-25
        $data55_B = $buildAntiIlliteracyLv1($y1-35, $y1-15); // 15-35
        $data55_C = $buildAntiIlliteracyLv1($y1-60, $y1-15); // 15-60

        $xmc55 = [];
        // TT 1-3: Tổng dân số trong độ tuổi
        $xmc55[1] = $data55_A['pop_tot']; // 15-25
        $xmc55[2] = $data55_B['pop_tot']; // 15-35
        $xmc55[3] = $data55_C['pop_tot']; // 15-60
        // TT 4-6: Mù chữ mức 1 trong độ tuổi
        $xmc55[4] = $data55_A['lv1_tot']; // 15-25
        $xmc55[5] = $data55_B['lv1_tot']; // 15-35
        $xmc55[6] = $data55_C['lv1_tot']; // 15-60
        // TT 7-9: chưa dùng (để null)

        /* ===================== Xóa mù chữ (5.6) theo AntiIlliteracyLevel2Report ===================== */
        // Hàm lấy dữ liệu mù chữ MỨC 2 theo dải tuổi
        $buildAntiIlliteracyLv2 = function(int $yMin, int $yMax) use ($academic, $scheme, $geo) {
            // latest PE
            $peLatest = DB::table('person_educations as pe1')
                ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) rn'))
                ->where('pe1.academic_year', $academic);
            
            // latest XMC
            $xmcLatest = DB::table('person_literacy as x')
                ->select('x.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY x.person_id ORDER BY x.id DESC) rn'));

            $qb = DB::table('survey_form_details as d')
                ->join('survey_forms as f', function($j){
                    $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
                })
                ->join('people as p', 'p.id', '=', 'd.person_id')
                ->leftJoinSub($peLatest, 'pe', function($j){
                    $j->on('pe.person_id','=','p.id')->where('pe.rn', 1);
                })
                ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id')
                ->leftJoinSub($xmcLatest, 'xmc', function($j){
                    $j->on('xmc.person_id','=','p.id')->where('xmc.rn', 1);
                })
                ->leftJoin('ethnicities as e', 'e.id', '=', 'p.ethnicity_id')
                ->where('d.is_deleted', false)
                ->where('d.academic_year', $academic);

            // geo filter
            if ($scheme === 'old') {
                if (!empty($geo['province_id'])) $qb->where('f.old_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $qb->where('f.old_ward_id',     $geo['ward_id']);
            } else {
                if (!empty($geo['province_id'])) $qb->where('f.new_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $qb->where('f.new_ward_id',     $geo['ward_id']);
            }

            $ageY  = "(p.dob_year BETWEEN {$yMin} AND {$yMax})";
            $isF   = "(p.is_female = 1)";
            $isEth = "(COALESCE(e.is_minority,0) = 1)";
            $ebn   = "COALESCE(CAST(NULLIF(TRIM(pe.bo_hoc_lop),'') AS UNSIGNED),0)";
            $grad  = "(COALESCE(pe.graduation_level_id,0) < 2)";
            
            // MÙ CHỮ MỨC 2 (dựa vào config)
            $rule1 = app(\App\Services\ReportFormatService::class)->cong_thuc_baocao(5,37) == 1;
            $lv2Drop = $rule1 ? "({$ebn} <= 5)" : "({$ebn} > 3 AND {$ebn} <= 5)";
            $lv2 = "( ({$grad} AND {$lv2Drop})  OR (xmc.hoan_thanh_lop = 5 OR xmc.tai_mu_chu_muc = 2) )";

            $row = $qb->selectRaw("
                    SUM(CASE WHEN {$ageY} THEN 1 ELSE 0 END) AS pop_tot,
                    SUM(CASE WHEN {$ageY} AND {$isF} THEN 1 ELSE 0 END) AS pop_f,
                    SUM(CASE WHEN {$ageY} AND {$isEth} THEN 1 ELSE 0 END) AS pop_eth,
                    SUM(CASE WHEN {$ageY} AND {$isF} AND {$isEth} THEN 1 ELSE 0 END) AS pop_feth,
                    
                    SUM(CASE WHEN {$ageY} AND {$lv2} THEN 1 ELSE 0 END) AS lv2_tot,
                    SUM(CASE WHEN {$ageY} AND {$lv2} AND {$isF} THEN 1 ELSE 0 END) AS lv2_f,
                    SUM(CASE WHEN {$ageY} AND {$lv2} AND {$isEth} THEN 1 ELSE 0 END) AS lv2_eth,
                    SUM(CASE WHEN {$ageY} AND {$lv2} AND {$isF} AND {$isEth} THEN 1 ELSE 0 END) AS lv2_feth
                ")->first();

            return [
                'pop_tot'  => (int)($row->pop_tot ?? 0),
                'pop_f'    => (int)($row->pop_f ?? 0),
                'pop_eth'  => (int)($row->pop_eth ?? 0),
                'pop_feth' => (int)($row->pop_feth ?? 0),
                'lv2_tot'  => (int)($row->lv2_tot ?? 0),
                'lv2_f'    => (int)($row->lv2_f ?? 0),
                'lv2_eth'  => (int)($row->lv2_eth ?? 0),
                'lv2_feth' => (int)($row->lv2_feth ?? 0),
            ];
        };

        // 3 dải tuổi cho báo cáo XMC 5.6
        $data56_A = $buildAntiIlliteracyLv2($y1-25, $y1-15); // 15-25
        $data56_B = $buildAntiIlliteracyLv2($y1-35, $y1-15); // 15-35
        $data56_C = $buildAntiIlliteracyLv2($y1-60, $y1-15); // 15-60

        $xmc56 = [];
        // TT 1-3: Tổng dân số trong độ tuổi
        $xmc56[1] = $data56_A['pop_tot']; // 15-25
        $xmc56[2] = $data56_B['pop_tot']; // 15-35
        $xmc56[3] = $data56_C['pop_tot']; // 15-60
        // TT 4-6: KHÔNG CÓ DỮ LIỆU (để null, sẽ bôi xám)
        // TT 7-9: Mù chữ mức 2 trong độ tuổi
        $xmc56[7] = $data56_A['lv2_tot']; // 15-25
        $xmc56[8] = $data56_B['lv2_tot']; // 15-35
        $xmc56[9] = $data56_C['lv2_tot']; // 15-60

        /* ===================== Xóa mù chữ (5.9) theo LiteracyOutcomeSummaryReport ===================== */
        // Hàm lấy tổng dân số theo dải tuổi (tương tự LiteracyOutcomeSummaryReport)
        $buildLiteracySummary = function(int $yMin, int $yMax) use ($academic, $scheme, $geo) {
            // latest PE
            $peLatest = DB::table('person_educations as pe1')
                ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) rn'))
                ->where('pe1.academic_year', $academic);
            
            // latest XMC
            $xmcLatest = DB::table('person_literacy as x')
                ->select('x.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY x.person_id ORDER BY x.id DESC) rn'));

            $qb = DB::table('survey_form_details as d')
                ->join('survey_forms as f', function($j){
                    $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
                })
                ->join('people as p', 'p.id', '=', 'd.person_id')
                ->leftJoinSub($peLatest, 'pe', function($j){
                    $j->on('pe.person_id','=','p.id')->where('pe.rn', 1);
                })
                ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id')
                ->leftJoinSub($xmcLatest, 'xmc', function($j){
                    $j->on('xmc.person_id','=','p.id')->where('xmc.rn', 1);
                })
                ->leftJoin('ethnicities as e', 'e.id', '=', 'p.ethnicity_id')
                ->where('d.is_deleted', false)
                ->where('d.academic_year', $academic);

            // geo filter
            if ($scheme === 'old') {
                if (!empty($geo['province_id'])) $qb->where('f.old_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $qb->where('f.old_ward_id',     $geo['ward_id']);
            } else {
                if (!empty($geo['province_id'])) $qb->where('f.new_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $qb->where('f.new_ward_id',     $geo['ward_id']);
            }

            $ageY = "(p.dob_year BETWEEN {$yMin} AND {$yMax})";

            $row = $qb->selectRaw("
                    SUM(CASE WHEN {$ageY} THEN 1 ELSE 0 END) AS pop
                ")->first();

            return [
                'pop' => (int)($row->pop ?? 0),
            ];
        };

        // 3 dải tuổi cho báo cáo XMC 5.9
        $data59_A = $buildLiteracySummary($y1-25, $y1-15); // 15-25
        $data59_B = $buildLiteracySummary($y1-35, $y1-15); // 15-35
        $data59_C = $buildLiteracySummary($y1-60, $y1-15); // 15-60

        $xmc59 = [];
        // TT 1-3: Tổng dân số trong độ tuổi
        $xmc59[1] = $data59_A['pop']; // 15-25
        $xmc59[2] = $data59_B['pop']; // 15-35
        $xmc59[3] = $data59_C['pop']; // 15-60
        // TT 4-9: KHÔNG CÓ DỮ LIỆU (để null, sẽ bôi xám)

        return compact('pcgd21','thcs31','thcs32','thpt41','thpt42','thpt43','xmc51','xmc52','xmc53','xmc55','xmc56','xmc59');
    }
    
    /**
     * Xóa cache - CHỈ XÓA CACHE BÁO CÁO, KHÔNG XÓA SESSION/AUTH
     */
    public function clearCache(Request $request)
    {
        $academic = $request->input('school_year', '2024-2025');
        $scheme = $request->input('scheme', 'old');
        $geo = (array)$request->input('geo', []);
        
        // Tạo cache key CỤ THỂ cho báo cáo này
        $cacheKey = 'stat_verify_' . md5($academic . $scheme . json_encode($geo));
        
        // CHỈ XÓA cache key cụ thể này, KHÔNG xóa toàn bộ cache
        Cache::forget($cacheKey);
        
        // Hoặc xóa tất cả cache có prefix 'stat_verify_' (nếu dùng Redis/Memcached có tag)
        // if (Cache::getStore() instanceof \Illuminate\Cache\TaggableStore) {
        //     Cache::tags(['statistical_verification'])->flush();
        // }
        
        // Nếu là AJAX request, trả về JSON
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true, 
                'message' => 'Đã xóa cache báo cáo thành công'
            ]);
        }
        
        // Nếu là request thường, redirect back
        return back()->with('success', 'Đã làm mới dữ liệu thành công');
    }
}


