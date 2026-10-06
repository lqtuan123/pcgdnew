<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Models\School;

/**
 * Report: Sổ theo dõi phổ cập giáo dục mầm non cho trẻ em 5 tuổi
 *
 * - Handler hiển thị danh sách: list(Request $req)
 * - Handler xuất Excel:        exportExcel(Request $req)
 *
 * Gọi từ ReportEntryController thông qua config/reports.php.
 */
class PreschoolCoverageTrackingReport extends BaseReport
{
   
    /**
     * Hiển thị danh sách trẻ em <=5 tuổi theo tham số tìm kiếm.
     */
    public function list(Request $req)
    {
        // Build query chung
        ['qb' => $qb, 'context' => $ctx] = $this->preschoolChildrenQuery($req);

        // Lấy dữ liệu trang hiện tại
        $rows  = $qb->paginate($ctx['perPage'])->withQueryString();
        $items = collect($rows->items())->map(fn($r) => $this->mapChildRow($r, $ctx['scheme']));

        // Render view
        return view('front.reports.preschool_coverage_tracking_list', [
            'rows'             => $rows,
            'items'            => $items,
            'year'             => $ctx['year'],
            'scheme'           => $ctx['scheme'],
            'geo'              => $ctx['geo'],
            'sort_by'          => $ctx['sortBy'],
            'sort_dir'         => $ctx['sortDir'],
            'per_page'         => $ctx['perPage'],
            'education' => ['school_id' => $ctx['schoolId']],
            'report_group_id'  => (int) $req->report_group_id,
            'report_detail_id' => (int) $req->report_detail_id,
            'so_phieu'         => $req->input('so_phieu',''),
            'q'                => $req->input('q',''),
        ]);
    }

    /**
     * Method để gọi trực tiếp export từ URL
     */
    public function directExport(Request $req)
    {
        // Thêm các parameter cần thiết
        $req->merge([
            'year' => $req->input('year', '2024-2025'),
            'geo' => $req->input('geo', ['province_id' => 1, 'ward_id' => 1]),
            'report_group_id' => 1,
            'report_detail_id' => 8,
        ]);
        
        return $this->export($req);
    }

    /**
     * Xuất Excel theo template với 6 sheet theo độ tuổi
     */
    public function export(Request $req)
    {
        try {
            $year = $req->input('year');
            $geo = $req->input('geo', []);
            
            // Debug
            // \Log::info('Export started', ['year' => $year, 'geo' => $geo]);
            
            // Debug: Kiểm tra template file
            $templatePath = resource_path('excel/1.8.MN.SoPhoCap.TatCaNam.xlsx');
            // \Log::info('Template path: ' . $templatePath);
            // \Log::info('Template exists: ' . (file_exists($templatePath) ? 'YES' : 'NO'));
            
            // Load template Excel như báo cáo 2.7
            $templatePath = resource_path('excel/1.8.MN.SoPhoCap.TatCaNam.xlsx');
            
            if (!file_exists($templatePath)) {
                \Log::error('Template not found: ' . $templatePath);
                return response()->json(['error' => 'Template not found'], 404);
            }
            
            $wb = IOFactory::load($templatePath);
            // \Log::info('Template loaded successfully');
            
            // Lấy năm đầu của năm học (ví dụ: 2024-2025 -> 2024)
            $yearParts = explode('-', $year);
            $academicStartYear = (int) $yearParts[0];
            
            // Định nghĩa các sheet và độ tuổi tương ứng
            $sheets = [
                '5 tuổi' => 5,
                '4 tuổi' => 4, 
                '3 tuổi' => 3,
                '2 tuổi' => 2,
                '1 tuổi' => 1,
                '0 tuổi' => 0
            ];
            
            foreach ($sheets as $sheetName => $age) {
                $sh = $wb->getSheetByName($sheetName);
                if (!$sh) {
                    \Log::warning("Sheet '$sheetName' not found");
                    continue;
                }
                
                // Tính năm sinh cho độ tuổi này
                $birthYear = $academicStartYear - $age;
                
                // Ghi năm sinh vào ô C3
                $sh->setCellValue('C3', $birthYear);
                
                // Lấy dữ liệu cho độ tuổi này
                $data = $this->getChildrenByAge($req, $age);
                // \Log::info("Sheet '$sheetName': " . count($data) . " records");
                
                // Ghi dữ liệu vào sheet
                $lastDataRow = $this->writeDataToSheet($sh, $data, $geo, $year);
                
                // Thêm dòng ngày tháng năm
                $dateRow = $lastDataRow + 2;
                $wardName = $sh->getCell('C2')->getValue();
                $sh->setCellValue('M' . $dateRow, $wardName . ', ngày ... tháng ... năm ...');
            }
            
            // \Log::info('Excel generation completed');
            
            return response()->streamDownload(function () use ($wb) {
                $writer = IOFactory::createWriter($wb, 'Xlsx');
                $writer->setPreCalculateFormulas(false);
                $writer->save('php://output');
            }, $this->getExportFilename($templatePath), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Export error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Tạo header cho sheet Excel
     */
    private function createSheetHeader($sheet, string $sheetName, int $birthYear, array $geo): void
    {
        // Ghi thông tin tỉnh và xã
        $provinceName = $this->geoName('province_id', $geo);
        $wardName = $this->geoName('ward_id', $geo);
        
        $sheet->setCellValue('C1', $provinceName);
        $sheet->setCellValue('C2', $wardName);
        $sheet->setCellValue('C3', $birthYear);
        
        // Tạo header cho bảng dữ liệu (dòng 9)
        $headers = [
            'A9' => 'TT',
            'B9' => 'Số phiếu',
            'C9' => 'Họ và tên',
            'D9' => 'Ngày tháng năm sinh',
            'E9' => 'Nữ (X)',
            'F9' => 'Chỗ ở',
            'G9' => 'Dân tộc',
            'H9' => 'Họ và tên cha mẹ hoặc người đỡ đầu',
            'I9' => '', // Trống
            'J9' => '', // Trống
            'K9' => '', // Trống
            'L9' => '', // Trống
            'M9' => '', // Trống
            'N9' => 'Tên lớp',
            'O9' => 'Tên trường',
            'P9' => 'Tên các loại khuyết tật hay tên của hoàn cảnh đặc biệt',
        ];
        
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        
        // Format header
        $sheet->getStyle('A9:P9')->getFont()->setName('Tahoma')->setSize(8)->setBold(true);
        $sheet->getStyle('A9:P9')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle('A9:P9')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E6E6FA');
        
        // Format header info
        $sheet->getStyle('C1:C3')->getFont()->setName('Tahoma')->setSize(8);
    }
    private function getChildrenByAge(Request $req, int $age): array
    {
        $year = $req->input('year');
        $geo = $req->input('geo', []);
        $schoolId = $req->input('education.school_id');
        $soPhieu = $req->input('so_phieu');
        $q = $req->input('q');

        $qb = DB::table('v_survey_detail_flat as v')
            ->select([
                'v.detail_id as id',
                'v.so_phieu',
                DB::raw("CONCAT(v.ho_dem, ' ', v.ten) as full_name"),
                DB::raw("DATE_FORMAT(v.dob_date, '%d/%m/%Y') as birth_date"),
                DB::raw("CASE WHEN v.is_female = 1 THEN 'X' ELSE '' END as gender"),
                'v.addressline as address',
                'v.ethnicity',
                'v.contact_name as parent_name',
                'v.education_grade_name as class_name',
                'v.school_name',
                DB::raw("CASE 
                    WHEN v.dis_THAN_KINH = 1 THEN 'Khuyết tật thần kinh'
                    WHEN v.dis_TRI_TUE = 1 THEN 'Khuyết tật trí tuệ'
                    WHEN v.dis_HOC_TAP = 1 THEN 'Khuyết tật học tập'
                    WHEN v.dis_TU_KY = 1 THEN 'Khuyết tật tự kỷ'
                    WHEN v.dis_KHAC = 1 THEN 'Khuyết tật khác'
                    ELSE ''
                END as disabilities"),
            ]);

        // Filter theo năm học
        if ($year && $year !== 'all') {
            $qb->where('v.academic_year', $year);
        }

        // Filter theo độ tuổi cụ thể
        if ($year && $year !== 'all') {
            $yearParts = explode('-', $year);
            $academicStartYear = (int) $yearParts[0];
            $targetBirthYear = $academicStartYear - $age;
            $qb->whereYear('v.dob_date', $targetBirthYear);
        }

        // Filter geo
        if (!empty($geo['province_id'])) {
            $qb->where('v.new_province_id', (int)$geo['province_id']);
        }
        if (!empty($geo['ward_id'])) {
            $qb->where('v.new_ward_id', (int)$geo['ward_id']);
        }
        if (!empty($geo['village_id'])) {
            $qb->where('v.village_id', (int)$geo['village_id']);
        }

        // Filter theo số phiếu
        if ($soPhieu) {
            $qb->where('v.so_phieu', 'like', '%' . $soPhieu . '%');
        }

        // Tìm kiếm theo tên
        if ($q) {
            $qb->where(function($query) use ($q) {
                $query->where(DB::raw("CONCAT(v.ho_dem, ' ', v.ten)"), 'like', '%' . $q . '%')
                      ->orWhere('v.contact_name', 'like', '%' . $q . '%');
            });
        }

        // Sắp xếp theo tên tăng dần (tên trước, họ sau)
        $qb->orderBy('v.ten', 'asc')->orderBy('v.ho_dem', 'asc');

        return $qb->get()->toArray();
    }
    /**
     * Ghi dữ liệu vào sheet Excel
     */
    private function writeDataToSheet($sheet, array $data, array $geo, string $year): int
    {
        // Ghi thông tin tỉnh và xã vào template
        $provinceName = $this->geoName('province_id', $geo);
        $wardName = $this->geoName('ward_id', $geo);
        
        $sheet->setCellValue('C1', $provinceName);
        $sheet->setCellValue('C2', $wardName);
        
        // Ghi năm học vào các ô I6, J6, K6, L6, M6, N6 và I7, J7, K7, L7, M7, N7
        $yearParts = explode('-', $year);
        $startYear = (int) $yearParts[0];
        
        // Dòng 6: năm sinh của trẻ (5 tuổi -> 4 tuổi -> 3 tuổi -> 2 tuổi -> 1 tuổi -> 0 tuổi)
        $sheet->setCellValue('I6', $startYear - 5); // 2019 (5 tuổi)
        $sheet->setCellValue('J6', $startYear - 4); // 2020 (4 tuổi)
        $sheet->setCellValue('K6', $startYear - 3); // 2021 (3 tuổi)
        $sheet->setCellValue('L6', $startYear - 2); // 2022 (2 tuổi)
        $sheet->setCellValue('M6', $startYear - 1); // 2023 (1 tuổi)
        $sheet->setCellValue('N6', $startYear);     // 2024 (0 tuổi)
        
        // Dòng 7: năm sinh + 1
        $sheet->setCellValue('I7', $startYear - 4); // 2020
        $sheet->setCellValue('J7', $startYear - 3); // 2021
        $sheet->setCellValue('K7', $startYear - 2); // 2022
        $sheet->setCellValue('L7', $startYear - 1); // 2023
        $sheet->setCellValue('M7', $startYear);     // 2024
        $sheet->setCellValue('N7', $startYear + 1); // 2025
        
        // Bắt đầu từ dòng 10
        $startRow = 10;
        $row = $startRow;
        
        foreach ($data as $index => $child) {
            // Insert dòng mới nếu không phải dòng đầu tiên
            if ($index > 0) {
                $sheet->insertNewRowBefore($row);
            }
            
            // TT
            $sheet->setCellValue('A' . $row, $index + 1);
            
            // Số phiếu
            $sheet->setCellValue('B' . $row, $child->so_phieu ?? '');
            
            // Họ và tên
            $sheet->setCellValue('C' . $row, $child->full_name ?? '');
            
            // Ngày tháng năm sinh
            $sheet->setCellValue('D' . $row, $child->birth_date ?? '');
            
            // Nữ (X)
            $sheet->setCellValue('E' . $row, $child->gender ?? '');
            
            // Chỗ ở
            $sheet->setCellValue('F' . $row, $child->address ?? '');
            
            // Dân tộc
            $sheet->setCellValue('G' . $row, $child->ethnicity ?? '');
            
            // Họ và tên cha mẹ
            $sheet->setCellValue('H' . $row, $child->parent_name ?? '');
            
            // Cột I, J, K, L, M: tên lớp trong các năm trước
            $this->writeClassNamesForPreviousYears($sheet, $row, $child->id, $startYear);
            
            // Tên lớp
            $sheet->setCellValue('N' . $row, $child->class_name ?? '');
            
            // Tên trường
            $sheet->setCellValue('O' . $row, $child->school_name ?? '');
            
            // Khuyết tật/hoàn cảnh đặc biệt
            $sheet->setCellValue('P' . $row, $child->disabilities ?? '');
            
            // Áp dụng font Tahoma, cỡ 8
            $sheet->getStyle('A' . $row . ':P' . $row)->getFont()->setName('Tahoma')->setSize(8);
            
            // Thêm border cho dòng mới
            $sheet->getStyle('A' . $row . ':P' . $row)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            
            $row++;
        }
        
        // Trả về dòng cuối cùng có dữ liệu
        return $row - 1;
    }
    
    /**
     * Ghi tên lớp trong các năm trước vào cột I, J, K, L, M
     */
    private function writeClassNamesForPreviousYears($sheet, int $row, int $childId, int $startYear): void
    {
        // Lấy person_id từ detail_id
        $personInfo = DB::table('v_survey_detail_flat as v')
            ->select('v.person_id')
            ->where('v.detail_id', $childId)
            ->first();
        
        if (!$personInfo) {
            return;
        }
        
        $personId = $personInfo->person_id;
        
        // Các năm học tương ứng với cột I, J, K, L, M
        $years = [
            'I' => ($startYear - 5) . '-' . ($startYear - 4), // 2019-2020
            'J' => ($startYear - 4) . '-' . ($startYear - 3), // 2020-2021
            'K' => ($startYear - 3) . '-' . ($startYear - 2), // 2021-2022
            'L' => ($startYear - 2) . '-' . ($startYear - 1), // 2022-2023
            'M' => ($startYear - 1) . '-' . $startYear,       // 2023-2024
        ];
        
        foreach ($years as $column => $academicYear) {
            // Tìm tên lớp từ bảng person_educations
            $classInfo = DB::table('person_educations')
                ->select('class_label')
                ->where('person_id', $personId)
                ->where('academic_year', $academicYear)
                ->first();
            
            $className = $classInfo ? $classInfo->class_label : '';
            $sheet->setCellValue($column . $row, $className);
        }
    }
    
    /**
     * Build query chung cho cả list và exportExcel
     */
    private function preschoolChildrenQuery(Request $req): array
    {
        $year     = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        $scheme   = (string) $req->input('scheme', 'new');
        $geo      = (array)  $req->input('geo', []);
        $q        = trim((string) $req->input('q', ''));
        $soPhieu  = trim((string) $req->input('so_phieu', ''));
        $perPage  = (int) $req->input('per_page', 20);
        $sortBy   = (string) $req->input('sort_by', 'full_name');
        $sortDir  = strtolower((string) $req->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        
        // Ưu tiên tham số truyền vào; nếu không có thì lấy từ request
        $schoolId = $req->filled('education.school_id') ? (int) $req->input('education.school_id') : null;

        $qb = DB::table('v_survey_detail_flat as v')
            ->select([
                'v.detail_id as id',
                'v.so_phieu',
                DB::raw("CONCAT(v.ho_dem, ' ', v.ten) as full_name"),
                DB::raw("DATE_FORMAT(v.dob_date, '%d/%m/%Y') as birth_date"),
                DB::raw("CASE WHEN v.is_female = 1 THEN 'Nữ' ELSE 'Nam' END as gender"),
                'v.addressline as address',
                'v.ethnicity',
                'v.contact_name as parent_name',
                'v.education_grade_name as class_name',
                'v.school_name',
                'v.sc_name as special_circumstances',
                'v.sc_detail as special_circumstances_detail',
                'v.new_ward_name as ward_name',
                'v.new_province_name as province_name',
                // Khuyết tật - ghép các loại khuyết tật
                DB::raw("CASE 
                    WHEN v.dis_VAN_DONG = 1 THEN 'Khuyết tật vận động'
                    WHEN v.dis_NGHE_NOI = 1 THEN 'Khuyết tật nghe nói'
                    WHEN v.dis_NHIN = 1 THEN 'Khuyết tật nhìn'
                    WHEN v.dis_THAN_KINH = 1 THEN 'Khuyết tật thần kinh'
                    WHEN v.dis_TRI_TUE = 1 THEN 'Khuyết tật trí tuệ'
                    WHEN v.dis_HOC_TAP = 1 THEN 'Khuyết tật học tập'
                    WHEN v.dis_TU_KY = 1 THEN 'Khuyết tật tự kỷ'
                    WHEN v.dis_KHAC = 1 THEN 'Khuyết tật khác'
                    ELSE ''
                END as disabilities"),
            ]);

        // Filter theo năm học như báo cáo 1.7
        if ($year && $year !== 'all') {
            $qb->where('v.academic_year', $year);
        }

        // Filter trẻ <=5 tuổi dựa trên năm học được chọn
        if ($year && $year !== 'all') {
            // Lấy năm đầu của năm học (ví dụ: 2024-2025 -> 2024)
            $yearParts = explode('-', $year);
            $academicStartYear = (int) $yearParts[0];
            
            // Trẻ <=5 tuổi trong năm học này có năm sinh >= năm đầu - 5
            $targetBirthYear = $academicStartYear - 5;
            
            // Filter trẻ có năm sinh >= năm mục tiêu (<=5 tuổi)
            $qb->whereYear('v.dob_date', '>=', $targetBirthYear);
        }

        // Filter geo trực tiếp theo view v_survey_detail_flat
        if (!empty($geo['province_id'])) {
            $qb->where('v.new_province_id', (int)$geo['province_id']);
        }
        if (!empty($geo['ward_id'])) {
            $qb->where('v.new_ward_id', (int)$geo['ward_id']);
        }
        if (!empty($geo['village_id'])) {
            $qb->where('v.village_id', (int)$geo['village_id']);
        }

        // Lọc theo trường (tạm thời comment vì view chưa có school_id)
        // if ($schoolId) {
        //     $qb->where('v.school_id', $schoolId);
        // }

        // Lọc theo số phiếu
        if ($soPhieu) {
            $qb->where('v.so_phieu', 'like', '%' . $soPhieu . '%');
        }

        // Tìm kiếm theo tên
        if ($q) {
            $qb->where(function($query) use ($q) {
                $query->where(DB::raw("CONCAT(v.ho_dem, ' ', v.ten)"), 'like', '%' . $q . '%')
                      ->orWhere('v.contact_name', 'like', '%' . $q . '%');
            });
        }

        // Sắp xếp như báo cáo 1.7
        $qb->orderBy($sortBy, $sortDir);

        $context = [
            'year' => $year,
            'scheme' => $scheme,
            'geo' => $geo,
            'schoolId' => $schoolId,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'perPage' => $perPage,
        ];

        return ['qb' => $qb, 'context' => $context];
    }

    /**
     * Map dữ liệu từ database sang format hiển thị
     */
    private function mapChildRow($row, string $scheme): array
    {
        // Ghép khuyết tật và hoàn cảnh đặc biệt
        $specialInfo = [];
        if ($row->disabilities) {
            $specialInfo[] = $row->disabilities;
        }
        if ($row->special_circumstances) {
            $specialInfo[] = $row->special_circumstances;
        }
        if ($row->special_circumstances_detail) {
            $specialInfo[] = $row->special_circumstances_detail;
        }
        $specialInfoStr = implode(', ', $specialInfo);

        return [
            'id' => $row->id,
            'so_phieu' => $row->so_phieu,
            'full_name' => $row->full_name,
            'birth_date' => $row->birth_date,
            'gender' => $row->gender,
            'is_female' => $row->gender === 'Nữ' ? 'X' : '',
            'address' => $row->address,
            'ethnicity' => $row->ethnicity,
            'parent_name' => $row->parent_name,
            'class_name' => $row->class_name,
            'school_name' => $row->school_name,
            'special_info' => $specialInfoStr,
            'ward_name' => $row->ward_name,
            'province_name' => $row->province_name,
        ];
    }

    /**
     * Cấu hình template Excel
     */
    protected function templateCfgForScheme(string $scheme): array
    {
        return [
            'path' => resource_path('excel/1.8.MN.SoPhoCap.TatCaNam.xlsx'),
            'header' => [
                'province' => 'D1',
                'district' => 'D2',
                'ward' => 'D3',
                'school' => 'D4',
            ],
            'data_start_row' => 6,
            'data_start_col' => 'A',
        ];
    }

    /**
     * Ghi dữ liệu vào Excel
     */
    private function writeDataToExcel(Worksheet $sh, $rows, array $cfg): void
    {
        $dataStartRow = $cfg['data_start_row'];
        
        foreach ($rows as $index => $row) {
            $rowNum = $dataStartRow + $index;
            
            $sh->setCellValue('A' . $rowNum, $index + 1); // TT
            $sh->setCellValue('B' . $rowNum, $row->so_phieu);
            $sh->setCellValue('C' . $rowNum, $row->full_name);
            $sh->setCellValue('D' . $rowNum, $row->birth_date);
            $sh->setCellValue('E' . $rowNum, $row->gender === 'Nữ' ? 'X' : '');
            $sh->setCellValue('F' . $rowNum, $row->address);
            $sh->setCellValue('G' . $rowNum, $row->ethnicity);
            $sh->setCellValue('H' . $rowNum, $row->parent_name);
            $sh->setCellValue('I' . $rowNum, $row->class_name);
            $sh->setCellValue('J' . $rowNum, $row->school_name);
            
            // Ghép khuyết tật và hoàn cảnh đặc biệt
            $specialInfo = [];
            if ($row->disabilities) {
                $specialInfo[] = $row->disabilities;
            }
            if ($row->special_circumstances) {
                $specialInfo[] = $row->special_circumstances;
            }
            if ($row->special_circumstances_detail) {
                $specialInfo[] = $row->special_circumstances_detail;
            }
            $sh->setCellValue('K' . $rowNum, implode(', ', $specialInfo));
        }
    }

    /**
     * Lấy thông tin meta từ collection
     */
    private function metaFromCollection($coll, string $scheme): array
    {
        if ($coll->isEmpty()) {
            return ['province' => '', 'ward' => ''];
        }

        $first = $coll->first();
        return [
            'province' => $first->province_name ?? '',
            'ward' => $first->ward_name ?? '',
        ];
    }
}
