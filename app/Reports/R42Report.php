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

class R42Report extends BaseReport
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

        if (method_exists(Calculation::getInstance(), 'setCalculationEnabled')) {
            Calculation::getInstance()->setCalculationEnabled(false);
        }

        // ===== 2) Template
        $cfg = [
            'path'      => resource_path('excel/4.2.THPT.KetQuaHuyDong.xlsx'),
            'header'    => ['province' => 'C2', 'ward' => 'C3'],
            'dateTop'   => 'G4',
            'startRow'  => 10,      // dòng đầu dữ liệu (xem ảnh template)
            'sumRow'    => 11,      // dòng “Tổng cộng” có sẵn trong template
            'lastCol'   => 'T',     // cột cuối cùng trong mẫu
        ];
        $wb = IOFactory::load($cfg['path']);
        $sh = $wb->getActiveSheet();

        // Header
        $sh->setCellValue($cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        $sh->setCellValue($cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
        $sh->setCellValue($cfg['dateTop'],            $this->formatService->top_date());

        // ===== 3) Dữ liệu
        $rows = $this->queryThptMobilizationByWard($academic, $scheme, $geo);

        // chèn đủ dòng
        $startRow = $cfg['startRow'];
        $sumRow   = $cfg['sumRow'];
        $need     = max(0, count($rows) - 1);
        if ($need > 0) {
            $sh->insertNewRowBefore($sumRow, $need);
            for ($i=1; $i<=$need; $i++) {
                $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $cfg['lastCol']);
            }
            $sumRow += $need;   // rất quan trọng: tránh “thêm 1 dòng rỗng sau Tổng cộng”
        }

        // ===== 4) Ghi dòng dữ liệu
        $r = $startRow; $stt = 1;
        foreach ($rows as $it) {
            // STT
            $this->setCellValue($sh, 'A'.$r, $stt);

            // B–C gộp tên đơn vị
            $this->safeMerge($sh, "B{$r}:C{$r}");
            $this->setCellValue($sh, 'B'.$r, $it['unit_name']);

            // D .. L
            $this->setCellValue($sh, 'D'.$r, $it['total_15_21']);
            $this->setCellValue($sh, 'E'.$r, $it['total_15_18']);
            $this->setCellValue($sh, 'F'.$r, $it['tn_thcs_15_18']);
            $this->setCellValue($sh, 'G'.$r, sprintf('=IF(E%d=0,"",ROUND(F%d/E%d*100,2))',$r,$r,$r));
            $this->setCellValue($sh, 'H'.$r, $it['still_thcs_15_18']);
            $this->setCellValue($sh, 'I'.$r, $it['drop_thcs_15_18']);
            $this->setCellValue($sh, 'J'.$r, $it['g9_last']);
            $this->setCellValue($sh, 'K'.$r, $it['grad_thcs_last']);
            $this->setCellValue($sh, 'L'.$r, sprintf('=IF(J%d=0,"",ROUND(K%d/J%d*100,2))',$r,$r,$r));

            // M .. S
            $this->setCellValue($sh, 'M'.$r, $it['now_thpt10']);
            $this->setCellValue($sh, 'N'.$r, $it['now_gdtx10']);
            $this->setCellValue($sh, 'O'.$r, $it['now_tccn']);
            $this->setCellValue($sh, 'P'.$r, $it['now_voc']);
            // Q = (M+N+O+P)/K
            $this->setCellValue($sh, 'Q'.$r, sprintf('=IF(K%d=0,"",ROUND((O%d)/K%d*100,2))',$r, $r,$r));
            $this->setCellValue($sh, 'R'.$r, sprintf('=IF(K%d=0,"",ROUND((P%d)/K%d*100,2))', $r,$r,$r));
            $this->setCellValue($sh, 'S'.$r, sprintf('=IF(K%d=0,"",ROUND((M%d+N%d+O%d+P%d)/K%d*100,2))', $r,$r,$r,$r,$r,$r));
    
            // R: để trống (template có thêm các % con thì bạn thêm công thức nếu cần)
            // S: đánh giá – để trống theo yêu cầu

            $r++; $stt++;
        }

        // ===== 5) Dòng Tổng cộng
        $this->safeMerge($sh, "B{$sumRow}:C{$sumRow}");
        $this->setCellValue($sh, "B{$sumRow}", "Tổng cộng");
        foreach (['D','E','F','H','I','J','K','M','N','O','P'] as $col) {
            $this->setCellValue($sh, $col.$sumRow, sprintf('=SUM(%s%d:%s%d)', $col, $startRow, $col, $r-1));
        }
        // G, L, Q là tỷ lệ
        $this->setCellValue($sh, 'G'.$sumRow, sprintf('=IF(E%d=0,"",ROUND(F%d/E%d*100,2))',$sumRow,$sumRow,$sumRow));
        $this->setCellValue($sh, 'L'.$sumRow, sprintf('=IF(J%d=0,"",ROUND(K%d/J%d*100,2))',$sumRow,$sumRow,$sumRow));
        $this->setCellValue($sh, 'Q'.$sumRow, sprintf('=IF(K%d=0,"",ROUND((M%d+N%d+O%d+P%d)/K%d*100,2))',$sumRow,$sumRow,$sumRow,$sumRow,$sumRow,$sumRow));
        $this->setCellValue($sh, 'R'.$sumRow, sprintf('=IF(K%d=0,"",ROUND((M%d+N%d+O%d+P%d)/K%d*100,2))',$sumRow,$sumRow,$sumRow,$sumRow,$sumRow,$sumRow));
        $this->setCellValue($sh, 'S'.$sumRow, sprintf('=IF(K%d=0,"",ROUND((M%d+N%d+O%d+P%d)/K%d*100,2))',$sumRow,$sumRow,$sumRow,$sumRow,$sumRow,$sumRow));

        // ===== 6) Định dạng
        $this->drawThinBorders($sh, $startRow, $sumRow, 'A', $cfg['lastCol']);
        $sh->getStyle("A{$startRow}:{$cfg['lastCol']}{$sumRow}")
        ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sh->getStyle("A{$startRow}:A{$sumRow}")
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("D{$startRow}:P{$sumRow}")
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("B{$startRow}:B{$sumRow}")
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['G','L','Q','R','S'] as $pct) {
            $sh->getStyle("{$pct}{$startRow}:{$pct}{$sumRow}")
            ->getNumberFormat()->setFormatCode('0.00');
        }
        $sh->getStyle("D{$startRow}:R".($sumRow-1))
         ->getNumberFormat()->setFormatCode('0;-0;;@'); // zero section rỗng

    // Ẩn số 0 cho 3 ô phần trăm (hiển thị 0.00% nếu muốn rỗng thì giữ null)
    // $sh->getStyle('M23:M25')
    // ->getNumberFormat()->setFormatCode('0.00%;-0.00%;;@'); // zero section rỗng

        // Footer ngày tháng
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
            $w = IOFactory::createWriter($wb, 'Xlsx');
            $w->setPreCalculateFormulas(false);
            $w->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
    protected function queryThptMobilizationByWard(string $academic, string $scheme, array $geo): array
    {
        [$y1] = array_map('intval', explode('-', $academic));
        $yrs_15_21 = range($y1-21, $y1-15);
        $yrs_15_18 = range($y1-18, $y1-15);
        // dd($yrs_15_18 );
        $yrs_15_19 = range($y1-19, $y1-15);

        $lastAcademic = ($y1 - 1) . '-' . $y1;

        // loại trường
        $TYPE_GDTX = [3, 11];     // GDTX/BT
        $TYPE_VOC  = [1];         // Trường dạy nghề
        $TYPE_TCCN  = [12];  
        $TN  = ['THCS','THPT'];   
        $inGdtx = implode(',', $TYPE_GDTX);
        $inVoc  = implode(',', $TYPE_VOC);
        $inTCCN  = implode(',', $TYPE_TCCN);
        $inTN  = "'THCS','THPT'";
        // Nhóm năm trước (để lấy HS lớp 9 và TN THCS năm trước)
       
            
     $last = DB::table('v_survey_detail_flat as v')
    ->leftJoin('person_educations as pet', 'pet.person_id', '=', 'v.person_id')
   
    ->where('v.academic_year', $lastAcademic)
    ->select(
        'v.person_id', 

          DB::raw("MAX(CASE
                   WHEN CAST(v.education_block_code AS UNSIGNED)=9
                     
                   THEN 1 ELSE 0 END) AS is_g9"),
        DB::raw("MAX(CASE
                   WHEN
                    pet.graduation_level_id < 4  AND 
                   pet.graduation_year = '$lastAcademic'
                   THEN 1 ELSE 0 END) AS grad_thcs_last"),
    )
    ->groupBy('v.person_id');
    
    // dd($last);
    
        // $last = DB::table('v_survey_detail_flat as v')
        //     ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
        //     ->leftJoin('person_education as pe1', 's.person_id', '=', 'pe1.education')
        //     ->select(
        //         'v.person_id',
        //         DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=9 THEN 1 ELSE 0 END) AS is_g9"),
        //         DB::raw("MAX(CASE WHEN UPPER(v.graduation_level_name)='THCS' THEN 1 ELSE 0 END) AS grad_thcs_last")
        //     )
        //     ->where('v.academic_year', $lastAcademic)
        //     ->groupBy('v.person_id');

        // Trạng thái năm hiện tại (đi đâu sau TN THCS)
        $nowDest = DB::table('v_survey_detail_flat as v')
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
                DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=10 AND 
                    (s.school_type_id IS NULL OR s.school_type_id NOT IN ($inGdtx)) THEN 1 ELSE 0 END) AS now_thpt10"),
                DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=10 AND 
                s.school_type_id IN ($inGdtx) THEN 1 ELSE 0 END) AS now_gdtx10"),
                // TCCN (trung cấp)
                DB::raw("MAX(CASE WHEN s.school_type_id IN ($inTCCN) THEN 1 ELSE 0 END) AS now_tccn"),
                // Cơ sở dạy nghề (bất kỳ)
                DB::raw("MAX(CASE WHEN s.school_type_id IN ($inVoc) THEN 1 ELSE 0 END) AS now_voc")
            )
            ->where('v.academic_year', $academic)
            ->groupBy('v.person_id');

        // Chọn cột địa bàn theo schema
        $unitIdCol   = $scheme === 'old' ? 'v.old_ward_id'  : 'v.new_ward_id';
        $unitNameCol = $scheme === 'old' ? 'v.old_ward_name': 'v.new_ward_name';

        // Tập hiện tại (để lọc tuổi + gom theo xã)
        $cur = DB::table('v_survey_detail_flat as v')
            ->leftJoinSub($last,    'ly', fn($j) => $j->on('ly.person_id','=','v.person_id'))
            ->leftJoinSub($nowDest, 'dn', fn($j) => $j->on('dn.person_id','=','v.person_id'))
            ->where('v.academic_year', $academic);

        // Lọc địa bàn theo yêu cầu
        if (($geo['province_id'] ?? null) || ($geo['ward_id'] ?? null)) {
            if ($scheme === 'old') {
                if (!empty($geo['province_id'])) $cur->where('v.old_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $cur->where('v.old_ward_id',     $geo['ward_id']);
            } else {
                if (!empty($geo['province_id'])) $cur->where('v.new_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $cur->where('v.new_ward_id',     $geo['ward_id']);
            }
        }
    $movedOutCond = "UPPER(TRIM(v.sc_name)) IN ('CHẾT', 'CHUYỂN ĐI','CHUYEN DI','MOVED_OUT')";
    // điều kiện “khuyết tật” (bất kỳ cột nào = 1)
    $disabledCond = "("
        . "COALESCE(v.dis_VAN_DONG,0)+COALESCE(v.dis_NGHE_NOI,0)+COALESCE(v.dis_NHIN,0)"
        . "+COALESCE(v.dis_THAN_KINH,0)+COALESCE(v.dis_TRI_TUE,0)+COALESCE(v.dis_HOC_TAP,0)"
        . "+COALESCE(v.dis_TU_KY,0)+COALESCE(v.dis_KHAC,0)"
        . ") > 0";

        $wardNameCol = ($scheme === 'old')
            ? "COALESCE(v.old_ward_name, v.new_ward_name)"
            : "COALESCE(v.new_ward_name, v.old_ward_name)";

        // Gom theo xã/phường
        $rows = $cur->selectRaw("
                     v.village_id            AS unit_id,
                     COALESCE(NULLIF(TRIM(v.village_name), ''), NULLIF(TRIM({$wardNameCol}), ''), 'Không rõ') AS unit_name,

                    -- D: tổng 15-21
                    SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_21).") THEN 1 ELSE 0 END) AS total_15_21,

                    -- E: tổng 15-18
                    SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).") THEN 1 ELSE 0 END) AS total_15_18,

                    -- F: số TN THCS trong 15-18 (đã TN THCS ở bất kỳ năm nào)
                    SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).")
                            AND UPPER(v.graduation_level_name) in ( $inTN) THEN 1 ELSE 0 END) AS tn_thcs_15_18,

                    -- H: còn học THCS (đang 6..9) trong 15-18
                    SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).")
                            AND CAST(v.education_block_code AS UNSIGNED) BETWEEN 6 AND 9
                            THEN 1 ELSE 0 END) AS still_thcs_15_18,

                    -- I: bỏ học THCS trong 15-18 (bỏ ở lớp 6..9)
                    SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).")
                            AND CAST(COALESCE(v.bo_hoc_lop,'0') AS UNSIGNED) BETWEEN 6 AND 12
                            THEN 1 ELSE 0 END) AS drop_thcs_15_18,

                    -- J: HS lớp 9 năm học trước (trên tập tuổi 15-18 hiện tại)
                    SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).") AND COALESCE(ly.is_g9,0)=1 THEN 1 ELSE 0 END) AS g9_last,

                    -- K: TN THCS năm học trước (trên tập tuổi 15-18 hiện tại)
                    SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).") AND COALESCE(ly.grad_thcs_last,0)=1 THEN 1 ELSE 0 END) AS grad_thcs_last,

                    -- M..P: điểm đến năm hiện tại cho nhóm tuổi 15-19 và có TN THCS năm trước
                    SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).")
                           
                            AND COALESCE(dn.now_thpt10,0)=1 THEN 1 ELSE 0 END) AS now_thpt10,

                    SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).")
                         
                            AND COALESCE(dn.now_gdtx10,0)=1 THEN 1 ELSE 0 END) AS now_gdtx10,

                    SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).")
                          
                            AND COALESCE(dn.now_tccn,0)=1 THEN 1 ELSE 0 END) AS now_tccn,

                    SUM(CASE WHEN v.dob_year IN (".implode(',', $yrs_15_18).")
                            
                            AND COALESCE(dn.now_voc,0)=1 THEN 1 ELSE 0 END) AS now_voc
                ")
                ->groupBy('unit_id','unit_name')
                ->orderBy('unit_name')
                ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'unit_id'          => (int)$r->unit_id,
                'unit_name'        => $r->unit_name,

                'total_15_21'      => (int)$r->total_15_21,
                'total_15_18'      => (int)$r->total_15_18,
                'tn_thcs_15_18'    => (int)$r->tn_thcs_15_18,
                'still_thcs_15_18' => (int)$r->still_thcs_15_18,
                'drop_thcs_15_18'  => (int)$r->drop_thcs_15_18,

                'g9_last'          => (int)$r->g9_last,
                'grad_thcs_last'   => (int)$r->grad_thcs_last,

                'now_thpt10'       => (int)$r->now_thpt10,
                'now_gdtx10'       => (int)$r->now_gdtx10,
                'now_tccn'         => (int)$r->now_tccn,
                'now_voc'          => (int)$r->now_voc,
            ];
        }
        return $out;
    }
}