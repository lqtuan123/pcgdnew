<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Services\ReportFormatService;
use App\Services\GeoService;
use App\Helpers\NumberFormatHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
 

class TiH614Report extends BaseReport
{
     public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}
    public function export(Request $req)
    {
        // 1) Chuẩn bị context
        $academic = (string) $req->input('year');
        [$y1, $y2] = $this->parseAcademic($academic);
        $years     = $this->yearsFor($y1, 6, 14); // 9 năm, desc
        $scheme    = (string) $req->input('scheme','old');
        $geo       = (array)  $req->input('geo',[]);

        // 2) Tính dataset chính + “trái tuyến theo tuổi”
        [$meta, $years, $series, $footer] = $this->buildDataset($req, $y1, $years, $scheme, $geo);
        [$metaOff, $yearsOff, $offSeries] = $this->offRoutePrimaryByAge($req, $y1, $years, $scheme, $geo);

        // gộp vào series
        $series = $series + $offSeries;

        // 3) Ghi Excel
        $tpl = resource_path('excel/2.1.TiH.6.14Tuoi.xlsx');
        $wb  = IOFactory::load($tpl);
        $sh  = $wb->getActiveSheet();

        $this->fillSheet($sh, $meta, $years, $series, $footer, $scheme);

        return response()->streamDownload(function () use ($wb) {
            $w = IOFactory::createWriter($wb, 'Xlsx');
            $w->setPreCalculateFormulas(false);
            $w->save('php://output');
        }, $this->getExportFilename($tpl), [
            'Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]);
    }

    /** ====== Phần dưới: bạn dán lại logic đang có, chỉ sửa chỗ gọi helper ====== */

    protected function buildDataset(Request $req, int $_y1, array $_years, string $_scheme, array $_geo): array
    {
        // ===== 1) Năm học + dải năm sinh 6..14 tại y1
        $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1, $y2] = array_map('intval', explode('-', $academic));
        $years = [];
        for ($age = 6; $age <= 14; $age++) $years[] = $y1 - $age;
        rsort($years);

        // ===== 2) Địa bàn
        $scheme = (string)$req->input('scheme', 'old'); // 'old'|'new'
        $geo    = (array)$req->input('geo', []);
        $pid = (int)($geo['province_id'] ?? 0);
        $did = (int)($geo['district_id'] ?? 0);
        $wid = (int)($geo['ward_id'] ?? 0);

        // ===== 3) Biểu thức "cùng địa bàn" vs "khác địa bàn" (so trường với địa bàn form)
        if ($scheme === 'old') {
            if ($wid) {
                $sameWard = "s.old_ward_id IS NOT NULL AND v.old_ward_id IS NOT NULL AND s.old_ward_id = v.old_ward_id";
                $diffWard = "s.old_ward_id IS NOT NULL AND v.old_ward_id IS NOT NULL AND s.old_ward_id <> v.old_ward_id";
            } elseif ($did) {
                $sameWard = "s.old_district_id IS NOT NULL AND v.old_district_id IS NOT NULL AND s.old_district_id = v.old_district_id";
                $diffWard = "s.old_district_id IS NOT NULL AND v.old_district_id IS NOT NULL AND s.old_district_id <> v.old_district_id";
            } else {
                $sameWard = "s.old_province_id IS NOT NULL AND v.old_province_id IS NOT NULL AND s.old_province_id = v.old_province_id";
                $diffWard = "s.old_province_id IS NOT NULL AND v.old_province_id IS NOT NULL AND s.old_province_id <> v.old_province_id";
            }
        } else {
            if ($wid) {
                $sameWard = "s.new_ward_id IS NOT NULL AND v.new_ward_id IS NOT NULL AND s.new_ward_id = v.new_ward_id";
                $diffWard = "s.new_ward_id IS NOT NULL AND v.new_ward_id IS NOT NULL AND s.new_ward_id <> v.new_ward_id";
            } else {
                $sameWard = "s.new_province_id IS NOT NULL AND v.new_province_id IS NOT NULL AND s.new_province_id = v.new_province_id";
                $diffWard = "s.new_province_id IS NOT NULL AND v.new_province_id IS NOT NULL AND s.new_province_id <> v.new_province_id";
            }
        }

        // ===== 4) Bộ lọc địa bàn cho dữ liệu đầu vào
        $qb = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', function($j) {
                $j->on('s.code', '=', 'v.school_code')
                  ->where(function($w) {
                      $w->whereNull('s.is_dissolved')->orWhere('s.is_dissolved', false);
                  });
            })
            ->where('v.academic_year', $academic)
            ->whereIn('v.dob_year', $years);

        if ($scheme === 'old') {
            if ($pid) $qb->where('v.old_province_id', $pid);
            if ($did) $qb->where('v.old_district_id', $did);
            if ($wid) $qb->where('v.old_ward_id', $wid);
        } else {
            if ($pid) $qb->where('v.new_province_id', $pid);
            if ($wid) $qb->where('v.new_ward_id', $wid);
        }

        // ===== 5) Các biểu thức cờ
        $disabledExpr       = " ( v.dis_VAN_DONG=1 OR v.dis_NGHE_NOI=1 OR v.dis_NHIN=1 OR v.dis_THAN_KINH=1 OR v.dis_TRI_TUE=1 OR v.dis_HOC_TAP=1 OR v.dis_TU_KY=1 OR v.dis_KHAC=1 ) ";
        $notDisabledExpr    = " NOT( {$disabledExpr} ) ";
        $enrolledBlockExpr  = " (v.education_block_code REGEXP '^[0-9]+$' AND CAST(v.education_block_code AS UNSIGNED) BETWEEN 1 AND 12) ";
        $notEnrolledByBlock = " ((v.education_block_code IS NULL or v.education_block_code = '')  and (v.graduation_level_name IS NULL or v.graduation_level_name=''))  ";

        // ===== 6) Tổng hợp theo năm sinh
        $rows = $qb->selectRaw("
            v.dob_year as y,
            COUNT(*)                                                   as total,
            SUM(CASE WHEN v.is_female = 1 THEN 1 ELSE 0 END)          as female,
            SUM(CASE WHEN LOWER(COALESCE(v.ethnicity,'')) <> 'kinh' THEN 1 ELSE 0 END) as minority,
            SUM(CASE WHEN {$disabledExpr} THEN 1 ELSE 0 END)          as disabled,
            SUM(CASE WHEN v.dis_can_learn = 1 THEN 1 ELSE 0 END)      as dis_can_learn,
            SUM(CASE WHEN v.dis_can_learn = 1 AND {$enrolledBlockExpr} THEN 1 ELSE 0 END) as dis_access,

            -- Lớp 1..5: trong/xã & ngoài/xã (chỉ tính khi có school_id từ PE)
            SUM(CASE WHEN {$enrolledBlockExpr} AND CAST(v.education_block_code AS UNSIGNED)=1 AND {$sameWard} AND {$notDisabledExpr} THEN 1 ELSE 0 END) as g11,
            SUM(CASE WHEN {$enrolledBlockExpr} AND CAST(v.education_block_code AS UNSIGNED)=1 AND {$diffWard} AND {$notDisabledExpr} THEN 1 ELSE 0 END) as g12,

            SUM(CASE WHEN {$enrolledBlockExpr} AND CAST(v.education_block_code AS UNSIGNED)=2 AND {$sameWard} AND {$notDisabledExpr} THEN 1 ELSE 0 END) as g21,
            SUM(CASE WHEN {$enrolledBlockExpr} AND CAST(v.education_block_code AS UNSIGNED)=2 AND {$diffWard} AND {$notDisabledExpr} THEN 1 ELSE 0 END) as g22,

            SUM(CASE WHEN {$enrolledBlockExpr} AND CAST(v.education_block_code AS UNSIGNED)=3 AND {$sameWard} AND {$notDisabledExpr} THEN 1 ELSE 0 END) as g31,
            SUM(CASE WHEN {$enrolledBlockExpr} AND CAST(v.education_block_code AS UNSIGNED)=3 AND {$diffWard} AND {$notDisabledExpr} THEN 1 ELSE 0 END) as g32,

            SUM(CASE WHEN {$enrolledBlockExpr} AND CAST(v.education_block_code AS UNSIGNED)=4 AND {$sameWard} AND {$notDisabledExpr} THEN 1 ELSE 0 END) as g41,
            SUM(CASE WHEN {$enrolledBlockExpr} AND CAST(v.education_block_code AS UNSIGNED)=4 AND {$diffWard} AND {$notDisabledExpr} THEN 1 ELSE 0 END) as g42,

            SUM(CASE WHEN {$enrolledBlockExpr} AND CAST(v.education_block_code AS UNSIGNED)=5 AND {$sameWard} AND {$notDisabledExpr} THEN 1 ELSE 0 END) as g51,
            SUM(CASE WHEN {$enrolledBlockExpr} AND CAST(v.education_block_code AS UNSIGNED)=5 AND {$diffWard} AND {$notDisabledExpr} THEN 1 ELSE 0 END) as g52,

            -- Tốt nghiệp TH (TH) theo cùng/xã & khác/xã
            SUM(CASE WHEN UPPER(TRIM(v.graduation_level_name))='TH' AND {$notDisabledExpr} THEN 1 ELSE 0 END) as th1,
            SUM(CASE WHEN UPPER(TRIM(v.graduation_level_name))='TH'   THEN 0 ELSE 0 END) as th2,

            -- Tốt nghiệp TH + KHuyết tật + cùng xã (sửa mâu thuẫn: chỉ dùng disabledExpr)
            SUM(CASE WHEN UPPER(TRIM(v.graduation_level_name))='TH' AND {$sameWard} AND {$disabledExpr} THEN 1 ELSE 0 END) as thk,

            -- Lưu ban & Bỏ học (đang theo dữ liệu PE)
            SUM(CASE WHEN v.bo_hoc_lop IS NULL AND v.education_grade_name LIKE '%!%' AND {$sameWard} AND {$notDisabledExpr} THEN 1 ELSE 0 END) as repeat_same_ward,
            SUM(CASE WHEN v.bo_hoc_lop IS NULL AND v.education_grade_name LIKE '%!%' AND {$diffWard} AND {$notDisabledExpr} THEN 1 ELSE 0 END) as repeat_other_ward,

            SUM(CASE WHEN v.bo_hoc_lop IS NOT NULL AND {$sameWard} AND {$notDisabledExpr} THEN 1 ELSE 0 END) as quit_same_ward,
            SUM(CASE WHEN v.bo_hoc_lop IS NOT NULL AND {$diffWard} AND {$notDisabledExpr} THEN 1 ELSE 0 END) as quit_other_ward,

            -- Không khuyết tật & chưa xếp khối/lớp 1..12
            SUM(CASE WHEN {$notEnrolledByBlock} AND {$notDisabledExpr} THEN 1 ELSE 0 END) as non_dis_not_enrolled
        ")
        ->groupBy('v.dob_year')
        ->get();

        // ===== 7) Chuẩn hoá series
        $series = [
            'total'=>array_fill_keys($years,0),'female'=>array_fill_keys($years,0),'minority'=>array_fill_keys($years,0),
            'disabled'=>array_fill_keys($years,0),'dis_can_learn'=>array_fill_keys($years,0),'dis_access'=>array_fill_keys($years,0),
            'g11'=>array_fill_keys($years,0),'g12'=>array_fill_keys($years,0),
            'g21'=>array_fill_keys($years,0),'g22'=>array_fill_keys($years,0),
            'g31'=>array_fill_keys($years,0),'g32'=>array_fill_keys($years,0),
            'g41'=>array_fill_keys($years,0),'g42'=>array_fill_keys($years,0),
            'g51'=>array_fill_keys($years,0),'g52'=>array_fill_keys($years,0),
            'th1'=>array_fill_keys($years,0),'thk'=>array_fill_keys($years,0),
            'ppc'=>array_fill_keys($years,0),
            'repeat_same_ward'=>array_fill_keys($years,0),'repeat_other_ward'=>array_fill_keys($years,0),
            'quit_same_ward'=>array_fill_keys($years,0),'quit_other_ward'=>array_fill_keys($years,0),
            'non_dis_not_enrolled'=>array_fill_keys($years,0),
        ];

        foreach ($rows as $r) {
            $y = (int)$r->y;
            if (!isset($series['total'][$y])) continue;
            $series['total'][$y]         = (int)$r->total;
            $series['female'][$y]        = (int)$r->female;
            $series['minority'][$y]      = (int)$r->minority;
            $series['disabled'][$y]      = (int)$r->disabled;
            $series['dis_can_learn'][$y] = (int)$r->dis_can_learn;
            $series['dis_access'][$y]    = (int)$r->dis_access;

            $series['g11'][$y]=(int)$r->g11; $series['g12'][$y]=(int)$r->g12;
            $series['g21'][$y]=(int)$r->g21; $series['g22'][$y]=(int)$r->g22;
            $series['g31'][$y]=(int)$r->g31; $series['g32'][$y]=(int)$r->g32;
            $series['g41'][$y]=(int)$r->g41; $series['g42'][$y]=(int)$r->g42;
            $series['g51'][$y]=(int)$r->g51; $series['g52'][$y]=(int)$r->g52;

            $series['th1'][$y]=(int)$r->th1;
            $series['thk'][$y]=(int)$r->thk;

            $series['ppc'][$y] = $series['total'][$y] - $series['disabled'][$y];

            $series['repeat_same_ward'][$y]=(int)$r->repeat_same_ward;
            $series['repeat_other_ward'][$y]=(int)$r->repeat_other_ward;
            $series['quit_same_ward'][$y]=(int)$r->quit_same_ward;
            $series['quit_other_ward'][$y]=(int)$r->quit_other_ward;
            $series['non_dis_not_enrolled'][$y]=(int)$r->non_dis_not_enrolled;
        }

        // ===== 8) Meta & footer
        $meta = [
            'year1'=>$y1,
            'dateLine'=>'Ngày '.date('d').' tháng '.date('m').' năm '.date('Y'),
            'province'=>$geo['province_id']??null,
            'district'=>$geo['district_id']??null,
            'ward'=>$geo['ward_id']??null,
        ];

        $y_6t=$years[0]; $y_11t=$years[5]; $y_12t=$years[6]; $y_13t=$years[7]; $y_14t=$years[8];

        $kt_hoc = array_sum(array_map(fn($y)=>$series['dis_access'][$y], $years));
        $kt_kn  = array_sum(array_map(fn($y)=>$series['dis_can_learn'][$y], $years));

        $t11hoc = $series['g11'][$y_11t]+$series['g12'][$y_11t]+$series['g21'][$y_11t]+$series['g22'][$y_11t]+$series['g31'][$y_11t]+$series['g32'][$y_11t]+$series['g41'][$y_11t]+$series['g42'][$y_11t]+$series['g51'][$y_11t]+$series['g52'][$y_11t];
        $t14th  = $series['th1'][$y_14t]+$series['th1'][$y_13t]+$series['th1'][$y_12t]+$series['th1'][$y_11t];
        $t14ppc = $series['ppc'][$y_14t]+$series['ppc'][$y_13t]+$series['ppc'][$y_12t]+$series['ppc'][$y_11t];

        $footer = [
            '6tuoilop1'         => $series['ppc'][$y_6t] - $series['non_dis_not_enrolled'][$y_6t],
            'p_6tuoilop1'       => $series['total'][$y_6t] ? ( ($series['ppc'][$y_6t]-$series['non_dis_not_enrolled'][$y_6t]) / $series['total'][$y_6t] * 100 ) : 0,
            '11tuoith'          => $series['th1'][$y_11t],
            'p_11tuoith'        => $series['ppc'][$y_11t] ? ($series['th1'][$y_11t] / $series['ppc'][$y_11t] * 100) : 0,
            '11tuoidanghoc'     => $t11hoc,
            'p_11tuoidanghoc'   => $series['ppc'][$y_11t] ? ($t11hoc / $series['ppc'][$y_11t] * 100) : 0,
            '14tuoith'          => $t14th,
            'p_14tuoith'        => $t14ppc ? ($t14th / $t14ppc * 100) : 0,
            'total_dis_access'  => $kt_hoc,
            'p_total_dis_access'=> $kt_kn ? ($kt_hoc / $kt_kn * 100) : 0,
        ];

        return [$meta, $years, $series, $footer];
    }
     
    protected function offRoutePrimaryByAge(Request $req, int $_y1, array $_years, string $_scheme, array $_geo): array
    {
        $academic = (string)$req->input('year');
        [$y1, $y2] = array_map('intval', explode('-', $academic));
        $years = [];
        for ($age=6; $age<=14; $age++) $years[] = $y1 - $age;
        rsort($years);

        $scheme = (string)$req->input('scheme', 'old');
        $geo    = (array)$req->input('geo', []);
        $pid = (int)($geo['province_id'] ?? 0);
        $did = (int)($geo['district_id'] ?? 0);
        $wid = (int)($geo['ward_id'] ?? 0);

        // "Trái tuyến" = trường KHÔNG nằm trong địa bàn form
        if ($scheme === 'old') {
            if ($wid) {
                $offExpr = "s.old_ward_id IS NOT NULL AND s.old_ward_id <> v.old_ward_id";
            } elseif ($did) {
                $offExpr = "s.old_district_id IS NOT NULL AND s.old_district_id <> v.old_district_id";
            } else {
                $offExpr = "s.old_province_id IS NOT NULL AND s.old_province_id <> v.old_province_id";
            }
        } else {
            if ($wid) {
                $offExpr = "s.new_ward_id IS NOT NULL AND s.new_ward_id <> v.new_ward_id";
            } else {
                $offExpr = "s.new_province_id IS NOT NULL AND s.new_province_id <> v.new_province_id";
            }
        }

        $notPreschool = "LOWER(COALESCE(v.education_block_code,'')) NOT REGEXP '(tháng|tuổi|m[ăa]m\\s*non|mẫu\\s*giáo|nhà\\s*trẻ)'";
        $g = fn(int $n) => "( {$notPreschool} AND v.education_block_code REGEXP '(^|[^0-9]){$n}([^0-9]|$)' )";

        $qb = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', function($j) {
                $j->on('s.code', '=', 'v.school_code')
                  ->where(function($w) {
                      $w->whereNull('s.is_dissolved')->orWhere('s.is_dissolved', false);
                  });
            })
            ->where('v.academic_year', $academic)
            ->whereIn('v.dob_year', $years);

        if ($scheme === 'old') {
            if ($pid) $qb->where('v.old_province_id', $pid);
            if ($did) $qb->where('v.old_district_id', $did);
            if ($wid) $qb->where('v.old_ward_id', $wid);
        } else {
            if ($pid) $qb->where('v.new_province_id', $pid);
            if ($wid) $qb->where('v.new_ward_id', $wid);
        }

        $rows = $qb->selectRaw("
            v.dob_year as y,
            SUM(CASE WHEN {$g(1)} AND {$offExpr} THEN 1 ELSE 0 END) AS off_g1,
            SUM(CASE WHEN {$g(2)} AND {$offExpr} THEN 1 ELSE 0 END) AS off_g2,
            SUM(CASE WHEN {$g(3)} AND {$offExpr} THEN 1 ELSE 0 END) AS off_g3,
            SUM(CASE WHEN {$g(4)} AND {$offExpr} THEN 1 ELSE 0 END) AS off_g4,
            SUM(CASE WHEN {$g(5)} AND {$offExpr} THEN 1 ELSE 0 END) AS off_g5,
            SUM(CASE WHEN UPPER(TRIM(v.graduation_level_name))='TH' AND {$offExpr} THEN 1 ELSE 0 END) AS off_th,

            -- Lưu ban & bỏ học (đánh dấu qua class_label chứa '!' và bo_hoc_lop)
            SUM(CASE WHEN v.bo_hoc_lop IS NULL AND v.education_grade_name LIKE '%!%' AND {$offExpr} THEN 1 ELSE 0 END) AS off_repeat,
            SUM(CASE WHEN v.bo_hoc_lop IS NOT NULL AND {$offExpr} THEN 1 ELSE 0 END) AS off_quit
        ")->groupBy('v.dob_year')->get();

        $series = [
            'off_g1'=>array_fill_keys($years,0),'off_g2'=>array_fill_keys($years,0),
            'off_g3'=>array_fill_keys($years,0),'off_g4'=>array_fill_keys($years,0),
            'off_g5'=>array_fill_keys($years,0),'off_th'=>array_fill_keys($years,0),
            'off_repeat'=>array_fill_keys($years,0),'off_quit'=>array_fill_keys($years,0),
        ];
        foreach ($rows as $r) {
            $y=(int)$r->y; if(!isset($series['off_g1'][$y])) continue;
            $series['off_g1'][$y]=(int)$r->off_g1;
            $series['off_g2'][$y]=(int)$r->off_g2;
            $series['off_g3'][$y]=(int)$r->off_g3;
            $series['off_g4'][$y]=(int)$r->off_g4;
            $series['off_g5'][$y]=(int)$r->off_g5;
            $series['off_th'][$y]=(int)$r->off_th;
            $series['off_repeat'][$y]=(int)$r->off_repeat;
            $series['off_quit'][$y]=(int)$r->off_quit;
        }

        $meta = [
            'year1'=>$y1,'scheme'=>$scheme,'geo'=>$geo,
            'dateLine'=>'Ngày '.date('d').' tháng '.date('m').' năm '.date('Y'),
        ];

        return [$meta, $years, $series];
    }
    protected function fillSheet(Worksheet $sh, array $meta, array $years, array $series, array $footer, string $scheme): void
    {
            // ===== Header địa bàn + ngày in =====
        // Các ô dưới đây khớp ảnh bạn gửi; sửa cell nếu template khác.
        $sh->setCellValue('D2', $meta['province']);          // Tỉnh/Thành
        // $sh->setCellValue('D3', $meta['district']);          // Quận/Huyện
        $sh->setCellValue('D3', $meta['ward']);              // Phường/Xã
        $sh->setCellValue('G4',  $this->formatService->top_date());  
        $sh->getStyle('G4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        // Dòng ngày in ngay dưới tiêu đề (ô ví dụ trong ảnh)
        $sh->setCellValue('G44',NumberFormatHelper::format($footer['6tuoilop1'],0)) ;
        $sh->setCellValue('G45',NumberFormatHelper::format( $footer['11tuoith'],0));
        $sh->setCellValue('G46',NumberFormatHelper::format( $footer['11tuoidanghoc'],0));
        $sh->setCellValue('G47',NumberFormatHelper::format( $footer['14tuoith'],0));
        $sh->setCellValue('G48',NumberFormatHelper::format( $footer['total_dis_access'],0));
        $sh->setCellValue('H44', NumberFormatHelper::format($footer['p_6tuoilop1']));
        $sh->setCellValue('H45', NumberFormatHelper::format($footer['p_11tuoith']));
        $sh->setCellValue('H46', NumberFormatHelper::format($footer['p_11tuoidanghoc']));
        $sh->setCellValue('H47',NumberFormatHelper::format( $footer['p_14tuoith']));
        $sh->setCellValue('H48', NumberFormatHelper::format($footer['p_total_dis_access']));
         
        $wardname =  $meta['ward'];
        $sh->setCellValue('N43',  $this->formatService->end_date( $wardname));
         
        //setfoot
        // ===== Xác định cột của từng năm trong template =====
        // Hàng tiêu đề năm sinh (xem ảnh), mình chọn row 7; nếu khác chỉ cần đổi số hàng.
        $headerRow = 7;
        $yearToCol = $this->fillYearHeadersFromArray($sh, $headerRow, $years);

        // ===== Map vị trí hàng cho các chỉ tiêu =====
        // Cứ giữ đúng như mẫu của bạn (dựa ảnh: “Tổng số” ngay sau dòng “Độ tuổi”).
       $rowIndex = [
            'total'          => 9,   // “Tổng số”
            'female'         => 10,  // “Nữ”
            'minority'       => 11,  // “Dân tộc”
            'disabled'       => 12,  // “Khuyết tật / Tổng số”
            'dis_can_learn'  => 13,  // “Có khả năng HT”
            'dis_access'     => 14,  // “Được tiếp cận GD”
            'ppc'     => 15, 
            // “Đã và đang học tiểu học” – phần lớp
         
            'g11'             => 16,  // Lớp 1 (ô “Số PPC”)
            'g12'             => 17,  // Lớp 2
            'off_g1' => 18,
            'g21'             => 19,  // Lớp 2
            'g22'             => 20,  // Lớp 2
            'off_g2'=>21,
            'g31'             => 22,  // Lớp 3
            'g32'             => 23,  // Lớp 3
            'off_g3'=>24,
            'g41'             => 25,  // Lớp 4
            'g42'             => 26,  // Lớp 4
            'off_g4'=>27,
            'g51'             => 28,  // Lớp 5
             'g52'             => 29,  // Lớp 5
             'off_g5'=>30,
            'th1'                => 31,  // Lớp 5
            'th2'                => 32,  // Lớp 5
                'off_th'=>33,
              'thk'                => 34,  // Lớp 5
             'repeat_same_ward'                => 35,  // Lớp 5
            'repeat_other_ward'                => 36,  // Lớp 5
             'off_repeat'=>37,
              
              
             'quit_same_ward'                => 38,  // Lớp 5
               'quit_same_ward'                => 39,  // Lớp 5
               'off_quit'=>40,
               'non_dis_not_enrolled'=>41,
               
        ];


        
        // Nếu template của bạn khác hàng — chỉ cần sửa các số trên.

        // ===== Ghi từng dãy số =====
        foreach ($rowIndex as $key => $row) {

            $this->fillOneRowSeries($sh, $row, $series[$key] ?? [], $yearToCol);
        }

        // ===== Tự cộng “Tổng (6–10)” và “Tổng (11–14)” nếu template KHÔNG có sẵn công thức
        $sumColsLeft  = $this->colsForAgeRange($yearToCol, $meta['year1'], 6, 10);   // 6..10
        $sumColsRight = $this->colsForAgeRange($yearToCol, $meta['year1'], 11, 14);  // 11..14

        foreach ($rowIndex as $key => $row) {
            if ($sumColsLeft) {
                $sh->setCellValueByColumnAndRow(
                    $this->colIndexFromMap($yearToCol, $meta['year1']-10) + 1, $row,
                    sprintf('=SUM(%s%d:%s%d)',
                        $sumColsLeft[0],  $row,
                        end($sumColsLeft), $row
                    )
                );
            }
            if ($sumColsRight) {
                $rightTotalColIdx = $this->colIndexFromMap($yearToCol, $meta['year1'] - 14) + 1; // O -> P
                    $sh->setCellValueByColumnAndRow(
                        $rightTotalColIdx, $row,
                        sprintf('=SUM(%s%d:%s%d)', $sumColsRight[0], $row, end($sumColsRight), $row)
                    );
            }
        }
    }
}
