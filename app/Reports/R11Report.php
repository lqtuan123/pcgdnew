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

class R11Report extends BaseReport
{
      public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

//     public function export(Request $req)
// {
//     $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear());
//     [$y1]     = array_map('intval', explode('-', $academic));
//     $scheme   = (string)$req->input('scheme', 'old');
//     $geo      = (array)$req->input('geo', []);

//     // Tăng tốc tính công thức
//     if (method_exists(\PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance(), 'setCalculationEnabled')) {
//         \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance()->setCalculationEnabled(false);
//     }

//     // ===== Template
//     $cfg = [
//         'path'     => resource_path('excel/1.1.MN.0.5Tuoi.xlsx'),
//         'header'   => ['province' => 'C2', 'ward' => 'C3'],
//         'dateTop'  => 'D4',
//         'yearsRow' => 4,
//         'startRow' => 9,
//         'lastCol'  => 'M',                 // Tổng cộng
//         'yearCols' => ['G','H','I','J','K','L'], // 0→G … 5→L (6 cột)
//         'sumCol'   => 'M',                 // cột Tổng cộng
//         'rows' => [
//             'tong_trong_do_tuoi'       => 9,
//             'trong_do_gai'             => 10,
//             'trong_do_dt_thieu_so'     => 11,
//             'khuyet_tat_tong'          => 12,
//             'khuyet_tat_co_kha_nang'   => 13,
//             'khuyet_tat_duoc_tiep_can' => 14,
//             'phai_huy_dong'            => 15,
//             'den_truong'               => 16,
//             'den_truong_noi_dia'       => 17,
//             'den_truong_trai_tuyen'    => 18,
//             'ty_le_huy_dong'           => 19,  // %
//             'den_truong_gai'           => 20,
//             'den_truong_dt'            => 21,
//             'dt_chuan_bi_tv'           => 22,
//             'hoc_trai_tuyen'           => 23,
//             'hoc_2_buoi'               => 24,
//             'ty_le_2_buoi'             => 25,  // %
//             'tre_chet'                 => 26,
//             'tre_chuyen_di'            => 27,
//             'tre_chuyen_den'           => 28,
//             'hoan_thanh_ct'            => 29,  // số lượng
//             'ti_le_hoan_thanh_ct'      => 30,  // %
//             'trai_tuyen_hoan_thanh_ct' => 31,  // số lượng trái tuyến hoàn thành
//         ],
//         'summary' => [
//             'startRow' => 34, // nhãn C; số lượng E; tỷ lệ F
//             'labelCol' => 'C',
//             'qtyCol'   => 'E',
//             'pctCol'   => 'F',
//         ],
//     ];

//     // ===== Load template
//     $wb = \PhpOffice\PhpSpreadsheet\IOFactory::load($cfg['path']);
//     $sh = $wb->getActiveSheet();

//     // Header
//     $sh->setCellValue($cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
//     $sh->setCellValue($cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
//     $sh->setCellValue($cfg['dateTop'],            "Năm học $academic");

//     // Nhãn 0..5
//     foreach (range(0,5) as $age) {
//         $sh->setCellValue($cfg['yearCols'][$age].$cfg['yearsRow'], $y1 - $age);
//     }
//     $sh->setCellValue($cfg['sumCol'].$cfg['yearsRow'], 'Tổng cộng (0–4)');

//     // ===== Dữ liệu
//     // Giả sử queryMamnon0To5() trả về 1 dataset: ['by_age'=>[0..5=>mảng chỉ tiêu], 'total'=>mảng]
//     // (đã tích hợp sẵn enrolled_trai_tuyen, traituyen_grad_gdmn nếu có)
//     $data  = $this->queryMamnon0To5($academic, $scheme, $geo);
//     $byAge = $data['by_age'];   // 0..5
//     $total = $data['total'];    // (không dùng cho "Tổng cộng" của sheet, vì ta chỉ cộng 0..4)

//     // Helpers
//     $zeroblank = fn($v) => ((int)($v ?? 0) === 0 ? null : $v);
//     $pct = function($num, $den) {
//         $num = (float)($num ?? 0); $den = (float)($den ?? 0);
//         return $den > 0 ? round($num / $den, 4) : null; // để set format % sau
//     };

//     // CHỈ số tuổi hiển thị & tuổi được tính tổng
//     $AGES_SHOW = range(0,5);      // hiển thị 6 cột 0..5
//     $AGES_SUM  = range(0,4);      // Tổng cộng = cộng 5 cột đầu 0..4

//     // Hàm cộng dồn theo nhóm tuổi được chọn
//     $sumByAges = function(array $byAge, array $ageList) {
//         $sum = [];
//         foreach ($ageList as $a) {
//             $row = $byAge[$a] ?? [];
//             foreach ($row as $k => $v) {
//                 $sum[$k] = ($sum[$k] ?? 0) + ($v ?? 0);
//             }
//         }
//         return $sum;
//     };

//     // —— Ghi theo tuổi cho từng chỉ tiêu —— (Tổng cộng chỉ cộng 0..4)
//     $put = function(string $rowKey, callable $getter) use ($cfg, $sh, $byAge, $zeroblank, $pct, $AGES_SHOW, $AGES_SUM) {
//         $r = $cfg['rows'][$rowKey];

//         // 6 cột 0..5
//         foreach ($AGES_SHOW as $age) {
//             [$val, $isPct] = $getter($byAge[$age] ?? [], $age);
//             $addr = $cfg['yearCols'][$age].$r;
//             $sh->setCellValue($addr, $isPct ? $val : $zeroblank($val));
//             if ($isPct) {
//                 $sh->getStyle($addr)->getNumberFormat()->setFormatCode('0.00%');
//             }
//         }

//         // Cột Tổng cộng (chỉ 0..4)
//         $sumRow = array_reduce($AGES_SUM, function($c,$age) use ($byAge){
//             $row = $byAge[$age] ?? [];
//             foreach ($row as $k=>$x) $c[$k] = ($c[$k] ?? 0) + ($x ?? 0);
//             return $c;
//         }, []);
//         [$sumVal, $isPctSum] = $getter($sumRow, -1);
//         $sumAddr = $cfg['sumCol'].$r;
//         $sh->setCellValue($sumAddr, $isPctSum ? $sumVal : $zeroblank($sumVal));
//         if ($isPctSum) {
//             $sh->getStyle($sumAddr)->getNumberFormat()->setFormatCode('0.00%');
//         }
//     };

//     // 9: Tổng số trong độ tuổi
//     $put('tong_trong_do_tuoi', fn($v)=>[$v['total'] ?? 0, false]);

//     // 11: Trẻ em gái
//     $put('trong_do_gai', fn($v)=>[$v['female'] ?? 0, false]);

//     // 12: Trẻ DTTS
//     $put('trong_do_dt_thieu_so', fn($v)=>[$v['minority'] ?? 0, false]);

//     // 14–16: Khuyết tật
//     $put('khuyet_tat_tong',          fn($v)=>[$v['disabled_any']       ?? 0, false]);
//     $put('khuyet_tat_co_kha_nang',   fn($v)=>[$v['disabled_can_learn'] ?? 0, false]);
//     $put('khuyet_tat_duoc_tiep_can', fn($v)=>[$v['disabled_access']    ?? 0, false]);

//     // 18: Phải huy động
//     $put('phai_huy_dong', fn($v)=>[$v['must_mobilize'] ?? 0, false]);

//     // 20–22: Đến trường & cơ cấu
//     $put('den_truong',            fn($v)=>[$v['enrolled']            ?? 0, false]);
//     $put('den_truong_noi_dia',    fn($v)=>[$v['enrolled_local']      ?? 0, false]);
//     $put('den_truong_trai_tuyen', fn($v)=>[$v['enrolled_trai_tuyen'] ?? 0, false]);

//     // 23: Tỷ lệ huy động = enrolled / must_mobilize
//     $put('ty_le_huy_dong', fn($v)=>[$pct($v['enrolled'] ?? 0, $v['must_mobilize'] ?? 0), true]);

//     // 25–27: cơ cấu trẻ đến trường
//     $put('den_truong_gai', fn($v)=>[$v['enrolled_girl']     ?? 0, false]);
//     $put('den_truong_dt',  fn($v)=>[$v['enrolled_minority'] ?? 0, false]);
//     $put('dt_chuan_bi_tv', fn($v)=>[$v['prepared_tv']       ?? 0, false]);

//     // 29–31: 2 buổi & hoàn thành CT
//     $put('hoc_2_buoi',          fn($v)=>[$v['two_sessions'] ?? 0, false]);
//     $put('ty_le_2_buoi',        fn($v)=>[$pct($v['two_sessions'] ?? 0, $v['enrolled'] ?? 0), true]);
//     $put('tre_chuyen_di',       fn($v)=>[$v['moveout'] ?? 0, false]);
//     $put('tre_chuyen_den',      fn($v)=>[$v['movein']  ?? 0, false]);
//     $put('tre_chet',            fn($v)=>[$v['died']    ?? 0, false]);
//     $put('hoan_thanh_ct',       fn($v)=>[$v['grad_gdmn'] ?? 0, false]);
//     $put('ti_le_hoan_thanh_ct', fn($v)=>[$pct($v['grad_gdmn'] ?? 0, $v['enrolled'] ?? 0), true]);
//     $put('trai_tuyen_hoan_thanh_ct', fn($v)=>[$v['traituyen_grad_gdmn'] ?? 0, false]); // nếu key khác, đổi lại cho khớp

//     // —— Bảng tổng hợp cuối trang (giữ nguyên logic cũ) ——
//     $sumCfg = $cfg['summary'];
//     $age5   = $byAge[5] ?? array_fill_keys(array_keys($total), 0);

//     // 1) Trẻ 5 tuổi đến trường
//     $sh->setCellValue($sumCfg['labelCol'].$sumCfg['startRow'], 'Trẻ 5 tuổi đến trường');
//     $sh->setCellValue($sumCfg['qtyCol']  .$sumCfg['startRow'], ($age5['enrolled'] ?? 0) ?: null);
//     $sh->setCellValue($sumCfg['pctCol']  .$sumCfg['startRow'], $pct($age5['enrolled'] ?? 0, $age5['must_mobilize'] ?? 0));
//     $sh->getStyle($sumCfg['pctCol'].$sumCfg['startRow'])->getNumberFormat()->setFormatCode('0.00%');

//     // 2) Trẻ 5 tuổi hoàn thành CT GDMN
//     $r2 = $sumCfg['startRow'] + 1;
//     $sh->setCellValue($sumCfg['labelCol'].$r2, 'Trẻ 5 tuổi hoàn thành chương trình GDMN');
//     $sh->setCellValue($sumCfg['qtyCol']  .$r2, ($age5['grad_gdmn'] ?? 0) ?: null);
//     $sh->setCellValue($sumCfg['pctCol']  .$r2, $pct($age5['grad_gdmn'] ?? 0, $age5['enrolled'] ?? 0));
//     $sh->getStyle($sumCfg['pctCol'].$r2)->getNumberFormat()->setFormatCode('0.00%');

//     // 3) Trẻ 5 tuổi khuyết tật được tiếp cận GD
//     $r3 = $sumCfg['startRow'] + 2;
//     $sh->setCellValue($sumCfg['labelCol'].$r3, 'Trẻ 5 tuổi khuyết tật được tiếp cận GD');
//     $sh->setCellValue($sumCfg['qtyCol']  .$r3, ($age5['disabled_access'] ?? 0) ?: null);
//     $sh->setCellValue($sumCfg['pctCol']  .$r3, $pct($age5['disabled_access'] ?? 0, $age5['disabled_any'] ?? 0));
//     $sh->getStyle($sumCfg['pctCol'].$r3)->getNumberFormat()->setFormatCode('0.00%');

//     // 4) Trẻ học 2 buổi/ngày (toàn 0–5) — giữ nguyên
//     $r4 = $sumCfg['startRow'] + 3;
//     $sh->setCellValue($sumCfg['labelCol'].$r4, 'Trẻ học 2 buổi/ngày');
//     $sh->setCellValue($sumCfg['qtyCol']  .$r4, ($total['two_sessions'] ?? 0) ?: null);
//     $sh->setCellValue($sumCfg['pctCol']  .$r4, $pct($total['two_sessions'] ?? 0, $total['enrolled'] ?? 0));
//     $sh->getStyle($sumCfg['pctCol'].$r4)->getNumberFormat()->setFormatCode('0.00%');

//     // —— Ẩn số 0 bằng Conditional Formatting (không đụng NumberFormat %) ——
//     $dataRange = 'G9:'.$cfg['lastCol'].'32';
//     $cond = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
//     $cond->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS)
//          ->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_EQUAL)
//          ->addCondition('0');
//     $cond->getStyle()->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
//     $styles = $sh->getStyle($dataRange)->getConditionalStyles();
//     $styles[] = $cond;
//     $sh->getStyle($dataRange)->setConditionalStyles($styles);

//     // Ngày tháng
//     $sh->setCellValue('K40', $this->formatService->end_date($this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? ''));

//     // Xuất
//     return response()->streamDownload(function () use ($wb) {
//         $w = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($wb, 'Xlsx');
//         $w->setPreCalculateFormulas(false);
//         $w->save('php://output');
//     }, 'MN_0_5_Tuoi_'.date('Ymd_His').'.xlsx', [
//         'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
//     ]);
// }


    public function export(Request $req)
    {
        $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1]     = array_map('intval', explode('-', $academic));
        $scheme   = (string)$req->input('scheme', 'old');
        $geo      = (array)$req->input('geo', []);

        // tăng tốc
        if (method_exists(\PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance(), 'setCalculationEnabled')) {
            \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance()->setCalculationEnabled(false);
        }

        // ===== Template
        $cfg = [
            'path'       => resource_path('excel/1.1.MN.0.5Tuoi.xlsx'), // đổi đúng đường dẫn: 4.?.MN-01-TE
            'header'     => ['province' => 'B2', 'ward' => 'B3'],
            'dateTop'   => 'D4',
                   // “Năm học …”/ngày tháng – tuỳ bạn
            'yearsRow'   => 7,               // hàng ghi nhãn năm sinh
            'startRow'   => 9,               // dòng bắt đầu khối số liệu (dòng “Tổng số trẻ trong độ tuổi”)
            'lastCol'    => 'M',
            // cột tương ứng 0..5 tuổi và Tổng
            'yearCols'   => ['F','G','H','I','J','K','L'], // 0→G … 5→L
            'sumCol'     => 'M',
            // các dòng chỉ tiêu chính (theo ảnh bạn gửi)
            'rows' => [
                'tong_trong_do_tuoi'      => 9,   // “Tổng số trẻ trong độ tuổi”
                'trong_do_gai'            => 10,
                'trong_do_dt_thieu_so'    => 11,
                'khuyet_tat_tong'         => 12,
                'khuyet_tat_co_kha_nang'  => 13,
                'khuyet_tat_duoc_tiep_can'=> 14,
                'phai_huy_dong'           => 15,
                'den_truong'              => 16,
                'den_truong_noi_dia'      => 17,  // ở cơ sở địa bàn PX
                'den_truong_trai_tuyen'   => 18,  // học trái tuyến
                'ty_le_huy_dong'          => 19,  // %
                'den_truong_gai'          => 20,
                'den_truong_dt'           => 21,
                'dt_chuan_bi_tv'          => 22,
                'hoc_trai_tuyen'          => 23,
                'hoc_2_buoi'              => 24,
                'ty_le_2_buoi'            => 25,  // %
                'tre_chet'              => 26,
                'tre_chuyen_di'          => 27,
                 'tre_chuyen_den'          => 28,
                'hoan_thanh_ct'           => 29,  // %
                'ti_le_hoan_thanh_ct'           => 30,  // %
                'trai_tuyen_hoan_thanh_ct'           => 31  // %
            ],
            // khối tổng hợp cuối trang (bảng nhỏ)
            'summary' => [
                'startRow' => 34, // nhãn ở cột C; số lượng E; tỷ lệ F
                'labelCol' => 'C',
                'qtyCol'   => 'E',
                'pctCol'   => 'F',
            ],
        ];

        $wb = \PhpOffice\PhpSpreadsheet\IOFactory::load($cfg['path']);
        $sh = $wb->getActiveSheet();

        // header
        $sh->setCellValue($cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        $sh->setCellValue($cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
        $sh->setCellValue($cfg['dateTop'],            "Năm học $academic");

        // năm sinh cho 0..5
        $yearCols = $cfg['yearCols']; // G..L
        foreach (range(0,6) as $age) {
            $sh->setCellValue($yearCols[$age].$cfg['yearsRow'], $y1 - $age);
        }
        $sh->setCellValue($cfg['sumCol'].$cfg['yearsRow'], 'Tổng cộng');

        // dữ liệu
        $data   = $this->queryMamnon0To5($academic, $scheme, $geo);

        // $traituyen=$data[0];
        // $dungtuyen=$data[1];

        $byAge  = $data['by_age'];   // 0..5
        $total  = $data['total'];    // tổng 0..5

        // helper
        $zeroblank = function($v) { return ((int)$v === 0) ? null : $v; };
        $pct = function($num,$den) {
            if ($den > 0) return round($num/$den, 4); // để format % sau
            return null;
        };

        // —— ghi theo tuổi cho từng chỉ tiêu ——
       // hiển thị 6 cột 0..5
    $AGES_SHOW = range(0,6);
    // TỔNG CỘNG: nếu bạn muốn cộng 0..5 thì để 0..5;
    // nếu chỉ cộng 0..4 thì đổi thành range(0,4)
    $AGES_SUM  = range(0,5);

    $put = function (string $rowKey, callable $getter)
    use ($cfg, $sh, $byAge, $zeroblank, $pct, $yearCols, $AGES_SHOW, $AGES_SUM) {

        $r = $cfg['rows'][$rowKey];

        // --- 6 cột tuổi 0..5 ---
        foreach ($AGES_SHOW as $age) {
            [$val, $isPct] = $getter($byAge[$age] ?? [], $age);
            $addr = $yearCols[$age] . $r;
            $sh->setCellValue($addr, $isPct ? $val*100 : $zeroblank($val));
            if ($isPct) {
                $sh->getStyle($addr)->getNumberFormat()->setFormatCode('0.00');
            }
        }

        // --- Tổng cộng: chỉ cộng theo AGES_SUM (không chạy hết byAge) ---
        $sumRow = [];
        foreach ($AGES_SUM as $age) {
            $row = $byAge[$age] ?? [];
            foreach ($row as $k => $x) {
                $sumRow[$k] = ($sumRow[$k] ?? 0) + ($x ?? 0);
            }
        }
        [$sumVal, $isPctSum] = $getter($sumRow, -1);

        $sumAddr = $cfg['sumCol'] . $r;
        $sh->setCellValue($sumAddr, $isPctSum ? $sumVal*100 : $zeroblank($sumVal));
        if ($isPctSum) {
            $sh->getStyle($sumAddr)->getNumberFormat()->setFormatCode('0.00');
        }
    };
         

        // 9: Tổng số trong độ tuổi
        $put('tong_trong_do_tuoi', fn($v)=>[$v['total'] ?? 0, false]);
        // 11: Trẻ em gái
        $put('trong_do_gai', fn($v)=>[$v['female'] ?? 0, false]);
        // 12: Trẻ DTTS
        $put('trong_do_dt_thieu_so', fn($v)=>[$v['minority'] ?? 0, false]);

        // 14–16: Khuyết tật
        $put('khuyet_tat_tong', fn($v)=>[$v['disabled_any'] ?? 0, false]);
        $put('khuyet_tat_co_kha_nang', fn($v)=>[$v['disabled_can_learn'] ?? 0, false]);
        $put('khuyet_tat_duoc_tiep_can', fn($v)=>[$v['disabled_access'] ?? 0, false]);

        // 18: Phải huy động
        $put('phai_huy_dong', fn($v)=>[$v['must_mobilize'] ?? 0, false]);

        // 20–22: Đến trường & cơ cấu
        $put('den_truong',            fn($v)=>[$v['enrolled'] ?? 0, false]);
        $put('den_truong_noi_dia',    fn($v)=>[$v['enrolled_local'] ?? 0, false]);
        $put('den_truong_trai_tuyen', fn($v)=>[$v['enrolled_trai_tuyen'] ?? 0, false]);

        // 23: Tỷ lệ huy động = enrolled / must_mobilize
        $put('ty_le_huy_dong', fn($v)=>[$pct($v['enrolled'] ?? 0, $v['must_mobilize'] ?? 0), true]);

        // 25–27: cơ cấu trẻ đến trường
        $put('den_truong_gai',         fn($v)=>[$v['enrolled_girl'] ?? 0, false]);
        $put('den_truong_dt',          fn($v)=>[$v['enrolled_minority'] ?? 0, false]);
        $put('dt_chuan_bi_tv',         fn($v)=>[$v['prepared_tv'] ?? 0, false]);

        // 29: Số học 2 buổi
        $put('hoc_2_buoi', fn($v)=>[$v['two_sessions'] ?? 0, false]);
        // 30: Tỷ lệ 2 buổi = two_sessions / enrolled
        $put('ty_le_2_buoi', fn($v)=>[$pct($v['two_sessions'] ?? 0, $v['enrolled'] ?? 0), true]);
        $put('tre_chuyen_di', fn($v)=>[$v['moveout'] ?? 0, false]);
        $put('tre_chuyen_den', fn($v)=>[$v['movein'] ?? 0, false]);
        $put('tre_chet', fn($v)=>[$v['died'] ?? 0, false]);
        // 32: Tỷ lệ hoàn thành CT GDMN (áp cho lứa có giá trị; thường là 5 tuổi)
        $put('hoan_thanh_ct', fn($v)=>[$v['grad_gdmn'] ?? 0, false]);
         $put('ti_le_hoan_thanh_ct', fn($v,$age)=>[$pct($v['grad_gdmn'] ?? 0, $v['enrolled'] ?? 0), true]);
          $put('trai_tuyen_hoan_thanh_ct', fn($v)=>[$v['traituyen_grad_gdmn'] ?? 0, false]);
       

        // —— Bảng tổng hợp cuối trang ——
        $sumCfg = $cfg['summary'];
        // 1) Trẻ 5 tuổi đến trường
        $age5 = $byAge[5] ?? array_fill_keys(array_keys($total),0); 
         $age6 = $byAge[6] ?? array_fill_keys(array_keys($total),0);
        $sh->setCellValue( 'E35',     ($age5['enrolled'] ?? 0) ?: null);
         $sh->setCellValue( 'E36',     ($age6['grad_gdmn'] ?? 0) ?: null);
        $sh->setCellValue( 'E37',     ($age5['disabled_access'] ?? 0) ?: null);
        $sh->setCellValue( 'E38',     ($age5['two_sessions'] ?? 0) ?: null);
        $sh->setCellValue('G35',     $pct($age5['enrolled']*100 ?? 0, $age5['must_mobilize'] ?? 0));
       $sh->setCellValue('G36',     $pct($age6['grad_gdmn'] *100?? 0, $age6['must_mobilize'] ?? 0));
    //    dd($pct($age5['disabled_access']*100 ?? 0, $age5['must_mobilize'] ?? 0)) ;
      $this->setCellValue( $sh,'G37',     $pct($age5['disabled_access']*100 ?? 0, $age5['must_mobilize'] ?? 0)==0 ? null:$pct($age5['disabled_access']*100 ?? 0, $age5['must_mobilize'] ?? 0));
         $sh->setCellValue('G38',     $pct($age5['two_sessions'] *100?? 0, $age5['must_mobilize'])==0 ? null:$pct($age5['two_sessions'] *100?? 0, $age5['must_mobilize']) );
        
        // $sh->setCellValue($sumCfg['pctCol']  .$sumCfg['startRow'],     $pct($age5['enrolled'] ?? 0, $age5['must_mobilize'] ?? 0));
        // $sh->getStyle($sumCfg['pctCol'].$sumCfg['startRow'])->getNumberFormat()->setFormatCode('0.00%');

        // // 2) Trẻ 5 tuổi hoàn thành CT GDMN
        // $r2 = $sumCfg['startRow']+1;
        // $sh->setCellValue($sumCfg['labelCol'].$r2, 'Trẻ 5 tuổi hoàn thành chương trình GDMN');
        // $sh->setCellValue($sumCfg['qtyCol']  .$r2, ($age5['grad_gdmn'] ?? 0) ?: null);
        // $sh->setCellValue($sumCfg['pctCol']  .$r2, $pct($age5['grad_gdmn'] ?? 0, $age5['enrolled'] ?? 0));
        // $sh->getStyle($sumCfg['pctCol'].$r2)->getNumberFormat()->setFormatCode('0.00%');

        // // 3) Trẻ 5 tuổi khuyết tật được tiếp cận GD
        // $r3 = $sumCfg['startRow']+2;
        // $sh->setCellValue($sumCfg['labelCol'].$r3, 'Trẻ 5 tuổi khuyết tật được tiếp cận GD');
        // $sh->setCellValue($sumCfg['qtyCol']  .$r3, ($age5['disabled_access'] ?? 0) ?: null);
        // $sh->setCellValue($sumCfg['pctCol']  .$r3, $pct($age5['disabled_access'] ?? 0, $age5['disabled_any'] ?? 0));
        // $sh->getStyle($sumCfg['pctCol'].$r3)->getNumberFormat()->setFormatCode('0.00%');

        // // 4) Trẻ học 2 buổi/ngày (toàn 0–5)
        // $r4 = $sumCfg['startRow']+3;
        // $sh->setCellValue($sumCfg['labelCol'].$r4, 'Trẻ học 2 buổi/ngày');
        // $sh->setCellValue($sumCfg['qtyCol']  .$r4, ($total['two_sessions'] ?? 0) ?: null);
        // $sh->setCellValue($sumCfg['pctCol']  .$r4, $pct($total['two_sessions'] ?? 0, $total['enrolled'] ?? 0));
        // $sh->getStyle($sumCfg['pctCol'].$r4)->getNumberFormat()->setFormatCode('0.00%');

        // Ẩn số 0 (để trống) trong toàn vùng dữ liệu
        $dataRange = 'G9:'.$cfg['lastCol'].'32';
        $cond = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
        $cond->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS)
            ->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_EQUAL)
            ->addCondition('0');
        $style = new \PhpOffice\PhpSpreadsheet\Style\Style();
        $style->getFont()->getColor()->setARGB('FFFFFFFF'); // “ẩn” 0 (màu trắng). Hoặc dùng custom format:
        // Tốt hơn: định dạng custom: 0;-0;;@  (âm;dương;zero;text)
        // $sh->getStyle($dataRange)->getNumberFormat()->setFormatCode('0;-0;;@');

        // ngày tháng
        $sh->setCellValue('K40', $this->formatService->end_date($this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? ''));

        $sh->getStyle("F9:M31")
        ->getNumberFormat()->setFormatCode('0;-0;;@'); // zero section rỗng

            // Ẩn số 0 cho 3 ô phần trăm (hiển thị 0.00% nếu muốn rỗng thì giữ null)
            
        $sh->getStyle("F19:M19")
        ->getNumberFormat()->setFormatCode('0.00;-0.00;;@'); // zero section rỗng
          $sh->getStyle("F25:M25")
       ->getNumberFormat()->setFormatCode('0.00;-0.00;;@'); // zero section rỗng
           $sh->getStyle("F30:M30")
       ->getNumberFormat()->setFormatCode('0.00;-0.00;;@'); // zero section rỗng
        // xuất
        return response()->streamDownload(function () use ($wb) {
            $w = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($wb, 'Xlsx');
            $w->setPreCalculateFormulas(false);
            $w->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

    }
    protected function queryMamnon0To5(string $academic, string $scheme, array $geo): array
    {
        [$y1] = array_map('intval', explode('-', $academic));
        // 0..5 tuổi => các năm sinh
        $ages  = range(0, 6);
        $years = array_map(fn($a) => $y1 - $a, $ages);


        $q = DB::table('students_off_track as sot')
        ->where('sot.academic_year', $academic)
        ->whereIn('sot.dob_year', $years)
        ->whereRaw('COALESCE(sot.is_deleted,0)=0');

        if (($geo['province_id'] ?? null) || ($geo['ward_id'] ?? null)) {
            if (($scheme ?? 'old') === 'old') {
                if (!empty($geo['province_id'])) $q->where('sot.selected_province_code_old', $this->geoService->geoCodeFromId($geo['province_id']) );
                if (!empty($geo['ward_id']))     $q->where('sot.selected_ward_code_old',   $this->geoService->geoCodeFromId($geo['ward_id'])   );
            } else {
                if (!empty($geo['province_id'])) $q->where('sot.selected_province_code_new', $this->geoService->geoCodeFromId($geo['province_id']));
                if (!empty($geo['ward_id']))     $q->where('sot.selected_ward_code_new',   $this->geoService->geoCodeFromId($geo['ward_id']));
            }
        }

        $rows = $q->selectRaw('
                    sot.dob_year AS yob,
                    COUNT(*) AS attending,
                    SUM(CASE WHEN sot.graduation_level_id = 1 THEN 1 ELSE 0 END) AS grad_gdmn
                ')
                ->groupBy('yob')
                ->orderBy('yob')
                ->get();

        // Chuẩn hóa về key theo TUỔI 0..6
        $byAge = [];
        foreach ($ages as $a) {
            $byAge[$a] = ['attending' => 0, 'grad_gdmn' => 0];
        }
        foreach ($rows as $r) {
            $age = $y1 - (int)$r->yob;
            if ($age >= 0 && $age <= 6) {
                $byAge[$age]['attending'] = (int)$r->attending;
                $byAge[$age]['grad_gdmn'] = (int)$r->grad_gdmn;
            }
        }

        // Tổng
        $total = [
            'attending' => array_sum(array_column($byAge, 'attending')),
            'grad_gdmn' => array_sum(array_column($byAge, 'grad_gdmn')),
        ];

        $traituyen= ['by_age' => $byAge, 'total' => $total];
        $peLatest = DB::table('person_educations as pe1')
            ->select('pe1.person_id', 'pe1.hoc_hai_buoi  as hoc_2_buoi')
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

        
        // dataset năm học hiện tại
        $v = DB::table('v_survey_detail_flat as v')
            
            ->leftJoin('person_mamnon as pm', 'pm.person_id', '=', 'v.person_id')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->leftJoinSub($peLatest, 'pe', function ($j) {
                $j->on('pe.person_id', '=', 'v.person_id');
            })
            ->where('v.academic_year', $academic)
            ->whereIn('v.dob_year', $years);

        // lọc địa bàn
        if (($geo['province_id'] ?? null) || ($geo['ward_id'] ?? null)) {
            if (($scheme ?? 'old') === 'old') {
                if (!empty($geo['province_id'])) $v->where('v.old_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $v->where('v.old_ward_id',     $geo['ward_id']);
            } else {
                if (!empty($geo['province_id'])) $v->where('v.new_province_id', $geo['province_id']);
                if (!empty($geo['ward_id']))     $v->where('v.new_ward_id',     $geo['ward_id']);
            }
        }

        // các điều kiện dùng lại
       $wardId = $geo['ward_id'] ?? null;

        // điều kiện tái sử dụng
        $isMinority     = "(v.ethnicity IS NOT NULL AND UPPER(v.ethnicity) <> 'KINH')";
        $disAny         = "(
            COALESCE(v.dis_VAN_DONG,0)+COALESCE(v.dis_NGHE_NOI,0)+COALESCE(v.dis_NHIN,0)+
            COALESCE(v.dis_THAN_KINH,0)+COALESCE(v.dis_TRI_TUE,0)+COALESCE(v.dis_HOC_TAP,0)+
            COALESCE(v.dis_TU_KY,0)+COALESCE(v.dis_KHAC,0)
        ) > 0 OR v.dis_has_certificate=1";
        $movedOutOrDead = "v.sc_name IN ('Chuyển đi','MOVED_OUT','Chết','DECEASED')";
        $isEnrolled     = "( v.education_block_code IS NOT NULL)";

        $rows = $v->selectRaw("
            v.dob_year AS yob,

            COUNT(DISTINCT v.person_id) AS total,
            COUNT(DISTINCT CASE WHEN CAST(v.is_female AS UNSIGNED)=1 THEN v.person_id END) AS female,
            COUNT(DISTINCT CASE WHEN $isMinority THEN v.person_id END) AS minority,

            COUNT(DISTINCT CASE WHEN $disAny THEN v.person_id END) AS disabled_any,
            COUNT(DISTINCT CASE WHEN v.dis_can_learn=1 THEN v.person_id END) AS disabled_can_learn,

            /* phải huy động */
            (
            COUNT(DISTINCT v.person_id)
            - COUNT(DISTINCT CASE WHEN $disAny THEN v.person_id END)
            - COUNT(DISTINCT CASE WHEN $movedOutOrDead THEN v.person_id END)
            ) AS must_mobilize,

            /* đến trường */
            COUNT(DISTINCT CASE WHEN $isEnrolled  THEN v.person_id END) AS enrolled,
            COUNT(DISTINCT CASE WHEN $isEnrolled AND (s.code IS NULL OR s.code NOT LIKE 'NGOAI_TINH%') AND s.new_ward_id =  ? THEN v.person_id END) AS enrolled_local,

            /* cơ cấu nhóm đến trường */
            COUNT(DISTINCT CASE WHEN $isEnrolled AND CAST(v.is_female AS UNSIGNED)=1 THEN v.person_id END) AS enrolled_girl,
            COUNT(DISTINCT CASE WHEN $isEnrolled AND $isMinority THEN v.person_id END) AS enrolled_minority,
            COUNT(DISTINCT CASE WHEN $isEnrolled AND COALESCE(pm.dan_toc_chuan_bi_tv,0)=1 THEN v.person_id END) AS prepared_tv,

            /* khuyết tật được tiếp cận GD */
            COUNT(DISTINCT CASE WHEN ($disAny) AND $isEnrolled THEN v.person_id END) AS disabled_access,

            /* 2 buổi & hoàn thành CT */
            COUNT(DISTINCT CASE WHEN $isEnrolled AND (COALESCE(pm.hoc_2_buoi,0)=1 OR COALESCE(pe.hoc_2_buoi,0)=1) THEN v.person_id END) AS two_sessions,
            COUNT(DISTINCT CASE WHEN  $isEnrolled and (COALESCE(pm.hoan_thanh_chuong_trinh_gdmn,0)=1 OR (v.graduation_level_name='MN')) THEN v.person_id END) AS grad_gdmn,

            /* rời khỏi hệ thống */
            COUNT(DISTINCT CASE WHEN v.sc_name='Chuyển đến' THEN v.person_id END) AS moved_in,
            COUNT(DISTINCT CASE WHEN v.sc_name IN ('Chết','DECEASED') THEN v.person_id END) AS died,
            COUNT(DISTINCT CASE WHEN v.sc_name='Chuyển đi' THEN v.person_id END) AS moved_out
        ", [  $wardId])
        ->groupBy('yob')
        ->orderBy('yob')
        ->get();

        // map về 0..5 tuổi
        $byAge = [];
        foreach ($rows as $r) {
            $age = $y1 - (int)$r->yob; // 0..5
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
              
                'enrolled_girl'      => (int)$r->enrolled_girl,
                'enrolled_minority'  => (int)$r->enrolled_minority,
                'prepared_tv'        => (int)$r->prepared_tv,
                'two_sessions'       => (int)$r->two_sessions,
                'grad_gdmn'          => (int)$r->grad_gdmn,
                 'move_out'          => (int)$r->moved_out,
                  'move_in'          => (int)$r->moved_in,
                  'died'          => (int)$r->died,
            ];
        }

        // điền 0 cho tuổi thiếu
        foreach ($ages as $a) {
            $byAge[$a] = $byAge[$a] ?? array_fill_keys(array_keys($byAge[array_key_first($byAge)] ?? [
                'total'=>0,'female'=>0,'minority'=>0,'disabled_any'=>0,'disabled_can_learn'=>0,'disabled_access'=>0,
                'must_mobilize'=>0,'enrolled'=>0,'enrolled_local'=>0,'enrolled_trai_tuyen'=>0,
                'enrolled_girl'=>0,'enrolled_minority'=>0,'prepared_tv'=>0,'two_sessions'=>0,'grad_gdmn'=>0
            ]), 0);
        }
        ksort($byAge);

        // tổng 0–5
        $sum = array_fill_keys(array_keys(current($byAge)), 0);
        foreach ($byAge as $vals) foreach ($vals as $k=>$v) $sum[$k] += $v;

        $dungtuyen = ['by_age'=>$byAge, 'total'=>$sum];

        // Bảo đảm cấu trúc mảng con tồn tại và gán giá trị theo tuổi 0..5
        foreach (range(0, 5) as $age) {
            if (!isset($dungtuyen['by_age'][$age]) || !is_array($dungtuyen['by_age'][$age])) {
                $dungtuyen['by_age'][$age] = []; // tạo nếu thiếu
            }

            $dungtuyen['by_age'][$age]['traituyen_attend'] = (int)($traituyen['by_age'][$age]['attending']  ?? 0);
            $dungtuyen['by_age'][$age]['traituyen_grad']   = (int)($traituyen['by_age'][$age]['grad_gdmn']  ?? 0);
        }

        // Tổng cộng (nếu traituyen.total có sẵn thì lấy luôn, nếu không thì tự cộng dồn theo tuổi)
        $sumAtt = $traituyen['total']['attending']  ?? null;
        $sumGrad= $traituyen['total']['grad_gdmn']  ?? null;

        if ($sumAtt === null || $sumGrad === null) {
            $sumAtt = 0;
            $sumGrad = 0;
            foreach (range(0, 5) as $age) {
                $sumAtt  += (int)($traituyen['by_age'][$age]['attending'] ?? 0);
                $sumGrad += (int)($traituyen['by_age'][$age]['grad_gdmn'] ?? 0);
            }
        }

        if (!isset($dungtuyen['total']) || !is_array($dungtuyen['total'])) {
            $dungtuyen['total'] = [];
        }
        $dungtuyen['total']['traituyen_attend'] = (int)$sumAtt;
        $dungtuyen['total']['traituyen_grad']   = (int)$sumGrad;

        // (tuỳ chọn) nếu bạn muốn dùng ngay các field chuẩn cũ trong export:
        // Map sang các khoá mà phần export đang đọc (vd: 'enrolled_trai_tuyen')
        foreach (range(0, 5) as $age) {
            $dungtuyen['by_age'][$age]['enrolled_trai_tuyen'] = $dungtuyen['by_age'][$age]['traituyen_attend'];
        }
        $dungtuyen['total']['enrolled_trai_tuyen'] = $dungtuyen['total']['traituyen_attend'];
        $dungtuyen['age_cols'] = $ages; // thêm mảng tuổi (nếu cần)
// dd($dungtuyen);
        // dd($traituyen,$dungtuyen);
        return $dungtuyen;
    }

}