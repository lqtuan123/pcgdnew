<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Survey;
use App\Models\SurveyMember;
use App\Models\StudentsOffTrack;
use App\Models\PreschoolStudent;
use App\Models\School;
use App\Models\GeoUnit;
use App\Models\SurveyDetailFlat;

use App\Services\AccessGuardService;

class DataValidationController extends Controller
{
    public function __construct(
        private AccessGuardService $guard
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $isAdmin = in_array($user?->role_id, [1, 2]);

        $ctx = $this->guard->enforce($request, 'quan_ly_doi_tuong_dieu_tra', 'view', [
            'force_scheme' => $isAdmin ? $request->get('scheme', 'new') : 'new',
            'geo_keys' => ['province_id', 'district_id', 'ward_id', 'village_id'],
        ]);
        
        // Lấy năm học hiện tại và tạo danh sách năm học từ 2000-2001 đến năm hiện tại + 1
        $academicYear = $request->get('academic_year', \App\Models\SurveyForm::currentAcademicYear());
        $academicYears = $this->getAcademicYears();
        
        // Lấy thông tin geo từ context đã được ép theo địa bàn tài khoản
        $geoData = $ctx['geo'];
        if ($isAdmin) {
            $geoData['province_id'] = $geoData['province_id'] ?? $request->get('geo')['province_id'] ?? null;
            $geoData['district_id'] = $geoData['district_id'] ?? $request->get('geo')['district_id'] ?? null;
            $geoData['ward_id'] = $geoData['ward_id'] ?? $request->get('geo')['ward_id'] ?? null;
            $geoData['village_id'] = $geoData['village_id'] ?? $request->get('geo')['village_id'] ?? null;
        }
        $scheme = $ctx['scheme'] ?? ($isAdmin ? $request->get('scheme', 'new') : 'new');
        
        // Tiêu chí kiểm tra nâng cao
        $criteria = [
            'duplicate_pcgd' => '1. Kiểm tra trùng lặp đối tượng PCGD (CCCD, Họ tên, Ngày sinh)',
            'invalid_birth_date' => '2. Ngày sinh, năm sinh không hợp lệ hoặc tương lai',
            'invalid_graduation_year' => '3. Năm tốt nghiệp không phù hợp với năm sinh / độ tuổi',
            'invalid_class_age' => '4. Lớp đang học không phù hợp với năm sinh',
            'invalid_graduation_class' => '5. Bậc tốt nghiệp mâu thuẫn với lớp đang học, bỏ học',
            'missing_study_info' => '6. Nhập thiếu thông tin học tập (Có khối thiếu trường hoặc ngược lại)',
            'ppc_missing_study_info' => '7. Đối tượng PPC (3-18 tuổi): Thiếu thông tin học tập',
            'special_circumstance' => '8. Hoàn cảnh đặc biệt: Thiếu chi tiết mô tả',
            'logic_errors' => '9. Lỗi mâu thuẫn logic (Vừa học vừa bỏ học, cư trú mâu thuẫn)'
        ];
        
        return view('front.data-validation.index', compact(
            'academicYears',
            'academicYear',
            'geoData',
            'scheme',
            'criteria'
        ));
    }
    
    public function check(Request $request)
    {
        try {
            $user = Auth::user();
            $isAdmin = in_array($user?->role_id, [1, 2]);

            $ctx = $this->guard->enforce($request, 'quan_ly_doi_tuong_dieu_tra', 'view', [
                'force_scheme' => $isAdmin ? $request->input('scheme', 'new') : 'new',
                'geo_keys' => ['province_id', 'district_id', 'ward_id', 'village_id'],
            ]);

            $criteria = $request->input('criteria');
            $academicYear = $request->input('academic_year');
            $scheme = $ctx['scheme'] ?? ($isAdmin ? $request->input('scheme', 'new') : 'new');
            
            // Lấy thông tin geo đã được ép theo địa bàn tài khoản
            $geoData = $ctx['geo'];
            $reqGeo = (array) $request->input('geo', []);
            $provinceId = $geoData['province_id'] ?? ($isAdmin ? ($reqGeo['province_id'] ?? null) : null);
            $districtId = $geoData['district_id'] ?? ($isAdmin ? ($reqGeo['district_id'] ?? null) : null);
            $wardId = $geoData['ward_id'] ?? ($isAdmin ? ($reqGeo['ward_id'] ?? null) : null);
            $villageId = $geoData['village_id'] ?? ($isAdmin ? ($reqGeo['village_id'] ?? null) : null);
            
            // Validate input
            $request->validate([
                'criteria' => 'required|string',
            ]);
            
            // Sử dụng SurveyDetailFlat view thay vì buildBaseQuery
            $query = SurveyDetailFlat::query()
                ->ofYear($academicYear);
            
            // Áp dụng lọc địa bàn
            $this->applyGeoFilter($query, $provinceId, $districtId, $wardId, $villageId, $scheme);
            
            // Áp dụng tiêu chí kiểm tra thông minh
            $query = $this->applyCriteriaFilterToView($query, $criteria, $academicYear, $provinceId, $districtId, $wardId, $villageId, $scheme);
            
            // Lấy kết quả với pagination
            $perPage = $request->input('per_page', 10);
            $perPage = in_array($perPage, [10, 20, 50, 100]) ? $perPage : 10;
            try {
                $results = $query->paginate($perPage);
            } catch (\Illuminate\Database\QueryException $qe) {
                if (str_contains($qe->getMessage(), '1615') || str_contains($qe->getMessage(), 're-prepared')) {
                    DB::reconnect();
                    $results = $query->paginate($perPage);
                } else {
                    throw $qe;
                }
            }
            
            // Chuẩn bị dữ liệu cho view
            $rows = $results->map(function($item) {
                return $this->formatViewRowData($item);
            });
            
            // Sắp xếp đặc biệt cho tiêu chí duplicate_pcgd để những người trùng hiển thị gần nhau
            if ($criteria === 'duplicate_pcgd') {
                $rows = $this->sortDuplicateRows($rows);
            }
            
            // Chuẩn bị cấu trúc bảng giống như trang surveys
            $groups = $this->getTableGroups();
            $columns = $this->getTableColumns();
            
            return response()->json([
                'success' => true,
                'criteria' => $criteria,
                'total' => $results->total(),
                'rows' => $rows,
                'groups' => $groups,
                'columns' => $columns,
                'pagination' => [
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'from' => $results->firstItem(),
                    'to' => $results->lastItem(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('DataValidation check error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    private function getAcademicYears()
    {
        // Tạo danh sách năm học từ 2000-2001 đến năm hiện tại + 1
        $base = \App\Models\SurveyForm::currentAcademicYear();
        [$y1, $y2] = explode('-', $base);
        
        $years = [];
        $startYear = 2000;
        $endYear = $y2 + 1; // Hơn 1 năm so với năm hiện tại
        
        for ($year = $startYear; $year <= $endYear; $year++) {
            $years[] = $year . '-' . ($year + 1);
        }
        
        // Sắp xếp từ mới nhất đến cũ nhất
        $years = array_reverse($years);
        
        return $years;
    }
    
    /**
     * Áp dụng lọc địa bàn cho SurveyDetailFlat view
     */
    private function applyGeoFilter($query, $provinceId, $districtId, $wardId, $villageId, $scheme)
    {
        if ($scheme === 'old') {
            if ($provinceId) $query->where('old_province_id', $provinceId);
            if ($districtId) $query->where('old_district_id', $districtId);
            if ($wardId)     $query->where('old_ward_id',     $wardId);
        } else {
            if ($provinceId) $query->where('new_province_id', $provinceId);
            if ($wardId)     $query->where('new_ward_id',     $wardId);
        }
        if ($villageId) {
            $query->where('village_id', $villageId);
        }
    }
    
    /**
     * Áp dụng tiêu chí kiểm tra cho SurveyDetailFlat view
     */
    private function applyCriteriaFilterToView($query, $criteria, $academicYear = null, $provinceId = null, $districtId = null, $wardId = null, $villageId = null, $scheme = 'new')
    {
        switch ($criteria) {
            case 'duplicate_pcgd':
                return $this->filterDuplicatePCGDView($query, $academicYear, $provinceId, $districtId, $wardId, $villageId, $scheme);
            case 'invalid_birth_date':
                return $this->filterInvalidBirthDateView($query);
            case 'invalid_graduation_year':
                return $this->filterInvalidGraduationYearView($query);
            case 'invalid_class_age':
                return $this->filterInvalidClassAgeView($query, $academicYear);
            case 'invalid_graduation_class':
                return $this->filterInvalidGraduationClassView($query);
            case 'missing_study_info':
                return $this->filterMissingStudyInfoView($query);
            case 'ppc_missing_study_info':
                return $this->filterPPCMissingStudyInfoView($query, $academicYear);
            case 'special_circumstance':
                return $this->filterSpecialCircumstanceView($query);
            case 'logic_errors':
                return $this->filterLogicErrorsView($query, $academicYear);
            default:
                return $query;
        }
    }
    
    /**
     * Format dữ liệu từ SurveyDetailFlat view
     */
    private function formatViewRowData($item)
    {
        return [
            'detail_id' => $item->detail_id,
            'survey_id' => $item->survey_id,
            'person_id' => $item->person_id,
            'academic_year' => $item->academic_year,
            'so_phieu' => $item->so_phieu,
            
            // Thông tin đối tượng
            'ho_dem' => $item->ho_dem,
            'ten' => $item->ten,
            'dob_day' => $item->dob_day,
            'dob_month' => $item->dob_month,
            'dob_year' => $item->dob_year,
            'identity_number' => $item->identity_number,
            'is_female' => (bool)$item->is_female,
            'ethnicity' => $item->ethnicity,
            'religion' => $item->religion,
            'priority_name' => $item->priority_name ?? '',
            
            // Thông tin nơi đang ở
            'chuho_ho_dem' => $item->chuho_ho_dem,
            'chuho_ten' => $item->chuho_ten,
            'addressline' => $item->addressline,
            'residence_type_name' => $item->residence_type_name,
            'residence_status_name' => $item->residence_status_name,
            
            // Thông tin học tập
            'education_block_code' => $item->education_block_code,
            'education_grade_name' => $item->education_grade_name,
            'studying_district' => $item->studying_district,
            'school_name' => $item->school_name,
            'school_code' => $item->school_code,
            'graduation_level_name' => $item->graduation_level_name,
            'is_bo_tuc' => (bool)$item->is_bo_tuc,
            'graduation_year' => $item->graduation_year,
            'vocational_level_name' => $item->vocational_level_name,
            'graduation_certificate_no' => $item->graduation_certificate_no,
            'vocational_grad_year' => $item->vocational_grad_year,
            'hoc_xong_lop' => $item->hoc_xong_lop,
            'hoc_xong_nam' => $item->hoc_xong_nam,
            'bo_hoc_lop' => $item->bo_hoc_lop,
            'bo_hoc_nam' => $item->bo_hoc_nam,
            
            // Xóa mù chữ
            'lit_dang_hoc_lop' => $item->lit_dang_hoc_lop,
            'lit_hoan_thanh_lop' => $item->lit_hoan_thanh_lop,
            'lit_tai_mu_muc' => $item->lit_tai_mu_muc,
            
            // Khuyết tật trực tiếp từ VIEW (tránh N+1 query)
            'dis_van_dong' => (bool)($item->dis_VAN_DONG ?? false),
            'dis_nghe_noi' => (bool)($item->dis_NGHE_NOI ?? false),
            'dis_nhin' => (bool)($item->dis_NHIN ?? false),
            'dis_than_kinh' => (bool)($item->dis_THAN_KINH ?? false),
            'dis_tri_tue' => (bool)($item->dis_TRI_TUE ?? false),
            'dis_hoc_tap' => (bool)($item->dis_HOC_TAP ?? false),
            'dis_tu_ky' => (bool)($item->dis_TU_KY ?? false),
            'dis_khac' => (bool)($item->dis_KHAC ?? false),
            'dis_has_certificate' => (bool)($item->dis_has_certificate ?? false),
            'dis_can_learn' => (bool)($item->dis_can_learn ?? false),
            
            // Hoàn cảnh
            'sc_name' => $item->sc_name,
            'sc_detail' => $item->sc_detail,
            
            // Thông tin liên lạc
            'relationship_name' => $item->relationship_name,
            'contact_name' => $item->contact_name,
            'contact_phone' => $item->contact_phone,
            'contact_note' => $item->contact_note,
            
            // Địa bàn
            'old_province_name' => $item->old_province_name,
            'old_district_name' => $item->old_district_name,
            'old_ward_name' => $item->old_ward_name,
            'new_province_name' => $item->new_province_name,
            'new_ward_name' => $item->new_ward_name,
            'village_name' => $item->village_name,
        ];
    }
    
    /**
     * Chẩn đoán lỗi thông minh cho từng dòng dữ liệu
     */
    private function detectRowErrors($item, $academicYear, $criteria = null): array
    {
        $reasons = [];
        $fields = [];
        $severity = 'warning';

        $currentYear = is_numeric($academicYear) ? (int)$academicYear : (int)substr($academicYear ?: date('Y'), 0, 4);
        $curDate = date('Y-m-d');
        $curYear = (int)date('Y');

        // 1. Kiểm tra ngày tháng năm sinh
        if ($item->dob_year) {
            if ($item->dob_year < 1900 || $item->dob_year > $curYear) {
                $reasons[] = "Năm sinh {$item->dob_year} không hợp lệ (phải từ 1900 đến {$curYear})";
                $fields[] = 'dob_year';
                $severity = 'danger';
            }
        } else {
            $reasons[] = "Chưa có năm sinh";
            $fields[] = 'dob_year';
            $severity = 'danger';
        }

        if ($item->dob_month) {
            if ($item->dob_month < 1 || $item->dob_month > 12) {
                $reasons[] = "Tháng sinh ({$item->dob_month}) không hợp lệ";
                $fields[] = 'dob_month';
                $severity = 'danger';
            }
        }

        if ($item->dob_day) {
            if ($item->dob_day < 1 || $item->dob_day > 31) {
                $reasons[] = "Ngày sinh ({$item->dob_day}) không hợp lệ";
                $fields[] = 'dob_day';
                $severity = 'danger';
            } elseif ($item->dob_month) {
                if (in_array($item->dob_month, [4, 6, 9, 11]) && $item->dob_day > 30) {
                    $reasons[] = "Tháng {$item->dob_month} chỉ có tối đa 30 ngày";
                    $fields = array_merge($fields, ['dob_day', 'dob_month']);
                    $severity = 'danger';
                } elseif ($item->dob_month == 2) {
                    $isLeap = ($item->dob_year % 4 == 0 && ($item->dob_year % 100 != 0 || $item->dob_year % 400 == 0));
                    $maxFeb = $isLeap ? 29 : 28;
                    if ($item->dob_day > $maxFeb) {
                        $reasons[] = "Tháng 2 năm {$item->dob_year} chỉ có tối đa {$maxFeb} ngày";
                        $fields = array_merge($fields, ['dob_day', 'dob_month', 'dob_year']);
                        $severity = 'danger';
                    }
                }
            }
        }

        if ($item->dob_date && $item->dob_date > $curDate) {
            $reasons[] = "Ngày sinh nằm trong tương lai (" . date('d/m/Y', strtotime($item->dob_date)) . ")";
            $fields = array_merge($fields, ['dob_day', 'dob_month', 'dob_year']);
            $severity = 'danger';
        }

        // 2. Kiểm tra năm tốt nghiệp
        if ($item->graduation_year && $item->dob_year) {
            $gradYear = (int)substr($item->graduation_year, 0, 4);
            if ($gradYear > 0) {
                $ageGrad = $gradYear - $item->dob_year;
                if ($gradYear <= $item->dob_year) {
                    $reasons[] = "Năm tốt nghiệp ({$gradYear}) nhỏ hơn hoặc bằng năm sinh ({$item->dob_year})";
                    $fields = array_merge($fields, ['graduation_year', 'dob_year']);
                    $severity = 'danger';
                } elseif ($gradYear > $curYear) {
                    $reasons[] = "Năm tốt nghiệp ({$gradYear}) ở tương lai";
                    $fields[] = 'graduation_year';
                    $severity = 'danger';
                } else {
                    $minAge = match ($item->graduation_level_name) {
                        'TH' => 10,
                        'THCS' => 14,
                        'THPT' => 17,
                        'MN' => 5,
                        default => 5,
                    };
                    if ($ageGrad < $minAge && $item->graduation_level_name) {
                        $reasons[] = "Tốt nghiệp {$item->graduation_level_name} năm {$gradYear} khi mới {$ageGrad} tuổi (quy định ≥ {$minAge})";
                        $fields = array_merge($fields, ['graduation_year', 'graduation_level_name', 'dob_year']);
                        $severity = 'danger';
                    }
                }
            }
        }

        // 3. Kiểm tra độ tuổi theo khối học
        if ($item->education_block_code && $item->dob_year && !$item->is_bo_tuc) {
            $age = $currentYear - $item->dob_year;
            $validAges = [
                '1' => [6, 9], '2' => [7, 10], '3' => [8, 11], '4' => [9, 12], '5' => [10, 13],
                '6' => [11, 14], '7' => [12, 15], '8' => [13, 16], '9' => [14, 17],
                '10' => [15, 19], '11' => [16, 20], '12' => [17, 21],
                '3-12 tháng' => [0, 1], '13-24 tháng' => [1, 2], '25-36 tháng' => [2, 3],
                '3-4 tuổi' => [3, 4], '4-5 tuổi' => [4, 5], '5-6 tuổi' => [5, 6]
            ];

            if (isset($validAges[$item->education_block_code])) {
                [$minA, $maxA] = $validAges[$item->education_block_code];
                if ($age < $minA) {
                    $reasons[] = "Học khối {$item->education_block_code} khi mới {$age} tuổi (chưa đủ tuổi tối thiểu {$minA})";
                    $fields = array_merge($fields, ['education_block_code', 'dob_year']);
                    $severity = 'danger';
                } elseif ($age > $maxA) {
                    $reasons[] = "Học khối {$item->education_block_code} khi đã {$age} tuổi (độ tuổi chuẩn là {$minA}-{$maxA})";
                    $fields = array_merge($fields, ['education_block_code', 'dob_year']);
                    if ($severity !== 'danger') $severity = 'warning';
                }
            }
        }

        // 4. Bậc tốt nghiệp mâu thuẫn với lớp đang học / bỏ học
        if ($item->graduation_level_name) {
            $mnBlocks = ['3-12 tháng', '13-24 tháng', '25-36 tháng', '3-4 tuổi', '4-5 tuổi', '5-6 tuổi'];
            $thBlocks = array_merge($mnBlocks, ['1', '2', '3', '4', '5']);
            $thcsBlocks = array_merge($thBlocks, ['6', '7', '8', '9']);

            if ($item->graduation_level_name === 'TH') {
                if (in_array($item->education_block_code, $mnBlocks)) {
                    $reasons[] = "Đã tốt nghiệp TH nhưng lại đang học Mầm non";
                    $fields = array_merge($fields, ['graduation_level_name', 'education_block_code']);
                    $severity = 'danger';
                }
                if ($item->bo_hoc_lop && $item->bo_hoc_lop <= 4) {
                    $reasons[] = "Đã tốt nghiệp TH nhưng hồ sơ lại ghi bỏ học lớp {$item->bo_hoc_lop}";
                    $fields = array_merge($fields, ['graduation_level_name', 'bo_hoc_lop']);
                    $severity = 'danger';
                }
            } elseif ($item->graduation_level_name === 'THCS') {
                if (in_array($item->education_block_code, $thBlocks)) {
                    $reasons[] = "Đã tốt nghiệp THCS nhưng lại đang học khối {$item->education_block_code}";
                    $fields = array_merge($fields, ['graduation_level_name', 'education_block_code']);
                    $severity = 'danger';
                }
                if ($item->bo_hoc_lop && $item->bo_hoc_lop <= 8) {
                    $reasons[] = "Đã tốt nghiệp THCS nhưng hồ sơ ghi bỏ học lớp {$item->bo_hoc_lop}";
                    $fields = array_merge($fields, ['graduation_level_name', 'bo_hoc_lop']);
                    $severity = 'danger';
                }
            } elseif ($item->graduation_level_name === 'THPT') {
                if (in_array($item->education_block_code, $thcsBlocks)) {
                    $reasons[] = "Đã tốt nghiệp THPT nhưng lại đang học khối {$item->education_block_code}";
                    $fields = array_merge($fields, ['graduation_level_name', 'education_block_code']);
                    $severity = 'danger';
                }
                if ($item->bo_hoc_lop && $item->bo_hoc_lop <= 11) {
                    $reasons[] = "Đã tốt nghiệp THPT nhưng hồ sơ ghi bỏ học lớp {$item->bo_hoc_lop}";
                    $fields = array_merge($fields, ['graduation_level_name', 'bo_hoc_lop']);
                    $severity = 'danger';
                }
            }
        }

        // 5. Thiếu thông tin học tập
        if ($item->education_block_code && !$item->school_name) {
            $reasons[] = "Có khối/lớp học ({$item->education_block_code}) nhưng chưa chọn Trường học";
            $fields = array_merge($fields, ['education_block_code', 'school_name']);
        } elseif ($item->school_name && !$item->education_block_code) {
            $reasons[] = "Có Trường học nhưng chưa chọn Khối/Lớp học";
            $fields = array_merge($fields, ['education_block_code', 'school_name']);
        }

        // 6. Đối tượng PPC thiếu thông tin
        if ($item->dob_year && !$item->education_block_code && !$item->school_name) {
            $age = $currentYear - $item->dob_year;
            if ($age >= 3 && $age <= 18 && !$item->bo_hoc_lop && $item->graduation_level_name !== 'THPT' && !$item->dis_has_certificate && $item->dis_can_learn !== 0) {
                $reasons[] = "Độ tuổi PCGD ({$age} tuổi) nhưng chưa có thông tin trường lớp hoặc lý do nghỉ";
                $fields = array_merge($fields, ['education_block_code', 'school_name', 'dob_year']);
            }
        }

        // 7. Hoàn cảnh đặc biệt thiếu chi tiết
        if ($item->sc_name && empty(trim($item->sc_detail ?? ''))) {
            $reasons[] = "Thuộc diện HCĐB ({$item->sc_name}) nhưng chưa có mô tả chi tiết hoàn cảnh";
            $fields = array_merge($fields, ['sc_name', 'sc_detail']);
        }

        // 8. Lỗi mâu thuẫn logic khác
        if ($item->bo_hoc_lop && ($item->education_block_code || $item->school_name) && $item->bo_hoc_nam == $academicYear) {
            $reasons[] = "Mâu thuẫn: Vừa ghi đang học vừa ghi đã bỏ học lớp {$item->bo_hoc_lop} năm {$item->bo_hoc_nam}";
            $fields = array_merge($fields, ['bo_hoc_lop', 'education_block_code', 'school_name']);
            $severity = 'danger';
        }
        if (in_array($item->residence_status_name, ['Chết', 'Đã chuyển đi', 'Đi khỏi địa phương']) && $item->education_block_code) {
            $reasons[] = "Cư trú là '{$item->residence_status_name}' nhưng vẫn có hồ sơ đang đi học";
            $fields = array_merge($fields, ['residence_status_name', 'education_block_code']);
            $severity = 'danger';
        }

        // 9. Nếu là tiêu chí trùng lặp
        if ($criteria === 'duplicate_pcgd' || in_array('duplicate_pcgd', (array)$criteria)) {
            if (!empty($item->identity_number)) {
                $reasons[] = "Trùng số CCCD/Định danh ({$item->identity_number}) với đối tượng khác";
                $fields[] = 'identity_number';
            } else {
                $reasons[] = "Trùng Họ tên và Ngày tháng năm sinh với đối tượng khác";
                $fields = array_merge($fields, ['ho_dem', 'ten', 'dob_day', 'dob_month', 'dob_year']);
            }
            $severity = 'danger';
        }

        if (empty($reasons)) {
            $reasons[] = "Dữ liệu có dấu hiệu bất thường cần rà soát lại";
        }

        return [
            'reason' => implode('; ', array_unique($reasons)),
            'fields' => array_values(array_unique($fields)),
            'severity' => $severity,
        ];
    }
    
    /**
     * Filter trùng lặp PCGD cho SurveyDetailFlat view
     */
    private function filterDuplicatePCGDView($query, $academicYear = null, $provinceId = null, $districtId = null, $wardId = null, $villageId = null, $scheme = 'new')
    {
        // 1. Tìm trùng CCCD
        $dupCccdBuilder = DB::table('survey_form_details as sfd')
            ->join('people as p', 'sfd.person_id', '=', 'p.id')
            ->where('sfd.is_deleted', 0)
            ->where('p.is_deleted', 0);

        $subCccdBuilder = DB::table('survey_form_details as sfd2')
            ->join('people as p2', 'sfd2.person_id', '=', 'p2.id')
            ->select('p2.identity_number')
            ->where('sfd2.is_deleted', 0)
            ->where('p2.is_deleted', 0)
            ->whereNotNull('p2.identity_number')
            ->where('p2.identity_number', '!=', '');

        // 2. Tìm trùng Họ tên + Ngày tháng năm sinh
        $dupNameDobBuilder = DB::table('survey_form_details as sfd')
            ->join('people as p', 'sfd.person_id', '=', 'p.id')
            ->where('sfd.is_deleted', 0)
            ->where('p.is_deleted', 0);

        $subNameDobBuilder = DB::table('survey_form_details as sfd2')
            ->join('people as p2', 'sfd2.person_id', '=', 'p2.id')
            ->select('p2.ho_dem', 'p2.ten', 'p2.dob_day', 'p2.dob_month', 'p2.dob_year')
            ->where('sfd2.is_deleted', 0)
            ->where('p2.is_deleted', 0)
            ->whereNotNull('p2.dob_year');

        // Lọc theo năm học
        if ($academicYear && $academicYear !== 'all') {
            $dupCccdBuilder->where('sfd.academic_year', $academicYear);
            $subCccdBuilder->where('sfd2.academic_year', $academicYear);
            $dupNameDobBuilder->where('sfd.academic_year', $academicYear);
            $subNameDobBuilder->where('sfd2.academic_year', $academicYear);
        }

        // Lọc theo địa bàn (nếu có)
        $hasGeoFilter = $villageId || $wardId || $districtId || $provinceId;
        if ($hasGeoFilter) {
            $dupCccdBuilder->join('survey_forms as sf', 'sfd.survey_form_id', '=', 'sf.id');
            $subCccdBuilder->join('survey_forms as sf2', 'sfd2.survey_form_id', '=', 'sf2.id');
            $dupNameDobBuilder->join('survey_forms as sf', 'sfd.survey_form_id', '=', 'sf.id');
            $subNameDobBuilder->join('survey_forms as sf2', 'sfd2.survey_form_id', '=', 'sf2.id');

            $applySfGeo = function($builder, $alias) use ($villageId, $wardId, $districtId, $provinceId, $scheme) {
                if ($villageId) {
                    $builder->where("{$alias}.village_id", $villageId);
                } elseif ($wardId) {
                    $builder->where("{$alias}." . ($scheme === 'old' ? 'old_ward_id' : 'new_ward_id'), $wardId);
                } elseif ($districtId) {
                    $builder->where("{$alias}." . ($scheme === 'old' ? 'old_district_id' : 'new_district_id'), $districtId);
                } elseif ($provinceId) {
                    $builder->where("{$alias}." . ($scheme === 'old' ? 'old_province_id' : 'new_province_id'), $provinceId);
                }
            };

            $applySfGeo($dupCccdBuilder, 'sf');
            $applySfGeo($subCccdBuilder, 'sf2');
            $applySfGeo($dupNameDobBuilder, 'sf');
            $applySfGeo($subNameDobBuilder, 'sf2');
        }

        $subCccdBuilder->groupBy('p2.identity_number')->havingRaw('COUNT(*) > 1');
        $dupCccdIds = $dupCccdBuilder
            ->joinSub($subCccdBuilder, 'dup_cccd', 'p.identity_number', '=', 'dup_cccd.identity_number')
            ->pluck('sfd.id')
            ->toArray();

        $subNameDobBuilder->groupBy('p2.ho_dem', 'p2.ten', 'p2.dob_day', 'p2.dob_month', 'p2.dob_year')->havingRaw('COUNT(*) > 1');
        $dupNameDobIds = $dupNameDobBuilder
            ->joinSub($subNameDobBuilder, 'dup_nd', function($join) {
                $join->on('p.ho_dem', '=', 'dup_nd.ho_dem')
                     ->on('p.ten', '=', 'dup_nd.ten')
                     ->on('p.dob_year', '=', 'dup_nd.dob_year')
                     ->where(function($w) {
                         $w->whereColumn('p.dob_month', 'dup_nd.dob_month')
                           ->orWhere(function($w2) { $w2->whereNull('p.dob_month')->whereNull('dup_nd.dob_month'); });
                     })
                     ->where(function($w) {
                         $w->whereColumn('p.dob_day', 'dup_nd.dob_day')
                           ->orWhere(function($w2) { $w2->whereNull('p.dob_day')->whereNull('dup_nd.dob_day'); });
                     });
            })
            ->pluck('sfd.id')
            ->toArray();

        $allDupIds = array_unique(array_merge($dupCccdIds, $dupNameDobIds));

        return $query->whereIn('v_survey_detail_flat.detail_id', !empty($allDupIds) ? $allDupIds : [-1])
            ->orderBy('ho_dem')
            ->orderBy('ten')
            ->orderBy('dob_year')
            ->orderBy('dob_month')
            ->orderBy('dob_day')
            ->orderBy('detail_id');
    }
    
    /**
     * Filter ngày sinh không hợp lệ cho SurveyDetailFlat view
     */
    private function filterInvalidBirthDateView($query)
    {
        return $query->where(function($q) {
            $q->whereNull('dob_year')
              ->orWhere('dob_year', '<', 1900)
              ->orWhere('dob_year', '>', (int)date('Y'))
              ->orWhere('dob_month', '<', 1)
              ->orWhere('dob_month', '>', 12)
              ->orWhere('dob_day', '<', 1)
              ->orWhere('dob_day', '>', 31)
              ->orWhereRaw('(dob_month IN (4, 6, 9, 11) AND dob_day > 30)')
              ->orWhereRaw('(dob_month = 2 AND (dob_day > 29 OR (MOD(dob_year, 4) != 0 AND dob_day > 28)))')
              ->orWhereRaw('(dob_date IS NOT NULL AND dob_date > CURDATE())')
              ->orWhereRaw('(dob_year = YEAR(CURDATE()) AND (dob_month > MONTH(CURDATE()) OR (dob_month = MONTH(CURDATE()) AND dob_day > DAY(CURDATE()))))');
        });
    }
    
    /**
     * Filter năm tốt nghiệp không phù hợp với năm sinh cho SurveyDetailFlat view
     */
    private function filterInvalidGraduationYearView($query)
    {
        return $query->where(function($q) {
            $q->whereNotNull('graduation_year')
              ->whereNotNull('dob_year')
              ->where(function($subQ) {
                  $subQ->where(function($levelQ) {
                      // MN: < 5 tuổi
                      $levelQ->where(function($mnQ) {
                          $mnQ->whereNull('graduation_level_name')
                              ->orWhere('graduation_level_name', 'MN')
                              ->orWhere('graduation_level_name', '');
                      })->whereRaw('CAST(SUBSTRING(graduation_year, 1, 4) AS UNSIGNED) < dob_year + 5');
                  })->orWhere(function($levelQ) {
                      // TH: < 10 tuổi
                      $levelQ->where('graduation_level_name', 'TH')
                             ->whereRaw('CAST(SUBSTRING(graduation_year, 1, 4) AS UNSIGNED) < dob_year + 10');
                  })->orWhere(function($levelQ) {
                      // THCS: < 14 tuổi
                      $levelQ->where('graduation_level_name', 'THCS')
                             ->whereRaw('CAST(SUBSTRING(graduation_year, 1, 4) AS UNSIGNED) < dob_year + 14');
                  })->orWhere(function($levelQ) {
                      // THPT: < 17 tuổi
                      $levelQ->where('graduation_level_name', 'THPT')
                             ->whereRaw('CAST(SUBSTRING(graduation_year, 1, 4) AS UNSIGNED) < dob_year + 17');
                  });
              })
              ->orWhereRaw('CAST(SUBSTRING(graduation_year, 1, 4) AS UNSIGNED) > YEAR(CURDATE())')
              ->orWhereRaw('CAST(SUBSTRING(graduation_year, 1, 4) AS UNSIGNED) <= dob_year');
        });
    }
    
    /**
     * Filter lớp đang học không phù hợp với tuổi cho SurveyDetailFlat view
     */
    private function filterInvalidClassAgeView($query, $academicYear = null)
    {
        if (!$academicYear) {
            $academicYear = \App\Models\SurveyForm::currentAcademicYear();
        }
        $currentYear = is_numeric($academicYear) ? (int)$academicYear : (int)substr($academicYear, 0, 4);

        return $query->where(function($q) use ($currentYear) {
            $q->whereNotNull('education_block_code')
              ->whereNotNull('dob_year')
              ->where(function($noBoTuc) {
                  $noBoTuc->whereNull('is_bo_tuc')->orWhere('is_bo_tuc', 0);
              })
              ->where(function($subQ) use ($currentYear) {
                  // Mầm non
                  $subQ->where(function($mnQ) use ($currentYear) {
                      $mnQ->where('education_block_code', '3-12 tháng')
                          ->where(fn($w) => $w->where('dob_year', '>', $currentYear)->orWhere('dob_year', '<', $currentYear - 1))
                      ->orWhere(function($sq) use ($currentYear) {
                          $sq->where('education_block_code', '13-24 tháng')
                             ->where(fn($w) => $w->where('dob_year', '>', $currentYear - 1)->orWhere('dob_year', '<', $currentYear - 2));
                      })
                      ->orWhere(function($sq) use ($currentYear) {
                          $sq->where('education_block_code', '25-36 tháng')
                             ->where(fn($w) => $w->where('dob_year', '>', $currentYear - 2)->orWhere('dob_year', '<', $currentYear - 3));
                      })
                      ->orWhere(function($sq) use ($currentYear) {
                          $sq->where('education_block_code', '3-4 tuổi')
                             ->where(fn($w) => $w->where('dob_year', '>', $currentYear - 3)->orWhere('dob_year', '<', $currentYear - 4));
                      })
                      ->orWhere(function($sq) use ($currentYear) {
                          $sq->where('education_block_code', '4-5 tuổi')
                             ->where(fn($w) => $w->where('dob_year', '>', $currentYear - 4)->orWhere('dob_year', '<', $currentYear - 5));
                      })
                      ->orWhere(function($sq) use ($currentYear) {
                          $sq->where('education_block_code', '5-6 tuổi')
                             ->where(fn($w) => $w->where('dob_year', '>', $currentYear - 5)->orWhere('dob_year', '<', $currentYear - 6));
                      });
                  })
                  // Tiểu học (cho phép trễ đến 3 tuổi theo luật giáo dục)
                  ->orWhere(function($thQ) use ($currentYear) {
                      // Khối 1: 6 - 9 tuổi
                      $thQ->where(function($sq) use ($currentYear) {
                          $sq->where('education_block_code', '1')
                             ->where(fn($w) => $w->where('dob_year', '>', $currentYear - 6)->orWhere('dob_year', '<', $currentYear - 9));
                      })
                      // Khối 2: 7 - 10 tuổi
                      ->orWhere(function($sq) use ($currentYear) {
                          $sq->where('education_block_code', '2')
                             ->where(fn($w) => $w->where('dob_year', '>', $currentYear - 7)->orWhere('dob_year', '<', $currentYear - 10));
                      })
                      // Khối 3: 8 - 11 tuổi
                      ->orWhere(function($sq) use ($currentYear) {
                          $sq->where('education_block_code', '3')
                             ->where(fn($w) => $w->where('dob_year', '>', $currentYear - 8)->orWhere('dob_year', '<', $currentYear - 11));
                      })
                      // Khối 4: 9 - 12 tuổi
                      ->orWhere(function($sq) use ($currentYear) {
                          $sq->where('education_block_code', '4')
                             ->where(fn($w) => $w->where('dob_year', '>', $currentYear - 9)->orWhere('dob_year', '<', $currentYear - 12));
                      })
                      // Khối 5: 10 - 13 tuổi
                      ->orWhere(function($sq) use ($currentYear) {
                          $sq->where('education_block_code', '5')
                             ->where(fn($w) => $w->where('dob_year', '>', $currentYear - 10)->orWhere('dob_year', '<', $currentYear - 13));
                      });
                  })
                  // THCS
                  ->orWhere(function($thcsQ) use ($currentYear) {
                      $thcsQ->where(function($sq) use ($currentYear) {
                          $sq->where('education_block_code', '6')
                             ->where(fn($w) => $w->where('dob_year', '>', $currentYear - 11)->orWhere('dob_year', '<', $currentYear - 14));
                      })
                      ->orWhere(function($sq) use ($currentYear) {
                          $sq->where('education_block_code', '7')
                             ->where(fn($w) => $w->where('dob_year', '>', $currentYear - 12)->orWhere('dob_year', '<', $currentYear - 15));
                      })
                      ->orWhere(function($sq) use ($currentYear) {
                          $sq->where('education_block_code', '8')
                             ->where(fn($w) => $w->where('dob_year', '>', $currentYear - 13)->orWhere('dob_year', '<', $currentYear - 16));
                      })
                      ->orWhere(function($sq) use ($currentYear) {
                          $sq->where('education_block_code', '9')
                             ->where(fn($w) => $w->where('dob_year', '>', $currentYear - 14)->orWhere('dob_year', '<', $currentYear - 17));
                      });
                  })
                  // THPT
                  ->orWhere(function($thptQ) use ($currentYear) {
                      $thptQ->where(function($sq) use ($currentYear) {
                          $sq->where('education_block_code', '10')
                             ->where(fn($w) => $w->where('dob_year', '>', $currentYear - 15)->orWhere('dob_year', '<', $currentYear - 19));
                      })
                      ->orWhere(function($sq) use ($currentYear) {
                          $sq->where('education_block_code', '11')
                             ->where(fn($w) => $w->where('dob_year', '>', $currentYear - 16)->orWhere('dob_year', '<', $currentYear - 20));
                      })
                      ->orWhere(function($sq) use ($currentYear) {
                          $sq->where('education_block_code', '12')
                             ->where(fn($w) => $w->where('dob_year', '>', $currentYear - 17)->orWhere('dob_year', '<', $currentYear - 21));
                      });
                  });
              });
        });
    }
    
    /**
     * Filter năm tốt nghiệp không phù hợp với lớp đang học/bỏ học cho SurveyDetailFlat view
     */
    private function filterInvalidGraduationClassView($query)
    {
        $mnBlocks = ["'3-12 tháng'", "'13-24 tháng'", "'25-36 tháng'", "'3-4 tuổi'", "'4-5 tuổi'", "'5-6 tuổi'"];
        $thBlocks = array_merge($mnBlocks, ["'1'", "'2'", "'3'", "'4'", "'5'"]);
        $thcsBlocks = array_merge($thBlocks, ["'6'", "'7'", "'8'", "'9'"]);
        
        $mnBlocksStr = implode(',', $mnBlocks);
        $thBlocksStr = implode(',', $thBlocks);
        $thcsBlocksStr = implode(',', $thcsBlocks);

        return $query->where(function($q) use ($mnBlocksStr, $thBlocksStr, $thcsBlocksStr) {
            $q->whereNotNull('graduation_level_name')
              ->where(function($subQ) use ($mnBlocksStr, $thBlocksStr, $thcsBlocksStr) {
                  // Đã tốt nghiệp TH
                  $subQ->where(function($thQ) use ($mnBlocksStr) {
                      $thQ->where('graduation_level_name', 'TH')
                          ->where(function($errQ) use ($mnBlocksStr) {
                              $errQ->whereRaw("education_block_code IN ($mnBlocksStr)")
                                   ->orWhereBetween('bo_hoc_lop', [1, 4])
                                   ->orWhereBetween('hoc_xong_lop', [1, 4]);
                          });
                  })
                  // Đã tốt nghiệp THCS
                  ->orWhere(function($thcsQ) use ($thBlocksStr) {
                      $thcsQ->where('graduation_level_name', 'THCS')
                            ->where(function($errQ) use ($thBlocksStr) {
                                $errQ->whereRaw("education_block_code IN ($thBlocksStr)")
                                     ->orWhereBetween('bo_hoc_lop', [1, 8])
                                     ->orWhereBetween('hoc_xong_lop', [1, 8]);
                            });
                  })
                  // Đã tốt nghiệp THPT
                  ->orWhere(function($thptQ) use ($thcsBlocksStr) {
                      $thptQ->where('graduation_level_name', 'THPT')
                            ->where(function($errQ) use ($thcsBlocksStr) {
                                $errQ->whereRaw("education_block_code IN ($thcsBlocksStr)")
                                     ->orWhereBetween('bo_hoc_lop', [1, 11])
                                     ->orWhereBetween('hoc_xong_lop', [1, 11]);
                            });
                  });
              });
        });
    }
    
    /**
     * Filter thiếu thông tin học tập cho SurveyDetailFlat view
     */
    private function filterMissingStudyInfoView($query)
    {
        return $query->where(function($q) {
            $q->where(function($subQ) {
                // Có khối học nhưng không có tên trường
                $subQ->whereNotNull('education_block_code')
                     ->whereNull('school_name');
            })->orWhere(function($subQ) {
                // Có tên trường nhưng không có khối học
                $subQ->whereNotNull('school_name')
                     ->whereNull('education_block_code');
            });
        });
    }
    
    /**
     * Filter đối tượng PPC thiếu thông tin học tập cho SurveyDetailFlat view
     */
    private function filterPPCMissingStudyInfoView($query, $academicYear = null)
    {
        if (!$academicYear) {
            $academicYear = \App\Models\SurveyForm::currentAcademicYear();
        }
        $currentYear = is_numeric($academicYear) ? (int)$academicYear : (int)substr($academicYear, 0, 4);
        $minDobYear = $currentYear - 18;
        $maxDobYear = $currentYear - 3;
        
        return $query->where(function($q) use ($minDobYear, $maxDobYear) {
            $q->whereNotNull('dob_year')
              ->whereBetween('dob_year', [$minDobYear, $maxDobYear])
              ->whereNull('education_block_code')
              ->whereNull('school_name')
              ->whereNull('bo_hoc_lop')
              ->where(function($gradQ) {
                  $gradQ->whereNull('graduation_level_name')
                        ->orWhere('graduation_level_name', '!=', 'THPT');
              })
              ->where(function($disQ) {
                  $disQ->where('dis_has_certificate', 0)
                       ->where('dis_can_learn', '!=', 0);
              });
        });
    }
    
    /**
     * Filter đối tượng có hoàn cảnh đặc biệt thiếu chi tiết
     */
    private function filterSpecialCircumstanceView($query)
    {
        return $query->whereNotNull('sc_name')
                     ->where(function($q) {
                         $q->whereNull('sc_detail')
                           ->orWhereRaw("TRIM(sc_detail) = ''");
                     });
    }
    
    /**
     * Filter lỗi logic và mâu thuẫn thông tin
     */
    private function filterLogicErrorsView($query, $academicYear = null)
    {
        return $query->where(function($q) use ($academicYear) {
            // Ngày sinh trong tương lai
            $q->where(function($subQ) {
                $subQ->whereRaw('dob_date IS NOT NULL AND dob_date > CURDATE()');
            })
            // Vừa học vừa bỏ học trong cùng năm
            ->orWhere(function($subQ) {
                $subQ->whereNotNull('bo_hoc_lop')
                     ->whereNotNull('education_block_code')
                     ->whereRaw('bo_hoc_nam = academic_year');
            })
            // Tình trạng cư trú đã chết/chuyển đi nhưng vẫn có hồ sơ học
            ->orWhere(function($subQ) {
                $subQ->whereIn('residence_status_name', ['Chết', 'Đã chuyển đi', 'Đi khỏi địa phương'])
                     ->whereNotNull('education_block_code');
            })
            // Đã tốt nghiệp THPT nhưng đang học THPT
            ->orWhere(function($subQ) {
                $subQ->where('graduation_level_name', 'THPT')
                     ->whereIn('education_block_code', ['10', '11', '12']);
            })
            // Năm tốt nghiệp nhỏ hơn hoặc bằng năm sinh
            ->orWhere(function($subQ) {
                $subQ->whereNotNull('graduation_year')
                     ->whereNotNull('dob_year')
                     ->whereRaw('CAST(SUBSTRING(graduation_year, 1, 4) AS UNSIGNED) <= dob_year');
            });
        });
    }
    
    
    private function buildBaseQuery($academicYear, $provinceId, $districtId, $wardId, $villageId, $scheme, $user)
    {
        $query = \App\Models\SurveyFormDetail::with([
            'person',
            'person.ethnicity',
            'person.religion',
            'person.type',
            'person.status',
            'surveyForm',
            'surveyForm.headPerson',
            'education.school',
            'education.block',
            'education.graduationLevel',
            'education.vocationalLevel',
            'specialCircumstance.specialCircumstance'
        ])->notDeleted();
        
        // Lọc theo năm học
        if ($academicYear && $academicYear !== 'all') {
            $query->where('academic_year', $academicYear);
        }
        
        // Lọc theo khu vực - ưu tiên từ cấp nhỏ đến lớn
        // Lọc theo thôn/ấp trước, sau đó mới đến xã/huyện/tỉnh
        if ($villageId) {
            $query->whereHas('surveyForm', function($q) use ($villageId) {
                $q->where('village_id', $villageId);
            });
        } elseif ($wardId) {
            $query->whereHas('surveyForm', function($q) use ($wardId, $scheme) {
                if ($scheme === 'old') {
                    $q->where('old_ward_id', $wardId);
                } else {
                    $q->where('new_ward_id', $wardId);
                }
            });
        } elseif ($districtId) {
            $query->whereHas('surveyForm', function($q) use ($districtId, $scheme) {
                if ($scheme === 'old') {
                    $q->where('old_district_id', $districtId);
                } else {
                    $q->where('new_district_id', $districtId);
                }
            });
        } elseif ($provinceId) {
            $query->whereHas('surveyForm', function($q) use ($provinceId, $scheme) {
                if ($scheme === 'old') {
                    $q->where('old_province_id', $provinceId);
                } else {
                    $q->where('new_province_id', $provinceId);
                }
            });
        }
        
        return $query;
    }
    
    private function applyCriteriaFilter($query, $criteria, $academicYear = null, $provinceId = null, $districtId = null, $wardId = null, $villageId = null, $scheme = 'old')
    {
        switch ($criteria) {
            case 'duplicate_pcgd':
                return $this->filterDuplicatePCGD($query, $academicYear, $provinceId, $districtId, $wardId, $villageId, $scheme);
            case 'invalid_birth_date':
                return $this->filterInvalidBirthDate($query);
            case 'invalid_graduation_year':
                return $this->filterInvalidGraduationYear($query);
            case 'invalid_class_age':
                return $this->filterInvalidClassAge($query);
            case 'invalid_graduation_class':
                return $this->filterInvalidGraduationClass($query);
            case 'missing_study_info':
                return $this->filterMissingStudyInfo($query);
            case 'ppc_missing_study_info':
                return $this->filterPPCMissingStudyInfo($query);
            case 'logic_errors':
                return $this->filterLogicErrors($query);
            default:
                return $query;
        }
    }
    
    private function filterDuplicatePCGD($query, $academicYear = null, $provinceId = null, $districtId = null, $wardId = null, $villageId = null, $scheme = 'old')
    {
        // Tìm các đối tượng có thông tin trùng lặp trong cùng năm học và khu vực
        $whereConditions = [];
        $bindings = [];
        
        // Điều kiện năm học
        if ($academicYear && $academicYear !== 'all') {
            $whereConditions[] = 'sfd2.academic_year = ?';
            $bindings[] = $academicYear;
        }
        
        // Điều kiện khu vực - ưu tiên từ cấp nhỏ đến lớn (nhất quán với logic lọc chính)
        if ($villageId) {
            $whereConditions[] = 'sf2.village_id = ?';
            $bindings[] = $villageId;
        } elseif ($wardId) {
            $whereConditions[] = 'sf2.' . ($scheme === 'old' ? 'old_ward_id' : 'new_ward_id') . ' = ?';
            $bindings[] = $wardId;
        } elseif ($districtId) {
            $whereConditions[] = 'sf2.' . ($scheme === 'old' ? 'old_district_id' : 'new_district_id') . ' = ?';
            $bindings[] = $districtId;
        } elseif ($provinceId) {
            $whereConditions[] = 'sf2.' . ($scheme === 'old' ? 'old_province_id' : 'new_province_id') . ' = ?';
            $bindings[] = $provinceId;
        }
        
        $whereClause = '';
        if (!empty($whereConditions)) {
            $whereClause = ' AND ' . implode(' AND ', $whereConditions);
        }
        
        return $query->whereRaw('EXISTS (
            SELECT 1 FROM survey_form_details sfd2 
            JOIN people p2 ON sfd2.person_id = p2.id 
            JOIN people p1 ON survey_form_details.person_id = p1.id
            JOIN survey_forms sf2 ON sfd2.survey_form_id = sf2.id
            WHERE sfd2.id != survey_form_details.id 
            AND p2.ho_dem = p1.ho_dem 
            AND p2.ten = p1.ten 
            AND p2.dob_day = p1.dob_day 
            AND p2.dob_month = p1.dob_month 
            AND p2.dob_year = p1.dob_year
            AND sfd2.is_deleted = 0
            AND survey_form_details.is_deleted = 0
            AND p1.is_deleted = 0
            AND p2.is_deleted = 0
            ' . $whereClause . '
        )', $bindings);
    }
    
    private function filterInvalidBirthDate($query)
    {
        return $query->whereHas('person', function($q) {
            $q->where(function($subQ) {
                // Ngày sinh trong tương lai
                $subQ->whereRaw('CONCAT(dob_year, "-", LPAD(dob_month, 2, "0"), "-", LPAD(dob_day, 2, "0")) > CURDATE()')
                     // Hoặc năm sinh quá xa (trước 1900 hoặc sau năm hiện tại)
                     ->orWhere('dob_year', '<', 1900)
                     ->orWhere('dob_year', '>', date('Y'));
            });
        });
    }
    
    private function filterInvalidGraduationYear($query)
    {
        return $query->whereHas('person', function($q) {
            $q->where(function($subQ) {
                // Năm tốt nghiệp không hợp lý với năm sinh
                $subQ->whereRaw('graduation_year IS NOT NULL AND graduation_year < dob_year + 5')
                     ->orWhereRaw('graduation_year IS NOT NULL AND graduation_year > dob_year + 25');
            });
        });
    }
    
    private function filterInvalidClassAge($query)
    {
        return $query->whereHas('education', function($q) {
            $q->where(function($subQ) {
                // Lớp học không phù hợp với tuổi
                $subQ->whereRaw('class_label IS NOT NULL AND (
                    (class_label LIKE "%1%" AND dob_year > YEAR(CURDATE()) - 7) OR
                    (class_label LIKE "%12%" AND dob_year > YEAR(CURDATE()) - 18)
                )');
            });
        });
    }
    
    private function filterInvalidGraduationClass($query)
    {
        return $query->whereHas('person', function($q) {
            $q->where(function($subQ) {
                // Năm tốt nghiệp không phù hợp với lớp tốt nghiệp
                $subQ->whereRaw('graduation_year IS NOT NULL AND graduation_class IS NOT NULL AND (
                    (graduation_class LIKE "%5%" AND graduation_year < dob_year + 11) OR
                    (graduation_class LIKE "%9%" AND graduation_year < dob_year + 15)
                )');
            });
        });
    }
    
    private function filterMissingStudyInfo($query)
    {
        return $query->where(function($q) {
            $q->whereNull('person_education_id')
              ->orWhereHas('education', function($subQ) {
                  $subQ->whereNull('school_id')
                       ->whereNull('class_label');
              });
        });
    }
    
    private function filterPPCMissingStudyInfo($query)
    {
        return $query->whereNotNull('person_pcgd_id')
                    ->where(function($q) {
                        $q->whereNull('person_education_id')
                          ->orWhereHas('education', function($subQ) {
                              $subQ->whereNull('school_id')
                                   ->whereNull('class_label');
                          });
                    });
    }
    
    private function filterLogicErrors($query)
    {
        return $query->where(function($q) {
            // Các lỗi logic khác
            $q->whereHas('person', function($subQ) {
                $subQ->where(function($logicQ) {
                    // Ngày sinh không hợp lệ
                    $logicQ->whereRaw('dob_day > 31 OR dob_day < 1')
                           ->orWhereRaw('dob_month > 12 OR dob_month < 1')
                           ->orWhereRaw('dob_year > YEAR(CURDATE()) OR dob_year < 1900');
                });
            });
        });
    }
    
    private function formatRowData($item)
    {
        return [
            'detail_id' => $item->id,
            'survey_id' => $item->survey_form_id,
            'person_id' => $item->person_id,
            
            // Thông tin đối tượng
            'ho_dem' => $item->person->ho_dem ?? '',
            'ten' => $item->person->ten ?? '',
            'dob_day' => $item->person->dob_day ?? '',
            'dob_month' => $item->person->dob_month ?? '',
            'dob_year' => $item->person->dob_year ?? '',
            'is_female' => $item->person->is_female ?? false,
            'identity_number' => $item->person->identity_number ?? '',
            'ethnicity' => $item->person->ethnicity?->name ?? '',
            'religion' => $item->person->religion?->name ?? '',
            'priority_name' => $item->specialCircumstance?->specialCircumstance?->name ?? '',
            
            // Thông tin nơi đang ở
            'chuho_ho_dem' => $item->surveyForm?->headPerson?->ho_dem ?? '',
            'chuho_ten' => $item->surveyForm?->headPerson?->ten ?? '',
            'addressline' => $item->person->addressline ?? '',
            'so_phieu' => $item->surveyForm->so_phieu ?? '',
            'residence_type_name' => $item->person->type?->name ?? '',
            'residence_status_name' => $item->person->status?->name ?? '',
            
            // Thông tin học tập
            'education_block_code' => $item->education->block?->name ?? '',
            'education_grade_name' => $item->education->class_label ?? '',
            'studying_district' => $item->education->studying_district ?? '',
            'school_name' => $item->education->school->name ?? '',
            'school_code' => $item->education->school->code ?? '',
            'graduation_level_name' => $item->education->graduationLevel?->name ?? '',
            'is_bo_tuc' => $item->education->is_bo_tuc ?? false,
            'graduation_year' => $item->education->graduation_year ?? '',
            'vocational_level_name' => $item->education->vocationalLevel?->name ?? '',
            'vocational_grad_cert_no' => $item->education->graduation_certificate_no ?? '',
            'vocational_grad_year' => $item->education->vocational_grad_year ?? '',
            'hoc_xong_lop' => $item->person->hoc_xong_lop ?? '',
            'hoc_xong_nam' => $item->person->hoc_xong_nam ?? '',
            'bo_hoc_lop' => $item->person->bo_hoc_lop ?? '',
            'bo_hoc_nam' => $item->person->bo_hoc_nam ?? '',
            
            // Xóa mù chữ
            'lit_dang_hoc_lop' => $item->person->lit_dang_hoc_lop ?? '',
            'lit_hoan_thanh_lop' => $item->person->lit_hoan_thanh_lop ?? '',
            'lit_tai_mu_muc' => $item->person->lit_tai_mu_muc ?? '',
            
            // Khuyết tật
            'dis_VAN_DONG' => $item->person->dis_VAN_DONG ?? false,
            'dis_NGHE_NOI' => $item->person->dis_NGHE_NOI ?? false,
            'dis_NHIN' => $item->person->dis_NHIN ?? false,
            'dis_THAN_KINH' => $item->person->dis_THAN_KINH ?? false,
            'dis_TRI_TUE' => $item->person->dis_TRI_TUE ?? false,
            'dis_HOC_TAP' => $item->person->dis_HOC_TAP ?? false,
            'dis_TU_KY' => $item->person->dis_TU_KY ?? false,
            'dis_KHAC' => $item->person->dis_KHAC ?? false,
            'dis_has_certificate' => $item->person->dis_has_certificate ?? false,
            'dis_can_learn' => $item->person->dis_can_learn ?? false,
            
            // Hoàn cảnh
            'sc_name' => $item->person->sc_name ?? '',
            'sc_detail' => $item->person->sc_detail ?? '',
            
            // Thông tin liên lạc
            'relationship_name' => $item->person->relationship_name ?? '',
            'contact_name' => $item->person->contact_name ?? '',
            'contact_phone' => $item->person->contact_phone ?? '',
            'contact_note' => $item->person->contact_note ?? '',
        ];
    }
    
    private function getTableGroups()
    {
        return [
            [
                'title' => 'Thông tin đối tượng',
                'keys' => ['ho_dem', 'ten', 'dob_day', 'dob_month', 'dob_year', 'identity_number', 'is_female', 'ethnicity', 'religion', 'priority_name']
            ],
            [
                'title' => 'Thông tin nơi đang ở',
                'keys' => ['chuho_ho_dem', 'chuho_ten', 'addressline', 'so_phieu', 'residence_type_name', 'residence_status_name']
            ],
            [
                'title' => 'Thông tin học tập',
                'keys' => ['education_block_code', 'education_grade_name', 'studying_district', 'school_name', 'school_code', 'graduation_level_name', 'is_bo_tuc', 'graduation_year', 'vocational_level_name', 'graduation_certificate_no', 'vocational_grad_year', 'hoc_xong_lop', 'hoc_xong_nam', 'bo_hoc_lop', 'bo_hoc_nam']
            ],
            [
                'title' => 'Xóa mù chữ',
                'keys' => ['lit_dang_hoc_lop', 'lit_hoan_thanh_lop', 'lit_tai_mu_muc']
            ],
            [
                'title' => 'Khuyết tật',
                'keys' => ['dis_van_dong', 'dis_nghe_noi', 'dis_nhin', 'dis_than_kinh', 'dis_tri_tue', 'dis_hoc_tap', 'dis_tu_ky', 'dis_khac', 'dis_has_certificate', 'dis_can_learn']
            ],
            [
                'title' => 'Hoàn cảnh',
                'keys' => ['sc_name', 'sc_detail']
            ],
            [
                'title' => 'Thông tin liên lạc',
                'keys' => ['relationship_name', 'contact_name', 'contact_phone', 'contact_note']
            ]
        ];
    }
    
    private function getTableColumns()
    {
        return [
            'ho_dem' => 'Họ đệm',
            'ten' => 'Tên',
            'dob_day' => 'Ngày sinh',
            'dob_month' => 'Tháng sinh',
            'dob_year' => 'Năm sinh',
            'is_female' => 'Giới tính (Nữ)',
            'identity_number' => 'CCCD',
            'ethnicity' => 'Dân tộc',
            'religion' => 'Tôn giáo',
            'priority_name' => 'Diện ưu tiên',
            'chuho_ho_dem' => 'Chủ hộ - Họ đệm',
            'chuho_ten' => 'Chủ hộ - Tên',
            'addressline' => 'Địa chỉ',
            'so_phieu' => 'Số phiếu',
            'residence_type_name' => 'Diện cư trú',
            'residence_status_name' => 'Tình trạng cư trú',
            'education_block_code' => 'Khối học',
            'education_grade_name' => 'Lớp học',
            'studying_district' => 'Quận/Huyện học',
            'school_name' => 'Tên trường',
            'school_code' => 'Mã trường',
            'graduation_level_name' => 'Bậc TN PT',
            'is_bo_tuc' => 'Bổ túc',
            'graduation_year' => 'Năm TN PT',
            'vocational_level_name' => 'Bậc TN nghề',
            'graduation_certificate_no' => 'Số bằng TN',
            'vocational_grad_year' => 'Năm TN nghề',
            'hoc_xong_lop' => 'Học xong lớp',
            'hoc_xong_nam' => 'Học xong năm',
            'bo_hoc_lop' => 'Bỏ học lớp',
            'bo_hoc_nam' => 'Bỏ học năm',
            'lit_dang_hoc_lop' => 'Đang học lớp',
            'lit_hoan_thanh_lop' => 'Hoàn thành lớp',
            'lit_tai_mu_muc' => 'Tái mù mức',
            'dis_van_dong' => 'KT vận động',
            'dis_nghe_noi' => 'KT nghe, nói',
            'dis_nhin' => 'KT nhìn',
            'dis_than_kinh' => 'KT thần kinh',
            'dis_tri_tue' => 'KT trí tuệ',
            'dis_hoc_tap' => 'KT học tập',
            'dis_tu_ky' => 'Tự kỷ',
            'dis_khac' => 'KT khác',
            'dis_has_certificate' => 'Có chứng nhận',
            'dis_can_learn' => 'Khả năng học',
            'sc_name' => 'Hoàn cảnh ĐB',
            'sc_detail' => 'Chi tiết HC',
            'relationship_name' => 'Quan hệ với chủ hộ',
            'contact_name' => 'Họ tên (Cha/Mẹ)',
            'contact_phone' => 'Điện thoại',
            'contact_note' => 'Ghi chú'
        ];
    }
    
    /**
     * Lấy dữ liệu diện ưu tiên từ bảng priority_categories
     */
    private function getPriorityData($personId)
    {
        try {
            $priorityRecord = DB::table('people')
                ->join('priority_categories', 'people.priority_category_id', '=', 'priority_categories.id')
                ->where('people.id', $personId)
                ->select('priority_categories.name as priority_name')
                ->first();
            
            return [
                'priority_name' => $priorityRecord ? $priorityRecord->priority_name : ''
            ];
            
        } catch (\Exception $e) {
            Log::error('Error getting priority data for person ' . $personId, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'priority_name' => ''
            ];
        }
    }
    
    /**
     * Lấy dữ liệu khuyết tật từ bảng person_disabilities
     */
    private function getDisabilityData($personId)
    {
        try {
            // Lấy disability_ids từ person_disabilities
            $disabilityRecord = DB::table('person_disabilities')
                ->where('person_id', $personId)
                ->first();
            
            if (!$disabilityRecord || !$disabilityRecord->disability_ids) {
                return [
                    'dis_van_dong' => false,
                    'dis_nghe_noi' => false,
                    'dis_nhin' => false,
                    'dis_than_kinh' => false,
                    'dis_tri_tue' => false,
                    'dis_hoc_tap' => false,
                    'dis_tu_ky' => false,
                    'dis_khac' => false,
                    'dis_has_certificate' => false,
                    'dis_can_learn' => false,
                ];
            }
            
            // Parse disability_ids (có thể là JSON array hoặc comma-separated)
            $disabilityIds = $disabilityRecord->disability_ids;
            if (is_string($disabilityIds)) {
                // Nếu là JSON string, decode nó
                if (strpos($disabilityIds, '[') === 0) {
                    $disabilityIds = json_decode($disabilityIds, true);
                } else {
                    // Nếu là comma-separated string
                    $disabilityIds = explode(',', $disabilityIds);
                }
            }
            
            if (!is_array($disabilityIds)) {
                $disabilityIds = [];
            }
            
            // Lấy mapping từ disability_categories
            $disabilityCategories = DB::table('disability_categories')
                ->whereIn('code', ['VAN_DONG', 'NGHE_NOI', 'NHIN', 'THAN_KINH', 'TRI_TUE', 'HOC_TAP', 'TU_KY', 'KHAC'])
                ->get()
                ->keyBy('code');
            
            // Kiểm tra từng loại khuyết tật
            $result = [
                'dis_van_dong' => false,
                'dis_nghe_noi' => false,
                'dis_nhin' => false,
                'dis_than_kinh' => false,
                'dis_tri_tue' => false,
                'dis_hoc_tap' => false,
                'dis_tu_ky' => false,
                'dis_khac' => false,
                'dis_has_certificate' => $disabilityRecord->has_certificate ?? false,
                'dis_can_learn' => $disabilityRecord->can_learn ?? false,
            ];
            
            foreach ($disabilityCategories as $code => $category) {
                $key = 'dis_' . strtolower($code);
                if (isset($result[$key])) {
                    $result[$key] = in_array($category->id, $disabilityIds);
                }
            }
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Error getting disability data for person ' . $personId, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'dis_van_dong' => false,
                'dis_nghe_noi' => false,
                'dis_nhin' => false,
                'dis_than_kinh' => false,
                'dis_tri_tue' => false,
                'dis_hoc_tap' => false,
                'dis_tu_ky' => false,
                'dis_khac' => false,
                'dis_has_certificate' => false,
                'dis_can_learn' => false,
            ];
        }
    }
    
    private function sortDuplicateRows($rows)
    {
        // Sắp xếp theo họ đệm, tên, ngày sinh để những người trùng hiển thị gần nhau
        return $rows->sort(function($a, $b) {
            // Tạo key sắp xếp từ họ đệm + tên + ngày sinh
            $keyA = ($a['ho_dem'] ?? '') . ($a['ten'] ?? '') . 
                   str_pad($a['dob_year'] ?? '0', 4, '0', STR_PAD_LEFT) .
                   str_pad($a['dob_month'] ?? '0', 2, '0', STR_PAD_LEFT) .
                   str_pad($a['dob_day'] ?? '0', 2, '0', STR_PAD_LEFT);
                   
            $keyB = ($b['ho_dem'] ?? '') . ($b['ten'] ?? '') . 
                   str_pad($b['dob_year'] ?? '0', 4, '0', STR_PAD_LEFT) .
                   str_pad($b['dob_month'] ?? '0', 2, '0', STR_PAD_LEFT) .
                   str_pad($b['dob_day'] ?? '0', 2, '0', STR_PAD_LEFT);
            
            // So sánh key, nếu bằng nhau thì sắp xếp theo detail_id
            $compare = strcmp($keyA, $keyB);
            if ($compare === 0) {
                return $a['detail_id'] <=> $b['detail_id'];
            }
            return $compare;
        })->values(); // Reset keys để tránh lỗi khi convert sang JSON
    }
    
}
