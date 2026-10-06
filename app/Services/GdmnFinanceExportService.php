<?php

namespace App\Services;

use App\Models\GdmnFinance;
use App\Models\GeoUnit;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class GdmnFinanceExportService
{
    /**
     * Export dữ liệu GDMN Finance ra file Excel - sử dụng template
     */
    public function export($year, $provinceId = null, $wardId = null): Spreadsheet
    {
        // Load template
        $templatePath = base_path('resources/excel/1.5.MN.MauTinhHinhTaiChinh.xlsx');
        
        if (!file_exists($templatePath)) {
            throw new \Exception('Không tìm thấy file template: ' . $templatePath);
        }
        
        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Build query
        $query = GdmnFinance::with(['province', 'ward'])
            ->where('year', $year);

        if ($provinceId) {
            $query->where('province_id', $provinceId);
        }

        if ($wardId) {
            $query->where('ward_id', $wardId);
        }

        $finances = $query->orderBy('ward_id')
            ->orderBy('main_item')
            ->orderBy('sub_item')
            ->get();

        // Lấy thông tin tỉnh/xã
        $provinceName = '';
        $wardName = '';
        
        if ($finances->isEmpty()) {
            // Nếu không có dữ liệu, lấy trực tiếp từ ID
            if ($provinceId) {
                $province = GeoUnit::find($provinceId);
                $provinceName = $province->name ?? '';
            }
            if ($wardId) {
                $ward = GeoUnit::find($wardId);
                $wardName = $ward->name ?? '';
                
                // Nếu có ward mà chưa có province name, lấy từ ward
                if (!$provinceName && $ward) {
                    $provinceId = $ward->getProvinceId();
                    $province = GeoUnit::find($provinceId);
                    $provinceName = $province->name ?? '';
                }
            }
        } else {
            // Có dữ liệu, lấy từ record đầu tiên
            $firstRecord = $finances->first();
            $provinceName = $firstRecord->province->name ?? '';
            $wardName = $firstRecord->ward->name ?? '';
            
            // Nếu province name vẫn trống, thử lấy từ ward
            if (!$provinceName && $firstRecord->ward) {
                $provinceId = $firstRecord->ward->getProvinceId();
                $province = GeoUnit::find($provinceId);
                $provinceName = $province->name ?? '';
            }
        }

        // Điền thông tin header (luôn điền, dù có dữ liệu hay không)
        $sheet->setCellValue('C2', $provinceName ?: '');
        $sheet->getStyle('C2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        
        $sheet->setCellValue('C3', $wardName ?: '');
        $sheet->getStyle('C3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        
        $sheet->setCellValue('C4', $year);
        $sheet->getStyle('C4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        
        // Điền năm học vào E6:E7 (merged cell)
        $sheet->setCellValue('E6', $year);
        $sheet->getStyle('E6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Nếu không có dữ liệu, chỉ trả về template với thông tin cơ bản
        if ($finances->isEmpty()) {
            return $spreadsheet;
        }

        // Map dữ liệu vào các dòng tương ứng
        // Row 9: Mục 1 (main_item = 1, sub_item = null)
        // Row 10: Mục 1.1 (main_item = 1, sub_item = 1)
        // Row 11: Mục 1.2 (main_item = 1, sub_item = 2)
        // Row 12: Mục 1.3 (main_item = 1, sub_item = 3)
        // Row 13: Mục 1.4 (main_item = 1, sub_item = 4)
        // Row 14: Mục 2 (main_item = 2, sub_item = null)
        // Row 15: Mục 3 (main_item = 3, sub_item = null)
        // Row 16: Mục 4 (main_item = 4, sub_item = null)
        // Row 17: Mục 5 (main_item = 5, sub_item = null)
        // Row 18: Mục 6 (main_item = 6, sub_item = null)
        // Row 19: Mục 6.1 (main_item = 6, sub_item = 1)
        // Row 20: Mục 6.2 (main_item = 6, sub_item = 2)
        // Row 21: Mục 6.3 (main_item = 6, sub_item = 3)
        // Row 22: Mục 7 (main_item = 7, sub_item = null)
        // Row 23: Mục 7.1 (main_item = 7, sub_item = 1)
        // Row 24: Mục 7.2 (main_item = 7, sub_item = 2)
        // Row 25: Mục 7.3 (main_item = 7, sub_item = 3)

        $rowMapping = [
            '1-null' => 9,
            '1-1' => 10,
            '1-2' => 11,
            '1-3' => 12,
            '1-4' => 13,
            '2-null' => 14,
            '3-null' => 15,
            '4-null' => 16,
            '5-null' => 17,
            '6-null' => 18,
            '6-1' => 19,
            '6-2' => 20,
            '6-3' => 21,
            '7-null' => 22,
            '7-1' => 23,
            '7-2' => 24,
            '7-3' => 25,
        ];

        foreach ($finances as $finance) {
            $key = $finance->main_item . '-' . ($finance->sub_item ?? 'null');
            
            if (isset($rowMapping[$key])) {
                $row = $rowMapping[$key];
                // Điền số tiền vào cột E (column index 5)
                $sheet->setCellValue('E' . $row, $finance->amount ?? '');
                // Điền ghi chú vào cột F (column index 6)
                $sheet->setCellValue('F' . $row, $finance->note ?? '');
            }
        }

        return $spreadsheet;
    }

    /**
     * Tạo file Excel mẫu để import - sử dụng template có sẵn
     */
    public function exportTemplate(?string $provinceName = null, ?string $wardName = null, ?int $year = null): Spreadsheet
    {
        // Load template từ resources/excel
        $templatePath = base_path('resources/excel/1.5.MN.MauTinhHinhTaiChinh.xlsx');
        
        if (!file_exists($templatePath)) {
            throw new \Exception('Không tìm thấy file template: ' . $templatePath);
        }
        
        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();
        
        // Điền thông tin vào C2 (Tỉnh), C3 (Xã), C4 (Năm học)
        if ($provinceName) {
            $sheet->setCellValue('C2', $provinceName);
            $sheet->getStyle('C2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
        
        if ($wardName) {
            $sheet->setCellValue('C3', $wardName);
            $sheet->getStyle('C3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
        
        if ($year) {
            $sheet->setCellValue('C4', $year);
            $sheet->getStyle('C4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            
            // Điền năm học vào E6:E7 (merged cell)
            $sheet->setCellValue('E6', $year);
            $sheet->getStyle('E6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        
        return $spreadsheet;
    }

}


