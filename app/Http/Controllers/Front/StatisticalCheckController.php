<?php

namespace App\Http\Controllers\Front;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;

class StatisticalCheckController extends Controller
{
    public function index(Request $request)
    {
        // Parse parameters - mặc định năm học 2024-2025
        $academic = (string)($request->input('school_year') ?: '2024-2025');
        $scheme = (string)$request->input('scheme', 'old');
        $geo = (array)$request->input('geo', []);
        
        // Tính năm sinh dựa trên năm học
        [$y1] = array_map('intval', explode('-', $academic));
        
        // Tạo danh sách các năm và độ tuổi
        $years = [];
        for ($age = 6; $age <= 18; $age++) {
            $yearOfBirth = $y1 - $age;
            $years[] = [
                'age' => $age,
                'year' => $yearOfBirth,
                'academic_year' => ($yearOfBirth - 1) . '-' . $yearOfBirth
            ];
        }
        
        // Danh sách các tiêu chí
        $criteria = [
            1 => 'Số đối tượng PPC',
            2 => 'Số đối tượng đang học TH',
            3 => 'Số đối tượng HTCTTH',
            4 => 'Số đối tượng đang học THCS',
            5 => 'Số đối tượng TN THCS',
            6 => 'Đã và đang học CT GDPT hoặc GDTX cấp THPT hoặc GDNN',
            7 => 'Số đối tượng bỏ học cấp TH',
            8 => 'Số đối tượng bỏ học cấp THCS',
            9 => 'Số đối tượng bỏ học cấp THPT',
            10 => 'Số đối tượng chưa đi học'
        ];
        
        // TÍNH DỮ LIỆU từ logic tương đương báo cáo 2.1 (TiH614) và 3.1 (R31)
        [$y1] = array_map('intval', explode('-', $academic));

        // 1) 2.1 Tiểu học: dùng dải 6..14 tuổi (năm sinh y1-age) - SỬ DỤNG v_survey_detail_flat
        $yearsPri = [];
        for ($age = 6; $age <= 14; $age++) { $yearsPri[] = $y1 - $age; }
        rsort($yearsPri);

        // Sử dụng view v_survey_detail_flat giống TiH614Report
        $pri = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->where('v.academic_year', $academic)
            ->whereIn('v.dob_year', $yearsPri);

        // geo filter theo scheme
        if ($scheme === 'old') {
            if (!empty($geo['province_id'])) $pri->where('v.old_province_id', (int)$geo['province_id']);
            if (!empty($geo['district_id'])) $pri->where('v.old_district_id', (int)$geo['district_id']);
            if (!empty($geo['ward_id']))     $pri->where('v.old_ward_id',     (int)$geo['ward_id']);
        } else {
            if (!empty($geo['province_id'])) $pri->where('v.new_province_id', (int)$geo['province_id']);
            if (!empty($geo['ward_id']))     $pri->where('v.new_ward_id',     (int)$geo['ward_id']);
        }

        // Điều kiện khuyết tật và khối lớp giống TiH614Report
        $disabledExpr = "(v.dis_VAN_DONG=1 OR v.dis_NGHE_NOI=1 OR v.dis_NHIN=1 OR v.dis_THAN_KINH=1 OR v.dis_TRI_TUE=1 OR v.dis_HOC_TAP=1 OR v.dis_TU_KY=1 OR v.dis_KHAC=1)";
        $notDisabledExpr = "NOT({$disabledExpr})";
        $enrolledBlockExpr = "(v.education_block_code REGEXP '^[0-9]+$' AND CAST(v.education_block_code AS UNSIGNED) BETWEEN 1 AND 12)";
        $notEnrolledByBlock = "((v.education_block_code IS NULL OR v.education_block_code = '') AND (v.graduation_level_name IS NULL OR v.graduation_level_name=''))";
        $inPrimary = "(CAST(v.education_block_code AS UNSIGNED) BETWEEN 1 AND 5)";

        $priRows = $pri->selectRaw("
                v.dob_year AS yob,
                COUNT(*) AS total,
                SUM(CASE WHEN {$notDisabledExpr} THEN 1 ELSE 0 END) AS non_dis,
                SUM(CASE WHEN {$enrolledBlockExpr} AND {$inPrimary} AND v.school_code IS NOT NULL AND {$notDisabledExpr} THEN 1 ELSE 0 END) AS studying_primary,
                SUM(CASE WHEN {$enrolledBlockExpr} AND CAST(v.education_block_code AS UNSIGNED)=1 AND v.school_code IS NOT NULL THEN 1 ELSE 0 END) AS studying_grade1_all,
                SUM(CASE WHEN v.bo_hoc_lop IS NOT NULL AND CAST(v.bo_hoc_lop AS UNSIGNED) BETWEEN 1 AND 5 AND {$notDisabledExpr} THEN 1 ELSE 0 END) AS drop_primary,
                SUM(CASE WHEN {$notEnrolledByBlock} AND {$notDisabledExpr} THEN 1 ELSE 0 END) AS not_enrolled,
                SUM(CASE WHEN UPPER(TRIM(v.graduation_level_name))='TH' AND {$notDisabledExpr} THEN 1 ELSE 0 END) AS grad_primary
            ")
            ->groupBy('v.dob_year')
            ->get()
            ->keyBy('yob');

        // 2) 3.1 THCS: dùng view phẳng như R31 cho 11..18
        $yearsSec = range($y1-11, $y1-18);
        $flat = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->where('v.academic_year', $academic)
            ->whereIn('v.dob_year', $yearsSec);

        if ($scheme === 'old') {
            if (!empty($geo['province_id'])) $flat->where('v.old_province_id', $geo['province_id']);
            if (!empty($geo['ward_id']))     $flat->where('v.old_ward_id',     $geo['ward_id']);
        } else {
            if (!empty($geo['province_id'])) $flat->where('v.new_province_id', $geo['province_id']);
            if (!empty($geo['ward_id']))     $flat->where('v.new_ward_id',     $geo['ward_id']);
        }

        $grade = "CAST(v.education_block_code AS UNSIGNED)";
        $disAny = "(COALESCE(v.dis_VAN_DONG,0)+COALESCE(v.dis_NGHE_NOI,0)+COALESCE(v.dis_NHIN,0)+COALESCE(v.dis_THAN_KINH,0)+COALESCE(v.dis_TRI_TUE,0)+COALESCE(v.dis_HOC_TAP,0)+COALESCE(v.dis_TU_KY,0)+COALESCE(v.dis_KHAC,0)) > 0 OR v.dis_has_certificate=1";
        $movedOut = "UPPER(TRIM(v.sc_name)) IN ('CHẾT','DECEASED','CHUYỂN ĐI','CHUYEN DI','MOVED_OUT')";

        $secHead = (clone $flat)->selectRaw("
                v.dob_year AS yob,
                (
                    COUNT(DISTINCT v.person_id)
                    - COUNT(DISTINCT CASE WHEN ($disAny) THEN v.person_id END)
                    - COUNT(DISTINCT CASE WHEN ($movedOut) THEN v.person_id END)
                ) AS must_pc
            ")
            ->groupBy('yob')->get()->keyBy('yob');

        $secRows = (clone $flat)->selectRaw("
                v.dob_year AS yob,
                SUM(CASE WHEN {$grade} BETWEEN 6 AND 9 THEN 1 ELSE 0 END) AS studying_secondary,
                SUM(CASE WHEN UPPER(v.graduation_level_name) IN ('THCS','THPT') THEN 1 ELSE 0 END) AS grad_secondary,
                SUM(CASE WHEN {$grade} BETWEEN 6 AND 9 AND v.bo_hoc_lop IS NOT NULL THEN 1 ELSE 0 END) AS drop_secondary,
                SUM(CASE WHEN (UPPER(v.graduation_level_name)='THCS' OR UPPER(v.graduation_level_name)='THPT') THEN 1 ELSE 0 END) AS tn_thcs,
                SUM(CASE WHEN ({$grade} IS NOT NULL AND UPPER(v.graduation_level_name)='THCS') OR UPPER(v.graduation_level_name)='THPT' THEN 1 ELSE 0 END) AS after_thcs_proxy
            ")
            ->groupBy('yob')->get()->keyBy('yob');

        // 3) Lắp dữ liệu vào ma trận tiêu chí
        $data = [];
        $sources = []; // Mapping nguồn báo cáo cho mỗi ô

        // Tiêu chí 1: Số đối tượng PPC
        // - Tuổi 6: LẤY TẤT CẢ (bao gồm khuyết tật) - từ 2.1 (TiH614Report)
        // - Tuổi 7-14: Lấy PPC (không khuyết tật) - từ 2.1 (TiH614Report)
        // - Tuổi 15-18: Lấy từ 3.1 (R31Report: must_pc = tổng - khuyết tật - chuyển đi/chết)
        foreach ($years as $yinfo) {
            $yob = $yinfo['year']; $age = $yinfo['age'];
            if ($age == 6) {
                $data[1][$yob] = (int)($priRows[$yob]->total ?? 0);
                $sources[1][$yob] = '2.1';
            } elseif ($age >= 7 && $age <= 14) {
                $data[1][$yob] = (int)($priRows[$yob]->non_dis ?? 0);
                $sources[1][$yob] = '2.1';
            } elseif ($age >= 15 && $age <= 18) {
                $data[1][$yob] = (int)($secHead[$yob]->must_pc ?? 0);
                $sources[1][$yob] = '3.1';
            }
        }

        // Tiêu chí 2: Đang học TH (lớp 1-5)
        foreach ($yearsPri as $yob) {
            $age = $y1 - $yob;
            if ($age == 6) {
                $data[2][$yob] = (int)($priRows[$yob]->studying_grade1_all ?? 0);
                $sources[2][$yob] = '2.1';
            } else {
                $data[2][$yob] = (int)($priRows[$yob]->studying_primary ?? 0);
                $sources[2][$yob] = '2.1';
            }
        }

        // Tiêu chí 3: HTCTTH (tốt nghiệp TH)
        foreach ($yearsPri as $yob) {
            $data[3][$yob] = (int)($priRows[$yob]->grad_primary ?? 0);
            $sources[3][$yob] = '2.1';
        }

        // Tiêu chí 4: Đang học THCS (6..9)
        foreach ($yearsSec as $yob) {
            $data[4][$yob] = (int)($secRows[$yob]->studying_secondary ?? 0);
            $sources[4][$yob] = '3.1';
        }

        // Tiêu chí 5: TN THCS (bao gồm TN THPT)
        foreach ($yearsSec as $yob) {
            $data[5][$yob] = (int)($secRows[$yob]->grad_secondary ?? 0);
            $sources[5][$yob] = '3.1';
        }

        // Tiêu chí 6: Đã/đang học CT GDPT hoặc GDTX cấp THPT hoặc GDNN
        foreach (range($y1-18, $y1-15) as $yob) {
            $data[6][$yob] = (int)($secRows[$yob]->after_thcs_proxy ?? 0);
            $sources[6][$yob] = '3.1';
        }

        // Tiêu chí 7: Bỏ học cấp TH
        foreach ($yearsPri as $yob) {
            $data[7][$yob] = (int)($priRows[$yob]->drop_primary ?? 0);
            $sources[7][$yob] = '2.1';
        }

        // Tiêu chí 8: Bỏ học cấp THCS
        foreach ($yearsSec as $yob) {
            $data[8][$yob] = (int)($secRows[$yob]->drop_secondary ?? 0);
            $sources[8][$yob] = '3.1';
        }

        // 3) 4.1 THPT: dùng logic R41Report cho 15..18 tuổi
        $yearsHigh = range($y1-15, $y1-18);
        $high = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->where('v.academic_year', $academic)
            ->whereIn('v.dob_year', $yearsHigh);

        if ($scheme === 'old') {
            if (!empty($geo['province_id'])) $high->where('v.old_province_id', $geo['province_id']);
            if (!empty($geo['ward_id']))     $high->where('v.old_ward_id',     $geo['ward_id']);
        } else {
            if (!empty($geo['province_id'])) $high->where('v.new_province_id', $geo['province_id']);
            if (!empty($geo['ward_id']))     $high->where('v.new_ward_id',     $geo['ward_id']);
        }

        $highRows = $high->selectRaw("
                v.dob_year AS yob,
                SUM(CASE WHEN CAST(COALESCE(v.bo_hoc_lop,'0') AS UNSIGNED) BETWEEN 10 AND 12 THEN 1 ELSE 0 END) AS drop_thpt
            ")
            ->groupBy('v.dob_year')
            ->get()
            ->keyBy('yob');

        // Tiêu chí 9: Bỏ học cấp THPT (từ 4.1)
        foreach ($yearsHigh as $yob) {
            $data[9][$yob] = (int)($highRows[$yob]->drop_thpt ?? 0);
            $sources[9][$yob] = '4.1';
        }

        // Tiêu chí 10: Chưa đi học (không khuyết tật, không có khối/lớp hợp lệ)
        foreach ($yearsPri as $yob) {
            $data[10][$yob] = (int)($priRows[$yob]->not_enrolled ?? 0);
            $sources[10][$yob] = '2.1';
        }

        // 4) KẾT QUẢ KIỂM TRA: ✅ logic, ❌ sai, ❕ cảnh báo chênh giữa 2.1 và 3.1
        $results = [];
        foreach ($years as $yinfo) {
            $yob = $yinfo['year'];
            $age = $yinfo['age'];
            $status = 'pass';

            // so khớp PPC giữa 2.1 (11–14) và 3.1 (11–14) → cảnh báo nếu lệch
            if ($age >= 11 && $age <= 14) {
                $ppc21 = $data[1][$yob] ?? null; // đã set theo 2.1
                $ppc31 = (int)($secHead[$yob]->must_pc ?? null);
                if ($ppc21 !== null && $ppc31 !== null && $ppc21 !== $ppc31) {
                    $status = 'warning';
                }
            }

            // kiểm tra nội bộ 2.1:
            // - Tuổi 6: KHÔNG kiểm tra (vì lấy tất cả, không theo công thức PPC)
            // - Tuổi 7-14: PPC = đang học TH + chưa đi học (không khuyết tật)
            if ($status !== 'warning' && $age >= 7 && $age <= 14) {
                $ppc    = $data[1][$yob]  ?? null;
                $study  = $data[2][$yob]  ?? 0;
                $noSch  = $data[10][$yob] ?? 0;
                if ($ppc !== null) {
                    if (($study + $noSch) !== $ppc) {
                        $status = 'fail';
                    }
                }
            }

            $results[$yob] = $status;
        }

        $data['result'] = $results;
        
        return view('front.reports.statistical-check.index', compact('years', 'criteria', 'data', 'sources', 'academic'));
    }
    
    /**
     * Xóa cache báo cáo
     */
    public function clearCache(Request $request)
    {
        $academic = $request->input('school_year', '2024-2025');
        $scheme = $request->input('scheme', 'old');
        $geo = (array)$request->input('geo', []);
        
        // Tạo cache key cụ thể cho báo cáo này
        $cacheKey = 'stat_check_' . md5($academic . $scheme . json_encode($geo));
        
        // Xóa cache
        Cache::forget($cacheKey);
        
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true, 
                'message' => 'Đã xóa cache báo cáo thành công'
            ]);
        }
        
        return back()->with('success', 'Đã làm mới dữ liệu thành công');
    }
}

