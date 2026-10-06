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

class TKTreCongLapVaNgoaiCongLapReport extends BaseReport
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

        // Tăng tốc tính công thức - bỏ qua nếu method không tồn tại
        // Calculation::setCalculationEnabled() có thể không có trong một số phiên bản

        // Cấu hình template
        $cfg = [
            'path' => resource_path('excel/1.10.TKTreCongLapVaNgoaiCongLap_PhuongXa.xlsx'),
            'header' => [
                'province' => 'B1',  // Tên tỉnh
                'ward' => 'B2'       // Tên phường/xã
            ],
            'yearsRow' => 5,         // Hàng 5 chứa các năm
            'yearCols' => ['F', 'G', 'H', 'I', 'J', 'K', 'L'] // Cột F đến L
        ];

        // Load file Excel template
        $wb = \PhpOffice\PhpSpreadsheet\IOFactory::load($cfg['path']);
        $sh = $wb->getActiveSheet();

        // Ghi tên tỉnh và phường/xã
        $sh->setCellValue($cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        $sh->setCellValue($cfg['header']['ward'], $this->geoService->geoNameFromId($geo['ward_id'] ?? null));

        // Ghi đè các năm học vào cột F đến L hàng 5
        // Ví dụ: 2025-2026 thì cột F = 2025, G = 2024, H = 2023, ...
        $yearCols = $cfg['yearCols'];
        foreach (range(0, 6) as $index) {
            $year = $y1 - $index;
            $sh->setCellValue($yearCols[$index] . $cfg['yearsRow'], $year);
        }

        // Lấy dữ liệu từ database
        $data = $this->queryMamnon0To6($academic, $scheme, $geo);
        $byAge = $data['by_age'];   // 0..6
        $total = $data['total'];    // tổng 0..5

        // Helper functions
        $zeroblank = function($v) { return ((int)$v === 0) ? null : $v; };
        $pct = function($num,$den) {
            if ($den > 0) return round($num/$den, 4);
            return null;
        };

        // Ghi dữ liệu vào các hàng theo yêu cầu
        $yearCols = $cfg['yearCols']; // F..L
        $AGES_SHOW = range(0, 6);     // hiển thị 7 cột 0..6
        $AGES_SUM = range(0, 5);      // tổng cộng chỉ tính 0..5

        // Hàm ghi dữ liệu cho một hàng
        $putRow = function(int $row, callable $getter) use ($sh, $byAge, $yearCols, $AGES_SHOW, $AGES_SUM, $zeroblank) {
            // Ghi theo từng cột tuổi 0..6
            foreach ($AGES_SHOW as $age) {
                $val = $getter($byAge[$age] ?? [], $age);
                $addr = $yearCols[$age] . $row;
                $sh->setCellValue($addr, $zeroblank($val));
            }

            // Tổng cộng (chỉ tính 0..5)
            $sum = 0;
            foreach ($AGES_SUM as $age) {
                $sum += $getter($byAge[$age] ?? [], $age);
            }
            $sh->setCellValue('M' . $row, $zeroblank($sum));
        };

        // Hàng 7: Tổng số trẻ trong độ tuổi
        $putRow(7, fn($v) => $v['total'] ?? 0);

        // Hàng 8: Số trẻ phải huy động
        $putRow(8, fn($v) => $v['must_mobilize'] ?? 0);

        // Hàng 9: Số trẻ đến trường, nhóm, lớp
        $putRow(9, fn($v) => $v['enrolled'] ?? 0);

        // Hàng 10: Trẻ ở phường/xã học tại địa bàn phường/xã
        $putRow(10, fn($v) => $v['enrolled_local'] ?? 0);

        // Hàng 11: Trẻ học tại trường công lập trong địa bàn
        $putRow(11, fn($v) => $v['enrolled_local_conglap'] ?? 0);

        // Hàng 12: Trẻ học tại trường ngoài công lập trong địa bàn
        $putRow(12, fn($v) => $v['enrolled_local_ngoai_conglap'] ?? 0);

        // Hàng 13: Trẻ phường/xã học trái tuyến địa bàn khác
        $putRow(13, fn($v) => $v['enrolled_trai_tuyen'] ?? 0);

        // Hàng 14: Trẻ học trái tuyến tại trường công lập ngoài địa bàn
        $putRow(14, fn($v) => $v['enrolled_trai_tuyen_conglap'] ?? 0);

        // Hàng 15: Trẻ học trái tuyến tại trường ngoài công lập ngoài địa bàn
        $putRow(15, fn($v) => $v['enrolled_trai_tuyen_ngoai_conglap'] ?? 0);

        // Ghi thông tin xã và ngày tháng năm vào cột K17 (để trống các chỗ ...)
        $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null);
        $footerText = $wardName . ', ngày ... tháng ... năm ...';
        $sh->setCellValue('K17', $footerText);

        // Trả về file Excel / Stream Response
        $filename = $this->getExportFilename($cfg['path']);

        return response()->streamDownload(function () use ($wb) {
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($wb, 'Xlsx');
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Query dữ liệu mầm non 0-6 tuổi (dựa vào logic R11Report.php)
     */
    protected function queryMamnon0To6(string $academic, string $scheme, array $geo): array
    {
        [$y1] = array_map('intval', explode('-', $academic));
        // 0..6 tuổi => các năm sinh
        $ages  = range(0, 6);
        $years = array_map(fn($a) => $y1 - $a, $ages);

        // Query chính từ v_survey_detail_flat
        $peLatest = DB::table('person_educations as pe1')
            ->select('pe1.person_id', 'pe1.hoc_hai_buoi as hoc_2_buoi')
            ->where('pe1.academic_year', $academic)
            ->whereRaw(
                'pe1.id = (
                    SELECT MAX(pe2.id)
                    FROM person_educations pe2
                    WHERE pe2.person_id = pe1.person_id
                    AND pe2.academic_year = ?
                )',
                [$academic]
            );

        $v = DB::table('v_survey_detail_flat as v')
            ->leftJoin('person_mamnon as pm', 'pm.person_id', '=', 'v.person_id')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->leftJoinSub($peLatest, 'pe', function ($j) {
                $j->on('pe.person_id', '=', 'v.person_id');
            })
            ->where('v.academic_year', $academic)
            ->whereIn('v.dob_year', $years);

        // Lọc địa bàn
        if (($geo['province_id'] ?? null) || ($geo['ward_id'] ?? null)) {
            if (($scheme ?? 'old') === 'old') {
                if (!empty($geo['province_id'])) $v->where('v.old_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $v->where('v.old_ward_id',     $geo['ward_id']);
            } else {
                if (!empty($geo['province_id'])) $v->where('v.new_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $v->where('v.new_ward_id',     $geo['ward_id']);
            }
        }

        $wardId = $geo['ward_id'] ?? null;

        // Các điều kiện
        $isMinority     = "(v.ethnicity IS NOT NULL AND UPPER(v.ethnicity) <> 'KINH')";
        $disAny         = "(
            COALESCE(v.dis_VAN_DONG,0)+COALESCE(v.dis_NGHE_NOI,0)+COALESCE(v.dis_NHIN,0)+
            COALESCE(v.dis_THAN_KINH,0)+COALESCE(v.dis_TRI_TUE,0)+COALESCE(v.dis_HOC_TAP,0)+
            COALESCE(v.dis_TU_KY,0)+COALESCE(v.dis_KHAC,0)
        ) > 0 OR v.dis_has_certificate=1";
        $movedOutOrDead = "v.sc_name IN ('Chuyển đi','MOVED_OUT','Chết','DECEASED')";
        $isEnrolled     = "(v.education_block_code IS NOT NULL AND v.school_code IS NOT NULL AND v.school_code != '')";

        $rows = $v->selectRaw("
            v.dob_year AS yob,
            COUNT(DISTINCT v.person_id) AS total,
            COUNT(DISTINCT CASE WHEN CAST(v.is_female AS UNSIGNED)=1 THEN v.person_id END) AS female,
            COUNT(DISTINCT CASE WHEN $isMinority THEN v.person_id END) AS minority,
            COUNT(DISTINCT CASE WHEN $disAny THEN v.person_id END) AS disabled_any,
            COUNT(DISTINCT CASE WHEN v.dis_can_learn=1 THEN v.person_id END) AS disabled_can_learn,
            (
            COUNT(DISTINCT v.person_id)
            - COUNT(DISTINCT CASE WHEN $disAny THEN v.person_id END)
            - COUNT(DISTINCT CASE WHEN $movedOutOrDead THEN v.person_id END)
            ) AS must_mobilize,
            COUNT(DISTINCT CASE WHEN $isEnrolled  THEN v.person_id END) AS enrolled,
            COUNT(DISTINCT CASE WHEN $isEnrolled AND (s.code IS NULL OR s.code NOT LIKE 'NGOAI_TINH%') AND s.new_ward_id = ? THEN v.person_id END) AS enrolled_local,
            COUNT(DISTINCT CASE WHEN $isEnrolled AND (s.code IS NULL OR s.code NOT LIKE 'NGOAI_TINH%') AND s.new_ward_id = ? AND COALESCE(s.is_conglap,0) = 1 THEN v.person_id END) AS enrolled_local_conglap,
            COUNT(DISTINCT CASE WHEN $isEnrolled AND (s.code IS NULL OR s.code NOT LIKE 'NGOAI_TINH%') AND s.new_ward_id = ? AND COALESCE(s.is_conglap,0) = 0 THEN v.person_id END) AS enrolled_local_ngoai_conglap,
            COUNT(DISTINCT CASE WHEN $isEnrolled AND (s.code LIKE 'NGOAI_TINH%' OR s.new_ward_id IS NULL OR s.new_ward_id != ?) THEN v.person_id END) AS enrolled_ngoai_dia_ban,
            COUNT(DISTINCT CASE WHEN $isEnrolled AND (s.code LIKE 'NGOAI_TINH%' OR s.new_ward_id IS NULL OR s.new_ward_id != ?) AND COALESCE(s.is_conglap,0) = 1 THEN v.person_id END) AS enrolled_ngoai_dia_ban_conglap,
            COUNT(DISTINCT CASE WHEN $isEnrolled AND (s.code LIKE 'NGOAI_TINH%' OR s.new_ward_id IS NULL OR s.new_ward_id != ?) AND COALESCE(s.is_conglap,0) = 0 THEN v.person_id END) AS enrolled_ngoai_dia_ban_ngoai_conglap,
            COUNT(DISTINCT CASE WHEN $isEnrolled AND CAST(v.is_female AS UNSIGNED)=1 THEN v.person_id END) AS enrolled_girl,
            COUNT(DISTINCT CASE WHEN $isEnrolled AND $isMinority THEN v.person_id END) AS enrolled_minority,
            COUNT(DISTINCT CASE WHEN $isEnrolled AND COALESCE(pm.dan_toc_chuan_bi_tv,0)=1 THEN v.person_id END) AS prepared_tv,
            COUNT(DISTINCT CASE WHEN ($disAny) AND $isEnrolled THEN v.person_id END) AS disabled_access,
            COUNT(DISTINCT CASE WHEN $isEnrolled AND (COALESCE(pm.hoc_2_buoi,0)=1 OR COALESCE(pe.hoc_2_buoi,0)=1) THEN v.person_id END) AS two_sessions,
            COUNT(DISTINCT CASE WHEN  $isEnrolled and (COALESCE(pm.hoan_thanh_chuong_trinh_gdmn,0)=1 OR (v.graduation_level_name='MN')) THEN v.person_id END) AS grad_gdmn,
            COUNT(DISTINCT CASE WHEN v.sc_name='Chuyển đến' THEN v.person_id END) AS moved_in,
            COUNT(DISTINCT CASE WHEN v.sc_name IN ('Chết','DECEASED') THEN v.person_id END) AS died,
            COUNT(DISTINCT CASE WHEN v.sc_name='Chuyển đi' THEN v.person_id END) AS moved_out
        ", [$wardId, $wardId, $wardId, $wardId, $wardId, $wardId])
        ->groupBy('yob')
        ->orderBy('yob')
        ->get();

        // Map về 0..6 tuổi
        $byAge = [];
        foreach ($rows as $r) {
            $age = $y1 - (int)$r->yob; // 0..6
            $byAge[$age] = [
                'total'              => (int)$r->total,
                'female'             => (int)$r->female,
                'minority'           => (int)$r->minority,
                'disabled_any'       => (int)$r->disabled_any,
                'disabled_can_learn' => (int)$r->disabled_can_learn,
                'disabled_access'    => (int)$r->disabled_access,
                'must_mobilize'      => max(0,(int)$r->must_mobilize),
                'enrolled'           => (int)$r->enrolled,
                'enrolled_local'     => (int)$r->enrolled_local,
                'enrolled_local_conglap' => (int)$r->enrolled_local_conglap,
                'enrolled_local_ngoai_conglap' => (int)$r->enrolled_local_ngoai_conglap,
                'enrolled_trai_tuyen' => (int)$r->enrolled_ngoai_dia_ban,
                'enrolled_trai_tuyen_conglap' => (int)$r->enrolled_ngoai_dia_ban_conglap,
                'enrolled_trai_tuyen_ngoai_conglap' => (int)$r->enrolled_ngoai_dia_ban_ngoai_conglap,
                'enrolled_girl'      => (int)$r->enrolled_girl,
                'enrolled_minority'  => (int)$r->enrolled_minority,
                'prepared_tv'        => (int)$r->prepared_tv,
                'two_sessions'       => (int)$r->two_sessions,
                'grad_gdmn'          => (int)$r->grad_gdmn,
                'move_out'          => (int)$r->moved_out,
                'move_in'           => (int)$r->moved_in,
                'died'              => (int)$r->died,
            ];
        }

        // Điền 0 cho tuổi thiếu
        foreach ($ages as $a) {
            $byAge[$a] = $byAge[$a] ?? array_fill_keys([
                'total','female','minority','disabled_any','disabled_can_learn','disabled_access',
                'must_mobilize','enrolled','enrolled_local','enrolled_local_conglap','enrolled_local_ngoai_conglap',
                'enrolled_trai_tuyen','enrolled_trai_tuyen_conglap','enrolled_trai_tuyen_ngoai_conglap',
                'enrolled_girl','enrolled_minority','prepared_tv','two_sessions','grad_gdmn','move_out','move_in','died'
            ], 0);
        }
        ksort($byAge);

        // Tổng 0–5
        $sum = array_fill_keys(array_keys(current($byAge)), 0);
        foreach (range(0, 5) as $age) {
            if (isset($byAge[$age])) {
                foreach ($byAge[$age] as $k => $v) {
                    $sum[$k] += $v;
                }
            }
        }

        return ['by_age'=>$byAge, 'total'=>$sum];
    }

}
