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
        }, 'TH_6_14_PCGDTH_'.date('Ymd_His').'.xlsx', [
            'Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]);
    }

    /** ====== Phần dưới: bạn dán lại logic đang có, chỉ sửa chỗ gọi helper ====== */

    protected function buildDataset(Request $req, int $y1, array $years, string $scheme, array $geo): array
    {
         // Năm học -> lấy năm đầu làm mốc (giống mẫu của bạn)
        $academic = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1, $y2] = array_map('intval', explode('-', $academic)); // ví dụ 2025, 2026

        // Cột năm sinh cần thống kê: 6..14 tuổi tại NĂM y1
        $years = [];
        for ($age = 6; $age <= 14; $age++) {
            $years[] = $y1 - $age;
        }
        // muốn hiển thị giảm dần (giống ảnh: 2019..2011)
        rsort($years);

        // Địa bàn lọc
        $scheme = (string) $req->input('scheme', 'old'); // 'old'|'new'
        $geo    = (array)  $req->input('geo', []);

        // Base query: trẻ cư trú trong địa bàn, 6–14 tuổi (theo năm sinh), chưa xóa
        $academic = (string) $academic;

        $peLatestForYear = DB::table('person_educations as pe1')
            ->select(
                'pe1.*',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) AS rn')
            )
            ->where('pe1.academic_year', $academic);

 
        $qb = DB::table('survey_form_details as d')
            ->join('survey_forms as f', function($j){
                $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
            })
            ->join('people as p', 'p.id', '=', 'd.person_id')
            ->leftJoinSub($peLatestForYear, 'pe', function($j){
                $j->on('pe.person_id','=','d.person_id')->where('pe.rn', 1);
            })
            ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id') // <— thêm dòng này
            ->leftJoin('ethnicities as eth', 'eth.id', '=', 'p.ethnicity_id')
            ->leftJoin('person_disabilities as pd', 'pd.id', '=', 'd.person_disability_id')
            ->leftJoin('schools as s', 's.id', '=', 'pe.school_id')
            ->leftJoin('graduation_levels as gl', 'gl.id', '=', 'pe.graduation_level_id')

            ->where('d.is_deleted', false)
            ->whereIn('p.dob_year', $years);
        // ->whereNotNull('pe.school_id');
        $wardNotMatch = "";
        $wardMatch = "";
        // Lọc địa bàn
        if ($scheme === 'old') {
            if (!empty($geo['province_id'])) $qb->where('f.old_province_id', (int)$geo['province_id']);
            if (!empty($geo['district_id'])) $qb->where('f.old_district_id', (int)$geo['district_id']);
            if (!empty($geo['ward_id']))     $qb->where('f.old_ward_id',     (int)$geo['ward_id']);
          
           if (!empty($geo['province_id']) && empty($geo['district_id'] ))
                 $wardNotMatch  =  's.old_province_id IS NOT NULL AND f.old_ward_id IS NOT NULL AND s.old_province_id != f.old_province_id';
            if (!empty($geo['province_id']) && !empty($geo['district_id'] )  && empty($geo['ward_id'] ))
                 $wardNotMatch  =  's.old_district_id IS NOT NULL AND f.old_district_id IS NOT NULL AND s.old_district_id != f.old_district_id';
            if (!empty($geo['province_id']) && !empty($geo['district_id'] )  && !empty($geo['ward_id'] ))
                 $wardNotMatch  =  's.old_ward_id IS NOT NULL AND f.old_ward_id IS NOT NULL AND s.old_ward_id != f.old_ward_id';

           

        } else {
            if (!empty($geo['province_id'])) $qb->where('f.new_province_id', (int)$geo['province_id']);
            if (!empty($geo['ward_id']))     $qb->where('f.new_ward_id',     (int)$geo['ward_id']);
            if (!empty($geo['province_id']) && empty($geo['ward_id'] ))
                 $wardNotMatch  =  's.newprovince_id IS NOT NULL AND f.old_ward_id IS NOT NULL AND s.newprovince_id != f.newprovince_id';
            if (!empty($geo['province_id'])   && !empty($geo['ward_id'] ))
                 $wardNotMatch  =  's.old_ward_id IS NOT NULL AND f.new_ward_id IS NOT NULL AND s.new_ward_id != f.new_ward_id';

        }
        $wardMatch = str_replace('!=','=',$wardNotMatch);
        // dd(    $wardNotMatch, $wardMatch);
        $disabledExpr = "JSON_LENGTH(COALESCE(pd.disability_ids,'[]')) > 0";
       // Không khuyết tật: hoặc không có bản ghi pd, hoặc danh sách trống
       // 1) Điều kiện "không khuyết tật"
        $notDisabledExpr = "(pd.id IS NULL OR JSON_LENGTH(COALESCE(pd.disability_ids,'[]')) = 0)";
        // 2) Điều kiện "chưa đi học theo khối":
        //    - không có education_block_id, HOẶC
        //    - eb.name không nằm trong 1..6
        $notEnrolledByBlockExpr = "(pe.education_block_id IS NULL OR CAST(eb.name AS UNSIGNED) NOT BETWEEN 1 AND 12)";

        // Gom nhóm theo NĂM SINH + pivot vài thuộc tính
        // Chú ý: JSON_LENGTH(d.disability_ids) có thể khác tùy schema của bạn; chỉnh cho đúng.
       $rows = $qb->selectRaw("
            p.dob_year as y,
            COUNT(*) as total,
            SUM(CASE WHEN p.is_female = 1 THEN 1 ELSE 0 END) as female,
            SUM(CASE WHEN eth.is_minority = 1 THEN 1 ELSE 0 END) as minority,
            SUM(CASE WHEN JSON_LENGTH(COALESCE(pd.disability_ids,'[]')) > 0 THEN 1 ELSE 0 END) as disabled,
            SUM(CASE WHEN pd.can_learn = 1 THEN 1 ELSE 0 END) as dis_can_learn,
            -- 'được tiếp cận' = khuyết tật có khả năng học và có xếp khối/lớp
            SUM(CASE WHEN pd.can_learn = 1 AND pe.education_block_id IS NOT NULL THEN 1 ELSE 0 END) as dis_access,

            -- Lớp 1: trong xã (g11) / ngoài xã (g12)
            SUM(CASE WHEN CAST(eb.name AS UNSIGNED)=1 AND pe.school_id IS NOT NULL AND {$wardMatch}   AND {$notDisabledExpr}   THEN 1 ELSE 0 END) as g11,
            SUM(CASE WHEN CAST(eb.name AS UNSIGNED)=1 AND pe.school_id IS NOT NULL AND {$wardNotMatch}   AND {$notDisabledExpr}   THEN 1 ELSE 0 END) as g12,

            -- Lớp 2: trong xã (g21) / ngoài xã (g22)
            SUM(CASE WHEN CAST(eb.name AS UNSIGNED)=2 AND pe.school_id IS NOT NULL AND {$wardMatch}   AND {$notDisabledExpr}     THEN 1 ELSE 0 END) as g21,
            SUM(CASE WHEN CAST(eb.name AS UNSIGNED)=2 AND pe.school_id IS NOT NULL AND {$wardNotMatch}   AND {$notDisabledExpr}  THEN 1 ELSE 0 END) as g22,

            -- Lớp 3
            SUM(CASE WHEN CAST(eb.name AS UNSIGNED)=3 AND pe.school_id IS NOT NULL AND {$wardMatch}    AND {$notDisabledExpr}    THEN 1 ELSE 0 END) as g31,
            SUM(CASE WHEN CAST(eb.name AS UNSIGNED)=3 AND pe.school_id IS NOT NULL AND {$wardNotMatch}   AND {$notDisabledExpr}   THEN 1 ELSE 0 END) as g32,

            -- Lớp 4
            SUM(CASE WHEN CAST(eb.name AS UNSIGNED)=4 AND pe.school_id IS NOT NULL AND {$wardMatch}    AND {$notDisabledExpr}    THEN 1 ELSE 0 END) as g41,
            SUM(CASE WHEN CAST(eb.name AS UNSIGNED)=4 AND pe.school_id IS NOT NULL AND {$wardNotMatch}  AND {$notDisabledExpr}  THEN 1 ELSE 0 END) as g42,

            -- Lớp 5
            SUM(CASE WHEN CAST(eb.name AS UNSIGNED)=5 AND pe.school_id IS NOT NULL AND {$wardMatch}    AND {$notDisabledExpr}    THEN 1 ELSE 0 END) as g51,
            SUM(CASE WHEN CAST(eb.name AS UNSIGNED)=5 AND pe.school_id IS NOT NULL AND {$wardNotMatch}   AND {$notDisabledExpr}   THEN 1 ELSE 0 END) as g52,
       
            -- Số em TỐT NGHIỆP TIỂU HỌC và đang/đã học tại TRƯỜNG thuộc CÙNG XÃ/PHƯỜNG
            SUM(
                CASE WHEN UPPER(TRIM(gl.name)) = 'TH'
                    AND pe.school_id IS NOT NULL
                         AND {$notDisabledExpr}
                THEN 1 ELSE 0 END
            ) as th1,
              SUM(
                CASE WHEN UPPER(TRIM(gl.name)) = 'TH'
                    AND pe.school_id IS NOT NULL  AND {$notDisabledExpr}
                    AND {$wardNotMatch}   
                THEN 1 ELSE 0 END
            ) as th2,
            -- Tốt nghiệp TH + học trong CÙNG xã/phường + KHuyết tật
            SUM(
                CASE WHEN UPPER(TRIM(gl.name)) = 'TH'
                    AND pe.school_id IS NOT NULL
                    AND {$wardMatch}
                    AND {$disabledExpr}  AND {$notDisabledExpr}
                THEN 1 ELSE 0 END
            ) as thk,
            
                -- LƯU BAN (is_repeat_grade=1) theo xã/phường
                SUM(
                    CASE WHEN pe.is_repeat_grade = 1
                        AND pe.school_id IS NOT NULL  AND {$notDisabledExpr}
                        AND {$wardMatch}  
                    THEN 1 ELSE 0 END
                ) as repeat_same_ward,

                SUM(
                    CASE WHEN pe.is_repeat_grade = 1
                        AND pe.school_id IS NOT NULL  AND {$notDisabledExpr}
                        AND {$wardNotMatch}  
                    THEN 1 ELSE 0 END
                ) as repeat_other_ward,
        -- Bo hoc (bo_hoc_lop !=null) theo xã/phường
                SUM(
                    CASE WHEN pe.bo_hoc_lop IS NOT null
                        AND pe.school_id IS NOT NULL  AND {$notDisabledExpr}
                        AND {$wardMatch}   
                    THEN 1 ELSE 0 END
                ) as quit_same_ward,

                SUM(
                    CASE WHEN pe.bo_hoc_lop IS NOT null
                        AND pe.school_id IS NOT NULL   AND {$notDisabledExpr}
                        AND {$wardNotMatch}    THEN 1 ELSE 0 END
                    
                ) as quit_other_ward,
                SUM(
                    CASE WHEN (pe.education_block_id IS null or {$notEnrolledByBlockExpr})  and {$notDisabledExpr}
                        
                         
                    THEN 1 ELSE 0 END
                ) as non_dis_not_enrolled
                   
            
            ")->groupBy('p.dob_year')->get();

            // dd($rows);
        // Chuyển thành dãy [year=>value] cho từng chỉ tiêu cần in
        $series = [
            'total'          => array_fill_keys($years, 0),
            'female'         => array_fill_keys($years, 0),
            'minority'       => array_fill_keys($years, 0),
            'disabled'       => array_fill_keys($years, 0),
            'dis_can_learn'  => array_fill_keys($years, 0),
            'dis_access'     => array_fill_keys($years, 0),
            'g11'             => array_fill_keys($years, 0),
            'g12'             => array_fill_keys($years, 0),
            'g21'             => array_fill_keys($years, 0),
            'g22'             => array_fill_keys($years, 0),
            'g31'             => array_fill_keys($years, 0),
            'g32'             => array_fill_keys($years, 0),
            'g41'             => array_fill_keys($years, 0),
            'g42'             => array_fill_keys($years, 0),
            'g51'             => array_fill_keys($years, 0),
            'g52'             => array_fill_keys($years, 0),
            'th1'             => array_fill_keys($years, 0),
            // 'th2'             => array_fill_keys($years, 0),
             'thk'              => array_fill_keys($years, 0),
            'ppc' => array_fill_keys($years, 0), 
            'repeat_same_ward'=> array_fill_keys($years, 0),
            'repeat_other_ward'=> array_fill_keys($years, 0),
            'quit_same_ward'=> array_fill_keys($years, 0),
            'quit_other_ward'=> array_fill_keys($years, 0),
            'non_dis_not_enrolled'=> array_fill_keys($years, 0),
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
             $series['ppc'][$y]            =  $series['total'][$y]  -  $series['disabled'][$y]   ;
            $series['g11'][$y]            = (int)$r->g11;
            $series['g12'][$y]            = (int)$r->g12;
            $series['g21'][$y]            = (int)$r->g21;
            $series['g22'][$y]            = (int)$r->g22;
            $series['g31'][$y]            = (int)$r->g31;
            $series['g32'][$y]            = (int)$r->g32;
            $series['g41'][$y]            = (int)$r->g41;
            $series['g42'][$y]            = (int)$r->g42;
            $series['g51'][$y]            = (int)$r->g51;
            $series['g52'][$y]            = (int)$r->g52;
            $series['th1'][$y]            = (int)$r->th1;
            // $series['th2'][$y]            = (int)$r->th2;
            $series['thk'][$y]            = (int)$r->thk;
            $series['repeat_same_ward'][$y]            = (int)$r->repeat_same_ward;
            $series['repeat_other_ward'][$y]            = (int)$r->repeat_other_ward;
            $series['quit_same_ward'][$y]            = (int)$r->quit_same_ward;
            $series['quit_other_ward'][$y]            = (int)$r->quit_other_ward;
            $series['non_dis_not_enrolled'][$y]            = (int)$r->non_dis_not_enrolled;
        }
        // dd($series);
        // Meta header (địa bàn)
        // -> dùng lại metaFromCollection của bạn cũng được; ở đây map tối giản.
        $meta = [
            'year1'    => $y1,
            'dateLine' => 'Ngày '.date('d').' tháng '.date('m').' năm '.date('Y'),
            'province' => $this->geoName('province_id', $geo, $rows),
            'district' => !empty($geo['district_id'] ?? null)
              ? $this->geoName('district_id', $geo, $rows)
              : null,
            'ward'     => $this->geoName('ward_id', $geo, $rows),
        ];
        $y_6t = $years[0];
        $y_11t = $years[5];
         $y_12t = $years[6];
          $y_13t = $years[7];
        $y_14t = $years[8];
        $kt_hoc = 0;
        $kt_kn = 0;
        foreach($years as $y)
        {
            $kt_hoc += $series['dis_access'][$y];
            $kt_kn += $series['dis_can_learn'][$y];
        }
        $t11tuoidanghoc =  $series['g11'][ $y_11t] +  $series['g12'][ $y_11t]+  $series['g21'][ $y_11t]+  $series['g22'][ $y_11t]+  $series['g31'][ $y_11t]+  $series['g32'][ $y_11t]+  $series['g41'][ $y_11t] +  $series['g42'][ $y_11t]+  $series['g51'][ $y_11t] +  $series['g52'][ $y_11t];
        $t14tuoitieuhoc =$series['th1'][ $y_14t] +  $series['th1'][ $y_13t]+  $series['th1'][ $y_12t]+  $series['th1'][ $y_11t];
        $t14 =$series['ppc'][ $y_14t] +  $series['ppc'][ $y_13t]+  $series['ppc'][ $y_12t]+  $series['ppc'][ $y_11t];
        $footer =[
            '6tuoilop1' => $series['ppc'][ $y_6t] - $series['non_dis_not_enrolled'][ $y_6t],
            'p_6tuoilop1' => ( $series['ppc'][ $y_6t] - $series['non_dis_not_enrolled'][ $y_6t])/ $series['total'][$y_6t] *100 ,
            // '11tuoith' => $series['th1'][ $y_11t] +  $series['th2'][ $y_11t],
            '11tuoith' => $series['th1'][ $y_11t] ,
            // 'p_11tuoith' => ($series['th1'][ $y_11t] +  $series['th2'][ $y_11t])/($series['ppc'][ $y_11t])  * 100,
             'p_11tuoith' => ($series['th1'][ $y_11t] )/($series['ppc'][ $y_11t])  * 100,
            '11tuoidanghoc' =>  $t11tuoidanghoc ,
            'p_11tuoidanghoc' =>  $t11tuoidanghoc /$series['ppc'][ $y_11t] * 100,
            '14tuoith' => $t14tuoitieuhoc ,
            'p_14tuoith' => $t14tuoitieuhoc/$t14 *100,
             'total_dis_access' => $kt_hoc,
               'p_total_dis_access' => $kt_hoc/$kt_kn*100,
        ];
        
        return [$meta, $years, $series, $footer];
    }
     
    protected function offRoutePrimaryByAge(Request $req, int $y1, array $years, string $scheme, array $geo): array
    {
          // 1) Năm học và dải năm sinh 6..14
        $academic = (string) $req->input('year');
        [$y1, $y2] = array_map('intval', explode('-', $academic));
        $years = [];
        for ($age = 6; $age <= 14; $age++) $years[] = $y1 - $age;
        rsort($years);

        // 2) Đầu vào địa bàn
        $scheme = (string) $req->input('scheme', 'old'); // 'old'|'new'
        $geo    = (array)  $req->input('geo', []);
        $pid = (int)($geo['province_id'] ?? 0);
        $did = (int)($geo['district_id'] ?? 0); // chỉ scheme old
        $wid = (int)($geo['ward_id'] ?? 0);

        // 3) Xác định cấp so sánh & biểu thức "trái tuyến" (ID TRƯỜNG khác ID đang thống kê)
        if ($scheme === 'old') {
            if ($wid) {
                $offExpr = "s.old_ward_id IS NOT NULL AND s.old_ward_id = {$wid}";
            } elseif ($did) {
                $offExpr = "s.old_district_id IS NOT NULL AND s.old_district_id = {$did}";
            } else {
                $offExpr = "s.old_province_id IS NOT NULL AND s.old_province_id = {$pid}";
            }
        } else { // new
            if ($wid) {
                $offExpr = "s.new_ward_id IS NOT NULL AND s.new_ward_id = {$wid}";
            } else {
                $offExpr = "s.new_province_id IS NOT NULL AND s.new_province_id = {$pid}";
            }
        }

        // 4) Nhận diện lớp 1..5 bằng REGEXP, đồng thời loại tên mầm non
        $notPreschoolName = "LOWER(eb.name) NOT REGEXP '(tháng|tuổi|m[ăa]m\\s*non|mẫu\\s*giáo|nhà\\s*trẻ)'";
        $g = function (int $n) use ($notPreschoolName) {
            // số n đứng độc lập (không dính 10, 11, 12...) + không phải mầm non
            return "(eb.name REGEXP '(^|[^0-9]){$n}([^0-9]|$)' AND {$notPreschoolName})";
        };

        // 5) Base query
        $qb = DB::table('students_off_track as sot')
            ->join('schools as s', 's.id', '=', 'sot.school_id')
            ->leftJoin('education_blocks as eb', 'eb.id', '=', 'sot.education_block_id')
             ->leftJoin('graduation_levels as gl', 'gl.id', '=', 'sot.graduation_level_id')
            ->where('sot.is_deleted', false)
            ->where('sot.academic_year', $academic)
            ->whereIn('sot.dob_year', $years)
            ->whereNotNull('sot.school_id')
            ->whereNotNull('sot.education_block_id');

        // 6) Select: đếm trái tuyến theo lớp, group theo năm sinh
        $rows = $qb->selectRaw("
            sot.dob_year as y,
            SUM(CASE WHEN  {$g(1)} AND {$offExpr} THEN 1 ELSE 0 END) AS off_g1,
            SUM(CASE WHEN {$g(2)} AND {$offExpr} THEN 1 ELSE 0 END) AS off_g2,
            SUM(CASE WHEN {$g(3)} AND {$offExpr} THEN 1 ELSE 0 END) AS off_g3,
            SUM(CASE WHEN {$g(4)} AND {$offExpr} THEN 1 ELSE 0 END) AS off_g4,
            SUM(CASE WHEN {$g(5)} AND {$offExpr} THEN 1 ELSE 0 END) AS off_g5,
            SUM(CASE WHEN UPPER(TRIM(gl.name)) = 'TH' AND sot.school_id IS NOT NULL  AND {$offExpr} THEN 1 ELSE 0 END) AS off_th,
              
            SUM(CASE WHEN sot.class_label like '%!%'  AND sot.school_id IS NOT NULL  AND {$offExpr} THEN 1 ELSE 0 END) AS off_repeat,
            SUM(CASE WHEN sot.bo_hoc_lop IS NOT null AND   {$offExpr} THEN 1 ELSE 0 END) AS off_quit
             ")->groupBy('sot.dob_year')->get();

			  
        // 7) Chuẩn hóa series trả về
        $series = [
            'off_g1' => array_fill_keys($years, 0),
            'off_g2' => array_fill_keys($years, 0),
            'off_g3' => array_fill_keys($years, 0),
            'off_g4' => array_fill_keys($years, 0),
            'off_g5' => array_fill_keys($years, 0),
            'off_th' => array_fill_keys($years, 0),
            'off_repeat' => array_fill_keys($years, 0),
            'off_quit' => array_fill_keys($years, 0),
        ];
        foreach ($rows as $r) {
            $y = (int)$r->y;
            if (!isset($series['off_g1'][$y])) continue;
            $series['off_g1'][$y] = (int)$r->off_g1;
            $series['off_g2'][$y] = (int)$r->off_g2;
            $series['off_g3'][$y] = (int)$r->off_g3;
            $series['off_g4'][$y] = (int)$r->off_g4;
            $series['off_g5'][$y] = (int)$r->off_g5;
            $series['off_th'][$y] = (int)$r->off_th;
            $series['off_repeat'][$y] = (int)$r->off_repeat;
            $series['off_quit'][$y] = (int)$r->off_quit;
        }

        $meta = [
            'year1'    => $y1,
            'scheme'   => $scheme,
            'geo'      => $geo,
            'dateLine' => 'Ngày '.date('d').' tháng '.date('m').' năm '.date('Y'),
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
