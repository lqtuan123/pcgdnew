<?php

namespace App\Reports;

use App\Models\GdmnFinance;
use App\Models\GeoUnit;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class GdmnFinanceReport extends BaseReport
{
    /**
     * Export báo cáo tài chính GDMN
     */
    public function export(Request $request)
    {
        // Lấy tham số từ request
        $year = $request->input('year'); // Năm học ví dụ: 2024-2025
        $provinceId = $request->input('geo.province_id');
        $wardId = $request->input('geo.ward_id');
        
        // Parse năm học để lấy năm đầu
        [$startYear, $endYear] = $this->parseAcademic($year);
        
        // Lấy 5 năm từ nhỏ đến lớn (2020 → 2024)
        $years = [];
        for ($i = 4; $i >= 0; $i--) {
            $years[] = $startYear - $i;
        }
        
        // Load template
        $templatePath = resource_path('excel/1.5.MN.TinhHinhTaiChinh.xlsx');
        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();
        
        // Lấy tên tỉnh và xã
        $provinceName = '';
        $wardName = '';
        
        if ($provinceId) {
            $province = GeoUnit::find($provinceId);
            $provinceName = $province ? $province->name : '';
        }
        
        if ($wardId) {
            $ward = GeoUnit::find($wardId);
            $wardName = $ward ? $ward->name : '';
        }
        
        // Fill header
        $this->setCellValue($sheet, 'C2', $provinceName);
        $this->setCellValue($sheet, 'C3', $wardName);
        $this->setCellValue($sheet, 'C4', $year);
        
        // Fill years vào hàng 7 (F7:J7)
        $cols = ['F', 'G', 'H', 'I', 'J'];
        foreach ($years as $index => $y) {
            if (isset($cols[$index])) {
                $this->setCellValue($sheet, $cols[$index].'7', $y);
            }
        }
        
        // Mapping row -> main_item và sub_item
        $rowMapping = [
            9  => ['main_item' => 1, 'sub_item' => null],
            10 => ['main_item' => 1, 'sub_item' => 1],
            11 => ['main_item' => 1, 'sub_item' => 2],
            12 => ['main_item' => 1, 'sub_item' => 3],
            13 => ['main_item' => 1, 'sub_item' => 4],
            14 => ['main_item' => 2, 'sub_item' => null],
            15 => ['main_item' => 3, 'sub_item' => null],
            16 => ['main_item' => 4, 'sub_item' => null],
            17 => ['main_item' => 5, 'sub_item' => null],
            18 => ['main_item' => 6, 'sub_item' => null],
            19 => ['main_item' => 6, 'sub_item' => 1],
            20 => ['main_item' => 6, 'sub_item' => 2],
            21 => ['main_item' => 6, 'sub_item' => 3],
            22 => ['main_item' => 7, 'sub_item' => null],
            23 => ['main_item' => 7, 'sub_item' => 1],
            24 => ['main_item' => 7, 'sub_item' => 2],
            25 => ['main_item' => 7, 'sub_item' => 3],
        ];
        
        // Mảng lưu notes cho từng row
        $notesPerRow = [];
        
        // Fill data cho từng năm
        foreach ($years as $index => $y) {
            $col = $cols[$index];
            
            // Query dữ liệu cho năm này
            $query = GdmnFinance::where('year', $y);
            
            if ($wardId) {
                $query->where('ward_id', $wardId);
            } elseif ($provinceId) {
                $query->where('province_id', $provinceId);
            }
            
            $finances = $query->get();
            
            // Fill data theo mapping
            foreach ($rowMapping as $row => $map) {
                $mainItem = $map['main_item'];
                $subItem = $map['sub_item'];
                
                // Tìm bản ghi phù hợp
                $record = $finances->first(function ($item) use ($mainItem, $subItem) {
                    return $item->main_item == $mainItem && $item->sub_item == $subItem;
                });
                
                // Fill vào cell
                if ($record) {
                    $value = $record->amount;
                    
                    // Chuyển string thành số (hỗ trợ cả dấu phẩy và chấm)
                    $numValue = str_replace(',', '.', $value);
                    $numValue = floatval($numValue);
                    
                    // Nếu là % thì hiển thị dạng số với 2 chữ số thập phân
                    if ($record->unit === '%') {
                        $this->setCellValue($sheet, $col.$row, $numValue, 2, ['decimals' => 2]);
                    } else {
                        // Tự động xác định số chữ số thập phân cần thiết
                        $decimals = 0;
                        if (strpos($value, '.') !== false || strpos($value, ',') !== false) {
                            // Có phần thập phân -> giữ nguyên
                            $parts = explode('.', str_replace(',', '.', $value));
                            if (isset($parts[1])) {
                                $decimals = strlen(rtrim($parts[1], '0')); // Loại bỏ số 0 cuối
                            }
                        }
                        
                        $this->setCellValue($sheet, $col.$row, $numValue, $decimals, [
                            'decimals' => $decimals,
                            'blankIfZero' => true
                        ]);
                    }
                    
                    // Thu thập ghi chú
                    if (!empty($record->note)) {
                        if (!isset($notesPerRow[$row])) {
                            $notesPerRow[$row] = [];
                        }
                        $notesPerRow[$row][] = $y . ': ' . trim($record->note);
                    }
                } else {
                    // Không có dữ liệu -> để trống
                    $this->setCellValue($sheet, $col.$row, null);
                }
            }
        }
        
        // Fill ghi chú vào cột K
        foreach ($notesPerRow as $row => $notes) {
            $combinedNote = implode('; ', $notes);
            $this->setCellValue($sheet, 'K'.$row, $combinedNote, 0, ['wrap' => true]);
        }
        
        // Thêm tổng vào cột E (từ hàng 9 đến 25)
        foreach ($rowMapping as $row => $map) {
            $mainItem = $map['main_item'];
            
            // Lấy đơn vị từ database hoặc dùng mặc định
            $unit = GdmnFinance::UNITS[$mainItem] ?? 'Tr.đồng';
            
            // Đặc biệt cho E14 và E15: tính trung bình chia cho 5 (null = 0)
            if ($row == 14 || $row == 15) {
                // Công thức trung bình: =(F{row}+G{row}+H{row}+I{row}+J{row})/5
                $formula = "=(F{$row}+G{$row}+H{$row}+I{$row}+J{$row})/5";
            } else {
                // Công thức tổng: =SUM(F{row}:J{row})
                $formula = "=SUM(F{$row}:J{$row})";
            }
            
            // Nếu là % thì format 2 chữ số thập phân
            if ($unit === '%') {
                $this->setCellValue($sheet, 'E'.$row, $formula, 2, ['decimals' => 2]);
            } else {
                $this->setCellValue($sheet, 'E'.$row, $formula, 0, ['blankIfZero' => true]);
            }
        }
        
        // Set alignment cho các cells
        $sheet->getStyle('C2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('C3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('C4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        
        // Căn giữa cột E từ hàng 9 đến 25
        $sheet->getStyle('E9:E25')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Ghi đè thông tin vào H28:K28
        $this->setCellValue($sheet, 'H28', $wardName . ', ngày ... tháng ... năm ...', 0, [
            'align' => Alignment::HORIZONTAL_CENTER,
            'fontSize' => 8
        ]);
        
        // Set font size và alignment cho H28
        $sheet->getStyle('H28')->getFont()->setSize(8);
        $sheet->getStyle('H28')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Tên file từ template
        $filename = $this->getExportFilename($templatePath);
        
        // Download / Stream Response
        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}

