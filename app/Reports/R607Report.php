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

class R607Report extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    public function export(Request $req)
    {
        set_time_limit(900); // 15 phút để xử lý toàn bộ dữ liệu
        ini_set('max_execution_time', 900);
        
        // ===== 1) Input
        $academic = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        $scheme   = (string) $req->input('scheme', 'old');
        $geo      = (array)  $req->input('geo', []);

        if (method_exists(Calculation::getInstance(), 'setCalculationEnabled')) {
            Calculation::getInstance()->setCalculationEnabled(false);
        }

        // ===== 2) Template
        $cfg = [
            'path'      => resource_path('excel/6.7.TK.DanTocThieuSo.xlsx'),
            'header'    => [
                'province' => 'C1',  // ô ghi Tỉnh/Thành
                'district' => 'C2',  // ô ghi Phường/Xã
                'yearline' => 'H3',  // ô ghi dòng "Năm ...." (cỡ 12)
            ],
            'startRow'  => 10,       // dòng đầu ghi dữ liệu
            'lastCol'   => 'DT',     // cột cuối cho 60 tuổi (theo file template)
        ];
        $wb = IOFactory::load($cfg['path']);
        $sh = $wb->getActiveSheet();

        // Header
        $sh->setCellValue($cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        $sh->setCellValue($cfg['header']['district'], $this->geoService->geoNameFromId($geo['ward_id'] ?? $geo['district_id'] ?? null));
        if (!empty($cfg['header']['yearline'])) {
            $sh->setCellValue($cfg['header']['yearline'], 'Năm '.$academic);
            // Format năm học - cỡ 12
            $sh->getStyle($cfg['header']['yearline'])->getFont()->setSize(12);
        }

        // Format header cells - Tahoma size 8, bold
        $sh->getStyle($cfg['header']['province'])->getFont()->setName('Tahoma')->setSize(8)->setBold(true);
        $sh->getStyle($cfg['header']['district'])->getFont()->setName('Tahoma')->setSize(8)->setBold(true);

        $startRow = $cfg['startRow'];
        
        // ===== 3) Đo thời gian chi tiết để tìm nút cổ chai
        $startTotal = microtime(true);

        // 1) Query
        $start = microtime(true);
        $allData = $this->getAllDataForEthnicMinorities($academic, $scheme, $geo);
        $timeQuery = microtime(true) - $start;

        // 2) Process & build rows
        $start = microtime(true);
        $lastRow = $this->fillEthnicMinorityStatistics($sh, $allData, $cfg, $academic);
        $timeBuild = microtime(true) - $start;
        

        // 3) Styling & Signature TRƯỚC KHI SAVE
        $start = microtime(true);
        
        // Thêm phần ký tên TRƯỚC khi format
        $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
        $this->addSignatureSection($sh, $wardName, $lastRow);

        // Format toàn bộ dữ liệu - TỐI ƯU: 1 lần gọi style cho toàn bộ (BAO GỒM TỔNG CỘNG)
        if ($lastRow > $cfg['startRow']) {
            $dataRange = "A{$cfg['startRow']}:{$cfg['lastCol']}{$lastRow}";
            
            // Font và alignment cho toàn bộ
            $sh->getStyle($dataRange)
               ->getFont()->setName('Tahoma')->setSize(8);
            $sh->getStyle($dataRange)
               ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            
            // Căn giữa cho cột STT (BAO GỒM TỔNG CỘNG)
            $sh->getStyle("A{$cfg['startRow']}:A{$lastRow}")
               ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Căn trái cho cột Tên dân tộc (BAO GỒM TỔNG CỘNG)
            $sh->getStyle("B{$cfg['startRow']}:B{$lastRow}")
               ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            
            // Căn giữa cho TẤT CẢ cột số liệu (C-DT) - BAO GỒM TỔNG CỘNG
            $sh->getStyle("C{$cfg['startRow']}:{$cfg['lastCol']}{$lastRow}")
               ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Ẩn số 0 (để trống) cho toàn bảng dữ liệu
            $sh->getStyle($dataRange)
               ->getNumberFormat()->setFormatCode('0;-0;;@');
            
            // Kẻ khung & căn lề - BAO GỒM CẢ DÒNG TỔNG CỘNG
            $this->drawThinBorders($sh, $cfg['startRow'], $lastRow, 'A', $cfg['lastCol']);
            
        }
        
        $timeStyle = microtime(true) - $start;

        // 4) Writer: save to temp file SAU KHI STYLE HOÀN TẤT
        $start = microtime(true);
        $temp = storage_path('app/tmp_R607_'.time().'.xlsx');
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($wb, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        $writer->save($temp);
        $timeWrite = microtime(true) - $start;
        

        // ===== 5) Xuất file từ temp
        return response()->download($temp, $this->getExportFilename($cfg['path']))->deleteFileAfterSend(true);
    }

    /**
     * Lấy TẤT CẢ dữ liệu cần thiết 1 lần - RAW SQL TỐI ƯU
     */
    protected function getAllDataForEthnicMinorities(string $academic, string $scheme, array $geo): array
    {
        $academicYear = (int)substr($academic, 0, 4);
        
        // Raw SQL tối ưu - nhanh hơn Query Builder
        $sql = "
            SELECT 
                eth.name as ethnicity,
                p.dob_year,
                p.is_female
            FROM survey_form_details d
            INNER JOIN survey_forms f ON f.id = d.survey_form_id AND f.is_deleted = 0
            INNER JOIN people p ON p.id = d.person_id
            INNER JOIN ethnicities eth ON eth.id = p.ethnicity_id
            WHERE d.is_deleted = 0
              AND d.academic_year = ?
              AND eth.name != 'Kinh'
              AND p.dob_year >= ?
              AND p.dob_year <= ?
        ";
        
        $params = [$academic, $academicYear - 60, $academicYear];
        
        // Lọc địa bàn theo scheme
        if (($scheme ?? 'old') === 'old') {
            if (!empty($geo['province_id'])) {
                $sql .= " AND f.old_province_id = ?";
                $params[] = $geo['province_id'];
            }
            if (!empty($geo['district_id'])) {
                $sql .= " AND f.old_district_id = ?";
                $params[] = $geo['district_id'];
            }
            if (!empty($geo['ward_id'])) {
                $sql .= " AND f.old_ward_id = ?";
                $params[] = $geo['ward_id'];
            }
        } else {
            if (!empty($geo['province_id'])) {
                $sql .= " AND f.new_province_id = ?";
                $params[] = $geo['province_id'];
            }
            if (!empty($geo['ward_id'])) {
                $sql .= " AND f.new_ward_id = ?";
                $params[] = $geo['ward_id'];
            }
        }
        
        // Thêm ORDER BY để tối ưu - KHÔNG LIMIT để xử lý toàn bộ dữ liệu
        $sql .= " ORDER BY eth.name, p.dob_year";
        
        return DB::select($sql, $params);
    }

    /**
     * Xử lý và ghi thống kê dân tộc thiểu số vào Excel - TỐI ƯU BUILD TIME
     */
    protected function fillEthnicMinorityStatistics(Worksheet $sh, array $allData, array $cfg, string $academic): int
    {
        $academicYear = (int)substr($academic, 0, 4);

        // 1️⃣ Lấy danh sách dân tộc thiểu số (bỏ Kinh)
        $ethnicities = DB::table('ethnicities')
            ->where('name', '!=', 'Kinh')
            ->orderBy('name')
            ->pluck('name')
            ->toArray();

        // 2️⃣ Gom dữ liệu thống kê (tính trước trong RAM) - TỐI ƯU
        $stats = [];
        foreach ($allData as $row) {
            $age = $academicYear - $row->dob_year;
            if ($age >= 0 && $age <= 60) {
                $eth = $row->ethnicity;
                if (!isset($stats[$eth])) {
                    $stats[$eth] = array_fill(0, 61, ['total' => 0, 'female' => 0]);
                }
                $stats[$eth][$age]['total']++;
                if ($row->is_female) {
                    $stats[$eth][$age]['female']++;
                }
            }
        }

        // 3️⃣ Ghi trực tiếp vào Excel - KHÔNG DÙNG fromArray()
        $r = $cfg['startRow'];
        $stt = 1;
        $totalAll = [];

        foreach ($ethnicities as $eth) {
            // A: STT, B: Dân tộc
            $sh->setCellValue('A'.$r, $stt);
            $sh->setCellValue('B'.$r, $eth);

            // C-DT: Ghi trực tiếp từng cột
            for ($age = 0; $age <= 60; $age++) {
                $ts = $stats[$eth][$age]['total'] ?? 0;
                $fm = $stats[$eth][$age]['female'] ?? 0;
                
                $colTS = $this->getColumnLetter(3 + $age * 2);
                $colFemale = $this->getColumnLetter(4 + $age * 2);
                
                // Chỉ ghi nếu có dữ liệu
                if ($ts > 0) $sh->setCellValue($colTS.$r, $ts);
                if ($fm > 0) $sh->setCellValue($colFemale.$r, $fm);

                // cộng dồn tổng cuối
                $totalAll[$age]['total'] = ($totalAll[$age]['total'] ?? 0) + $ts;
                $totalAll[$age]['female'] = ($totalAll[$age]['female'] ?? 0) + $fm;
            }

            $r++;
            $stt++;
        }

        // 4️⃣ Thêm dòng "Tổng cộng"
        $sh->setCellValue('B'.$r, 'Tổng cộng');
        for ($age = 0; $age <= 60; $age++) {
            $colTS = $this->getColumnLetter(3 + $age * 2);
            $colFemale = $this->getColumnLetter(4 + $age * 2);
            
            $totalTS = $totalAll[$age]['total'] ?? 0;
            $totalFM = $totalAll[$age]['female'] ?? 0;
            
            // Chỉ ghi nếu có dữ liệu
            if ($totalTS > 0) $sh->setCellValue($colTS.$r, $totalTS);
            if ($totalFM > 0) $sh->setCellValue($colFemale.$r, $totalFM);
        }

        // 5️⃣ In đậm dòng Tổng cộng và return đúng lastRow
        $sh->getStyle("A{$r}:{$cfg['lastCol']}{$r}")
            ->getFont()->setBold(true);

        return $r; // Return dòng Tổng cộng làm lastRow
    }


    /**
     * Thêm phần ký tên vào cuối báo cáo
     */
    private function addSignatureSection(Worksheet $sh, string $wardName, int $lastRow): void
    {
        // Tính toán vị trí dòng ký tên (bắt đầu từ dòng dữ liệu cuối + 2 để có khoảng cách)
        $signatureStartRow = $lastRow + 2;
        
        // Tên xã, ngày ... tháng ... năm ... vào ô AA (trên TM. BAN CHỈ ĐẠO)
        $dateRow = $signatureStartRow;
        $sh->setCellValue('AA' . $dateRow, $wardName . ', ngày ... tháng ... năm ...');
        $sh->getStyle('AA' . $dateRow)->getFont()->setName('Tahoma')->setSize(8);
        $sh->getStyle('AA' . $dateRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(false);
        
        // 'NGƯỜI LẬP BIỂU' vào ô I, in đậm
        $signatureRow1 = $signatureStartRow + 1;
        $sh->setCellValue('I' . $signatureRow1, 'NGƯỜI LẬP BIỂU');
        $sh->getStyle('I' . $signatureRow1)->getFont()->setName('Tahoma')->setSize(8)->setBold(true);
        $sh->getStyle('I' . $signatureRow1)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(false);
        
        // 'TM. BAN CHỈ ĐẠO PCGD, XMC' vào ô AA, in đậm
        $sh->setCellValue('AA' . $signatureRow1, 'TM. BAN CHỈ ĐẠO PCGD, XMC');
        $sh->getStyle('AA' . $signatureRow1)->getFont()->setName('Tahoma')->setSize(8)->setBold(true);
        $sh->getStyle('AA' . $signatureRow1)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(false);
        
        // '(Ký và ghi rõ họ tên)' vào ô I, in nghiêng
        $signatureRow2 = $signatureStartRow + 2;
        $sh->setCellValue('I' . $signatureRow2, '(Ký và ghi rõ họ tên)');
        $sh->getStyle('I' . $signatureRow2)->getFont()->setName('Tahoma')->setSize(8)->setItalic(true);
        $sh->getStyle('I' . $signatureRow2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(false);
        
        // 'TRƯỞNG BAN' vào ô AA, in đậm
        $sh->setCellValue('AA' . $signatureRow2, 'TRƯỞNG BAN');
        $sh->getStyle('AA' . $signatureRow2)->getFont()->setName('Tahoma')->setSize(8)->setBold(true);
        $sh->getStyle('AA' . $signatureRow2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(false);
        
        // '(Ký tên, đóng dấu)' vào ô AA, in nghiêng
        $signatureRow3 = $signatureStartRow + 3;
        $sh->setCellValue('AA' . $signatureRow3, '(Ký tên, đóng dấu)');
        $sh->getStyle('AA' . $signatureRow3)->getFont()->setName('Tahoma')->setSize(8)->setItalic(true);
        $sh->getStyle('AA' . $signatureRow3)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(false);
        
    }

    /**
     * Vẽ border mỏng cho bảng dữ liệu - VIẾT LẠI ĐỂ ĐẢM BẢO HOẠT ĐỘNG
     */
    protected function drawThinBorders(Worksheet $sh, int $startRow, int $endRow, string $startCol, string $endCol): void
    {
        // Tạo border style
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ];
        
        // Áp dụng border cho toàn bộ range
        $range = "{$startCol}{$startRow}:{$endCol}{$endRow}";
        $sh->getStyle($range)->applyFromArray($borderStyle);
        
    }

    /**
     * Chuyển đổi số cột thành chữ cái Excel
     */
    private function getColumnLetter(int $columnNumber): string
    {
        $letter = '';
        while ($columnNumber > 0) {
            $columnNumber--;
            $letter = chr(65 + ($columnNumber % 26)) . $letter;
            $columnNumber = intval($columnNumber / 26);
        }
        return $letter;
    }
}
