<?php

namespace App\Services;

use App\Models\StudentsOffTrack;
use App\Models\Religion;
use App\Models\PriorityCategory;
use App\Models\EducationBlock;
use App\Models\School;
use App\Models\GraduationLevel;
use App\Models\SpecialCircumstance;
use App\Models\Ethnicity;
use App\Models\GeoUnit;
use App\Services\GeoMappingService;
use App\Services\ExcelGeoMappingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StudentsOffTrackSimpleImportService
{
    protected GeoMappingService $geoMappingService;
    protected ExcelGeoMappingService $excelGeoMappingService;

    public function __construct(GeoMappingService $geoMappingService, ExcelGeoMappingService $excelGeoMappingService)
    {
        $this->geoMappingService = $geoMappingService;
        $this->excelGeoMappingService = $excelGeoMappingService;
    }

    /**
     * Import dữ liệu học sinh trái tuyến vào bảng mới
     */
    public function import(array $payload): array
    {
        $rows = $payload['rows'] ?? [];
        $academicYear = $payload['academic_year'] ?? '';
        $scheme = 'new'; // Luôn dùng hành chính mới cho cả Excel cũ và mới
        $geoData = $payload['geo'] ?? [];
        $importBatch = 'BATCH_' . time() . '_' . Str::random(6);
        $editorId = auth()->id(); // Lấy ID của user hiện tại

        // Generate geo codes từ geo filter selection
        $selectedId = $this->getSelectedGeoId($geoData, $scheme);
        $geoCodes = $this->geoMappingService->generateGeoCodesFromSelectedId($selectedId, $scheme);
        
        // Log để debug geo data
        Log::info('StudentsOffTrackSimpleImportService - Excel cũ Geo data debug:', [
            'geoData' => $geoData,
            'scheme' => $scheme,
            'selectedId' => $selectedId,
            'geoCodes' => $geoCodes,
            'selected_province_code_new' => $geoCodes['selected_province_code_new'] ?? 'NULL',
            'selected_ward_code_new' => $geoCodes['selected_ward_code_new'] ?? 'NULL',
            'selected_village_code_new' => $geoCodes['selected_village_code_new'] ?? 'NULL'
        ]);
        
        
        $stats = [
            'total_rows' => count($rows),
            'processed_count' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'ok_count' => 0,
            'fail_count' => 0,
            'row_errors' => [],
            'message_errors' => [],
            'errors' => [] // Giữ để tương thích với controller
        ];
        
        // Xử lý từng dòng (giống SurveyImportOrchestrator)
        foreach ($rows as $index => $row) {
            try {
                DB::beginTransaction();
                
                // Validate dòng này trước (trừ geo validation)
                $rowValidation = $this->validateSingleRow($row);
                $geoValidation = $this->validateGeoData($row);
                
                // Nếu có lỗi validation (trừ geo), bỏ dòng này
                if (!$rowValidation['valid']) {
                    // Thêm lỗi validation vào stats
                    $stats['row_errors'][] = $row['row_no'] ?? ($index + 1);
                    $stats['message_errors'][] = implode('; ', $rowValidation['errors']);
                    $stats['errors'][] = [
                        'row_no' => $row['row_no'] ?? ($index + 1),
                        'ho_ten' => trim(($row['ho_dem'] ?? '') . ' ' . ($row['ten'] ?? '')),
                        'errors' => $rowValidation['errors']
                    ];
                    $stats['fail_count']++;
                    DB::rollBack();
                    continue;
                }
                
                // Nếu chỉ có lỗi geo, vẫn tiếp tục nhưng ghi nhận lỗi
                if (!$geoValidation['valid']) {
                    $stats['row_errors'][] = $row['row_no'] ?? ($index + 1);
                    $stats['message_errors'][] = implode('; ', $geoValidation['errors']);
                    $stats['errors'][] = [
                        'row_no' => $row['row_no'] ?? ($index + 1),
                        'ho_ten' => trim(($row['ho_dem'] ?? '') . ' ' . ($row['ten'] ?? '')),
                        'errors' => $geoValidation['errors']
                    ];
                    // Không bỏ dòng, chỉ ghi nhận lỗi
                }
                
                $result = $this->processStudentRow($row, $academicYear, $geoCodes, $importBatch, $editorId);
                
                if ($result['action'] === 'created') {
                    $stats['created_count']++;
                } else {
                    $stats['updated_count']++;
                }
                
                $stats['processed_count']++;
                $stats['ok_count']++;
                
                DB::commit();
                
            } catch (\Throwable $e) {
                DB::rollBack();
                
                // Thêm lỗi import vào danh sách lỗi (format đơn giản như SurveyImportOrchestrator)
                $stats['row_errors'][] = $row['row_no'] ?? ($index + 1);
                $stats['message_errors'][] = 'Lỗi xử lý: ' . $e->getMessage();
                
                // Giữ format phức tạp để tương thích với controller
                $stats['errors'][] = [
                    'row_no' => $row['row_no'] ?? ($index + 1),
                    'ho_ten' => trim(($row['ho_dem'] ?? '') . ' ' . ($row['ten'] ?? '')),
                    'errors' => ['Lỗi xử lý: ' . $e->getMessage()]
                ];
                $stats['fail_count']++;
            }
        }
        
        // Log để debug
        Log::info('StudentsOffTrackSimpleImportService - Final stats:', $stats);
        
        return $stats;
    }

    /**
     * Import dữ liệu học sinh từ file Excel mới (có ID và bỏ Quận/Huyện)
     */
    public function importNew(array $payload): array
    {
        $rows = $payload['rows'] ?? [];
        $academicYear = $payload['academic_year'] ?? '';
        $scheme = 'new'; // Luôn là 'new' cho file Excel mới
        $geoData = $payload['geo'] ?? [];
        $importBatch = 'BATCH_NEW_' . time() . '_' . Str::random(6);
        $editorId = auth()->id();

        // Generate geo codes từ geo filter selection
        $selectedId = $this->getSelectedGeoId($geoData, $scheme);
        $geoCodes = $this->geoMappingService->generateGeoCodesFromSelectedId($selectedId, $scheme);
        
        // Log để debug geo data
        Log::info('StudentsOffTrackSimpleImportService - Import NEW Excel geo data debug:', [
            'geoData' => $geoData,
            'scheme' => $scheme,
            'selectedId' => $selectedId,
            'geoCodes' => $geoCodes
        ]);
        
        // Log chi tiết để debug
        Log::info('Geo codes detail:', [
            'selected_province_code_new' => $geoCodes['selected_province_code_new'] ?? 'NULL',
            'selected_ward_code_new' => $geoCodes['selected_ward_code_new'] ?? 'NULL'
        ]);
        
        $stats = [
            'total_rows' => count($rows),
            'processed_count' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'ok_count' => 0,
            'fail_count' => 0,
            'row_errors' => [],
            'message_errors' => [],
            'errors' => []
        ];
        
        // Xử lý từng dòng
        foreach ($rows as $index => $row) {
            try {
                DB::beginTransaction();
                
                // Validate dòng này trước (trừ geo validation)
                $rowValidation = $this->validateSingleRow($row);
                $geoValidation = $this->validateGeoData($row);
                
                // Nếu có lỗi validation (trừ geo), bỏ dòng này
                if (!$rowValidation['valid']) {
                    $stats['row_errors'][] = $row['row_no'] ?? ($index + 1);
                    $stats['message_errors'][] = implode('; ', $rowValidation['errors']);
                    $stats['errors'][] = [
                        'row_no' => $row['row_no'] ?? ($index + 1),
                        'ho_ten' => trim(($row['ho_dem'] ?? '') . ' ' . ($row['ten'] ?? '')),
                        'errors' => $rowValidation['errors']
                    ];
                    $stats['fail_count']++;
                    DB::rollBack();
                    continue;
                }
                
                // Validate dữ liệu select trước khi xử lý
                try {
                    $this->getReferenceIds($row);
                } catch (\Exception $e) {
                    // Lỗi từ getReferenceIds - thêm vào danh sách lỗi
                    $stats['row_errors'][] = $row['row_no'] ?? ($index + 1);
                    $stats['message_errors'][] = $e->getMessage();
                    $stats['errors'][] = [
                        'row_no' => $row['row_no'] ?? ($index + 1),
                        'ho_ten' => trim(($row['ho_dem'] ?? '') . ' ' . ($row['ten'] ?? '')),
                        'errors' => [$e->getMessage()]
                    ];
                    $stats['fail_count']++;
                    DB::rollBack();
                    continue;
                }
                
                // Xử lý dòng dữ liệu
                $result = $this->processStudentRowNew($row, $academicYear, $geoCodes, $importBatch, $editorId);
                
                $stats['processed_count']++;
                if ($result['action'] === 'created') {
                    $stats['created_count']++;
                } elseif ($result['action'] === 'updated') {
                    $stats['updated_count']++;
                }
                $stats['ok_count']++;
                
                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Import NEW Excel error for row ' . ($index + 1) . ': ' . $e->getMessage());
                
                $stats['row_errors'][] = $row['row_no'] ?? ($index + 1);
                $stats['message_errors'][] = 'Lỗi: ' . $e->getMessage();
                $stats['errors'][] = [
                    'row_no' => $row['row_no'] ?? ($index + 1),
                    'ho_ten' => trim(($row['ho_dem'] ?? '') . ' ' . ($row['ten'] ?? '')),
                    'errors' => ['Lỗi: ' . $e->getMessage()]
                ];
                $stats['fail_count']++;
            }
        }
        
        // Log để debug
        Log::info('StudentsOffTrackSimpleImportService - Import NEW Excel Final stats:', $stats);
        
        return $stats;
    }
    
    /**
     * Xử lý một dòng dữ liệu học sinh từ file Excel mới
     */
    private function processStudentRowNew(array $row, string $academicYear, array $geoCodes, string $importBatch, ?int $editorId = null): array
    {
        // Chuẩn bị dữ liệu để lưu
        $data = $this->prepareStudentData($row, $academicYear, $geoCodes, $importBatch, $editorId);
        
        // Debug: Log data để kiểm tra
        Log::info('processStudentRowNew - Data prepared:', [
            'row_id' => $row['id'] ?? 'NO_ROW_ID',
            'data_has_id' => isset($data['id']),
            'data_id' => $data['id'] ?? 'NO_DATA_ID',
            'academic_year' => $academicYear
        ]);
        
        // Kiểm tra đối tượng đã tồn tại chưa (theo ID và địa chỉ)
        $existingStudent = $this->findExistingStudentNew($data, $geoCodes);
        
        // Debug: Log kết quả tìm kiếm
        Log::info('processStudentRowNew - Search result:', [
            'row_id' => $row['id'] ?? 'NO_ROW_ID',
            'found_existing' => $existingStudent ? 'YES' : 'NO',
            'existing_id' => $existingStudent?->id ?? 'NULL'
        ]);
        
        if ($existingStudent) {
            // Cập nhật dữ liệu cho người đã tồn tại (cùng ID + cùng năm học + cùng địa chỉ)
            // Nếu đối tượng đã bị đánh dấu xóa, khôi phục lại
            if ($existingStudent->is_deleted) {
                $data['is_deleted'] = false; // Khôi phục từ trạng thái xóa
            }
            $existingStudent->update($data);
            return ['action' => 'updated', 'student' => $existingStudent];
        } else {
            // Thêm mới người (khác ID hoặc khác năm học hoặc khác địa chỉ)
            $newStudent = StudentsOffTrack::create($data);
            return ['action' => 'created', 'student' => $newStudent];
        }
    }

    /**
     * Tìm học sinh đã tồn tại từ file Excel mới (theo ID + năm học + địa chỉ)
     */
    private function findExistingStudentNew(array $data, array $geoCodes): ?StudentsOffTrack
    {
        $academicYear = $data['academic_year'];
        $id = $data['id'] ?? null;
        
        // Nếu không có ID, không thể tìm kiếm
        if (!$id) {
            return null;
        }
        
        // Tìm theo ID + năm học + địa chỉ từ Excel
        $query = StudentsOffTrack::query();
        
        $idInt = (int) $id;
        $query->where('id', $idInt)
              ->where('academic_year', $academicYear);
        
        // Lấy mã địa chỉ từ request (province_id, ward_id, village_id từ select box)
        $requestProvinceCode = $geoCodes['selected_province_code_new'] ?? null;
        $requestWardCode = $geoCodes['selected_ward_code_new'] ?? null;
        $requestVillageCode = $geoCodes['selected_village_code_new'] ?? null;
        
        // Log để debug
        Log::info('findExistingStudentNew - Matching conditions:', [
            'id_original' => $id,
            'id_type' => gettype($id),
            'id_casted' => $idInt,
            'academic_year' => $academicYear,
            'request_province_code' => $requestProvinceCode,
            'request_ward_code' => $requestWardCode,
            'request_village_code' => $requestVillageCode,
            'data_keys' => array_keys($data),
            'data_has_id' => isset($data['id'])
        ]);
        
        // Thêm điều kiện địa chỉ từ request (dùng selected_code để so sánh)
        if ($requestProvinceCode) {
            $query->where('selected_province_code_new', $requestProvinceCode);
        }
        
        if ($requestWardCode) {
            $query->where('selected_ward_code_new', $requestWardCode);
        }
        
        if ($requestVillageCode) {
            $query->where('selected_village_code_new', $requestVillageCode);
        }
        
        
        // Debug: Log SQL query
        Log::info('findExistingStudentNew - SQL Query:', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);
        
        $result = $query->first();
        
        // Log kết quả tìm kiếm
        if ($result) {
            Log::info('Found existing student:', [
                'id' => $result->id,
                'academic_year' => $result->academic_year,
                'selected_province_code_new' => $result->selected_province_code_new,
                'selected_ward_code_new' => $result->selected_ward_code_new,
                'selected_village_code_new' => $result->selected_village_code_new
            ]);
        } else {
            Log::info('No existing student found for:', [
                'id' => $idInt,
                'academic_year' => $academicYear,
                'request_province_code' => $requestProvinceCode,
                'request_ward_code' => $requestWardCode,
                'request_village_code' => $requestVillageCode
            ]);
        }
        
        return $result;
    }
    
    /**
     * Xử lý một dòng dữ liệu học sinh
     */
    private function processStudentRow(array $row, string $academicYear, array $geoCodes, string $importBatch, ?int $editorId = null): array
    {
        // Chuẩn bị dữ liệu để lưu
        $data = $this->prepareStudentData($row, $academicYear, $geoCodes, $importBatch, $editorId);
        
        // Kiểm tra đối tượng đã tồn tại chưa
        $existingStudent = $this->findExistingStudent($data);
        
        if ($existingStudent) {
            // Cập nhật dữ liệu cho người đã tồn tại (cùng năm học + cùng địa chỉ + cùng thông tin cá nhân)
            // Nếu đối tượng đã bị đánh dấu xóa, khôi phục lại
            if ($existingStudent->is_deleted) {
                $data['is_deleted'] = false; // Khôi phục từ trạng thái xóa
            }
            $existingStudent->update($data);
            return ['action' => 'updated', 'student' => $existingStudent];
        } else {
            // Thêm mới người (khác năm học hoặc khác địa chỉ hoặc khác thông tin cá nhân)
            $newStudent = StudentsOffTrack::create($data);
            return ['action' => 'created', 'student' => $newStudent];
        }
    }
    
    /**
     * Tìm học sinh đã tồn tại (cùng năm học + cùng địa chỉ + cùng thông tin cá nhân)
     * Sử dụng scheme new (hành chính mới)
     * Bao gồm cả những học sinh đã bị đánh dấu xóa (is_deleted = true)
     */
    private function findExistingStudent(array $data): ?StudentsOffTrack
    {
        $academicYear = $data['academic_year'];
        
        // Bước 1: Tìm theo số định danh cá nhân + năm học + địa chỉ (ưu tiên cao nhất)
        if (!empty($data['identity_number'])) {
            $query = StudentsOffTrack::where('identity_number', $data['identity_number'])
                                   ->where('academic_year', $academicYear);
            
            // Kiểm tra địa chỉ - chỉ dùng scheme new
            $query->where('selected_province_code_new', $data['selected_province_code_new'] ?? null);
            $query->where('selected_ward_code_new', $data['selected_ward_code_new'] ?? null);
            $query->where('selected_village_code_new', $data['selected_village_code_new'] ?? null);
            
            $student = $query->first();
            if ($student) {
                return $student;
            }
        }
        
        // Bước 2: Tìm theo họ tên + ngày sinh + dân tộc + giới tính + năm học + địa chỉ
        $query = StudentsOffTrack::query();
        
        // Bắt buộc phải cùng năm học
        $query->where('academic_year', $academicYear);
        
        // Họ đệm
        if (!empty($data['ho_dem'])) {
            $query->where('ho_dem', $data['ho_dem']);
        }
        
        // Tên
        if (!empty($data['ten'])) {
            $query->where('ten', $data['ten']);
        }
        
        // Ngày sinh
        if (!empty($data['dob_day'])) {
            $query->where('dob_day', $data['dob_day']);
        }
        
        if (!empty($data['dob_month'])) {
            $query->where('dob_month', $data['dob_month']);
        }
        
        if (!empty($data['dob_year'])) {
            $query->where('dob_year', $data['dob_year']);
        }
        
        // Dân tộc
        if (!empty($data['ethnicity_id'])) {
            $query->where('ethnicity_id', $data['ethnicity_id']);
        }
        
        // Giới tính
        if (isset($data['is_female'])) {
            $query->where('is_female', $data['is_female']);
        }
        
        // Địa chỉ - chỉ dùng scheme new
        $query->where('selected_province_code_new', $data['selected_province_code_new'] ?? null);
        $query->where('selected_ward_code_new', $data['selected_ward_code_new'] ?? null);
        $query->where('selected_village_code_new', $data['selected_village_code_new'] ?? null);
        
        return $query->first();
    }
    
    /**
     * Chuẩn bị dữ liệu để lưu vào bảng students_off_track
     */
    private function prepareStudentData(array $row, string $academicYear, array $geoCodes, string $importBatch, ?int $editorId = null): array
    {
        // Lấy các ID tham chiếu
        $referenceIds = $this->getReferenceIds($row);
        
        // Map geo data từ Excel
        $excelGeoData = [
            'province_name' => $row['province_name'] ?? null,
            'district_name' => $row['district_name'] ?? null,
            'ward_name' => $row['ward_name'] ?? null,
        ];
        
        $excelGeoValidation = $this->excelGeoMappingService->validateExcelGeoData($excelGeoData);
        $excelGeoCodes = $excelGeoValidation['mapped_codes'];
        
        return [
            // ID từ Excel (để tìm kiếm)
            'id' => !empty($row['id']) ? (int)$row['id'] : null,
            
            // Thông tin cá nhân
            'ho_dem' => $row['ho_dem'] ?? null,
            'ten' => $row['ten'] ?? null,
            'dob_day' => !empty($row['dob_day']) ? (int)$row['dob_day'] : null,
            'dob_month' => !empty($row['dob_month']) ? (int)$row['dob_month'] : null,
            'dob_year' => !empty($row['dob_year']) ? (int)$row['dob_year'] : null,
            'identity_number' => $row['identity_number'] ?? null,
            'is_female' => $row['is_female'] ?? false,
            
            // Thông tin tham chiếu (ID)
            'ethnicity_id' => $referenceIds['ethnicity_id'] ?? null,
            'religion_id' => $referenceIds['religion_id'] ?? null,
            'priority_category_id' => $referenceIds['priority_category_id'] ?? null,
            'education_block_id' => $referenceIds['education_block_id'] ?? null,
            'school_id' => $referenceIds['school_id'] ?? null,
            'graduation_level_id' => $referenceIds['graduation_level_id'] ?? null,
            
            // Mã địa bàn cũ (3 cấp từ Excel) - nơi ở từ Excel
            'stay_province_code_old' => $excelGeoCodes['stay_province_code_old'] ?? null,
            'stay_district_code_old' => $excelGeoCodes['stay_district_code_old'] ?? null,
            'stay_ward_code_old' => $excelGeoCodes['stay_ward_code_old'] ?? null,
            
            // Mã địa bàn mới (2 cấp, bỏ quận/huyện) - nơi ở từ Excel
            'stay_province_code_new' => $excelGeoCodes['stay_province_code_new'] ?? null,
            'stay_ward_code_new' => $excelGeoCodes['stay_ward_code_new'] ?? null,
            
            // Mã địa bàn từ select box (khu vực được chọn) - chỉ dùng scheme new
            'selected_province_code_new' => $geoCodes['selected_province_code_new'] ?? null,
            'selected_ward_code_new' => $geoCodes['selected_ward_code_new'] ?? null,
            'selected_village_code_new' => $geoCodes['selected_village_code_new'] ?? null,
            
            // Thông tin học tập
            'class_label' => $row['education_grade_name'] ?? null,
            'hoc_hai_buoi' => $row['hoc_hai_buoi'] ?? false,
            'hoc_9_10_buoi_tuan' => $row['hoc_9_10_buoi_tuan'] ?? false,
            'is_bo_tuc' => $row['is_bo_tuc'] ?? false,
            'graduation_year' => $row['graduation_year'] ?? null,
            'bo_hoc_lop' => $referenceIds['bo_hoc_lop'] ?? null,
            'bo_hoc_nam' => !empty($row['bo_hoc_nam']) ? (int)$row['bo_hoc_nam'] : null,
            
            // Thông tin khuyết tật
            'disability_van_dong' => $row['dis_VAN_DONG'] ?? false,
            'disability_nghe_noi' => $row['dis_NGHE_NOI'] ?? false,
            'disability_nhin' => $row['dis_NHIN'] ?? false,
            'disability_than_kinh' => $row['dis_THAN_KINH'] ?? false,
            'disability_tri_tue' => $row['dis_TRI_TUE'] ?? false,
            'disability_hoc_tap' => $row['dis_HOC_TAP'] ?? false,
            'disability_tu_ky' => $row['dis_TU_KY'] ?? false,
            'disability_khac' => $row['dis_KHAC'] ?? false,
            'disability_has_certificate' => $row['dis_has_certificate'] ?? false,
            'disability_can_learn' => $row['dis_can_learn'] ?? null,
            
            // Hoàn cảnh đặc biệt - lưu trực tiếp
            'special_circumstance_name' => $row['special_circumstance_name'] ?? null,
            'special_circumstance_detail' => $row['special_circumstance_detail'] ?? null,
            
            // Thông tin import
            'academic_year' => $academicYear,
            'import_batch' => $importBatch,
            
            // Thông tin quản lý
            'is_deleted' => false,
            'editor_id' => $editorId,
        ];
    }
    
    /**
     * Lấy ID của các trường tham chiếu
     */
    private function getReferenceIds(array $row): array
    {
        $ids = [];
        $rowNo = $row['row_no'] ?? 'N/A';
        $hoTen = trim(($row['ho_dem'] ?? '') . ' ' . ($row['ten'] ?? ''));
        
        // Dân tộc với normalize
        if (!empty($row['ethnicity'])) {
            $normalizedExcelName = $this->normalizeEthnicityName($row['ethnicity']);
            
            // Tìm dân tộc bằng cách normalize cả tên trong DB
            $ethnicity = Ethnicity::all()->first(function ($item) use ($normalizedExcelName) {
                return $this->normalizeEthnicityName($item->name) === $normalizedExcelName;
            });
            
            if (!$ethnicity) {
                throw new \Exception("Dòng {$rowNo} - {$hoTen}: Dân tộc '{$row['ethnicity']}' không tồn tại trong hệ thống. Vui lòng kiểm tra hoặc thêm vào danh sách dân tộc.");
            }
            
            $ids['ethnicity_id'] = $ethnicity->id;
        }
        
        // Tôn giáo
        if (!empty($row['religion'])) {
            $religion = Religion::where('name', 'like', '%' . $row['religion'] . '%')->first();
            
            if (!$religion) {
                throw new \Exception("Dòng {$rowNo} - {$hoTen}: Tôn giáo '{$row['religion']}' không tồn tại trong hệ thống. Vui lòng kiểm tra hoặc thêm vào danh sách tôn giáo.");
            }
            
            $ids['religion_id'] = $religion->id;
        }
        
        // Diện ưu tiên
        if (!empty($row['priority_name'])) {
            $priority = PriorityCategory::where('name', 'like', '%' . $row['priority_name'] . '%')->first();
            
            if (!$priority) {
                throw new \Exception("Dòng {$rowNo} - {$hoTen}: Diện ưu tiên '{$row['priority_name']}' không tồn tại trong hệ thống. Vui lòng kiểm tra hoặc thêm vào danh sách diện ưu tiên.");
            }
            
            $ids['priority_category_id'] = $priority->id;
        }
        
        // Khối học
        if (!empty($row['education_block_name'])) {
            $excelValue = trim($row['education_block_name']);
            
            // Thử tìm khớp chính xác trước
            $block = EducationBlock::where('name', $excelValue)->first();
            
            // Nếu không tìm thấy, thử tìm không phân biệt hoa thường
            if (!$block) {
                $excelValueLower = mb_strtolower($excelValue);
                $block = EducationBlock::whereRaw('LOWER(name) = ?', [$excelValueLower])->first();
            }
            
            // Nếu vẫn không tìm thấy, thử tìm tương đối nhưng kiểm tra kết quả
            if (!$block) {
                $blocks = EducationBlock::where('name', 'like', '%' . $excelValue . '%')->get();
                if ($blocks->count() > 1) {
                    throw new \Exception("Dòng {$rowNo} - {$hoTen}: Tìm thấy nhiều khối học trùng tên với '{$excelValue}'. Vui lòng kiểm tra lại.");
                }
                $block = $blocks->first();
            }
            
            if (!$block) {
                throw new \Exception("Dòng {$rowNo} - {$hoTen}: Khối học '{$excelValue}' không tồn tại trong hệ thống. Vui lòng kiểm tra hoặc thêm vào danh sách khối học.");
            }
            
            $ids['education_block_id'] = $block->id;
        }
        
        // Bỏ học lớp (cũng map sang education_block_id)
        if (!empty($row['bo_hoc_lop'])) {
            $excelValue = trim($row['bo_hoc_lop']);
            
            // Thử tìm khớp chính xác trước
            $block = EducationBlock::where('name', $excelValue)->first();
            
            // Nếu không tìm thấy, thử tìm không phân biệt hoa thường
            if (!$block) {
                $excelValueLower = mb_strtolower($excelValue);
                $block = EducationBlock::whereRaw('LOWER(name) = ?', [$excelValueLower])->first();
            }
            
            // Nếu vẫn không tìm thấy, thử tìm tương đối nhưng kiểm tra kết quả
            if (!$block) {
                $blocks = EducationBlock::where('name', 'like', '%' . $excelValue . '%')->get();
                if ($blocks->count() > 1) {
                    throw new \Exception("Dòng {$rowNo} - {$hoTen}: Tìm thấy nhiều khối học trùng tên với '{$excelValue}'. Vui lòng kiểm tra lại.");
                }
                $block = $blocks->first();
            }
            
            if (!$block) {
                throw new \Exception("Dòng {$rowNo} - {$hoTen}: Bỏ học lớp '{$excelValue}' không tồn tại trong hệ thống. Vui lòng kiểm tra hoặc thêm vào danh sách khối học.");
            }
            
            $ids['bo_hoc_lop'] = $block->id;
        }
        
        // Mã trường
        if (!empty($row['school_code'])) {
            $school = School::where('code', $row['school_code'])->first();
            
            if (!$school) {
                throw new \Exception("Dòng {$rowNo} - {$hoTen}: Mã trường '{$row['school_code']}' không tồn tại trong hệ thống. Vui lòng kiểm tra hoặc thêm vào danh sách trường học.");
            }
            
            $ids['school_id'] = $school->id;
        }
        
        // Bậc tốt nghiệp
        if (!empty($row['graduation_level_name'])) {
            $graduation = GraduationLevel::where('name', 'like', '%' . $row['graduation_level_name'] . '%')->first();
            
            if (!$graduation) {
                throw new \Exception("Dòng {$rowNo} - {$hoTen}: Bậc tốt nghiệp '{$row['graduation_level_name']}' không tồn tại trong hệ thống. Vui lòng kiểm tra hoặc thêm vào danh sách bậc tốt nghiệp.");
            }
            
            $ids['graduation_level_id'] = $graduation->id;
        }
        
        return $ids;
    }

    /**
     * Lấy ID địa chính được chọn từ geo filter (ưu tiên cấp thấp nhất)
     */
    private function getSelectedGeoId(array $geoData, string $scheme): ?int
    {
        if ($scheme === 'old') {
            // Ưu tiên chọn cấp thấp nhất có dữ liệu từ scheme old
            if (!empty($geoData['village_id'])) {
                return (int) $geoData['village_id'];
            } elseif (!empty($geoData['ward_id'])) {
                return (int) $geoData['ward_id'];
            } elseif (!empty($geoData['district_id'])) {
                return (int) $geoData['district_id'];
            } elseif (!empty($geoData['province_id'])) {
                return (int) $geoData['province_id'];
            }
        } else {
            // Ưu tiên chọn cấp thấp nhất có dữ liệu từ scheme new
            if (!empty($geoData['village_id'])) {
                return (int) $geoData['village_id'];
            } elseif (!empty($geoData['ward_id'])) {
                return (int) $geoData['ward_id'];
            } elseif (!empty($geoData['district_id'])) {
                return (int) $geoData['district_id'];
            } elseif (!empty($geoData['province_id'])) {
                return (int) $geoData['province_id'];
            }
        }

        return null;
    }
    
    /**
     * Validate một dòng dữ liệu
     */
    private function validateSingleRow(array $row): array
    {
        $errors = [];
        
        // Kiểm tra các trường bắt buộc
        if (empty($row['ho_dem'])) {
            $errors[] = 'Họ đệm không được để trống';
        }
        
        if (empty($row['ten'])) {
            $errors[] = 'Tên không được để trống';
        }
        
        // Kiểm tra ngày sinh
        if (!empty($row['dob_day']) && (!is_numeric($row['dob_day']) || $row['dob_day'] < 1 || $row['dob_day'] > 31)) {
            $errors[] = 'Ngày sinh không hợp lệ';
        }
        
        if (!empty($row['dob_month']) && (!is_numeric($row['dob_month']) || $row['dob_month'] < 1 || $row['dob_month'] > 12)) {
            $errors[] = 'Tháng sinh không hợp lệ';
        }
        
        if (!empty($row['dob_year']) && (!is_numeric($row['dob_year']) || $row['dob_year'] < 1900 || $row['dob_year'] > date('Y'))) {
            $errors[] = 'Năm sinh không hợp lệ';
        }
        
        // Kiểm tra các trường tham chiếu
        $referenceValidation = $this->validateReferenceData($row);
        if (!$referenceValidation['valid']) {
            $errors = array_merge($errors, $referenceValidation['errors']);
        }
        
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate geo data từ Excel (riêng biệt, không bỏ dòng nếu chỉ có lỗi geo)
     */
    private function validateGeoData(array $row): array
    {
        $excelGeoData = [
            'province_name' => $row['province_name'] ?? null,
            'district_name' => $row['district_name'] ?? null,
            'ward_name' => $row['ward_name'] ?? null,
        ];
        
        return $this->excelGeoMappingService->validateExcelGeoData($excelGeoData);
    }
    
    /**
     * Normalize tên dân tộc để so sánh chính xác
     */
    private function normalizeEthnicityName(string $name): string
    {
        // Xóa dấu cách và dấu -, chuyển về chữ thường
        $normalized = str_replace([' ', '-'], '', $name);
        return mb_strtolower($normalized, 'UTF-8');
    }
    
    /**
     * Validate các trường tham chiếu
     */
    private function validateReferenceData(array $row): array
    {
        $errors = [];
        
        // Kiểm tra khối học
        if (!empty($row['education_block_name'])) {
            $block = EducationBlock::where('name', 'like', '%' . $row['education_block_name'] . '%')->first();
            if (!$block) {
                $errors[] = "Khối học '{$row['education_block_name']}' không tồn tại trong hệ thống. Vui lòng kiểm tra hoặc thêm vào danh sách khối học.";
            }
        }
        
        // Kiểm tra bỏ học lớp
        if (!empty($row['bo_hoc_lop'])) {
            $block = EducationBlock::where('name', 'like', '%' . $row['bo_hoc_lop'] . '%')->first();
            if (!$block) {
                $errors[] = "Bỏ học lớp '{$row['bo_hoc_lop']}' không tồn tại trong hệ thống. Vui lòng kiểm tra hoặc thêm vào danh sách khối học.";
            }
        }
        
        // Kiểm tra dân tộc với normalize
        if (!empty($row['ethnicity'])) {
            $normalizedExcelName = $this->normalizeEthnicityName($row['ethnicity']);
            
            // Tìm dân tộc bằng cách normalize cả tên trong DB
            $ethnicity = Ethnicity::all()->first(function ($item) use ($normalizedExcelName) {
                return $this->normalizeEthnicityName($item->name) === $normalizedExcelName;
            });
            
            if (!$ethnicity) {
                $errors[] = "Dân tộc '{$row['ethnicity']}' không tồn tại trong hệ thống. Vui lòng kiểm tra hoặc thêm vào danh sách dân tộc.";
            }
        }
        
        // Kiểm tra tôn giáo
        if (!empty($row['religion'])) {
            $religion = Religion::where('name', 'like', '%' . $row['religion'] . '%')->first();
            if (!$religion) {
                $errors[] = "Tôn giáo '{$row['religion']}' không tồn tại trong hệ thống. Vui lòng kiểm tra hoặc thêm vào danh sách tôn giáo.";
            }
        }
        
        // Kiểm tra diện ưu tiên
        if (!empty($row['priority_name'])) {
            $priority = PriorityCategory::where('name', 'like', '%' . $row['priority_name'] . '%')->first();
            if (!$priority) {
                $errors[] = "Diện ưu tiên '{$row['priority_name']}' không tồn tại trong hệ thống. Vui lòng kiểm tra hoặc thêm vào danh sách diện ưu tiên.";
            }
        }
        
        // Kiểm tra trường học
        if (!empty($row['school_code'])) {
            $school = School::where('code', $row['school_code'])->first();
            if (!$school) {
                $errors[] = "Mã trường '{$row['school_code']}' không tồn tại trong hệ thống. Vui lòng kiểm tra hoặc thêm vào danh sách trường học.";
            }
        }
        
        // Kiểm tra bậc tốt nghiệp
        if (!empty($row['graduation_level_name'])) {
            $graduation = GraduationLevel::where('name', 'like', '%' . $row['graduation_level_name'] . '%')->first();
            if (!$graduation) {
                $errors[] = "Bậc tốt nghiệp '{$row['graduation_level_name']}' không tồn tại trong hệ thống. Vui lòng kiểm tra hoặc thêm vào danh sách bậc tốt nghiệp.";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
}
