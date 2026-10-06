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

class R32Report extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

 public function export(Request $req)
    {
        $academic = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1]     = array_map('intval', explode('-', $academic));
        $scheme   = (string) $req->input('scheme', 'old');
        $geo      = (array)  $req->input('geo', []);

        if (method_exists(Calculation::getInstance(), 'setCalculationEnabled')) {
            Calculation::getInstance()->setCalculationEnabled(false);
        }

        // === Template ===
        $cfg = [
            'path'      => resource_path('excel/3.2.THCS.KetQuaHuyDong.xlsx'),
            'header'    => ['province' => 'C1', 'ward' => 'C2', 'year' => 'C3', 'dateTop' => 'G4'],
            'startRow'  => 11,   // dòng đầu của bảng (xem hình)
            'sumRow'    => 12,   // dòng "Tổng" trong template
            'lastCol'   => 'X',  // cột cuối (23)
        ];
        $wb = IOFactory::load($cfg['path']);
        $sh = $wb->getActiveSheet();

        // Header
        $sh->setCellValue($cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        $sh->setCellValue($cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
        $sh->setCellValue($cfg['header']['year'],     $academic);
        $sh->setCellValue($cfg['header']['dateTop'],  $this->formatService->top_date());

        // === Data ===
        $rows = $this->queryThcsByWard($academic, $scheme, $geo);

        // Chèn thêm dòng nếu cần (giữ merge/format)
        $startRow = $cfg['startRow'];
        $sumRow   = $cfg['sumRow'];
        $need     = max(0, count($rows) - 1);
        if ($need > 0) {
            $sh->insertNewRowBefore($sumRow, $need);
            for ($i = 1; $i <= $need; $i++) {
                $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $cfg['lastCol']);
            }
            $sumRow += $need;
        }

        // Ghi dữ liệu từng xã
        $r = $startRow; $stt = 1;
        // dd($rows);
        foreach ($rows as $it) {
            // STT + tên đơn vị (B–C gộp)
            $this->setCellValue($sh, 'A'.$r, $stt);
            $this->safeMerge($sh, "B{$r}:C{$r}");
            $this->setCellValue($sh, 'B'.$r, $it['unit_name']);

            // --- Trẻ 6 tuổi (D–F) ---
            $this->setCellValue($sh, 'D'.$r, $it['age6_total']);
            $this->setCellValue($sh, 'E'.$r, $it['age6_grade1']);
            $this->setCellValue($sh, 'F'.$r, sprintf('=IF(D%d=0,"",ROUND(E%d/D%d*100,2))', $r,$r,$r));

            // --- Vào lớp 6 sau TN TH năm qua (G–J) ---
            $this->setCellValue($sh, 'G'.$r, $it['grad_th_last']);
            $this->setCellValue($sh, 'H'.$r, $it['into6_pt']);
            $this->setCellValue($sh, 'I'.$r, $it['into6_gdtx']);
            $this->setCellValue($sh, 'J'.$r, sprintf('=I%d+H%d', $r,$r));
            // Mẫu số = tổng tốt nghiệp TH năm trước
            $this->setCellValue($sh, 'K'.$r, sprintf('=IF(H%d=0,"",ROUND(J%d/H%d*100,2))',$r,$r,$r));

            // --- 11–14 (K–M) ---
            $this->setCellValue($sh, 'L'.$r, $it['age11_14_total']);
            $this->setCellValue($sh, 'M'.$r, $it['age11_14_htct_th']);
            $this->setCellValue($sh, 'N'.$r, sprintf('=IF(L%d=0,"",ROUND(100*M%d/L%d,2))', $r,$r,$r));

            // --- Lớp 9 năm qua (N–Q) ---
            $this->setCellValue($sh, 'O'.$r, $it['g9_last_total']);
            $this->setCellValue($sh, 'P'.$r, $it['grad_thcs_last_pt']);
            $this->setCellValue($sh, 'Q'.$r, $it['grad_thcs_last_gdtx']);
            $this->setCellValue($sh, 'R'.$r, sprintf('=IF(O%d=0,"",ROUND((P%d+Q%d)/O%d*100,2))', $r,$r,$r,$r));

            // --- 15–18 có bằng THCS (R–V) ---
            $this->setCellValue($sh, 'S'.$r, $it['age15_18_total']);
            $this->setCellValue($sh, 'T'.$r, $it['age15_18_thcs_pt']);
            $this->setCellValue($sh, 'U'.$r, $it['age15_18_thcs_gdtx']);
            $this->setCellValue($sh, 'V'.$r, sprintf('=U%d+T%d', $r,$r));
            $this->setCellValue($sh, 'W'.$r, sprintf('=IF(S%d=0,"",ROUND(100*V%d/S%d,2))', $r,$r,$r,$r));
            $v =   $sh->getCell('W'.$r)->getCalculatedValue();
            if($v >=80) 
                $this->setCellValue($sh, 'X'.$r, 'Đạt');
            else
                 $this->setCellValue($sh, 'X'.$r, 'Chưa đạt');
           
            // W: đánh giá (để trống/hoặc tự điền quy tắc)
            

            $r++; $stt++;
        }

        // --- Dòng “Tổng” ---
        $this->safeMerge($sh, "B{$sumRow}:C{$sumRow}");
        $this->setCellValue($sh, "B{$sumRow}", "Tổng");
        foreach (['D','E','G','H','I','J','L','M', 'O','P','Q','S','T','U','V'] as $col) {
            $this->setCellValue($sh, $col.$sumRow, sprintf('=SUM(%s%d:%s%d)', $col,$startRow,$col,$r-1));
        }
        // Cộng & Tỷ lệ
        $this->setCellValue($sh, 'F'.$sumRow, sprintf('=ROUND(E%d/D%d*100,2) ', $sumRow,$sumRow));
        $this->setCellValue($sh, 'K'.$sumRow, sprintf('=ROUND(100*J%d/G%d,2) ', $sumRow,$sumRow));
        $this->setCellValue($sh, 'N'.$sumRow, sprintf('=IF(K%d=0,"",ROUND(M%d/L%d*100,2))',$sumRow,$sumRow,$sumRow));
        $this->setCellValue($sh, 'R'.$sumRow, sprintf('=IF(K%d=0,"",ROUND((P%d + Q%d)/O%d*100,2))',$sumRow,$sumRow,$sumRow,$sumRow));
        $this->setCellValue($sh, 'W'.$sumRow, sprintf('=IF(R%d=0,"",ROUND(V%d/S%d*100,2))',$sumRow,$sumRow,$sumRow));

        $lastrow = $sumRow + 5;
        $trelop1 =  $sh->getCell('E'.$sumRow)->getCalculatedValue();
        $tre6tuoi =  $sh->getCell('D'.$sumRow)->getCalculatedValue();
        $this->setCellValue($sh, 'I'. $lastrow ,  $trelop1.'/'.$tre6tuoi);
        $this->setCellValue($sh, 'K'. $lastrow ,  $trelop1/$tre6tuoi);

        $tre11_14 =  $sh->getCell('L'.$sumRow)->getCalculatedValue();
        $tre11_14_TH =  $sh->getCell('M'.$sumRow)->getCalculatedValue();
        $this->setCellValue($sh, 'I'.( $lastrow+1) ,  $tre11_14_TH.'/'.$tre11_14);
        $this->setCellValue($sh, 'K'.( $lastrow+1) ,  $tre11_14_TH/$tre11_14);

        $trelop6 =  $sh->getCell('G'.$sumRow)->getCalculatedValue();
        $trelop6_THCS =  $sh->getCell('J'.$sumRow)->getCalculatedValue();
        $this->setCellValue($sh, 'I'.( $lastrow+2) ,  $trelop6_THCS.'/'.$trelop6);
        $this->setCellValue($sh, 'K'.( $lastrow+2) ,  $trelop6_THCS/$trelop6);

         $trelop9 =  $sh->getCell('O'.$sumRow)->getCalculatedValue();
        $trelop9_THCS =  $sh->getCell('P'.$sumRow)->getCalculatedValue();
        $this->setCellValue($sh, 'I'.( $lastrow+3) ,  $trelop9_THCS.'/'.$trelop9);
        // $this->setCellValue($sh, 'K'.( $lastrow+3) ,  $trelop9_THCS/$trelop9);
        

          $trelop15_18 =  $sh->getCell('S'.$sumRow)->getCalculatedValue();
        $trelop15_18_THCS =  $sh->getCell('V'.$sumRow)->getCalculatedValue();
        $this->setCellValue($sh, 'I'.( $lastrow+4) ,  $trelop15_18_THCS.'/'.$trelop15_18);
        $this->setCellValue($sh, 'K'.( $lastrow+4) ,  $trelop15_18_THCS/$trelop15_18);
        $dks = $sh->getCell('M'.($sumRow + 10))->getCalculatedValue();
        for($j = $sumRow + 5 ; $j<=$sumRow + 9 ; $j++   )
        {
            if($dks =='X') 
            {
                $v1 =   $sh->getCell('K'.$j)->getCalculatedValue();
                $v2 =   $sh->getCell('Q'.$j)->getCalculatedValue();

            }
            else
            {
                $v1 =   $sh->getCell('K'.$j)->getCalculatedValue();
                $v2 =   $sh->getCell('Q'.$j)->getCalculatedValue();
            }
            if($v1 >=$v2) 
                $this->setCellValue($sh, 'U'.$j, 'Đạt');
            else
                    $this->setCellValue($sh, 'U'.$j, 'Chưa đạt');
        }
        $sh->getStyle('K'.( $lastrow+0).':U'.( $lastrow+4))->getNumberFormat()->setFormatCode('0.00%'); // hiển thị xx.xx%
        // Định dạng
        $this->drawThinBorders($sh, $startRow, $sumRow, 'A', $cfg['lastCol']);
        $sh->getStyle("A{$startRow}:{$cfg['lastCol']}{$sumRow}")
            ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sh->getStyle("A{$startRow}:A{$sumRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("D{$startRow}:V{$sumRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Ẩn số 0 (giữ trống)
        $sh->getStyle("D{$startRow}:U".($sumRow-1))
           ->getNumberFormat()->setFormatCode('0;-0;;@');
        // % 2 chữ số thập phân cho các cột F,J,M,V
        foreach (['F','K','N','R','W'] as $pct) {
            $sh->getStyle("{$pct}{$startRow}:{$pct}{$sumRow}")
               ->getNumberFormat()->setFormatCode('0.00');
        }

        // Footer ngày tháng
        $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
        $lastRow  = $sumRow + 12;
        $this->safeMerge($sh, "N{$lastRow}:V{$lastRow}");
        $this->setCellValue($sh, 'N'.$lastRow, $this->formatService->end_date($wardName));
        $sh->getStyle("N{$lastRow}:V{$lastRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Xuất file
        return response()->streamDownload(function () use ($wb) {
            $w = IOFactory::createWriter($wb, 'Xlsx');
            $w->setPreCalculateFormulas(false);
            $w->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Gom theo xã/phường cho mẫu PCGD THCS.
     */
    protected function queryThcsByWard(string $academic, string $scheme, array $geo): array
    {
        [$y1] = array_map('intval', explode('-', $academic));
        $lastAcademic = ($y1 - 1) . '-' . $y1;

        // Các nhóm tuổi
        $y_age6      = $y1 - 6;
        $yrs_11_14   = implode(',', range($y1-14, $y1-11));
        $yrs_15_18   = implode(',', range($y1-18, $y1-15));

        // Loại trường
        $TYPE_GDTX = [3, 11];
        $inGdtx    = implode(',', $TYPE_GDTX);

        // Subquery năm học trước
        $last = DB::table('v_survey_detail_flat as v')
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
                // Học lớp 9 năm học trước
                DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=9 THEN 1 ELSE 0 END) AS g9_last"),
                 DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=9  AND (s.school_type_id NOT IN ($inGdtx))        THEN 1 ELSE 0 END) AS g9_pt_last"),
                 DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=9  AND (s.school_type_id IN ($inGdtx))        THEN 1 ELSE 0 END) AS g9_gdtx_last"),
                // TN TH năm học trước
                 )
            ->where('v.academic_year', $lastAcademic)
            ->groupBy('v.person_id');

        // Subquery năm học hiện tại (điểm đến)
        $now = DB::table('v_survey_detail_flat as v')
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
                // Lớp 1 (cho nhóm tuổi 6)
                DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=1 THEN 1 ELSE 0 END) AS now_grade1"),
                // Lớp 6 – PT & GDTX
                DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=6 AND (s.school_type_id NOT IN ($inGdtx)) THEN 1 ELSE 0 END) AS now_thcs6_pt"),
                DB::raw("MAX(CASE WHEN CAST(v.education_block_code AS UNSIGNED)=6 AND (  s.school_type_id IN ($inGdtx)) THEN 1 ELSE 0 END) AS now_thcs6_gdtx"),
                  DB::raw("MAX(CASE WHEN UPPER(v.graduation_level_name)='TH' AND v.graduation_year='$lastAcademic' THEN 1 ELSE 0 END) AS grad_th_last"),
                // TN THCS năm học trước – tách PT/GDTX
                DB::raw("MAX(CASE WHEN UPPER(v.graduation_level_name)='THCS' AND v.graduation_year='$lastAcademic'  THEN 1 ELSE 0 END) AS grad_thcs_last_pt"),
                DB::raw("MAX(CASE WHEN UPPER(v.graduation_level_name)='THCS' AND v.graduation_year='$lastAcademic'  THEN 1 ELSE 0 END) AS grad_thcs_last_gdtx")
         
            )
            ->where('v.academic_year', $academic)
            ->groupBy('v.person_id');

        // Tập hiện tại để gom xã/phường
        $cur = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', function($j) {
                $j->on('s.code', '=', 'v.school_code')
                  ->where(function($w) {
                      $w->whereNull('s.is_dissolved')
                        ->orWhere('s.is_dissolved', false)
                        ->orWhere('s.is_dissolved', 0);
                  });
            })
            ->leftJoinSub($last, 'ly', fn($j) => $j->on('ly.person_id', '=', 'v.person_id'))
            ->leftJoinSub($now,  'nw', fn($j) => $j->on('nw.person_id', '=', 'v.person_id'))
            ->where('v.academic_year', $academic);

        // Lọc địa bàn
        if (($geo['province_id'] ?? null) || ($geo['ward_id'] ?? null)) {
            if (($scheme ?? 'old') === 'old') {
                if (!empty($geo['province_id'])) $cur->where('v.old_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $cur->where('v.old_ward_id',     $geo['ward_id']);
            } else {
                if (!empty($geo['province_id'])) $cur->where('v.new_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $cur->where('v.new_ward_id',     $geo['ward_id']);
            }
        }

        // Điều kiện GDTX/không GDTX để đếm văn bằng hiện có (15–18)
        $isGdtx  = "(  (s.school_type_id IN ($inGdtx)))";
        $notGdtx = "(   (s.school_type_id IS NULL OR s.school_type_id NOT IN ($inGdtx)))";

        $wardNameCol = ($scheme === 'old')
            ? "COALESCE(v.old_ward_name, v.new_ward_name)"
            : "COALESCE(v.new_ward_name, v.old_ward_name)";

        $rows = $cur->selectRaw("
                v.village_id AS unit_id,
                COALESCE(NULLIF(TRIM(v.village_name), ''), NULLIF(TRIM({$wardNameCol}), ''), 'Không rõ') AS unit_name,

                -- Trẻ 6 tuổi
                SUM(CASE WHEN v.dob_year = {$y_age6} THEN 1 ELSE 0 END) AS age6_total,
                SUM(CASE WHEN v.dob_year = {$y_age6} AND COALESCE(nw.now_grade1,0)=1 THEN 1 ELSE 0 END) AS age6_grade1,

                -- Tổng tốt nghiệp TH năm trước (để làm mẫu số cột J)
                SUM(CASE WHEN COALESCE(nw.grad_th_last,0)=1 THEN 1 ELSE 0 END) AS grad_th_last,

                -- Vào lớp 6 năm nay sau khi TN TH năm trước (PT/GDTX)
                SUM(CASE WHEN COALESCE(nw.grad_th_last,0)=1 AND COALESCE(nw.now_thcs6_pt,0)=1   THEN 1 ELSE 0 END) AS into6_pt,
                SUM(CASE WHEN COALESCE(nw.grad_th_last,0)=1 AND COALESCE(nw.now_thcs6_gdtx,0)=1 THEN 1 ELSE 0 END) AS into6_gdtx,

                -- 11–14
                SUM(CASE WHEN v.dob_year IN ({$yrs_11_14}) THEN 1 ELSE 0 END) AS age11_14_total,
                SUM(CASE WHEN v.dob_year IN ({$yrs_11_14})
                          AND UPPER(v.graduation_level_name) IN ('TH','THCS','THPT')
                         THEN 1 ELSE 0 END) AS age11_14_htct_th,

                -- Lớp 9 năm qua
                SUM(CASE WHEN COALESCE(ly.g9_last,0)=1 THEN 1 ELSE 0 END) AS g9_last_total,
                SUM(CASE WHEN COALESCE(nw.grad_thcs_last_pt,0)=1 and COALESCE(ly.g9_pt_last,0)=1 THEN 1 ELSE 0 END) AS grad_thcs_last_pt,
                SUM(CASE WHEN COALESCE(nw.grad_thcs_last_gdtx,0)=1 and COALESCE(ly.g9_gdtx_last,0)=1 THEN 1 ELSE 0 END) AS grad_thcs_last_gdtx,

                -- 15–18 có bằng THCS (tách PT/GDTX)
                SUM(CASE WHEN v.dob_year IN ({$yrs_15_18}) THEN 1 ELSE 0 END) AS age15_18_total,
                SUM(CASE WHEN v.dob_year IN ({$yrs_15_18})
                         AND( ( UPPER(v.graduation_level_name)='THCS'  ) or  UPPER(v.graduation_level_name)='THPT')
                         THEN 1 ELSE 0 END) AS age15_18_thcs_pt,
                SUM(CASE WHEN v.dob_year IN ({$yrs_15_18})
                         AND ( ( UPPER(v.graduation_level_name)='THCS'  AND {$isGdtx}) or  UPPER(v.graduation_level_name)='THPT')
                         THEN 0 ELSE 0 END) AS age15_18_thcs_gdtx
            ")
            ->groupBy('unit_id','unit_name')
            ->orderBy('unit_name')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'unit_id'                  => (int) $r->unit_id,
                'unit_name'                => $r->unit_name,

                'age6_total'               => (int) $r->age6_total,
                'age6_grade1'              => (int) $r->age6_grade1,

                'grad_th_last'             => (int) $r->grad_th_last,
                'into6_pt'                 => (int) $r->into6_pt,
                'into6_gdtx'               => (int) $r->into6_gdtx,

                'age11_14_total'           => (int) $r->age11_14_total,
                'age11_14_htct_th'         => (int) $r->age11_14_htct_th,

                'g9_last_total'            => (int) $r->g9_last_total,
                'grad_thcs_last_pt'        => (int) $r->grad_thcs_last_pt,
                'grad_thcs_last_gdtx'      => (int) $r->grad_thcs_last_gdtx,

                'age15_18_total'           => (int) $r->age15_18_total,
                'age15_18_thcs_pt'         => (int) $r->age15_18_thcs_pt,
                'age15_18_thcs_gdtx'       => (int) $r->age15_18_thcs_gdtx,
            ];
        }
        return $out;
    }
}