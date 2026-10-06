<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\StudentsOffTrack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\NamedRange;
use Illuminate\Support\Facades\Log;

use App\Services\AccessGuardService;

class StudentsOffTrackExportController extends Controller
{
    private $dropdownRanges = [];

    public function __construct(
        private AccessGuardService $guard
    ) {}

    public function export(Request $request)
    {
        // Tăng thời gian thực thi để tránh timeout khi xuất Excel
        @ini_set('max_execution_time', 180);
        @set_time_limit(180);

        // Enforce quyền xuất học sinh trái tuyến và ép địa bàn phân quyền
        $ctx = $this->guard->enforce($request, 'quan_ly_hoc_sinh_trai_tuyen', 'view', [
            'force_scheme' => null,
        ]);
        $user = $ctx['user'];

        // Default năm học giống StudentsOffTrackIndexController
        $currentYear = date('Y');
        $currentMonth = date('n');
        $defaultAcademicYear = $currentMonth >= 9
            ? ($currentYear . '-' . ($currentYear + 1))
            : (($currentYear - 1) . '-' . $currentYear);

        $academicYear = $request->get('academic_year', $defaultAcademicYear);
        $search = $request->get('search', '');
        $scheme = $ctx['scheme'] ?? $request->get('scheme', 'old');

        // Lấy thông tin geo từ context đã ép theo phân quyền tài khoản
        $geoData = array_merge((array) $request->get('geo', []), (array) $ctx['geo']);
        $provinceId = $geoData['province_id'] ?? null;
        $districtId = $geoData['district_id'] ?? null;
        $wardId = $geoData['ward_id'] ?? null;
        $villageId = $geoData['village_id'] ?? null;
        
        // Lấy code từ id của GeoUnit
        $provinceCode = $provinceId ? \App\Models\GeoUnit::find($provinceId)?->code : null;
        $districtCode = $districtId ? \App\Models\GeoUnit::find($districtId)?->code : null;
        $wardCode = $wardId ? \App\Models\GeoUnit::find($wardId)?->code : null;
        $villageCode = $villageId ? \App\Models\GeoUnit::find($villageId)?->code : null;

        $query = StudentsOffTrack::query()
            ->with(['ethnicity', 'religion', 'priorityCategory', 'educationBlock', 'school', 'graduationLevel'])
            ->where('academic_year', $academicYear)
            ->where('is_deleted', false);

        // Lọc theo khu vực hành chính dựa trên scheme (hỗ trợ khớp cả new/old code)
        if ($scheme === 'old') {
            if ($villageCode) {
                $query->where(fn($q) => $q->where('selected_village_code_old', $villageCode)->orWhere('selected_village_code_new', $villageCode));
            } elseif ($wardCode) {
                $query->where(fn($q) => $q->where('selected_ward_code_old', $wardCode)->orWhere('selected_ward_code_new', $wardCode));
            } elseif ($districtCode) {
                $query->where('selected_district_code_old', $districtCode);
            } elseif ($provinceCode) {
                $query->where(fn($q) => $q->where('selected_province_code_old', $provinceCode)->orWhere('selected_province_code_new', $provinceCode));
            }
        } else {
            if ($villageCode) {
                $query->where(fn($q) => $q->where('selected_village_code_new', $villageCode)->orWhere('selected_village_code_old', $villageCode));
            } elseif ($wardCode) {
                $query->where(fn($q) => $q->where('selected_ward_code_new', $wardCode)->orWhere('selected_ward_code_old', $wardCode));
            } elseif ($provinceCode) {
                $query->where(fn($q) => $q->where('selected_province_code_new', $provinceCode)->orWhere('selected_province_code_old', $provinceCode));
            }
        }

        // Tìm kiếm theo tên, họ đệm, số định danh
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('ten', 'like', "%{$search}%")
                  ->orWhere('ho_dem', 'like', "%{$search}%")
                  ->orWhere('identity_number', 'like', "%{$search}%")
                  ->orWhereRaw("concat(coalesce(ho_dem,''),' ',ten) like ?", ["%{$search}%"]);
            });
        }

        $students = $query->orderBy('created_at', 'asc')->get();


        // Bỏ kiểm tra dữ liệu - vẫn xuất file Excel ngay cả khi không có dữ liệu

        // Tạo file Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Dòng 1: Tiêu đề nhóm
        $this->addGroupHeaders($sheet, $scheme);

        // Dòng 2: Tên cột
        $this->addColumnHeaders($sheet, $scheme);

        // Dòng 3: Dãy số tăng dần
        $this->addSequenceNumbers($sheet, $scheme);

        // Từ dòng 4: Dữ liệu
        $this->addDataRows($sheet, $students, $scheme);

        // Tạo sheet ẩn chứa dữ liệu dropdown
        $this->createDropdownDataSheet($spreadsheet);

        // Định dạng file
        $this->formatSheet($sheet, $scheme);

        // Thêm dropdown cho các cột có dữ liệu tham chiếu
        $this->addDropdowns($sheet, $scheme);


        // Xuất file
        $filename = 'hoc_sinh_trai_tuyen_' . $academicYear . '_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        try {
            $writer = new Xlsx($spreadsheet);
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer->save('php://output');
            exit;
        } catch (\PhpOffice\PhpSpreadsheet\Writer\Exception $e) {
            Log::error('Error exporting Excel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo file Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    private function addGroupHeaders($sheet, $scheme)
    {
        $col = 1;
        
        // ID
        $sheet->setCellValueByColumnAndRow($col++, 1, '');
        
        // Thông tin đối tượng (11 cột)
        $sheet->mergeCellsByColumnAndRow(2, 1, 12, 1);
        $sheet->setCellValueByColumnAndRow(2, 1, 'Thông tin đối tượng');
        
        $col = 13;
        
        // Thông tin nơi đang ở (luôn 2 cột)
        $sheet->mergeCellsByColumnAndRow($col, 1, $col + 1, 1);
        $sheet->setCellValueByColumnAndRow($col, 1, 'Thông tin nơi đang ở');
        $col += 2;
        
        // Thông tin học tập (10 cột)
        $sheet->mergeCellsByColumnAndRow($col, 1, $col + 9, 1);
        $sheet->setCellValueByColumnAndRow($col, 1, 'Thông tin học tập');
        $col += 10;
        
        // Khuyết tật (10 cột)
        $sheet->mergeCellsByColumnAndRow($col, 1, $col + 9, 1);
        $sheet->setCellValueByColumnAndRow($col, 1, 'Khuyết tật');
        $col += 10;
        
        // Hoàn cảnh (2 cột)
        $sheet->mergeCellsByColumnAndRow($col, 1, $col + 1, 1);
        $sheet->setCellValueByColumnAndRow($col, 1, 'Hoàn cảnh');
    }

    private function addColumnHeaders($sheet, $scheme)
    {
        // Luôn xuất ra 37 cột với cấu trúc chuẩn (bỏ cột Quận/Huyện)
        $headers = [
            'ID',
            'STT',
            'Họ đệm',
            'Tên',
            'Ngày',
            'Tháng',
            'Năm sinh',
            'Số định danh cá nhân',
            'Nữ',
            'Dân tộc',
            'Tôn giáo',
            'Diện ưu tiên',
            'Tỉnh/Thành',
            'Phường/Xã', // Luôn chỉ có 2 cột địa chính
            'Khối học',
            'Lớp học',
            'Học 2 buổi/ngày',
            'Học đầy đủ theo quy định',
            'Mã trường',
            'Bậc tốt nghiệp',
            'Bổ túc',
            'Năm tốt nghiệp',
            'Bỏ học lớp',
            'Bỏ học năm',
            'Khuyết tật vận động',
            'Khuyết tật nghe, nói',
            'Khuyết tật nhìn',
            'Khuyết tật thần kinh, tâm thần',
            'Khuyết tật trí tuệ',
            'Khuyết tật học tập',
            'Tự kỷ',
            'Khuyết tật khác',
            'Có chứng nhận khuyết tật',
            'Khả năng học tập',
            'Hoàn cảnh đặc biệt',
            'Chi tiết hoàn cảnh đặc biệt'
        ];

        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 2, $header);
        }
    }

    private function addSequenceNumbers($sheet, $scheme)
    {
        // Luôn có 36 cột
        $totalCols = 36;
        
        for ($col = 1; $col <= $totalCols; $col++) {
            $sheet->setCellValueByColumnAndRow($col, 3, $col);
        }
    }

    private function addDataRows($sheet, $students, $scheme)
    {
        $row = 4;
        
        foreach ($students as $index => $student) {
            $col = 1;
            
            // ID
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->id);
            
            // STT
            $sheet->setCellValueByColumnAndRow($col++, $row, $index + 1);
            
            // Thông tin đối tượng
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->ho_dem);
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->ten);
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->dob_day);
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->dob_month);
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->dob_year);
            // Đặt số định danh dưới dạng text để giữ nguyên số 0 đầu
            $identityCell = $sheet->getCellByColumnAndRow($col, $row);
            $identityCell->setValueExplicit($student->identity_number, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $col++;
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->is_female ? 'x' : '');
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->ethnicity?->name);
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->religion?->name);
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->priorityCategory?->name);
            
            // Thông tin nơi đang ở - luôn xuất theo chuẩn mới (2 cột)
            if ($scheme === 'old') {
                // Khi lọc theo hành chính cũ, map sang chuẩn mới
                $sheet->setCellValueByColumnAndRow($col++, $row, $student->getStayProvinceName('old'));
                $sheet->setCellValueByColumnAndRow($col++, $row, $student->getStayWardName('old'));
            } else {
                // Khi lọc theo hành chính mới, xuất bình thường
                $sheet->setCellValueByColumnAndRow($col++, $row, $student->getStayProvinceName('new'));
                $sheet->setCellValueByColumnAndRow($col++, $row, $student->getStayWardName('new'));
            }
            
            // Thông tin học tập
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->educationBlock?->name);
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->class_label);
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->hoc_hai_buoi ? 'v' : '');
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->hoc_9_10_buoi_tuan ? 'v' : '');
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->school?->code);
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->graduationLevel?->name);
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->is_bo_tuc ? 'x' : '');
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->graduation_year);
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->bo_hoc_lop);
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->bo_hoc_nam);
            
            // Khuyết tật
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->disability_van_dong ? 'x' : '');
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->disability_nghe_noi ? 'x' : '');
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->disability_nhin ? 'x' : '');
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->disability_than_kinh ? 'x' : '');
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->disability_tri_tue ? 'x' : '');
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->disability_hoc_tap ? 'x' : '');
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->disability_tu_ky ? 'x' : '');
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->disability_khac ? 'x' : '');
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->disability_has_certificate ? 'x' : '');
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->disability_can_learn ? 'x' : '');
            
            // Hoàn cảnh
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->special_circumstance_name);
            $sheet->setCellValueByColumnAndRow($col++, $row, $student->special_circumstance_detail);
            
            $row++;
        }
    }

    private function formatSheet($sheet, $scheme)
    {
        // Luôn có 36 cột
        $totalCols = 36;
        $lastRow = $sheet->getHighestRow();
        
        $lastCol = Coordinate::stringFromColumnIndex($totalCols);
        
        // Định dạng dòng 1 (tiêu đề nhóm) - mỗi nhóm một màu
        $this->formatGroupHeaders($sheet, $scheme);
        
        // Định dạng dòng 2 (tên cột) - màu giống với tiêu đề nhóm tương ứng
        $this->formatColumnHeaders($sheet, $scheme);
        
        // Định dạng dòng 3 (dãy số) - màu xanh dương như học sinh mầm non
        $sheet->getStyle('A3:' . $lastCol . '3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'name' => 'Tahoma', 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2196F3']], // Xanh dương
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        
        // Giãn dòng cho các tiêu đề
        $sheet->getRowDimension(1)->setRowHeight(25); // Dòng tiêu đề nhóm
        $sheet->getRowDimension(2)->setRowHeight(20); // Dòng tên cột
        $sheet->getRowDimension(3)->setRowHeight(18); // Dòng số thứ tự
        
        // Định dạng dữ liệu với border cho tất cả các cột từ dòng 4 trở xuống đến cột 36
        // Thêm border cho hàng ngàn dòng để có thể nhập dữ liệu mới
        $maxDataRow = max($lastRow, 10000); // Ít nhất 10000 dòng
        $sheet->getStyle('A4:' . $lastCol . $maxDataRow)->applyFromArray([
            'font' => ['size' => 11, 'name' => 'Times New Roman'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        
        // Định dạng đặc biệt cho các cột cụ thể
        $this->formatSpecialColumns($sheet, $maxDataRow);
        
        // Tự động điều chỉnh độ rộng cột
        for ($col = 1; $col <= $totalCols; $col++) {
            $column = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Cố định dòng 1-3 và freeze 4 cột đầu từ dòng 4
        $sheet->freezePane('E4'); // Freeze từ cột E (cột 5) từ dòng 4, nghĩa là cố định 4 cột đầu (A-D)
    }

    private function formatGroupHeaders($sheet, $scheme)
    {
        $col = 1;
        
        // ID (1 cột) - Màu xanh lá (từ mầm non)
        $sheet->getStyle(Coordinate::stringFromColumnIndex($col) . '1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'name' => 'Tahoma', 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']], // Xanh lá
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $col++;
        
        // Thông tin đối tượng (11 cột) - Màu xanh lá (từ mầm non)
        $endCol = $col + 10;
        $sheet->mergeCellsByColumnAndRow($col, 1, $endCol, 1);
        $sheet->getStyle(Coordinate::stringFromColumnIndex($col) . '1:' . Coordinate::stringFromColumnIndex($endCol) . '1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'name' => 'Tahoma', 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']], // Xanh lá
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $col = $endCol + 1;
        
        // Thông tin nơi đang ở (luôn 2 cột) - Màu vàng (từ mầm non)
        $endCol = $col + 1;
        $sheet->mergeCellsByColumnAndRow($col, 1, $endCol, 1);
        $sheet->getStyle(Coordinate::stringFromColumnIndex($col) . '1:' . Coordinate::stringFromColumnIndex($endCol) . '1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'name' => 'Tahoma', 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFEB3B']], // Vàng
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $col = $endCol + 1;
        
        // Thông tin học tập (10 cột) - Màu xanh lá (từ mầm non)
        $endCol = $col + 9;
        $sheet->mergeCellsByColumnAndRow($col, 1, $endCol, 1);
        $sheet->getStyle(Coordinate::stringFromColumnIndex($col) . '1:' . Coordinate::stringFromColumnIndex($endCol) . '1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']], // Xanh lá
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $col = $endCol + 1;
        
        // Khuyết tật (10 cột) - Màu vàng (từ mầm non)
        $endCol = $col + 9;
        $sheet->mergeCellsByColumnAndRow($col, 1, $endCol, 1);
        $sheet->getStyle(Coordinate::stringFromColumnIndex($col) . '1:' . Coordinate::stringFromColumnIndex($endCol) . '1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'name' => 'Tahoma', 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFEB3B']], // Vàng
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $col = $endCol + 1;
        
        // Hoàn cảnh (2 cột) - Màu xanh lá (từ mầm non)
        $endCol = $col + 1;
        $sheet->mergeCellsByColumnAndRow($col, 1, $endCol, 1);
        $sheet->getStyle(Coordinate::stringFromColumnIndex($col) . '1:' . Coordinate::stringFromColumnIndex($endCol) . '1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']], // Xanh lá
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
    }

    private function formatColumnHeaders($sheet, $scheme)
    {
        $col = 1;
        
        // ID (1 cột) - Màu xanh lá (giống dòng 1)
        $sheet->getStyle(Coordinate::stringFromColumnIndex($col) . '2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'name' => 'Tahoma', 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']], // Xanh lá
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $col++;
        
        // Thông tin đối tượng (11 cột) - Màu xanh lá (giống dòng 1)
        $endCol = $col + 10;
        $sheet->getStyle(Coordinate::stringFromColumnIndex($col) . '2:' . Coordinate::stringFromColumnIndex($endCol) . '2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'name' => 'Tahoma', 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']], // Xanh lá
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $col = $endCol + 1;
        
        // Thông tin nơi đang ở (luôn 2 cột) - Màu vàng (giống dòng 1)
        $endCol = $col + 1;
        $sheet->getStyle(Coordinate::stringFromColumnIndex($col) . '2:' . Coordinate::stringFromColumnIndex($endCol) . '2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'name' => 'Tahoma', 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFEB3B']], // Vàng
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $col = $endCol + 1;
        
        // Thông tin học tập (10 cột) - Màu xanh lá (giống dòng 1)
        $endCol = $col + 9;
        $sheet->getStyle(Coordinate::stringFromColumnIndex($col) . '2:' . Coordinate::stringFromColumnIndex($endCol) . '2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'name' => 'Tahoma', 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']], // Xanh lá
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $col = $endCol + 1;
        
        // Khuyết tật (10 cột) - Màu vàng (giống dòng 1)
        $endCol = $col + 9;
        $sheet->getStyle(Coordinate::stringFromColumnIndex($col) . '2:' . Coordinate::stringFromColumnIndex($endCol) . '2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'name' => 'Tahoma', 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFEB3B']], // Vàng
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $col = $endCol + 1;
        
        // Hoàn cảnh (2 cột) - Màu xanh lá (giống dòng 1)
        $endCol = $col + 1;
        $sheet->getStyle(Coordinate::stringFromColumnIndex($col) . '2:' . Coordinate::stringFromColumnIndex($endCol) . '2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'name' => 'Tahoma', 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']], // Xanh lá
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
    }

    private function formatSpecialColumns($sheet, $maxDataRow)
    {
        // Các cột checkbox (x) và (v) - căn giữa
        $checkboxColumns = [9, 17, 18, 21, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34]; // Nữ, Học 2 buổi, Học đầy đủ, Bổ túc, các loại khuyết tật, khả năng học tập
        
        foreach ($checkboxColumns as $colIndex) {
            $column = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getStyle("{$column}4:{$column}{$maxDataRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
            ]);
        }
        
        // Cột bậc tốt nghiệp (cột 20) - căn giữa
        $graduationColumn = Coordinate::stringFromColumnIndex(20);
        $sheet->getStyle("{$graduationColumn}4:{$graduationColumn}{$maxDataRow}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);
        
        // Cột ngày (cột 5) và tháng (cột 6) - căn giữa và format 2 chữ số
        $dayColumn = Coordinate::stringFromColumnIndex(5);
        $monthColumn = Coordinate::stringFromColumnIndex(6);
        
        $sheet->getStyle("{$dayColumn}4:{$dayColumn}{$maxDataRow}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);
        $sheet->getStyle("{$dayColumn}4:{$dayColumn}{$maxDataRow}")->getNumberFormat()->setFormatCode('00');
        
        $sheet->getStyle("{$monthColumn}4:{$monthColumn}{$maxDataRow}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);
        $sheet->getStyle("{$monthColumn}4:{$monthColumn}{$maxDataRow}")->getNumberFormat()->setFormatCode('00');
        
        // Cột số định danh (cột 8) - kiểu text
        $identityColumn = Coordinate::stringFromColumnIndex(8);
        $sheet->getStyle("{$identityColumn}4:{$identityColumn}{$maxDataRow}")->getNumberFormat()->setFormatCode('@');
    }

    private function createDropdownDataSheet($spreadsheet)
    {
        // Tạo sheet ẩn chứa dữ liệu dropdown
        $dropdownSheet = $spreadsheet->createSheet();
        $dropdownSheet->setTitle('DropdownData');
        $dropdownSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
        
        // Dân tộc
        $ethnicities = \App\Models\Ethnicity::pluck('name')->toArray();
        if (count($ethnicities) > 0) {
            foreach ($ethnicities as $i => $name) {
                $dropdownSheet->setCellValue("A" . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('EthnicityList', $dropdownSheet, '$A$1:$A$' . count($ethnicities))
            );
            $this->dropdownRanges['ethnicity'] = 'EthnicityList';
        }
        
        // Tôn giáo
        $religions = \App\Models\Religion::pluck('name')->toArray();
        if (count($religions) > 0) {
            foreach ($religions as $i => $name) {
                $dropdownSheet->setCellValue("B" . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('ReligionList', $dropdownSheet, '$B$1:$B$' . count($religions))
            );
            $this->dropdownRanges['religion'] = 'ReligionList';
        }
        
        // Diện ưu tiên
        $priorityCategories = \App\Models\PriorityCategory::pluck('name')->toArray();
        if (count($priorityCategories) > 0) {
            foreach ($priorityCategories as $i => $name) {
                $dropdownSheet->setCellValue("C" . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('PriorityList', $dropdownSheet, '$C$1:$C$' . count($priorityCategories))
            );
            $this->dropdownRanges['priority'] = 'PriorityList';
        }
        
        // Khối học
        $educationBlocks = \App\Models\EducationBlock::pluck('name')->toArray();
        if (count($educationBlocks) > 0) {
            foreach ($educationBlocks as $i => $name) {
                $dropdownSheet->setCellValue("D" . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('EducationBlockList', $dropdownSheet, '$D$1:$D$' . count($educationBlocks))
            );
            $this->dropdownRanges['education_block'] = 'EducationBlockList';
        }
        
        // Bậc tốt nghiệp
        $graduationLevels = \App\Models\GraduationLevel::pluck('name')->toArray();
        if (count($graduationLevels) > 0) {
            foreach ($graduationLevels as $i => $name) {
                $dropdownSheet->setCellValue("E" . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('GraduationList', $dropdownSheet, '$E$1:$E$' . count($graduationLevels))
            );
            $this->dropdownRanges['graduation'] = 'GraduationList';
        }
        
        // Tạo dữ liệu địa lý có liên kết
        $this->createGeoData($dropdownSheet, $spreadsheet);
        
        // Bỏ học lớp (cùng dữ liệu với Khối học)
        $this->dropdownRanges['drop_out_class'] = 'EducationBlockList';
        
        // Hoàn cảnh đặc biệt
        $specialCircumstances = \App\Models\SpecialCircumstance::pluck('name')->toArray();
        if (count($specialCircumstances) > 0) {
            foreach ($specialCircumstances as $i => $name) {
                $dropdownSheet->setCellValue("I" . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('SpecialCircumstanceList', $dropdownSheet, '$I$1:$I$' . count($specialCircumstances))
            );
            $this->dropdownRanges['special_circumstance'] = 'SpecialCircumstanceList';
        }
        
        // Quay lại sheet chính
        $spreadsheet->setActiveSheetIndex(0);
    }

    private function createGeoData($dropdownSheet, $spreadsheet)
    {
        $row = 1;
        $col = 11; // Cột K

        // === TỈNH/THÀNH PHỐ ===
        $provinces = \App\Models\GeoUnit::where('geo_type_id', 1)->get();
        if ($provinces->count() > 0) {
            $colIndex = $col;
            foreach ($provinces as $province) {
                $dropdownSheet->setCellValueByColumnAndRow($colIndex, $row, $province->name);
                $colIndex++;
            }
            
            // Tạo Named Range cho danh sách tỉnh
            $spreadsheet->addNamedRange(
                new NamedRange('ProvinceList', $dropdownSheet, '$K$1:$' . Coordinate::stringFromColumnIndex($colIndex - 1) . '$1')
            );
            $this->dropdownRanges['province'] = 'ProvinceList';
        }

        // === QUẬN/HUYỆN ===
        $districts = \App\Models\GeoUnit::where('geo_type_id', 2)->get();
        if ($districts->count() > 0) {
            $row = 3;
            $colIndex = $col;
            foreach ($districts as $district) {
                $dropdownSheet->setCellValueByColumnAndRow($colIndex, $row, $district->name);
                $colIndex++;
            }
            
            // Tạo Named Range cho danh sách quận/huyện
            $spreadsheet->addNamedRange(
                new NamedRange('DistrictList', $dropdownSheet, '$K$3:$' . Coordinate::stringFromColumnIndex($colIndex - 1) . '$3')
            );
            $this->dropdownRanges['district'] = 'DistrictList';
        }

        // === XÃ/PHƯỜNG ===
        $wards = \App\Models\GeoUnit::where('geo_type_id', 3)->get();
        if ($wards->count() > 0) {
            $row = 5;
            $colIndex = $col;
            foreach ($wards as $ward) {
                $dropdownSheet->setCellValueByColumnAndRow($colIndex, $row, $ward->name);
                $colIndex++;
            }
            
            // Tạo Named Range cho danh sách xã/phường
            $spreadsheet->addNamedRange(
                new NamedRange('WardList', $dropdownSheet, '$K$5:$' . Coordinate::stringFromColumnIndex($colIndex - 1) . '$5')
            );
            $this->dropdownRanges['ward'] = 'WardList';
        }
    }
    

    private function addDropdowns($sheet, $scheme)
    {
        // Áp dụng dropdown cho toàn bộ cột (từ dòng 4 đến dòng 10000 để có thể thêm mới)
        $maxRow = 10000;
        
        // Các cột x hoặc trống (Nữ, Bổ túc, các loại khuyết tật, khả năng học tập)
        // Luôn có 36 cột với cấu trúc chuẩn
        $xOrEmptyColumns = [9, 21, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34]; // Nữ, Bổ túc, các loại khuyết tật, khả năng học tập
        
        foreach ($xOrEmptyColumns as $colIndex) {
            $this->addXOrEmptyDropdown($sheet, $colIndex, $maxRow);
        }
        
        // Các cột v hoặc trống (Học 2 buổi/ngày, Học đầy đủ theo quy định)
        // Luôn có 37 cột với cấu trúc chuẩn
        $vOrEmptyColumns = [17, 18]; // Học 2 buổi, Học đầy đủ
        
        foreach ($vOrEmptyColumns as $colIndex) {
            $this->addVOrEmptyDropdown($sheet, $colIndex, $maxRow);
        }
        
        // Các cột chọn hoặc trống (Dân tộc, Tôn giáo, Diện ưu tiên, v.v.)
        $this->addReferenceDropdown($sheet, 10, $maxRow, 'ethnicity'); // Dân tộc
        $this->addReferenceDropdown($sheet, 11, $maxRow, 'religion'); // Tôn giáo
        $this->addReferenceDropdown($sheet, 12, $maxRow, 'priority'); // Diện ưu tiên
        
        // Các cột địa chính - Dropdown đơn giản (luôn có 37 cột với cấu trúc chuẩn)
        $this->addReferenceDropdown($sheet, 13, $maxRow, 'province'); // Tỉnh/Thành
        $this->addReferenceDropdown($sheet, 14, $maxRow, 'ward'); // Phường/Xã
        
        // Khối học
        $this->addReferenceDropdown($sheet, 15, $maxRow, 'education_block');
        
        // Bậc tốt nghiệp
        $this->addReferenceDropdown($sheet, 20, $maxRow, 'graduation');
        
        // Bỏ học lớp (tham chiếu đến bảng EducationBlock)
        $this->addReferenceDropdown($sheet, 23, $maxRow, 'education_block');
        
        // Hoàn cảnh đặc biệt
        $this->addReferenceDropdown($sheet, 35, $maxRow, 'special_circumstance');
    }
    
    private function addXOrEmptyDropdown($sheet, $colIndex, $maxRow)
    {
        $column = Coordinate::stringFromColumnIndex($colIndex);
        
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Input error');
        $validation->setError('Value is not in list.');
        $validation->setPromptTitle('Pick from list');
        $validation->setPrompt('Please pick a value from the drop-down list.');
        $validation->setFormula1('"x"');
        
        // Áp dụng cho cả cột (cách 1: danh sách tĩnh)
        $range = "{$column}4:{$column}{$maxRow}";
        $sheet->setDataValidation($range, $validation);
    }
    
    private function addVOrEmptyDropdown($sheet, $colIndex, $maxRow)
    {
        $column = Coordinate::stringFromColumnIndex($colIndex);
        
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Input error');
        $validation->setError('Value is not in list.');
        $validation->setPromptTitle('Pick from list');
        $validation->setPrompt('Please pick a value from the drop-down list.');
        $validation->setFormula1('"v"');
        
        // Áp dụng cho cả cột (cách 1: danh sách tĩnh)
        $range = "{$column}4:{$column}{$maxRow}";
        $sheet->setDataValidation($range, $validation);
    }
    
    private function addReferenceDropdown($sheet, $colIndex, $maxRow, $rangeKey)
    {
        if (!isset($this->dropdownRanges[$rangeKey])) {
            return;
        }
        
        $column = Coordinate::stringFromColumnIndex($colIndex);
        $namedRange = $this->dropdownRanges[$rangeKey];
        
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Input error');
        $validation->setError('Value is not in list.');
        $validation->setPromptTitle('Pick from list');
        $validation->setPrompt('Please pick a value from the drop-down list.');
        $validation->setFormula1("={$namedRange}");
        
        // Clone validation cho từng cell
        for ($row = 4; $row <= $maxRow; $row++) {
            $cell = $sheet->getCell("{$column}{$row}");
            $cell->setDataValidation(clone $validation);
        }
    }

    public function exportTemplate(Request $request)
    {
        // Tăng thời gian thực thi để tránh timeout khi xuất file mẫu Excel
        @ini_set('max_execution_time', 180);
        @set_time_limit(180);

        // Kiểm tra quyền xuất template học sinh trái tuyến
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_hoc_sinh_trai_tuyen', 'view')) {
            abort(403, 'Bạn không có quyền xuất template học sinh trái tuyến.');
        }

        // Default năm học giống StudentsOffTrackIndexController (tháng 9 trở đi tính sang năm học mới)
        $currentYear = date('Y');
        $currentMonth = date('n');
        $defaultAcademicYear = $currentMonth >= 9
            ? ($currentYear . '-' . ($currentYear + 1))
            : (($currentYear - 1) . '-' . $currentYear);

        $academicYear = $request->get('academic_year', $defaultAcademicYear);
        $search = $request->get('search', '');
        $scheme = $request->get('scheme', 'old');

        // Lấy thông tin geo từ request - giống hệt controller index
        $geoData = $request->get('geo', []);
        $provinceId = $geoData['province_id'] ?? null;
        $districtId = $geoData['district_id'] ?? null;
        $wardId = $geoData['ward_id'] ?? null;
        $villageId = $geoData['village_id'] ?? null;
        
        // Lấy code từ id của GeoUnit
        $provinceCode = $provinceId ? \App\Models\GeoUnit::find($provinceId)?->code : null;
        $districtCode = $districtId ? \App\Models\GeoUnit::find($districtId)?->code : null;
        $wardCode = $wardId ? \App\Models\GeoUnit::find($wardId)?->code : null;
        $villageCode = $villageId ? \App\Models\GeoUnit::find($villageId)?->code : null;

        // Tạo query giống hệt method export nhưng không lấy dữ liệu
        $query = StudentsOffTrack::query()
            ->with(['ethnicity', 'religion', 'priorityCategory', 'educationBlock', 'school', 'graduationLevel'])
            ->where('academic_year', $academicYear);

        // Lọc theo khu vực hành chính dựa trên scheme
        if ($scheme === 'old') {
            // Hành chính cũ: Tỉnh → Huyện → Xã → Làng
            if ($villageCode) {
                // Chọn đến làng: lấy chính xác làng đó
                $query->where('selected_village_code_old', $villageCode);
            } elseif ($wardCode) {
                // Chọn đến xã: lấy tất cả làng trong xã đó
                $query->where('selected_ward_code_old', $wardCode);
            } elseif ($districtCode) {
                // Chọn đến huyện: lấy tất cả xã trong huyện đó
                $query->where('selected_district_code_old', $districtCode);
            } elseif ($provinceCode) {
                // Chọn đến tỉnh: lấy tất cả huyện trong tỉnh đó
                $query->where('selected_province_code_old', $provinceCode);
            }
        } else {
            // Hành chính mới: Tỉnh → Xã → Làng (bỏ huyện)
            if ($villageCode) {
                // Chọn đến làng: lấy chính xác làng đó
                $query->where('selected_village_code_new', $villageCode);
            } elseif ($wardCode) {
                // Chọn đến xã: lấy tất cả làng trong xã đó
                $query->where('selected_ward_code_new', $wardCode);
            } elseif ($provinceCode) {
                // Chọn đến tỉnh: lấy tất cả xã trong tỉnh đó
                $query->where('selected_province_code_new', $provinceCode);
            }
        }

        // Tìm kiếm theo tên, họ đệm, số định danh
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('ten', 'like', "%{$search}%")
                  ->orWhere('ho_dem', 'like', "%{$search}%")
                  ->orWhere('identity_number', 'like', "%{$search}%")
                  ->orWhereRaw("concat(coalesce(ho_dem,''),' ',ten) like ?", ["%{$search}%"]);
            });
        }

        // Lấy dữ liệu rỗng (không có học sinh nào)
        $students = collect(); // Tạo collection rỗng

        // Tạo file Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Dòng 1: Tiêu đề nhóm
        $this->addGroupHeaders($sheet, $scheme);

        // Dòng 2: Tên cột
        $this->addColumnHeaders($sheet, $scheme);

        // Dòng 3: Dãy số tăng dần
        $this->addSequenceNumbers($sheet, $scheme);

        // Từ dòng 4: Dữ liệu (rỗng)
        $this->addDataRows($sheet, $students, $scheme);

        // Tạo sheet ẩn chứa dữ liệu dropdown
        $this->createDropdownDataSheet($spreadsheet);

        // Định dạng file (bao gồm cả formatColumnHeaders mới)
        $this->formatSheet($sheet, $scheme);

        // Thêm dropdown cho các cột có dữ liệu tham chiếu
        $this->addDropdowns($sheet, $scheme);

        // Xuất file
        $filename = 'Hoc_sinh_trai_tuyen_mau_' . $academicYear . '_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        try {
            $writer = new Xlsx($spreadsheet);
            
            return response()->streamDownload(function() use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]);
        } catch (\Exception $e) {
            Log::error('Export template error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo file mẫu: ' . $e->getMessage()
            ], 500);
        }
    }

    
}
