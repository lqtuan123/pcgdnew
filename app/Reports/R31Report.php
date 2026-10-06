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

class R31Report extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    /* =========================================================
     * ================  PUBLIC: EXPORT TO EXCEL  ==============
     * =======================================================*/
    public function export(Request $req)
    {
        // 1) Input
        $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1]     = array_map('intval', explode('-', $academic));      // ví dụ 2024-2025 => 2024
        $scheme   = (string)$req->input('scheme', 'old');              // old/new
        $geo      = (array)$req->input('geo', []);

        // tắt calc để ghi nhanh
        if (method_exists(Calculation::getInstance(), 'setCalculationEnabled')) {
            Calculation::getInstance()->setCalculationEnabled(false);
        }

        // 2) Template
        // NOTE: chỉnh lại path theo file mẫu thực tế của bạn
        $cfg = [
            'path'      => resource_path('excel/3.1.THCS.11.18Tuoi.xlsx'),
            'header'    => ['province' => 'D2', 'ward' => 'D3'],
            'dateTop'   => 'G4',
            'lastCol'   => 'AA',

            // mapping cột năm sinh (tăng 2 cột mỗi năm như mẫu bạn chụp)
            // 2013..2006 ~ tuổi 11..18 nếu năm học là 2024-2025
            // cột đầu tiên nằm ở D, sau đó D, F, H, J, L, N, P, R ... (cứ +2)
            'yearStartCol' => 'F',
            'yearColStep'  => 2,
            'yearColStep3'  => 3,
            // các hàng chứa từng chỉ tiêu
            'row' => [
                'dob'        => 2,
                'total'      => 9,
                'female'     => 10,
                'ethnic'     => 11,     // dân tộc thiểu số
                'dis_total'  => 12,
                'dis_learn'  => 13,
                'dis_access' => 14,
                'must_pc'    => 15,

                // Khối 6..9
                'g6_local'   => 17,     // “Tại chỗ”: 3 ô PT / GDTX / GDNN
                'g6_away'    => 18,     // “Nơi khác đến”: 3 ô PT / GDTX / GDNN
                'g6_out'    => 19,
                'g7_local'   => 20,
                'g7_away'    => 21,
                 'g7_out'    => 22,
                'g8_local'   => 23,
                'g8_away'    => 24,
                'g8_out'    => 25,
                'g9_local'   => 26,
                'g9_away'    => 27,
                'g9_out'    => 28,
                'tn_local'   => 29,
                'tn_away'    => 30,
                'tn_out'    => 31,
                // “Đã và đang học CT GDPT/GDTX cấp THPT/GDNN” (cho lứa 15–18)
                'after_local'=> 32,     // tại chỗ: PT / GDTX / GDNN
                'after_away' => 33,     // nơi khác: PT / GDTX / GDNN
                'after_out'  => 34, 
                // Bỏ học (tuỳ template đang để hàng 36..)
                'drop_local' => 35,
                'drop_away'  => 36,
                 'drop_out'  => 37,
            ],
        ];
        $sumOffsets = [
            // phần đầu bảng: 1 cột/tuổi
            'total'      => [0],
            'female'     => [0],
            'ethnic'     => [0],
            'dis_total'  => [0],
            'dis_learn'  => [0],
            'dis_access' => [0],
            'must_pc'    => [0],

            // lớp 6
            'g6_local'   => [0,1],   // PT, TX (khớp code ghi của bạn)
            'g6_away'    => [0,1],

            // lớp 7
            'g7_local'   => [0,1,2], // PT, TX, GDNN
            'g7_away'    => [0,1,2],

            // lớp 8
            'g8_local'   => [0,1,2],
            'g8_away'    => [0,1,2],

            // lớp 9
            'g9_local'   => [0,1,2],
            'g9_away'    => [0,1,2],

            // tốt nghiệp THCS (tn_*)
            'tn_local'   => [0,1,2],
            'tn_away'    => [0,1,2],

            // bỏ học (drop_*)
            'drop_local' => [0,1,2],
            'drop_away'  => [0,1,2],

            // sau THCS (áp 15–18) — nếu bạn muốn tổng vào Z luôn
            'after_local'=> [0,1,2],
            'after_away' => [0,1,2],
        ];

        $wb = IOFactory::load($cfg['path']);
        $sh = $wb->getActiveSheet();

        // Header
        $provinceName = $this->geoService->geoNameFromId($geo['province_id'] ?? null);
        $wardName     = $this->geoService->geoNameFromId($geo['ward_id'] ?? null);
        $sh->setCellValue('D2', ': ' . ($provinceName ?? ''));
        $sh->setCellValue('D3', ': ' . ($wardName ?? ''));
        $sh->setCellValue($cfg['dateTop'], $this->formatService->top_date());

        // 3) Query
        $data = $this->queryTtnThcs($academic, $scheme, $geo);

        // 4) Ghi dữ liệu theo cột năm sinh
        $years = range($y1-11, $y1-18);                // 11..18 tuổi
        $col   = $cfg['yearStartCol'];

        // helper: dịch cột Excel (A..Z..AA..)
        $nextCol = function(string $c, int $delta = 1): string {
            $n=0; for ($i=0;$i<strlen($c);$i++) $n=$n*26 + (ord($c[$i])-64);
            $n+=$delta; $s=''; while($n>0){ $r=($n-1)%26; $s=chr(65+$r).$s; $n=intdiv($n-1,26); }
            return $s;
        };

        // ghi hàm, tự ẩn 0 (ghi null)
        $put = function(Worksheet $sh, string $addr, $val){
            $sh->setCellValue($addr, ($val ?? 0) ? (int)$val : null);
        };

        //   foreach ($years as $i => $yob) {
        //     $c = ($i===0) ? $col : $nextCol($col, $i * $cfg['yearColStep']);
        //     echo $c.'<br>';
        //     echo $yob.'<br>';
        //     echo $nextCol($col, $i * $cfg['yearColStep']).'<br>';
        //   }
        $cols15_18 = []; 
        $sumCells = [];
        foreach ($sumOffsets as $rowKey => $_) $sumCells[$rowKey] = [];
        $tongdihoc = 0;
        foreach ($years as $i => $yob) {
            if($i===0){
                $c = $col;
            }else{
                if($i>4){
                    $c = $nextCol($col,    $cfg['yearColStep3'] );
                }
                else
                {
                    $c = $nextCol($col,    $cfg['yearColStep'] );
                }
                
            }
           $col = $c;
$sh->setCellValue($c . '7', $yob);
           // Nếu năm sinh ứng với 15..18 tuổi: lưu lại cột để chút nữa SUM
            if ($yob >= $y1 - 18 && $yob <= $y1 - 15) {
                $cols15_18[] = $c; // ví dụ: ['F','H','J','L'] (tùy step của bạn)
            }
            // dd($data);
            // ——— phần đầu bảng ———
            $ageBlock = $data['by_yob'][$yob] ?? [];
            $tongdihoc += $ageBlock['in_pt_local'] ?? 0;
            // dd($data);
            $put($sh, $c.$cfg['row']['total'],      $ageBlock['total']      ?? 0);
            $put($sh, $c.$cfg['row']['female'],     $ageBlock['female']     ?? 0);
            $put($sh, $c.$cfg['row']['ethnic'],     $ageBlock['ethnic']     ?? 0);
            $put($sh, $c.$cfg['row']['dis_total'],  $ageBlock['dis_total']  ?? 0);
            $put($sh, $c.$cfg['row']['dis_learn'],  $ageBlock['dis_learn']  ?? 0);
            $put($sh, $c.$cfg['row']['dis_access'], $ageBlock['dis_access'] ?? 0);
            $put($sh, $c.$cfg['row']['must_pc'],    $ageBlock['must_pc']    ?? 0);

            // ——— lớp 6..9: mỗi “khối” là 3 cột liên tiếp: PT / GDTX / GDNN ———
            // G6
            // if($i == 4)
            // dd($c.$cfg['row']['g6_local'], $nextCol($c),$nextCol($c,2),$c.$cfg['row']['g6_away'],$nextCol($c),$nextCol($c,2));
            $put($sh, $c.$cfg['row']['g6_local'],   $ageBlock['g6_pt_local']   ?? 0);
            $put($sh, $nextCol($c).$cfg['row']['g6_local'],                 $ageBlock['g6_tx_local']   ?? 0);
            // $put($sh, $nextCol($c,2).$cfg['row']['g6_local'],               $ageBlock['g6_voc_local']  ?? 0);
            $put($sh, $c.$cfg['row']['g6_away'],    $ageBlock['g6_pt_away']    ?? 0);
            $put($sh, $nextCol($c).$cfg['row']['g6_away'],                 $ageBlock['g6_tx_away']    ?? 0);
            // $put($sh, $nextCol($c,2).$cfg['row']['g6_away'],               $ageBlock['g6_voc_away']   ?? 0);

            // // G7
            $put($sh, $c.$cfg['row']['g7_local'],   $ageBlock['g7_pt_local']   ?? 0);
            $put($sh, $nextCol($c).$cfg['row']['g7_local'],                 $ageBlock['g7_tx_local']   ?? 0);
            $put($sh, $nextCol($c,2).$cfg['row']['g7_local'],               $ageBlock['g7_voc_local']  ?? 0);
            $put($sh, $c.$cfg['row']['g7_away'],    $ageBlock['g7_pt_away']    ?? 0);
            $put($sh, $nextCol($c).$cfg['row']['g7_away'],                 $ageBlock['g7_tx_away']    ?? 0);
            $put($sh, $nextCol($c,2).$cfg['row']['g7_away'],               $ageBlock['g7_voc_away']   ?? 0);

            // // G8
            $put($sh, $c.$cfg['row']['g8_local'],   $ageBlock['g8_pt_local']   ?? 0);
            $put($sh, $nextCol($c).$cfg['row']['g8_local'],                 $ageBlock['g8_tx_local']   ?? 0);
            $put($sh, $nextCol($c,2).$cfg['row']['g8_local'],               $ageBlock['g8_voc_local']  ?? 0);
            $put($sh, $c.$cfg['row']['g8_away'],    $ageBlock['g8_pt_away']    ?? 0);
            $put($sh, $nextCol($c).$cfg['row']['g8_away'],                 $ageBlock['g8_tx_away']    ?? 0);
            $put($sh, $nextCol($c,2).$cfg['row']['g8_away'],               $ageBlock['g8_voc_away']   ?? 0);

            // // G9
            $put($sh, $c.$cfg['row']['g9_local'],   $ageBlock['g9_pt_local']   ?? 0);
            $put($sh, $nextCol($c).$cfg['row']['g9_local'],                 $ageBlock['g9_tx_local']   ?? 0);
            $put($sh, $nextCol($c,2).$cfg['row']['g9_local'],               $ageBlock['g9_voc_local']  ?? 0);
            $put($sh, $c.$cfg['row']['g9_away'],    $ageBlock['g9_pt_away']    ?? 0);
            $put($sh, $nextCol($c).$cfg['row']['g9_away'],                 $ageBlock['g9_tx_away']    ?? 0);
            $put($sh, $nextCol($c,2).$cfg['row']['g9_away'],               $ageBlock['g9_voc_away']   ?? 0);

             $put($sh, $c.$cfg['row']['tn_local'],   $ageBlock['tn_pt_local']   ?? 0);
            $put($sh, $nextCol($c).$cfg['row']['tn_local'],                 $ageBlock['tn_tx_local']   ?? 0);
            $put($sh, $nextCol($c,2).$cfg['row']['tn_local'],               $ageBlock['tn_voc_local']  ?? 0);
            $put($sh, $c.$cfg['row']['tn_away'],    $ageBlock['tn_pt_away']    ?? 0);
            $put($sh, $nextCol($c).$cfg['row']['tn_away'],                 $ageBlock['tn_tx_away']    ?? 0);
            $put($sh, $nextCol($c,2).$cfg['row']['tn_away'],               $ageBlock['tn_voc_away']   ?? 0);
            
            $put($sh, $c.$cfg['row']['drop_local'],   $ageBlock['drop_pt_local']   ?? 0);
            $put($sh, $nextCol($c).$cfg['row']['drop_local'],                 $ageBlock['drop_tx_local']   ?? 0);
            $put($sh, $nextCol($c,2).$cfg['row']['drop_local'],               $ageBlock['drop_voc_local']  ?? 0);
            $put($sh, $c.$cfg['row']['drop_away'],    $ageBlock['drop_pt_away']    ?? 0);
            $put($sh, $nextCol($c).$cfg['row']['drop_away'],                 $ageBlock['drop_tx_away']    ?? 0);
            $put($sh, $nextCol($c,2).$cfg['row']['drop_away'],               $ageBlock['drop_voc_away']   ?? 0);
            

            // // ——— sau THCS (áp cho 15–18 tuổi) ———
            if ($yob >= $y1-18 && $yob <= $y1-15) {
                $put($sh, $c.$cfg['row']['after_local'], $ageBlock['aft_pt_local'] ?? 0);
                $put($sh, $nextCol($c).$cfg['row']['after_local'],                  $ageBlock['aft_tx_local'] ?? 0);
                $put($sh, $nextCol($c,2).$cfg['row']['after_local'],                $ageBlock['aft_voc_local']?? 0);

                $put($sh, $c.$cfg['row']['after_away'],  $ageBlock['aft_pt_away']  ?? 0);
                $put($sh, $nextCol($c).$cfg['row']['after_away'],                  $ageBlock['aft_tx_away']  ?? 0);
                $put($sh, $nextCol($c,2).$cfg['row']['after_away'],                $ageBlock['aft_voc_away'] ?? 0);
            }

            // CHỈ gom địa chỉ để cộng khi là nhóm tuổi 15..18
            if ($yob >= $y1 - 18 && $yob <= $y1 - 15) {
                // helper trả về địa chỉ theo offset
                $addrAt = function(string $baseCol, int $offset, int $row) use ($nextCol) {
                    if ($offset === 0) return $baseCol.$row;
                    if ($offset === 1) return $nextCol($baseCol).$row;
                    return $nextCol($baseCol, 2).$row;
                };

                // phần đầu bảng (1 cột/tuổi)
                foreach (['total','female','ethnic','dis_total','dis_learn','dis_access','must_pc'] as $rk) {
                    foreach ($sumOffsets[$rk] as $off) {
                        $sumCells[$rk][] = $addrAt($c, $off, $cfg['row'][$rk]);
                    }
                }

                // lớp 6..9, tn, drop, after_*
                foreach ([
                    'g6_local','g6_away',
                    'g7_local','g7_away',
                    'g8_local','g8_away',
                    'g9_local','g9_away',
                    'tn_local','tn_away',
                    'drop_local','drop_away',
                    'after_local','after_away', // hoặc 'after_out' nếu bạn đổi tên
                ] as $rk) {
                    foreach ($sumOffsets[$rk] as $off) {
                        $sumCells[$rk][] = $addrAt($c, $off, $cfg['row'][$rk]);
                    }
                }
            }

        }

     

        if (!empty($cols15_18)) {
            $sumAddr = function(array $cols, int $row) {
                return '=SUM(' . implode(',', array_map(fn($C)=> $C.$row, $cols)) . ')';
            };

            $sh->setCellValue('Z'.$cfg['row']['total'],      $sumAddr($cols15_18, $cfg['row']['total']));
            $sh->setCellValue('Z'.$cfg['row']['female'],     $sumAddr($cols15_18, $cfg['row']['female']));
            $sh->setCellValue('Z'.$cfg['row']['ethnic'],     $sumAddr($cols15_18, $cfg['row']['ethnic']));
            $sh->setCellValue('Z'.$cfg['row']['dis_total'],  $sumAddr($cols15_18, $cfg['row']['dis_total']));
            $sh->setCellValue('Z'.$cfg['row']['dis_learn'],  $sumAddr($cols15_18, $cfg['row']['dis_learn']));
            $sh->setCellValue('Z'.$cfg['row']['dis_access'], $sumAddr($cols15_18, $cfg['row']['dis_access']));
            $sh->setCellValue('Z'.$cfg['row']['must_pc'],    $sumAddr($cols15_18, $cfg['row']['must_pc']));
        }

        $putSum = function(string $col, int $row, array $cells) use ($sh) {
            if (empty($cells)) return;
            $sh->setCellValue($col.$row, '=SUM('.implode(',', $cells).')');
        };

        // đặt SUM cho mọi hàng có cấu hình
        foreach ($sumCells as $rowKey => $cells) {
            if (!isset($cfg['row'][$rowKey])) continue;
            $putSum('Z', $cfg['row'][$rowKey], $cells);
        }

        $val = function(string $addr) use ($sh): float {
                $v = $sh->getCell($addr)->getCalculatedValue();
                return is_numeric($v) ? (float)$v : 0.0;
            };

        $tongtnthcs = $val('Z29')+$val('Z30');
        $pppc = $val('Z15');
        $hocphothong = $val('Z32')+$val('Z33');
        $khuyettat = $val('F12')+$val('H12')+$val('J12')+$val('L12') + $val('Z12');
        $khuyettathoc = $val('F13')+$val('H13')+$val('J13')+$val('L13') + $val('Z13');
        $tongttn = $val('F9')+$val('H9')+$val('J9')+$val('L9') + $val('Z9');
        $tongttndihoc = $val('F9')+$val('H9')+$val('J9')+$val('L9') + $val('Z9');
        $mucdo = 0;
        $xmc = 0;
        if($pppc >0 && $tongtnthcs/$pppc >= 0.95)
        {
            $mucdo = 3;
            $xmc = 2;
        }   
        else if($pppc > 0 && $tongtnthcs/$pppc >= 0.90)
        {
            $mucdo = 2;
            $xmc = 2;
        }
        else if($pppc > 0 && $tongtnthcs/$pppc > 0.8)
            $mucdo = 1;
        // dd($tongtnthcs,$pppc,$tongtnthcs/$pppc,$mucdo,$tongdihoc);
        $this->setCellValue($sh,'H40',  $mucdo);
        $this->setCellValue($sh,'H41',  $xmc);
        $this->setCellValue($sh,'H43',  $tongtnthcs);
        $this->setCellValue($sh,'H44',  $hocphothong);
        $this->setCellValue($sh,'H45',  $khuyettathoc);
        $this->setCellValue($sh,'H46',  $tongttndihoc);
        $this->setCellValue($sh,'J43', $pppc > 0 ? $tongtnthcs*100/$pppc : 0);
        $this->setCellValue($sh,'J44', $pppc > 0 ? $hocphothong*100/$pppc : 0);
        $this->setCellValue($sh,'J45', $khuyettat > 0 ? $khuyettathoc*100/$khuyettat : 0);
        $this->setCellValue($sh,'J46', $tongttn > 0 ? $tongttndihoc*100/$tongttn : 0);
        // viền/căn lề (tuỳ bạn muốn áp phạm vi nào)
        // $this->drawThinBorders($sh, 8, 36, 'C', $cfg['lastCol']);
        $sh->getStyle('C8:'.$cfg['lastCol'].'36')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle('C8:'.$cfg['lastCol'].'36')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sh->getStyle('C8:'.$cfg['lastCol'].'36')->getNumberFormat()->setFormatCode('0;-0;;@'); // ẩn số 0

        // Footer ngày tháng
        $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
        $sh->setCellValue('R40', $this->formatService->end_date($wardName));
        $sh->getStyle('R40')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // 7) Xuất file
        return response()->streamDownload(function () use ($wb) {
            $writer = IOFactory::createWriter($wb, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /* =========================================================
     * ======================  QUERY  ==========================
     * =======================================================*/
    protected function queryTtnThcs(string $academic, string $scheme, array $geo): array
    {
        [$y1] = array_map('intval', explode('-', $academic));
        $years = range($y1-11, $y1-18); // 11..18 tuổi
        $lastAcademic = ($y1-1).'-'.$y1;

        // nhóm loại trường
        $TYPE_GDTX = [3, 11];
        $TYPE_VOC  = [1];       // GDNN
        $inGdtx = implode(',', $TYPE_GDTX);
        $inVoc  = implode(',', $TYPE_VOC);

        // cột ward theo scheme
        $wardCol = $scheme === 'old' ? 'v.old_ward_id' : 'v.new_ward_id';
        $schWard = $scheme === 'old' ? 's.old_ward_id' : 's.new_ward_id';

        // Dataset từ view phẳng (HS hiện có)
        $flat = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->where('v.academic_year', $academic)
            ->whereIn('v.dob_year', $years);

        // lọc địa bàn
        if (($geo['province_id'] ?? null) || ($geo['ward_id'] ?? null)) {
            if ($scheme === 'old') {
                if (!empty($geo['province_id'])) $flat->where('v.old_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $flat->where('v.old_ward_id',     $geo['ward_id']);
            } else {
                if (!empty($geo['province_id'])) $flat->where('v.new_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $flat->where('v.new_ward_id',     $geo['ward_id']);
            }
        }

        // điều kiện helper
        $grade = "CAST(v.education_block_code AS UNSIGNED)";
        $pt_gdnn = "((v.education_block_code IS NOT NULL and  v.graduation_level_name ='THCS') )";
        $isPT  = "( (s.school_type_id IS NULL OR s.school_type_id NOT IN ($inGdtx,".implode(',',$TYPE_VOC)."))   )";
        $isTX  = "( v.is_bo_tuc=1 OR s.school_type_id IN ($inGdtx) )";
        $isVOC = "( s.school_type_id IN ($inVoc) or v.vocational_level_name ='Trung cấp' )";
        $local = "(s.code NOT LIKE 'NGOAI_TINH%' AND $schWard = $wardCol)";
        $bohoc = "(v.bo_hoc_lop IS NOT NULL )";
        $dihoc = "(v.education_block_code  IS NOT NULL and v.bo_hoc_lop IS NULL )";
        $tnthcs = "(v.graduation_level_name ='THCS' or v.graduation_level_name ='THPT')";
        $tnpt = "(v.graduation_level_name ='THPT')";
        $disAny = "(
            COALESCE(v.dis_VAN_DONG,0)+COALESCE(v.dis_NGHE_NOI,0)+COALESCE(v.dis_NHIN,0)+
            COALESCE(v.dis_THAN_KINH,0)+COALESCE(v.dis_TRI_TUE,0)+COALESCE(v.dis_HOC_TAP,0)+
            COALESCE(v.dis_TU_KY,0)+COALESCE(v.dis_KHAC,0)
        ) > 0 OR v.dis_has_certificate=1";
        $movedOutOrDead = "UPPER(TRIM(v.sc_name)) IN ('CHẾT','DECEASED','CHUYỂN ĐI','CHUYEN DI','MOVED_OUT')";

        // A) Tổng hợp đầu bảng (theo năm sinh)
        $head = (clone $flat)->selectRaw("
                v.dob_year AS yob,
                COUNT(DISTINCT v.person_id) AS total,
                COUNT(DISTINCT CASE WHEN CAST(v.is_female AS UNSIGNED)=1 THEN v.person_id END) AS female,
                COUNT(DISTINCT CASE WHEN (v.ethnicity IS NOT NULL AND UPPER(v.ethnicity)<>'KINH') THEN v.person_id END) AS ethnic,
                COUNT(DISTINCT CASE WHEN ($disAny) THEN v.person_id END) AS dis_total,
                COUNT(DISTINCT CASE WHEN v.dis_can_learn=1 THEN v.person_id END) AS dis_learn,
                COUNT(DISTINCT CASE WHEN ($disAny) AND ($grade BETWEEN 1 AND 9 OR $grade IS NULL) THEN v.person_id END) AS dis_access,
                (
                    COUNT(DISTINCT v.person_id)
                    - COUNT(DISTINCT CASE WHEN ($disAny) THEN v.person_id END)
                    - COUNT(DISTINCT CASE WHEN ($movedOutOrDead) THEN v.person_id END)
                ) AS must_pc
            ")
            ->groupBy('yob')
            ->get()
            ->keyBy('yob');

        // B) Dòng lớp 6..9 (PT/TX/GDNN – tại chỗ / nơi khác)
        $g = (clone $flat)->selectRaw("
                v.dob_year AS yob,

                -- lớp 6
                SUM(CASE WHEN $grade=6 AND $isPT  AND $local THEN 1 ELSE 0 END) AS g6_pt_local,
                SUM(CASE WHEN $grade=6 AND $isTX  AND $local THEN 1 ELSE 0 END) AS g6_tx_local,
                SUM(CASE WHEN $grade=6 AND $isVOC AND $local THEN 1 ELSE 0 END) AS g6_voc_local,
                SUM(CASE WHEN $grade=6 AND $isPT  AND NOT $local THEN 1 ELSE 0 END) AS g6_pt_away,
                SUM(CASE WHEN $grade=6 AND $isTX  AND NOT $local THEN 1 ELSE 0 END) AS g6_tx_away,
                SUM(CASE WHEN $grade=6 AND $isVOC AND NOT $local THEN 1 ELSE 0 END) AS g6_voc_away,

                -- lớp 7
                SUM(CASE WHEN $grade=7 AND $isPT  AND $local THEN 1 ELSE 0 END) AS g7_pt_local,
                SUM(CASE WHEN $grade=7 AND $isTX  AND $local THEN 1 ELSE 0 END) AS g7_tx_local,
                SUM(CASE WHEN $grade=7 AND $isVOC AND $local THEN 1 ELSE 0 END) AS g7_voc_local,
                SUM(CASE WHEN $grade=7 AND $isPT  AND NOT $local THEN 1 ELSE 0 END) AS g7_pt_away,
                SUM(CASE WHEN $grade=7 AND $isTX  AND NOT $local THEN 1 ELSE 0 END) AS g7_tx_away,
                SUM(CASE WHEN $grade=7 AND $isVOC AND NOT $local THEN 1 ELSE 0 END) AS g7_voc_away,

                -- lớp 8
                SUM(CASE WHEN $grade=8 AND $isPT  AND $local THEN 1 ELSE 0 END) AS g8_pt_local,
                SUM(CASE WHEN $grade=8 AND $isTX  AND $local THEN 1 ELSE 0 END) AS g8_tx_local,
                SUM(CASE WHEN $grade=8 AND $isVOC AND $local THEN 1 ELSE 0 END) AS g8_voc_local,
                SUM(CASE WHEN $grade=8 AND $isPT  AND NOT $local THEN 1 ELSE 0 END) AS g8_pt_away,
                SUM(CASE WHEN $grade=8 AND $isTX  AND NOT $local THEN 1 ELSE 0 END) AS g8_tx_away,
                SUM(CASE WHEN $grade=8 AND $isVOC AND NOT $local THEN 1 ELSE 0 END) AS g8_voc_away,

                -- lớp 9
                SUM(CASE WHEN $grade=9 AND $isPT  AND $local THEN 1 ELSE 0 END) AS g9_pt_local,
                SUM(CASE WHEN $grade=9 AND $isTX  AND $local THEN 1 ELSE 0 END) AS g9_tx_local,
                SUM(CASE WHEN $grade=9 AND $isVOC AND $local THEN 1 ELSE 0 END) AS g9_voc_local,
                SUM(CASE WHEN $grade=9 AND $isPT  AND NOT $local THEN 1 ELSE 0 END) AS g9_pt_away,
                SUM(CASE WHEN $grade=9 AND $isTX  AND NOT $local THEN 1 ELSE 0 END) AS g9_tx_away,
                SUM(CASE WHEN $grade=9 AND $isVOC AND NOT $local THEN 1 ELSE 0 END) AS g9_voc_away,
                  SUM(CASE WHEN $tnthcs   THEN 1 ELSE 0 END) AS tn_pt_local,
                 SUM(CASE WHEN $bohoc   THEN 1 ELSE 0 END) AS drop_pt_local,
                    SUM(CASE WHEN $dihoc   THEN 1 ELSE 0 END) AS in_pt_local
              
                
                 
            ")
            ->groupBy('yob')
            ->get()
            ->keyBy('yob');

        // C) Bổ sung “học trái tuyến” từ bảng students_off_track (nếu có),
        // normalize giống logic trên và cộng dồn
        $sot = DB::table('students_off_track as t')
            ->leftJoin('schools as s', 's.id', '=', 't.school_id')
            ->where('t.academic_year', $academic)
            ->whereIn('t.dob_year', $years)
            ->selectRaw("
                t.dob_year AS yob,
                t.education_block_id AS grade,
                -- phân loại
                (CASE WHEN COALESCE(t.is_bo_tuc,0)=1 OR s.school_type_id IN ($inGdtx) THEN 1 ELSE 0 END) AS is_tx,
                (CASE WHEN s.school_type_id IN ($inVoc) THEN 1 ELSE 0 END) AS is_voc,
                (CASE WHEN (COALESCE(t.is_bo_tuc,0)=0 AND (s.school_type_id IS NULL OR s.school_type_id NOT IN ($inGdtx,".implode(',',$TYPE_VOC)."))) THEN 1 ELSE 0 END) AS is_pt,

                -- local theo selected ward
                (CASE WHEN ".($scheme==='old'?'s.old_ward_id':'s.new_ward_id')."
                           = ".($scheme==='old'?'t.selected_ward_code_old':'t.selected_ward_code_new')."
                      THEN 1 ELSE 0 END) AS is_local
            ")->get();

        // cộng dồn sot vào $g
       
        $byYobG = $g->toArray();
       
        foreach ($sot as $row) {
            $yob   = (int)$row->yob;
            $grade = (int)$row->grade;
            $loc   = ((int)$row->is_local) === 1 ? 'local' : 'away';
            $kind  = ((int)$row->is_voc)===1 ? 'voc' : ( ((int)$row->is_tx)===1 ? 'tx' : 'pt' );

            if ($grade>=6 && $grade<=9) {
                $key = "g{$grade}_{$kind}_{$loc}";
                if (!isset($byYobG[$yob])) {
                    $byYobG[$yob] = (object)[$key=>0];
                }
                $byYobG[$yob]->$key = (int)($byYobG[$yob]->$key ?? 0) + 1;
            }
            else
            {
                // TN THCS
                if ($row->is_pt && $row->is_local && $row->grade===null) {
                    if (!isset($byYobG[$yob])) {
                        $byYobG[$yob] = (object)['tn_local'=>0];
                    }
                    $byYobG[$yob]->tn_local = (int)($byYobG[$yob]->tn_local ?? 0) + 1;
                }
                // Bỏ học
                if ($row->is_pt && $row->is_local && $row->grade===0) {
                    if (!isset($byYobG[$yob])) {
                        $byYobG[$yob] = (object)['drop_local'=>0];
                    }
                    $byYobG[$yob]->drop_local = (int)($byYobG[$yob]->drop_local ?? 0) + 1;
                }
            }
        }
  
        // D) “Sau THCS” (15–18): đi PT10 / GDTX10 / GDNN (tách tại chỗ / nơi khác)
          $after = (clone $flat)->selectRaw("
                v.dob_year AS yob,
                SUM(CASE WHEN   $tnpt or ( $pt_gdnn AND $local  AND $isPT) THEN 1 ELSE 0 END) AS aft_pt_local,
                SUM(CASE WHEN $pt_gdnn AND $isTX  AND $local THEN 1 ELSE 0 END) AS aft_tx_local,
                SUM(CASE WHEN  $isVOC AND $local THEN 1 ELSE 0 END) AS aft_voc_local,

                SUM(CASE WHEN ($pt_gdnn AND $isPT  AND NOT $local) THEN 1 ELSE 0 END) AS aft_pt_away,
                SUM(CASE WHEN $pt_gdnn AND $isTX  AND NOT $local THEN 1 ELSE 0 END) AS aft_tx_away,
                SUM(CASE WHEN   $isVOC AND NOT $local THEN 1 ELSE 0 END) AS aft_voc_away
            ")
            ->whereBetween('v.dob_year', [$y1-18, $y1-15])
            ->groupBy('yob')
            ->get()
            ->keyBy('yob');

        // E) Merge tất cả theo yob
        $by = [];
        foreach ($years as $yob) {
            $by[$yob] = [
                // head
                'total'      => (int)($head[$yob]->total      ?? 0),
                'female'     => (int)($head[$yob]->female     ?? 0),
                'ethnic'     => (int)($head[$yob]->ethnic     ?? 0),
                'dis_total'  => (int)($head[$yob]->dis_total  ?? 0),
                'dis_learn'  => (int)($head[$yob]->dis_learn  ?? 0),
                'dis_access' => (int)($head[$yob]->dis_access ?? 0),
                'must_pc'    => max(0,(int)($head[$yob]->must_pc ?? 0)),
            ];

            $grow = $byYobG[$yob] ?? (object)[];
            foreach ([6,7,8,9] as $gg) {
                foreach ([['pt','PT'], ['tx','TX'], ['voc','VOC']] as [$kind, $_]) {
                    $by[$yob]["g{$gg}_{$kind}_local"] = (int)($grow->{"g{$gg}_{$kind}_local"} ?? 0);
                    $by[$yob]["g{$gg}_{$kind}_away"]  = (int)($grow->{"g{$gg}_{$kind}_away"}  ?? 0);
                }
            }
           
            $by[$yob]['drop_pt_local']  = (int)( $byYobG[$yob]->drop_pt_local  ?? 0);
            $by[$yob]['tn_pt_local']  = (int)( $byYobG[$yob]->tn_pt_local  ?? 0);
             $by[$yob]['in_pt_local']  = (int)( $byYobG[$yob]->in_pt_local  ?? 0);
            // after
            $by[$yob]['aft_pt_local']  = (int)($after[$yob]->aft_pt_local  ?? 0);
            $by[$yob]['aft_tx_local']  = (int)($after[$yob]->aft_tx_local  ?? 0);
            $by[$yob]['aft_voc_local'] = (int)($after[$yob]->aft_voc_local ?? 0);
            $by[$yob]['aft_pt_away']   = (int)($after[$yob]->aft_pt_away   ?? 0);
            $by[$yob]['aft_tx_away']   = (int)($after[$yob]->aft_tx_away   ?? 0);
            $by[$yob]['aft_voc_away']  = (int)($after[$yob]->aft_voc_away  ?? 0);
        }
        // dd($by);
        return ['by_yob' => $by];
    }
}
