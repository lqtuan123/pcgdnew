<?php

namespace App\Http\Controllers\Front;

use App\Models\SecondaryTeacherReport;
use App\Models\EducationalLevel;
use App\Services\GeoService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Models\School;
use App\Models\GeoUnit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class SecondaryTeacherReportController extends BaseMultiLevelReportController
{
    protected $modelClass = SecondaryTeacherReport::class;
    protected $viewName = 'front.secondary-teacher-reports.index';
    protected $routePrefix = 'secondary-teacher-reports';

    /**
     * Lấy ID của các cấp học THCS
     */
    private function getSecondaryLevelIds(): array
    {
        $secondaryLevels = EducationalLevel::whereIn('name', [
            'THCS',
            'THCS + THPT',
            'Tiểu học + THCS',
            'Tiểu học + THCS + THPT'
        ])->pluck('id')->toArray();

        return $secondaryLevels;
    }

    /**
     * Override method index để thêm filter theo educational_level_id
     */
    public function index(Request $request)
    {
        $defaultYear = date('Y') . '-' . (date('Y') + 1);
        $year = $request->get('year', $defaultYear);
        $q = trim((string) $request->get('q'));
        $perPage = (int) $request->get('per_page', 50);
        $scheme = $request->get('scheme', 'old');
        $geoIn = (array) $request->input('geo', []);

        $geoIds = [
            'province' => (int) ($geoIn['province_id'] ?? 0) ?: null,
            'district' => (int) ($geoIn['district_id'] ?? 0) ?: null,
            'ward'     => (int) ($geoIn['ward_id'] ?? 0) ?: null,
            'school'   => (int) ($geoIn['school_id'] ?? $request->input('geo_school_id') ?? 0) ?: null,
        ];

        // Xác định cấp độ hiển thị dựa trên user role và filter
        $user = \Illuminate\Support\Facades\Auth::user();
        $level = $this->reportService->determineDisplayLevel($user, $geoIds);
        
        // Lấy dữ liệu từ các bảng khác nhau
        $reportData = $this->getSecondaryReportData($year, $geoIds, $level);
        
        // Tạo dữ liệu cho view
        // Không tạo fakeReport để tránh hiển thị thêm dòng trong bảng
            $reports = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
            $stats = collect([]);
            $routePrefix = $this->routePrefix;

            return view($this->viewName, compact(
            'reports', 'year', 'q', 'stats', 'level', 'perPage', 'routePrefix', 'geoIds', 'scheme', 'reportData'
        ));
    }

    /**
     * Lấy dữ liệu từ các bảng khác nhau
     */
    protected function getSecondaryReportData($year, $geoIds, $level)
    {
        $data = [
            'trong_bien_che' => null,
            'quan_ly' => null,
            'giao_vien' => null,
            'nhan_vien' => null,
            'giao_vien_hop_dong' => null,
            'nhu_cau_giao_vien' => null,
        ];

        // Xác định điều kiện tìm kiếm
        $whereConditions = ['academic_year' => $year];
        
        if ($level === 'school' && $geoIds['school']) {
            $whereConditions['school_id'] = $geoIds['school'];
        } elseif ($level === 'ward' && $geoIds['ward']) {
            $whereConditions['ward_id'] = $geoIds['ward'];
        } elseif ($level === 'province' && $geoIds['province']) {
            // Lấy tất cả ward trong tỉnh
            $wardIds = \App\Models\GeoUnit::where('parent_id', $geoIds['province'])
                ->where('type_level', 3)
                ->pluck('id')
                ->toArray();
            $whereConditions['ward_id'] = $wardIds;
        }

        // 2. Dòng "Quản lý" - từ secondary_management_report
        $quanLyRaw = \App\Models\SecondaryManagementReport::where($whereConditions)->first();
        $data['quan_ly'] = $this->processReportDataForIndex($quanLyRaw);

        // 3. Dòng "Giáo viên" - từ secondary_teacher_report
        $giaoVienRaw = \App\Models\SecondaryTeacherReport::where($whereConditions)->first();
        $data['giao_vien'] = $this->processReportDataForIndex($giaoVienRaw);

        // 4. Dòng "Nhân viên" - từ secondary_staff_report
        $nhanVienRaw = \App\Models\SecondaryStaffReport::where($whereConditions)->first();
        $data['nhan_vien'] = $this->processReportDataForIndex($nhanVienRaw);

        // 1. Dòng "Trong biên chế" - tính từ tổng của Quản lý + Giáo viên + Nhân viên (24 cột)
        $data['trong_bien_che'] = $this->calculateTrongBienCheFromThreeRows($quanLyRaw, $giaoVienRaw, $nhanVienRaw);

        // 5. Dòng "Giáo viên hợp đồng" - từ secondary_contract_teachers_report
        $giaoVienHopDongRaw = \App\Models\SecondaryContractTeachersReport::where($whereConditions)->first();
        $data['giao_vien_hop_dong'] = $this->processReportDataForIndex($giaoVienHopDongRaw);

        // 6. Dòng "Nhu cầu giáo viên" - từ secondary_teacher_demand
        $nhuCauGiaoVienRaw = \App\Models\SecondaryTeacherDemand::where($whereConditions)->first();
        $data['nhu_cau_giao_vien'] = $this->processReportDataForIndex($nhuCauGiaoVienRaw);

        return $data;
    }

    /**
     * Xử lý dữ liệu báo cáo cho hàm index - chỉ xử lý 24 cột cơ bản, giữ nguyên các cột khác
     */
    private function processReportDataForIndex($report)
    {
        if (!$report) {
            return null;
        }

        // Sử dụng json_encode/decode để copy đầy đủ object
        $processed = json_decode(json_encode($report));
        
        // Chỉ xử lý 24 cột từ Tổng số -> Tin học
        $basicFields = [
            'total', 'female', 'party_member', 'above_university', 'university', 'college', 'secondary',
            'mathematics', 'physics', 'chemistry', 'biology', 'literature', 'history', 'geography',
            'english', 'french', 'russian', 'other_language', 'civic_education', 'technology', 
            'physical_education', 'music', 'art', 'informatics'
        ];

        foreach ($basicFields as $field) {
            $value = $report->$field ?? 0;
            // Hiển thị rỗng nếu giá trị là 0 hoặc không có
            $processed->$field = ($value && $value != 0) ? $value : '';
        }

        return $processed;
    }

    /**
     * Tính dòng "Trong biên chế" từ tổng của 3 dòng Quản lý + Giáo viên + Nhân viên (24 cột)
     */
    private function calculateTrongBienCheFromThreeRows($quanLy, $giaoVien, $nhanVien)
    {
        $trongBienChe = new \stdClass();
        
        // 24 cột từ Tổng số -> Tin học
        $fields = [
            'total', 'female', 'party_member', 'above_university', 'university', 'college', 'secondary',
            'mathematics', 'physics', 'chemistry', 'biology', 'literature', 'history', 'geography',
            'english', 'french', 'russian', 'other_language', 'civic_education', 'technology', 
            'physical_education', 'music', 'art', 'informatics'
        ];

        foreach ($fields as $field) {
            $total = 0;
            $hasData = false;
            
            // Cộng từ dòng Quản lý
            if ($quanLy && isset($quanLy->$field) && $quanLy->$field > 0) {
                $total += $quanLy->$field;
                $hasData = true;
            }
            
            // Cộng từ dòng Giáo viên
            if ($giaoVien && isset($giaoVien->$field) && $giaoVien->$field > 0) {
                $total += $giaoVien->$field;
                $hasData = true;
            }
            
            // Cộng từ dòng Nhân viên
            if ($nhanVien && isset($nhanVien->$field) && $nhanVien->$field > 0) {
                $total += $nhanVien->$field;
                $hasData = true;
            }
            
            // Hiển thị rỗng nếu không có dữ liệu, ngược lại hiển thị tổng
            $trongBienChe->$field = $hasData ? $total : '';
        }

        return $trongBienChe;
    }

    /**
     * Lấy dữ liệu báo cáo theo cấp độ với filter THCS
     */
    private function getReportsByLevelWithFilter($level, $year, $q, $geoIds, $scheme, $perPage, $secondaryLevelIds)
    {
        switch ($level) {
            case 'school':
                return $this->getSchoolLevelReportsWithFilter($year, $q, $geoIds, $scheme, $perPage, $secondaryLevelIds);
            case 'ward':
                return $this->getWardLevelReportsWithFilter($year, $q, $geoIds, $scheme, $perPage, $secondaryLevelIds);
            case 'province':
                return $this->getProvinceLevelReportsWithFilter($year, $q, $geoIds, $scheme, $perPage, $secondaryLevelIds);
            default:
                return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Lấy dữ liệu cấp trường với filter THCS
     */
    private function getSchoolLevelReportsWithFilter($year, $q, $geoIds, $scheme, $perPage, $secondaryLevelIds)
    {
        if (!$geoIds['school']) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        // Lấy thông tin trường và kiểm tra xem có phải trường THCS không
        $school = \App\Models\School::active()
            ->where('id', $geoIds['school'])
            ->whereIn('educational_level_id', $secondaryLevelIds)
            ->first();
        if (!$school) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        $wardId = $school->getWardId();
        if (!$wardId) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        // Tìm báo cáo hiện có hoặc tạo mới
        $report = $this->modelClass::where('academic_year', $year)
            ->where('ward_id', $wardId)
            ->where('school_id', $geoIds['school'])
            ->first();

        if (!$report) {
            // Tạo báo cáo mới với dữ liệu mặc định
            $report = new $this->modelClass($this->getDefaultReportData($year, $wardId, $geoIds['school'], $school));
        }

        return new \Illuminate\Pagination\LengthAwarePaginator(
            collect([$report]),
            1,
            $perPage,
            1,
            [
                'path' => request()->url(),
                'pageName' => 'page',
                'query' => request()->query()
            ]
        );
    }

    /**
     * Lấy dữ liệu cấp xã với filter THCS
     */
    private function getWardLevelReportsWithFilter($year, $q, $geoIds, $scheme, $perPage, $secondaryLevelIds)
    {
        if (!$geoIds['ward']) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        // Lấy ward_code từ ward_id
        $ward = \App\Models\GeoUnit::find($geoIds['ward']);
        if (!$ward) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
        
        // Lấy tất cả trường THCS trong xã theo ward_code_new (chỉ trường đang hoạt động)
        $schoolsQuery = \App\Models\School::active()
            ->where('ward_code_new', $ward->code)
            ->whereIn('educational_level_id', $secondaryLevelIds);
        
        if ($q) {
            $schoolsQuery->where('name', 'like', "%{$q}%");
        }
        
        $schools = $schoolsQuery->orderBy('name')->get();
        
        // Lấy báo cáo hiện có cho các trường này
        $existingReports = $this->modelClass::with(['school', 'ward'])
            ->where('academic_year', $year)
            ->whereIn('school_id', $schools->pluck('id'))
            ->get()
            ->keyBy('school_id');
        
        // Tạo danh sách kết hợp: trường + báo cáo (nếu có)
        $combinedData = $schools->map(function($school) use ($existingReports, $year) {
            $report = $existingReports->get($school->id);
            
            if ($report) {
                // Có báo cáo, sử dụng dữ liệu từ báo cáo
                return $this->createReportObject($report, $school, true);
            } else {
                // Chưa có báo cáo, tạo object rỗng
                return $this->createEmptyReportObject($school, $year);
            }
        });
        
        // Tính tổng cộng từ các báo cáo có dữ liệu
        $reportsWithData = $existingReports->filter(function($report) {
            return $this->hasData($report);
        });
        
        $totalRow = $this->calculateTotals($reportsWithData);
        $totalRow->school_name = 'TỔNG CỘNG';
        $totalRow->school_code = '';
        $totalRow->school_rank = 0;
        $totalRow->has_data = true;
        
        // Thêm dòng tổng cộng
        $combinedData->push($totalRow);
        
        // Tạo paginator thủ công
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $items = $combinedData->slice($offset, $perPage)->values();
        
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $combinedData->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'page',
                'query' => request()->query()
            ]
        );
    }

    /**
     * Lấy dữ liệu cấp tỉnh với filter THCS
     */
    private function getProvinceLevelReportsWithFilter($year, $q, $geoIds, $scheme, $perPage, $secondaryLevelIds)
    {
        if (!$geoIds['province']) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        // Lấy tất cả xã/phường trong tỉnh
        $wards = \App\Models\GeoUnit::where('parent_id', $geoIds['province'])
            ->where('scheme', $scheme)
            ->where('type_level', 3)
            ->orderBy('name')
            ->get();
        
        // Lấy tất cả báo cáo trong tỉnh chỉ cho trường THCS (chỉ trường đang hoạt động)
        $reports = $this->modelClass::with(['school', 'ward'])
            ->where('academic_year', $year)
            ->whereHas('school', function ($schoolQuery) use ($secondaryLevelIds) {
                $schoolQuery->active()->whereIn('educational_level_id', $secondaryLevelIds);
            })
            ->whereHas('ward', function ($wardQuery) use ($geoIds, $scheme) {
                $wardQuery->where('parent_id', $geoIds['province'])
                         ->where('scheme', $scheme)
                         ->where('type_level', 3);
            })
            ->get();
        
        // Nhóm báo cáo theo ward_id
        $reportsByWard = $reports->groupBy('ward_id');
        
        // Tạo danh sách kết hợp: xã + tổng báo cáo (nếu có)
        $combinedData = $wards->map(function($ward) use ($reportsByWard, $year) {
            $wardReports = $reportsByWard->get($ward->id, collect([]));
            
            if ($wardReports->isNotEmpty()) {
                // Có báo cáo, tính tổng
                $total = $this->calculateTotals($wardReports);
                $total->ward = $ward;
                $total->ward_id = $ward->id;
                // ward_name sẽ được lấy thông qua relationship
                $total->has_data = true;
                return $total;
            } else {
                // Chưa có báo cáo, tạo object rỗng
                return $this->createEmptyWardObject($ward, $year);
            }
        });
        
        // Tính tổng cộng cho toàn tỉnh
        $provinceTotal = $this->calculateTotals($reports);
        // ward_name sẽ được lấy thông qua relationship
        $provinceTotal->school_rank = 0;
        $provinceTotal->has_data = true;
        
        // Thêm dòng tổng cộng
        $combinedData->push($provinceTotal);
        
        // Tạo paginator thủ công
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $items = $combinedData->slice($offset, $perPage)->values();
        
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $combinedData->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'page',
                'query' => request()->query()
            ]
        );
    }

    /**
     * Tạo object báo cáo từ dữ liệu có sẵn
     */
    protected function createReportObject($report, $school, $hasData = true)
    {
        $object = (object) [
            'id' => $report->id,
            'school_id' => $school->id,
            'school' => $school,
            'ward' => $report->ward,
            'academic_year' => $report->academic_year,
            'school_name' => $school->name,
            'school_code' => $school->code,
            'school_rank' => is_numeric($report->school_rank) ? (int) $report->school_rank : 0,
            'has_data' => $hasData
        ];

        // Thêm tất cả các trường báo cáo
        $reportFields = $this->getReportFields();
        foreach ($reportFields as $field) {
            $object->$field = $report->$field ?? 0;
        }

        return $object;
    }

    /**
     * Tạo object báo cáo rỗng cho trường
     */
    protected function createEmptyReportObject($school, $year)
    {
        $object = (object) [
            'id' => null,
            'school_id' => $school->id,
            'school' => $school,
            'ward' => null,
            'academic_year' => $year,
            'school_name' => $school->name,
            'school_code' => $school->code,
            'school_rank' => is_numeric($school->rank) ? (int) $school->rank : 0,
            'has_data' => false
        ];

        // Thêm tất cả các trường báo cáo với giá trị mặc định
        $reportFields = $this->getReportFields();
        foreach ($reportFields as $field) {
            $object->$field = 0;
        }

        return $object;
    }

    /**
     * Tạo object báo cáo rỗng cho xã
     */
    protected function createEmptyWardObject($ward, $year)
    {
        $object = (object) [
            'ward' => $ward,
            'ward_id' => $ward->id,
            // ward_name sẽ được lấy thông qua relationship
            'academic_year' => $year,
            'school_rank' => 0,
            'has_data' => false
        ];

        // Thêm tất cả các trường báo cáo với giá trị mặc định
        $reportFields = $this->getReportFields();
        foreach ($reportFields as $field) {
            $object->$field = 0;
        }

        return $object;
    }

    /**
     * Tính tổng cộng cho cấp xã
     */
    protected function calculateTotals($reports)
    {
        $total = new $this->modelClass;
        $total->school_name = 'TỔNG CỘNG';
        $total->school_rank = 0;
        
        // Tính tổng cho tất cả các trường số
        $reportFields = $this->getReportFields();
        foreach ($reportFields as $field) {
            if (in_array($field, ['teacher_student_ratio'])) {
                // Trường tỷ lệ tính trung bình chỉ của những trường có dữ liệu hợp lệ
                $validValues = $reports->filter(function($report) use ($field) {
                    return isset($report->$field) && 
                           $report->$field !== null && 
                           $report->$field !== '' && 
                           $report->$field > 0;
                });
                
                if ($validValues->isNotEmpty()) {
                    $total->$field = $validValues->avg($field);
                } else {
                    $total->$field = 0;
                }
            } else {
                // Các trường khác tính tổng
                $total->$field = $reports->sum($field);
            }
        }

        return $total;
    }

    /**
     * Kiểm tra báo cáo có dữ liệu không
     */
    protected function hasData($report)
    {
        $importantFields = ['total_teachers', 'principal', 'vice_principal'];
        
        foreach ($importantFields as $field) {
            if (isset($report->$field) && $report->$field > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Lấy danh sách các trường báo cáo
     */
    protected function getReportFields()
    {
        return [
            'principal', 'vice_principal', 'total_teachers', 'staff_quota',
            'contract_teachers', 'female_teachers', 'ethnic_teachers', 'teacher_student_ratio',
            'training_above_uni', 'training_uni', 'training_college', 'training_ped',
            'spec_math', 'spec_literature', 'spec_physics', 'spec_chemistry',
            'spec_biology', 'spec_history', 'spec_geography', 'spec_music',
            'spec_art', 'spec_pe', 'spec_english', 'spec_russian',
            'spec_french', 'spec_other_lang', 'spec_civic_edu', 'spec_technology',
            'spec_informatics', 'tpt_team', 'standard_good', 'standard_quite',
            'standard_reach', 'standard_not_reach', 'library_staff', 'lab_staff',
            'office_staff', 'medical_staff'
        ];
    }

    /**
     * Lấy dữ liệu mặc định cho báo cáo mới
     */
    protected function getDefaultReportData($year, $wardId, $schoolId, $school)
    {
        $data = [
            'academic_year' => $year,
            'ward_id' => $wardId,
            'school_id' => $schoolId,
            'school_name' => $school->name,
            'school_rank' => 0,
        ];

        // Thêm tất cả các trường báo cáo với giá trị mặc định
        $reportFields = $this->getReportFields();
        foreach ($reportFields as $field) {
            $data[$field] = 0;
        }

        return $data;
    }

    public function __construct(GeoService $geoService)
    {
        parent::__construct($geoService);
    }

    /**
     * Lấy tên file export (required by parent class)
     */
    protected function getExportFilename($year)
    {
        return 'bao_cao_doi_ngu_giao_vien_thcs_' . ($year ?: 'all') . '.xlsx';
    }

    /**
     * Lấy prefix cho temp file (required by parent class)
     */
    protected function getTempFilePrefix()
    {
        return 'secondary_teacher_report_';
    }

    /**
     * Lấy cấu trúc cột theo cấp độ
     */
    protected function columns($level = 'province')
    {
        // Cấu trúc cột cơ bản cho secondary teacher report
        $baseColumns = [
            'tt' => 'TT',
            'school_name' => 'Tên trường',
            'school_rank' => 'Hạng trường',
            'principal' => 'Hiệu trưởng',
            'vice_principal' => 'P.Hiệu trưởng',
            'total_teachers' => 'Tổng số',
            'staff_quota' => 'Biên chế',
            'contract_teachers' => 'Hợp đồng',
            'female_teachers' => 'Nữ',
            'ethnic_teachers' => 'Dân tộc',
            'teacher_student_ratio' => 'Tỷ lệ GV/Lớp',
            'training_above_uni' => 'Trên ĐH',
            'training_uni' => 'ĐH',
            'training_college' => 'CĐ',
            'training_ped' => 'THSP',
            'spec_math' => 'Toán',
            'spec_literature' => 'Ngữ văn',
            'spec_physics' => 'Lí',
            'spec_chemistry' => 'Hóa',
            'spec_biology' => 'Sinh',
            'spec_history' => 'Sử',
            'spec_geography' => 'Địa',
            'spec_music' => 'Nhạc',
            'spec_art' => 'MT',
            'spec_pe' => 'TD',
            'spec_english' => 'Anh',
            'spec_russian' => 'Nga',
            'spec_french' => 'Pháp',
            'spec_other_lang' => 'Khác',
            'spec_civic_edu' => 'GDCD',
            'spec_technology' => 'Công nghệ',
            'spec_informatics' => 'Tin học',
            'tpt_team' => 'TPT.Đội',
            'standard_good' => 'Tốt',
            'standard_quite' => 'Khá',
            'standard_reach' => 'Đạt',
            'standard_not_reach' => 'Chưa đạt',
            'library_staff' => 'Thư viện',
            'lab_staff' => 'TBị - Thí nghiệm',
            'office_staff' => 'Văn phòng',
            'medical_staff' => 'Y tế',
        ];

        // Điều chỉnh cột theo cấp độ
        if ($level === 'province') {
            // Cấp tỉnh: thay "Tên trường" bằng "Tên xã/phường"
            $baseColumns['school_name'] = 'Tên xã/phường';
            // Ẩn cột "Hạng trường"
            unset($baseColumns['school_rank']);
        } elseif ($level === 'ward') {
            // Cấp xã: hiển thị "Tên trường" và "Hạng trường"
            // Không thay đổi gì
        } else {
            // Cấp trường: ẩn "Tên trường" và "Hạng trường"
            unset($baseColumns['school_name']);
            unset($baseColumns['school_rank']);
        }

        return $baseColumns;
    }

    /**
     * Lấy nhóm cột
     */
    protected function columnGroups($level = 'province')
    {
        // Cấu trúc nhóm cột cho secondary teacher report
        $groups = [
            'don-vi' => [
                'title' => 'Đơn vị',
                'colspan' => $level === 'province' ? 2 : ($level === 'ward' ? 3 : 1),
                'columns' => $level === 'province' ? 
                    ['tt', 'school_name'] : 
                    ($level === 'ward' ? 
                        ['tt', 'school_name', 'school_rank'] : 
                        ['tt'])
            ],
            'cbql' => [
                'title' => 'CBQL',
                'colspan' => 2,
                'columns' => ['principal', 'vice_principal']
            ],
            'giao-vien' => [
                'title' => 'Giáo viên',
                'colspan' => 32,
                'subgroups' => [
                    'tong-quat' => [
                        'title' => 'Tổng quát',
                        'colspan' => 6,
                        'columns' => ['total_teachers', 'staff_quota', 'contract_teachers', 'female_teachers', 'ethnic_teachers', 'teacher_student_ratio']
                    ],
                    'trinh-do' => [
                        'title' => 'Trình độ đào tạo',
                        'colspan' => 4,
                        'columns' => ['training_above_uni', 'training_uni', 'training_college', 'training_ped']
                    ],
                    'chuyen-nganh' => [
                        'title' => 'Chuyên ngành đào tạo',
                        'colspan' => 17,
                        'columns' => [
                            'spec_math', 'spec_literature', 'spec_physics', 'spec_chemistry',
                            'spec_biology', 'spec_history', 'spec_geography', 'spec_music',
                            'spec_art', 'spec_pe', 'spec_english', 'spec_russian',
                            'spec_french', 'spec_other_lang', 'spec_civic_edu', 'spec_technology',
                            'spec_informatics'
                        ]
                    ],
                    'tpt' => [
                        'title' => 'TPT.Đội',
                        'colspan' => 1,
                        'columns' => ['tpt_team']
                    ],
                    'chuan' => [
                        'title' => 'Chuẩn nghề nghiệp',
                        'colspan' => 4,
                        'columns' => ['standard_good', 'standard_quite', 'standard_reach', 'standard_not_reach']
                    ]
                ]
            ],
            'nhan-vien' => [
                'title' => 'Nhân viên',
                'colspan' => 4,
                'columns' => ['library_staff', 'lab_staff', 'office_staff', 'medical_staff']
            ]
        ];

        return $groups;
    }

    /**
     * Export Excel theo mẫu template THCS Teacher Report
     */
    public function exportExcel(Request $request)
    {
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        $year = $request->get('year', date('Y') . '-' . (date('Y') + 1));
        $geoIn = (array) $request->input('geo', []);
        $geoIds = [
            'province' => (int) ($geoIn['province_id'] ?? 0) ?: null,
            'district' => (int) ($geoIn['district_id'] ?? 0) ?: null,
            'ward'     => (int) ($geoIn['ward_id'] ?? 0) ?: null,
            'school'   => (int) ($geoIn['school_id'] ?? $request->input('geo_school_id') ?? 0) ?: null,
        ];

        $user = \Illuminate\Support\Facades\Auth::user();
        $level = $this->reportService->determineDisplayLevel($user, $geoIds);
        
        // Lấy dữ liệu từ các bảng secondary mới
        $reportData = $this->getSecondaryReportData($year, $geoIds, $level);
        
        $templatePath = resource_path('excel/3.4.5.THCS.DoiNguQuanLyGiaoVien.xlsx');
        if (!file_exists($templatePath)) {
            return response()->json(['error' => 'Không tìm thấy file template Excel'], 400);
        }

        $spreadsheet = IOFactory::load($templatePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $provinceName = $this->getProvinceName($geoIds['province']);
        $wardName = $this->getWardName($geoIds['ward']);

        // Điền thông tin tỉnh/thành và phường/xã
        $worksheet->setCellValue('C2', $provinceName);
        $worksheet->setCellValue('C3', $wardName);
        
        // Đặt font Tahoma cỡ 8 cho tất cả
        $worksheet->getStyle('C2')->getFont()->setName('Tahoma')->setSize(8);
        $worksheet->getStyle('C3')->getFont()->setName('Tahoma')->setSize(8);
        
        // Điền dữ liệu từ các bảng secondary mới
        $this->fillSecondaryReportData($worksheet, $reportData);

        $filename = 'bao_cao_doi_ngu_giao_vien_thcs_' . $year . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'teacher_report_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Điền dữ liệu từ các bảng secondary mới vào template Excel
     */
    private function fillSecondaryReportData($worksheet, $reportData)
    {
        // Đặt font Tahoma cỡ 8 cho tất cả dữ liệu
        $fontStyle = [
            'font' => [
                'name' => 'Tahoma',
                'size' => 8
            ]
        ];

        // Dòng 10: Trong biên chế - chỉ có các trường cơ bản (bỏ ở cấp trường)
        // Không điền dòng "Trong biên chế" cho cấp trường

        // Dòng 11: + Quản lý - có thêm Hiệu trưởng, P.Hiệu trưởng
        if ($reportData['quan_ly']) {
            $this->fillManagementRowData($worksheet, 'D11', $reportData['quan_ly'], $fontStyle);
        }

        // Dòng 12: + Giáo viên - có thêm TPT.đội, chuẩn nghề nghiệp
        if ($reportData['giao_vien']) {
            $this->fillTeacherRowData($worksheet, 'D12', $reportData['giao_vien'], $fontStyle);
        }

        // Dòng 13: + Nhân viên - có thêm thư viện, thiết bị, văn phòng, y tế
        if ($reportData['nhan_vien']) {
            $this->fillStaffRowData($worksheet, 'D13', $reportData['nhan_vien'], $fontStyle);
        }

        // Dòng 14: Giáo viên hợp đồng - giống giáo viên nhưng không có hạng trường, tỷ lệ
        if ($reportData['giao_vien_hop_dong']) {
            $this->fillContractTeacherRowData($worksheet, 'D14', $reportData['giao_vien_hop_dong'], $fontStyle);
        }

        // Dòng 15: Nhu cầu giáo viên - chỉ có các trường cơ bản
        if ($reportData['nhu_cau_giao_vien']) {
            $this->fillBasicRowData($worksheet, 'D15', $reportData['nhu_cau_giao_vien'], $fontStyle);
        }

        // Dòng 17: Hạng trường
        if ($reportData['giao_vien'] && isset($reportData['giao_vien']->school_rank)) {
            $worksheet->setCellValue('D17', $reportData['giao_vien']->school_rank);
            $worksheet->getStyle('D17')->applyFromArray($fontStyle);
        }

        // Dòng 18: Tỷ lệ GV/Lớp
        if ($reportData['giao_vien'] && isset($reportData['giao_vien']->teacher_class_ratio)) {
            $worksheet->setCellValue('D18', $reportData['giao_vien']->teacher_class_ratio);
            $worksheet->getStyle('D18')->applyFromArray($fontStyle);
        }
    }

    /**
     * Điền dữ liệu cơ bản (Trong biên chế, Nhu cầu giáo viên) - chỉ có các trường cơ bản
     */
    private function fillBasicRowData($worksheet, $startCell, $data, $fontStyle)
    {
        // Các trường cơ bản: Tổng số -> Tin học (24 trường) - không có Dân tộc
        $basicFields = [
            'total', 'female', 'party_member', 'above_university', 'university', 'college', 'secondary',
            'mathematics', 'physics', 'chemistry', 'biology', 'literature', 'history', 'geography',
            'english', 'french', 'russian', 'other_language', 'civic_education', 'technology',
            'physical_education', 'music', 'art', 'informatics'
        ];

        $this->fillSpecificColumns($worksheet, $startCell, $data, $basicFields, $fontStyle);
    }

    /**
     * Điền dữ liệu quản lý (+ Quản lý) - có thêm Hiệu trưởng, P.Hiệu trưởng
     */
    private function fillManagementRowData($worksheet, $startCell, $data, $fontStyle)
    {
        // Các trường cơ bản + Hiệu trưởng, P.Hiệu trưởng (26 trường) - không có Dân tộc
        $managementFields = [
            'total', 'female', 'party_member', 'above_university', 'university', 'college', 'secondary',
            'mathematics', 'physics', 'chemistry', 'biology', 'literature', 'history', 'geography',
            'english', 'french', 'russian', 'other_language', 'civic_education', 'technology',
            'physical_education', 'music', 'art', 'informatics', 'principal', 'vice_principal'
        ];

        $this->fillSpecificColumns($worksheet, $startCell, $data, $managementFields, $fontStyle);
    }

    /**
     * Điền dữ liệu giáo viên (+ Giáo viên) - có thêm TPT.đội, chuẩn nghề nghiệp
     */
    private function fillTeacherRowData($worksheet, $startCell, $data, $fontStyle)
    {
        // Các trường cơ bản + Dân tộc + TPT.đội + chuẩn nghề nghiệp (29 trường)
        $teacherFields = [
            'total', 'female', 'party_member', 'above_university', 'university', 'college', 'secondary',
            'mathematics', 'physics', 'chemistry', 'biology', 'literature', 'history', 'geography',
            'english', 'french', 'russian', 'other_language', 'civic_education', 'technology',
            'physical_education', 'music', 'art', 'informatics', 'ethnic', 'tpt_team', 
            'standard_good', 'standard_quite', 'standard_reach', 'standard_not_reach'
        ];

        $this->fillSpecificColumns($worksheet, $startCell, $data, $teacherFields, $fontStyle);
    }

    /**
     * Điền dữ liệu nhân viên (+ Nhân viên) - có thêm thư viện, thiết bị, văn phòng, y tế
     */
    private function fillStaffRowData($worksheet, $startCell, $data, $fontStyle)
    {
        // Các trường cơ bản + nhân viên (28 trường) - không có Dân tộc
        $staffFields = [
            'total', 'female', 'party_member', 'above_university', 'university', 'college', 'secondary',
            'mathematics', 'physics', 'chemistry', 'biology', 'literature', 'history', 'geography',
            'english', 'french', 'russian', 'other_language', 'civic_education', 'technology',
            'physical_education', 'music', 'art', 'informatics', 'library_staff', 'equipment_lab_staff', 
            'office_staff', 'medical_staff'
        ];

        $this->fillSpecificColumns($worksheet, $startCell, $data, $staffFields, $fontStyle);
    }

    /**
     * Điền dữ liệu giáo viên hợp đồng - giống giáo viên nhưng không có hạng trường, tỷ lệ
     */
    private function fillContractTeacherRowData($worksheet, $startCell, $data, $fontStyle)
    {
        // Giống giáo viên (29 trường)
        $contractFields = [
            'total', 'female', 'party_member', 'above_university', 'university', 'college', 'secondary',
            'mathematics', 'physics', 'chemistry', 'biology', 'literature', 'history', 'geography',
            'english', 'french', 'russian', 'other_language', 'civic_education', 'technology',
            'physical_education', 'music', 'art', 'informatics', 'ethnic', 'tpt_team', 
            'standard_good', 'standard_quite', 'standard_reach', 'standard_not_reach'
        ];

        $this->fillSpecificColumns($worksheet, $startCell, $data, $contractFields, $fontStyle);
    }

    /**
     * Điền dữ liệu vào các cột cụ thể theo mapping của Excel template
     */
    private function fillSpecificColumns($worksheet, $startCell, $data, $fields, $fontStyle)
    {
        // Mapping từ field name đến cột Excel (bắt đầu từ cột D = index 4)
        $columnMapping = [
            'total' => 4,           // D
            'female' => 5,         // E
            'party_member' => 6,   // F
            'above_university' => 7,  // G
            'university' => 8,     // H
            'college' => 9,        // I
            'secondary' => 10,     // J
            'mathematics' => 11,   // K
            'physics' => 12,       // L
            'chemistry' => 13,     // M
            'biology' => 14,       // N
            'literature' => 15,    // O
            'history' => 16,       // P
            'geography' => 17,     // Q
            'english' => 18,       // R
            'french' => 19,        // S
            'russian' => 20,       // T
            'other_language' => 21, // U
            'civic_education' => 22, // V
            'technology' => 23,    // W
            'physical_education' => 24, // X
            'music' => 25,         // Y
            'art' => 26,           // Z
            'informatics' => 27,   // AA
            'principal' => 28,     // AB
            'vice_principal' => 29, // AC
            'ethnic' => 30,        // AD - Dân tộc
            'tpt_team' => 31,      // AE
            'standard_good' => 32, // AF
            'standard_quite' => 33, // AG
            'standard_reach' => 34, // AH
            'standard_not_reach' => 35, // AI
            'library_staff' => 36, // AJ
            'equipment_lab_staff' => 37, // AK
            'medical_staff' => 38, // AL
            'office_staff' => 39   // AM
        ];

        $rowNumber = substr($startCell, 1); // Lấy số dòng
        
        foreach ($fields as $field) {
            if (isset($columnMapping[$field])) {
                $colIndex = $columnMapping[$field];
                $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex) . $rowNumber;
                $value = $data->$field ?? 0;
                
                // Hiển thị rỗng nếu giá trị là 0 hoặc không có
                $displayValue = ($value && $value != 0) ? $value : '';
                $worksheet->setCellValue($cellCoordinate, $displayValue);
                $worksheet->getStyle($cellCoordinate)->applyFromArray($fontStyle);
            }
        }
    }

    /**
     * Điền dữ liệu đội ngũ giáo viên THCS vào template (cũ - giữ lại để tham khảo)
     */
    private function fillTeacherData($worksheet, $reports, $level, $wardName)
    {
        $startRow = 11; // Bắt đầu từ dòng 11
        $currentRow = $startRow;
        $stt = 1;
        $dataRows = [];

        foreach ($reports as $report) {
            if (isset($report->school_name) && $report->school_name === 'TỔNG CỘNG') {
                continue;
            }

            $worksheet->setCellValue('A' . $currentRow, $stt++);
            
            $schoolName = '';
            if ($level === 'province') {
                $schoolName = $report->ward?->name ?? '';
            } else {
                $schoolName = $report->school_name ?? '';
            }
            
            // Cột B,C: Đơn vị (tên trường) - merge 2 cột
            $worksheet->setCellValue('B' . $currentRow, $schoolName);
            $worksheet->mergeCells('B' . $currentRow . ':C' . $currentRow);
            
            // Cột D: Hạng trường
            $worksheet->setCellValue('D' . $currentRow, $report->school_rank ?: '');
            
            // CBQL - Hiệu trưởng
            $worksheet->setCellValue('E' . $currentRow, $report->principal ?: '');
            
            // CBQL - P.Hiệu trưởng
            $worksheet->setCellValue('F' . $currentRow, $report->vice_principal ?: '');
            
            // CBQL - Tổng số
            $worksheet->setCellValue('G' . $currentRow, $report->total_teachers ?: '');
            
            // Giáo viên - Biên chế
            $worksheet->setCellValue('H' . $currentRow, $report->staff_quota ?: '');
            
            // Giáo viên - Hợp đồng
            $worksheet->setCellValue('I' . $currentRow, $report->contract_teachers ?: '');
            
            // Giáo viên - Nữ
            $worksheet->setCellValue('J' . $currentRow, $report->female_teachers ?: '');
            
            // Giáo viên - Dân tộc
            $worksheet->setCellValue('K' . $currentRow, $report->ethnic_teachers ?: '');
            
            // Giáo viên - Tỷ lệ GV/Lớp
            $worksheet->setCellValue('L' . $currentRow, $report->teacher_student_ratio ?: '');
            $worksheet->getStyle('L' . $currentRow)->getNumberFormat()->setFormatCode('0.00');
            
            // Trình độ đào tạo - Trên ĐH
            $worksheet->setCellValue('M' . $currentRow, $report->training_above_uni ?: '');
            
            // Trình độ đào tạo - ĐH
            $worksheet->setCellValue('N' . $currentRow, $report->training_uni ?: '');
            
            // Trình độ đào tạo - CĐ
            $worksheet->setCellValue('O' . $currentRow, $report->training_college ?: '');
            
            // Trình độ đào tạo - THSP
            $worksheet->setCellValue('P' . $currentRow, $report->training_ped ?: '');
            
            // Chuyên ngành đào tạo - Toán
            $worksheet->setCellValue('Q' . $currentRow, $report->spec_math ?: '');
            
            // Chuyên ngành đào tạo - Ngữ văn
            $worksheet->setCellValue('R' . $currentRow, $report->spec_literature ?: '');
            
            // KHTN - Lí
            $worksheet->setCellValue('S' . $currentRow, $report->spec_physics ?: '');
            
            // KHTN - Hóa
            $worksheet->setCellValue('T' . $currentRow, $report->spec_chemistry ?: '');
            
            // KHTN - Sinh
            $worksheet->setCellValue('U' . $currentRow, $report->spec_biology ?: '');
            
            // KHXH - Sử
            $worksheet->setCellValue('V' . $currentRow, $report->spec_history ?: '');
            
            // KHXH - Địa
            $worksheet->setCellValue('W' . $currentRow, $report->spec_geography ?: '');
            
            // HĐGD - Nhạc
            $worksheet->setCellValue('X' . $currentRow, $report->spec_music ?: '');
            
            // HĐGD - MT
            $worksheet->setCellValue('Y' . $currentRow, $report->spec_art ?: '');
            
            // HĐGD - TD
            $worksheet->setCellValue('Z' . $currentRow, $report->spec_pe ?: '');
            
            // Ngoại ngữ - Anh
            $worksheet->setCellValue('AA' . $currentRow, $report->spec_english ?: '');
            
            // Ngoại ngữ - Nga
            $worksheet->setCellValue('AB' . $currentRow, $report->spec_russian ?: '');
            
            // Ngoại ngữ - Pháp
            $worksheet->setCellValue('AC' . $currentRow, $report->spec_french ?: '');
            
            // Ngoại ngữ - Khác
            $worksheet->setCellValue('AD' . $currentRow, $report->spec_other_lang ?: '');
            
            // GDCD
            $worksheet->setCellValue('AE' . $currentRow, $report->spec_civic_edu ?: '');
            
            // Công nghệ
            $worksheet->setCellValue('AF' . $currentRow, $report->spec_technology ?: '');
            
            // Tin học
            $worksheet->setCellValue('AG' . $currentRow, $report->spec_informatics ?: '');
            
            // TPT.Đội
            $worksheet->setCellValue('AH' . $currentRow, $report->tpt_team ?: '');
            
            // Chuẩn nghề nghiệp - Tốt
            $worksheet->setCellValue('AI' . $currentRow, $report->standard_good ?: '');
            
            // Chuẩn nghề nghiệp - Khá
            $worksheet->setCellValue('AJ' . $currentRow, $report->standard_quite ?: '');
            
            // Chuẩn nghề nghiệp - Đạt
            $worksheet->setCellValue('AK' . $currentRow, $report->standard_reach ?: '');
            
            // Chuẩn nghề nghiệp - Chưa đạt
            $worksheet->setCellValue('AL' . $currentRow, $report->standard_not_reach ?: '');
            
            // Nhân viên - Thư viện
            $worksheet->setCellValue('AM' . $currentRow, $report->library_staff ?: '');
            
            // Nhân viên - TBị - Thí nghiệm
            $worksheet->setCellValue('AN' . $currentRow, $report->lab_staff ?: '');
            
            // Nhân viên - Văn phòng
            $worksheet->setCellValue('AO' . $currentRow, $report->office_staff ?: '');
            
            // Nhân viên - Y tế
            $worksheet->setCellValue('AP' . $currentRow, $report->medical_staff ?: '');
            
            $this->setConsistentFont($worksheet, $currentRow);
            $dataRows[] = $report;
            $currentRow++;
        }
        
        // Áp dụng font Tahoma 8 cho toàn bộ dữ liệu từ dòng 11 đến dòng cuối
        if ($currentRow > 11) {
            $this->applyTahomaFontToDataRange($worksheet, 11, $currentRow - 1);
            // Áp dụng định dạng số cho cột L (Tỷ lệ GV/Lớp)
            for ($row = 11; $row < $currentRow; $row++) {
                $worksheet->getStyle('L' . $row)->getNumberFormat()->setFormatCode('0.00');
            }
        }

        if (!empty($dataRows)) {
            // Ghi dòng tổng cộng vào dòng tiếp theo (không chèn dòng)
            $totalRow = $currentRow;
            $this->addTotalRow($worksheet, $totalRow, $dataRows, $level);
            
            // Thêm phần tiêu chí (bắt đầu từ dòng 14)
            $this->addCriteriaHeaders($worksheet, 14);
            $this->addCriteriaData($worksheet, 14, $dataRows);
            
            // Thêm phần ký tên sau phần tiêu chí
            $this->addSignatureSection($worksheet, 17, $wardName);
            
            // Áp dụng font Tahoma 8 cho dòng tổng cộng
            $this->applyTahomaFontToDataRange($worksheet, $totalRow, $totalRow);
        }
    }

    /**
     * Thêm dòng tổng cộng
     */
    private function addTotalRow($worksheet, $row, $dataRows, $level)
    {
        // Cột A (TT) để trống
        $worksheet->setCellValue('A' . $row, '');
        
        $worksheet->setCellValue('B' . $row, 'TỔNG CỘNG');
        $worksheet->mergeCells('B' . $row . ':C' . $row);
        
        $this->setConsistentFont($worksheet, $row);
        
        // In đậm toàn bộ dòng tổng cộng
        $range = 'A' . $row . ':AP' . $row;
        $worksheet->getStyle($range)->getFont()->setBold(true);

        $totals = $this->calculateTeacherTotals(collect($dataRows));

        // Cột D: Hạng trường - tính tổng
        $totalSchoolRank = collect($dataRows)->sum(function($report) {
            return is_numeric($report->school_rank) ? (float)$report->school_rank : 0;
        });
        $worksheet->setCellValue('D' . $row, $totalSchoolRank);
        
        // CBQL - Hiệu trưởng
        $worksheet->setCellValue('E' . $row, $totals->principal ?? 0);
        
        // CBQL - P.Hiệu trưởng
        $worksheet->setCellValue('F' . $row, $totals->vice_principal ?? 0);
        
        // CBQL - Tổng số
        $worksheet->setCellValue('G' . $row, $totals->total_teachers ?? 0);
        
        // Giáo viên - Biên chế
        $worksheet->setCellValue('H' . $row, $totals->staff_quota ?? 0);
        
        // Giáo viên - Hợp đồng
        $worksheet->setCellValue('I' . $row, $totals->contract_teachers ?? 0);
        
        // Giáo viên - Nữ
        $worksheet->setCellValue('J' . $row, $totals->female_teachers ?? 0);
        
        // Giáo viên - Dân tộc
        $worksheet->setCellValue('K' . $row, $totals->ethnic_teachers ?? 0);
        
        // Giáo viên - Tỷ lệ GV/Lớp (tính trung bình)
        $worksheet->setCellValue('L' . $row, $totals->teacher_student_ratio ?? 0);
        $worksheet->getStyle('L' . $row)->getNumberFormat()->setFormatCode('0.00');
        
        // Trình độ đào tạo - Trên ĐH
        $worksheet->setCellValue('M' . $row, $totals->training_above_uni ?? 0);
        
        // Trình độ đào tạo - ĐH
        $worksheet->setCellValue('N' . $row, $totals->training_uni ?? 0);
        
        // Trình độ đào tạo - CĐ
        $worksheet->setCellValue('O' . $row, $totals->training_college ?? 0);
        
        // Trình độ đào tạo - THSP
        $worksheet->setCellValue('P' . $row, $totals->training_ped ?? 0);
        
        // Chuyên ngành đào tạo - Toán
        $worksheet->setCellValue('Q' . $row, $totals->spec_math ?? 0);
        
        // Chuyên ngành đào tạo - Ngữ văn
        $worksheet->setCellValue('R' . $row, $totals->spec_literature ?? 0);
        
        // KHTN - Lí
        $worksheet->setCellValue('S' . $row, $totals->spec_physics ?? 0);
        
        // KHTN - Hóa
        $worksheet->setCellValue('T' . $row, $totals->spec_chemistry ?? 0);
        
        // KHTN - Sinh
        $worksheet->setCellValue('U' . $row, $totals->spec_biology ?? 0);
        
        // KHXH - Sử
        $worksheet->setCellValue('V' . $row, $totals->spec_history ?? 0);
        
        // KHXH - Địa
        $worksheet->setCellValue('W' . $row, $totals->spec_geography ?? 0);
        
        // HĐGD - Nhạc
        $worksheet->setCellValue('X' . $row, $totals->spec_music ?? 0);
        
        // HĐGD - MT
        $worksheet->setCellValue('Y' . $row, $totals->spec_art ?? 0);
        
        // HĐGD - TD
        $worksheet->setCellValue('Z' . $row, $totals->spec_pe ?? 0);
        
        // Ngoại ngữ - Anh
        $worksheet->setCellValue('AA' . $row, $totals->spec_english ?? 0);
        
        // Ngoại ngữ - Nga
        $worksheet->setCellValue('AB' . $row, $totals->spec_russian ?? 0);
        
        // Ngoại ngữ - Pháp
        $worksheet->setCellValue('AC' . $row, $totals->spec_french ?? 0);
        
        // Ngoại ngữ - Khác
        $worksheet->setCellValue('AD' . $row, $totals->spec_other_lang ?? 0);
        
        // GDCD
        $worksheet->setCellValue('AE' . $row, $totals->spec_civic_edu ?? 0);
        
        // Công nghệ
        $worksheet->setCellValue('AF' . $row, $totals->spec_technology ?? 0);
        
        // Tin học
        $worksheet->setCellValue('AG' . $row, $totals->spec_informatics ?? 0);
        
        // TPT.Đội
        $worksheet->setCellValue('AH' . $row, $totals->tpt_team ?? 0);
        
        // Chuẩn nghề nghiệp - Tốt
        $worksheet->setCellValue('AI' . $row, $totals->standard_good ?? 0);
        
        // Chuẩn nghề nghiệp - Khá
        $worksheet->setCellValue('AJ' . $row, $totals->standard_quite ?? 0);
        
        // Chuẩn nghề nghiệp - Đạt
        $worksheet->setCellValue('AK' . $row, $totals->standard_reach ?? 0);
        
        // Chuẩn nghề nghiệp - Chưa đạt
        $worksheet->setCellValue('AL' . $row, $totals->standard_not_reach ?? 0);
        
        // Nhân viên - Thư viện
        $worksheet->setCellValue('AM' . $row, $totals->library_staff ?? 0);
        
        // Nhân viên - TBị - Thí nghiệm
        $worksheet->setCellValue('AN' . $row, $totals->lab_staff ?? 0);
        
        // Nhân viên - Văn phòng
        $worksheet->setCellValue('AO' . $row, $totals->office_staff ?? 0);
        
        // Nhân viên - Y tế
        $worksheet->setCellValue('AP' . $row, $totals->medical_staff ?? 0);
    }

    /**
     * Thêm phần tiêu chí với tiêu đề và border
     */
    private function addCriteriaHeaders($worksheet, $startRow)
    {
        // Dòng tiêu đề (dòng 14)
        // Merge A14:I14 và ghi 'Tiêu chí'
        $worksheet->setCellValue('A' . $startRow, 'Tiêu chí');
        $worksheet->mergeCells('A' . $startRow . ':I' . $startRow);
        
        // Merge J14:K14 và ghi 'SL'
        $worksheet->setCellValue('J' . $startRow, 'SL');
        $worksheet->mergeCells('J' . $startRow . ':K' . $startRow);
        
        // Merge L14:M14 và ghi 'Tỷ lệ'
        $worksheet->setCellValue('L' . $startRow, 'Tỷ lệ');
        $worksheet->mergeCells('L' . $startRow . ':M' . $startRow);
        
        // Căn giữa và in đậm A14:M14
        $headerRange = 'A' . $startRow . ':M' . $startRow;
        $headerStyle = $worksheet->getStyle($headerRange);
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $headerStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFont()->setName('Tahoma');
        $headerStyle->getFont()->setSize(8);
        $headerStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        // Dòng 15: GV đạt chuẩn trình độ đào tạo
        $worksheet->setCellValue('A' . ($startRow + 1), 'GV đạt chuẩn trình độ đào tạo');
        $worksheet->mergeCells('A' . ($startRow + 1) . ':I' . ($startRow + 1));
        $worksheet->mergeCells('J' . ($startRow + 1) . ':K' . ($startRow + 1)); // Merge J15:K15
        $worksheet->mergeCells('L' . ($startRow + 1) . ':M' . ($startRow + 1)); // Merge L15:M15
        
        // Dòng 16: GV đạt trên chuẩn trình độ đào tạo
        $worksheet->setCellValue('A' . ($startRow + 2), 'GV đạt trên chuẩn trình độ đào tạo');
        $worksheet->mergeCells('A' . ($startRow + 2) . ':I' . ($startRow + 2));
        $worksheet->mergeCells('J' . ($startRow + 2) . ':K' . ($startRow + 2)); // Merge J16:K16
        $worksheet->mergeCells('L' . ($startRow + 2) . ':M' . ($startRow + 2)); // Merge L16:M16
        
        // Dòng 17: GV đạt yêu cầu chuẩn nghề nghiệp
        $worksheet->setCellValue('A' . ($startRow + 3), 'GV đạt yêu cầu chuẩn nghề nghiệp');
        $worksheet->mergeCells('A' . ($startRow + 3) . ':I' . ($startRow + 3));
        $worksheet->mergeCells('J' . ($startRow + 3) . ':K' . ($startRow + 3)); // Merge J17:K17
        $worksheet->mergeCells('L' . ($startRow + 3) . ':M' . ($startRow + 3)); // Merge L17:M17
        
        // Format font và border cho các dòng tiêu chí
        for ($i = 1; $i <= 3; $i++) {
            $row = $startRow + $i;
            
            // Format cho cột Tiêu chí (A:I)
            $criteriaRange = 'A' . $row . ':I' . $row;
            $criteriaStyle = $worksheet->getStyle($criteriaRange);
            $criteriaStyle->getFont()->setName('Tahoma');
            $criteriaStyle->getFont()->setSize(8);
            $criteriaStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $criteriaStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $criteriaStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            
            // Format cho cột SL (J:K)
            $slRange = 'J' . $row . ':K' . $row;
            $slStyle = $worksheet->getStyle($slRange);
            $slStyle->getFont()->setName('Tahoma');
            $slStyle->getFont()->setSize(8);
            $slStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $slStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $slStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            
            // Format cho cột Tỷ lệ (L:M)
            $ratioRange = 'L' . $row . ':M' . $row;
            $ratioStyle = $worksheet->getStyle($ratioRange);
            $ratioStyle->getFont()->setName('Tahoma');
            $ratioStyle->getFont()->setSize(8);
            $ratioStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $ratioStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $ratioStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
    }

    /**
     * Thêm dữ liệu vào phần tiêu chí
     */
    private function addCriteriaData($worksheet, $startRow, $dataRows)
    {
        $totals = $this->calculateTeacherTotals(collect($dataRows));
        $totalTeachers = $totals->total_teachers ?? 0;
        
        // 1. GV đạt chuẩn trình độ đào tạo (Trên ĐH + ĐH + CĐ)
        $gvDatChuanTD = ($totals->training_above_uni ?? 0) + ($totals->training_uni ?? 0) + 
                        ($totals->training_college ?? 0);
        $tyLeDatChuanTD = $totalTeachers > 0 ? ($gvDatChuanTD / $totalTeachers) * 100 : 0;
        
        // 2. GV đạt trên chuẩn trình độ đào tạo (Trên ĐH + ĐH)
        $gvTrenChuanTD = ($totals->training_above_uni ?? 0) + ($totals->training_uni ?? 0);
        $tyLeTrenChuanTD = $totalTeachers > 0 ? ($gvTrenChuanTD / $totalTeachers) * 100 : 0;
        
        // 3. GV đạt yêu cầu chuẩn nghề nghiệp (Tốt + Khá + Đạt)
        $gvDatChuanNN = ($totals->standard_good ?? 0) + ($totals->standard_quite ?? 0) + 
                        ($totals->standard_reach ?? 0);
        $tyLeDatChuanNN = $totalTeachers > 0 ? ($gvDatChuanNN / $totalTeachers) * 100 : 0;
        
        // Ghi số liệu vào cột J (SL) - merge J:K
        $worksheet->setCellValue('J' . ($startRow + 1), $gvDatChuanTD);        // Dòng 15
        $worksheet->setCellValue('J' . ($startRow + 2), $gvTrenChuanTD);        // Dòng 16  
        $worksheet->setCellValue('J' . ($startRow + 3), $gvDatChuanNN);          // Dòng 17
        
        // Ghi tỷ lệ vào cột L (Tỷ lệ) - merge L:M
        $worksheet->setCellValue('L' . ($startRow + 1), $tyLeDatChuanTD);       // Dòng 15
        $worksheet->setCellValue('L' . ($startRow + 2), $tyLeTrenChuanTD);       // Dòng 16
        $worksheet->setCellValue('L' . ($startRow + 3), $tyLeDatChuanNN);        // Dòng 17
        
        // Format decimal places 2 cho tỷ lệ
        for ($i = 1; $i <= 3; $i++) {
            $row = $startRow + $i;
            $worksheet->getStyle('L' . $row)->getNumberFormat()->setFormatCode('0.00');
        }
    }

    /**
     * Thêm phần tiêu chí footer (cũ - giữ lại để backward compatible)
     */
    private function addFooterCriteria($worksheet, $startRow, $dataRows)
    {
        $totals = $this->calculateTeacherTotals(collect($dataRows));
        $totalTeachers = $totals->total_teachers ?? 0;
        
        // GV đạt chuẩn trình độ đào tạo: M+N+O (training_above_uni + training_uni + training_college)
        $standardTraining = ($totals->training_above_uni ?? 0) + ($totals->training_uni ?? 0) + ($totals->training_college ?? 0);
        $standardTrainingRatio = $totalTeachers > 0 ? round(($standardTraining / $totalTeachers) * 100, 2) : 0;
        
        // GV đạt trên chuẩn trình độ đào tạo: M+N (training_above_uni + training_uni)
        $aboveStandardTraining = ($totals->training_above_uni ?? 0) + ($totals->training_uni ?? 0);
        $aboveStandardTrainingRatio = $totalTeachers > 0 ? round(($aboveStandardTraining / $totalTeachers) * 100, 2) : 0;
        
        // GV đạt yêu cầu chuẩn nghề nghiệp: AI+AJ+AK (standard_good + standard_quite + standard_reach)
        $standardProfessional = ($totals->standard_good ?? 0) + ($totals->standard_quite ?? 0) + ($totals->standard_reach ?? 0);
        $standardProfessionalRatio = $totalTeachers > 0 ? round(($standardProfessional / $totalTeachers) * 100, 2) : 0;
        
        // GV đạt chuẩn trình độ đào tạo - SL vào J15, tỷ lệ vào L15
        $worksheet->setCellValue('J15', $standardTraining);
        $worksheet->setCellValue('L15', $standardTrainingRatio);
        $worksheet->getStyle('L15')->getNumberFormat()->setFormatCode('0.00');
        
        // GV đạt trên chuẩn trình độ đào tạo - SL vào J16, tỷ lệ vào L16
        $worksheet->setCellValue('J16', $aboveStandardTraining);
        $worksheet->setCellValue('L16', $aboveStandardTrainingRatio);
        $worksheet->getStyle('L16')->getNumberFormat()->setFormatCode('0.00');
        
        // GV đạt yêu cầu chuẩn nghề nghiệp - SL vào J17, tỷ lệ vào L17
        $worksheet->setCellValue('J17', $standardProfessional);
        $worksheet->setCellValue('L17', $standardProfessionalRatio);
        $worksheet->getStyle('L17')->getNumberFormat()->setFormatCode('0.00');
    }

    /**
     * Thêm phần ký tên vào cuối báo cáo
     */
    private function addSignatureSection($worksheet, $totalRow, $wardName)
    {
        // Tính toán để ký tên bắt đầu từ dòng 14 và kết thúc ở dòng 17
        // totalRow = 17, vậy ký tên sẽ từ dòng 14 đến 17
        
        // Tên xã, ngày ... tháng ... năm ... vào ô AI14
        $dateRow = 14;
        $worksheet->setCellValue('AI' . $dateRow, $wardName . ', ngày ... tháng ... năm ...');
        
        // 'NGƯỜI LẬP BIỂU' vào ô U15, in đậm
        $signatureRow1 = 15;
        $worksheet->setCellValue('U' . $signatureRow1, 'NGƯỜI LẬP BIỂU');
        $worksheet->getStyle('U' . $signatureRow1)->getFont()->setBold(true);
        
        // 'TM. BAN CHỈ ĐẠO PCGD, XMC' vào ô AI15, in đậm
        $worksheet->setCellValue('AI' . $signatureRow1, 'TM. BAN CHỈ ĐẠO PCGD, XMC');
        $worksheet->getStyle('AI' . $signatureRow1)->getFont()->setBold(true);
        
        // '(Ký và ghi rõ họ tên)' vào ô U16, in nghiêng
        $signatureRow2 = 16;
        $worksheet->setCellValue('U' . $signatureRow2, '(Ký và ghi rõ họ tên)');
        $worksheet->getStyle('U' . $signatureRow2)->getFont()->setItalic(true);
        
        // 'TRƯỞNG BAN' vào ô AI16, in đậm
        $worksheet->setCellValue('AI' . $signatureRow2, 'TRƯỞNG BAN');
        $worksheet->getStyle('AI' . $signatureRow2)->getFont()->setBold(true);
        
        // '(Ký tên, đóng dấu)' vào ô AI17, in nghiêng
        $signatureRow3 = 17;
        $worksheet->setCellValue('AI' . $signatureRow3, '(Ký tên, đóng dấu)');
        $worksheet->getStyle('AI' . $signatureRow3)->getFont()->setItalic(true);
        
        // Đặt font mặc định cho tất cả các ô ký tên
        $signatureRange = 'U' . $signatureRow1 . ':AI' . $signatureRow3;
        $worksheet->getStyle($signatureRange)->getFont()->setName('Tahoma');
        $worksheet->getStyle($signatureRange)->getFont()->setSize(8);
        
        // Đặt chiều cao dòng và căn giữa cho các dòng ký tên (dòng 14-17)
        for ($row = $dateRow; $row <= $signatureRow3; $row++) {
            $worksheet->getRowDimension($row)->setRowHeight(18.60);
            $worksheet->getStyle('U' . $row . ':AI' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $worksheet->getStyle('U' . $row . ':AI' . $row)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }
    }

    /**
     * Đặt font cho phần footer - chỉ có border cho cột A-M
     */
    private function setFooterFont($worksheet, $row)
    {
        // Đặt font cho cột A-M (có border)
        $rangeWithBorder = 'A' . $row . ':M' . $row;
        $styleWithBorder = $worksheet->getStyle($rangeWithBorder);
        
        // Font settings
        $styleWithBorder->getFont()->setBold(false);
        $styleWithBorder->getFont()->setItalic(false);
        $styleWithBorder->getFont()->setName('Arial');
        $styleWithBorder->getFont()->setSize(10);
        
        // Alignment settings
        $styleWithBorder->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $styleWithBorder->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $styleWithBorder->getAlignment()->setIndent(0);
        
        // Border cho cột A-M
        $styleWithBorder->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        // Xử lý từng cột cụ thể
        $columns = [
            'A' => 'left',      // Tên tiêu chí - căn trái
            'B' => 'center',    // Các cột B-I - căn giữa
            'C' => 'center',
            'D' => 'center',
            'E' => 'center',
            'F' => 'center',
            'G' => 'center',
            'H' => 'center',
            'I' => 'center',
            'J' => 'center',    // Số lượng - căn giữa
            'K' => 'center',    // Tỷ lệ - căn giữa
            'L' => 'center',    // Tỷ lệ - căn giữa
            'M' => 'center'     // Tỷ lệ - căn giữa
        ];
        
        foreach ($columns as $col => $alignment) {
            $cellStyle = $worksheet->getStyle($col . $row);
            
            // Font settings
            $cellStyle->getFont()->setBold(false);
            $cellStyle->getFont()->setItalic(false);
            $cellStyle->getFont()->setName('Arial');
            $cellStyle->getFont()->setSize(10);
            
            // Alignment settings
            if ($alignment === 'left') {
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            } else {
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $cellStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $cellStyle->getAlignment()->setIndent(0);
            
            // Border cho cột A-M
            $cellStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            
            // Number format cho các cột số
            if (in_array($col, ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M'])) {
                $cellStyle->getNumberFormat()->setFormatCode('0');
            }
        }
        
        // Đặt font cho cột N trở đi (không có border)
        $rangeWithoutBorder = 'N' . $row . ':AP' . $row;
        $styleWithoutBorder = $worksheet->getStyle($rangeWithoutBorder);
        
        // Font settings
        $styleWithoutBorder->getFont()->setBold(false);
        $styleWithoutBorder->getFont()->setItalic(false);
        $styleWithoutBorder->getFont()->setName('Arial');
        $styleWithoutBorder->getFont()->setSize(10);
        
        // Alignment settings
        $styleWithoutBorder->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $styleWithoutBorder->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $styleWithoutBorder->getAlignment()->setIndent(0);
        
        // Không có border cho cột N trở đi
        $styleWithoutBorder->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_NONE);
    }

    /**
     * Áp dụng font Tahoma 8 cho một vùng dữ liệu
     */
    private function applyTahomaFontToDataRange($worksheet, $startRow, $endRow)
    {
        for ($row = $startRow; $row <= $endRow; $row++) {
            $range = 'A' . $row . ':AP' . $row;
            $style = $worksheet->getStyle($range);
            $style->getFont()->setName('Tahoma');
            $style->getFont()->setSize(8);
        }
    }

    /**
     * Export Excel cho báo cáo thống kê xã - xuất tất cả trường THCS trong xã
     */
    public function exportExcelForWard(Request $request)
    {
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        $year = $request->get('year', date('Y') . '-' . (date('Y') + 1));
        $geoIn = (array) $request->input('geo', []);
        $geoIds = [
            'province' => (int) ($geoIn['province_id'] ?? 0) ?: null,
            'district' => (int) ($geoIn['district_id'] ?? 0) ?: null,
            'ward'     => (int) ($geoIn['ward_id'] ?? 0) ?: null,
            'school'   => null, // Không lọc theo trường cụ thể
        ];

        $secondaryLevelIds = $this->getSecondaryLevelIds();
        if (empty($secondaryLevelIds)) {
            return response()->json(['error' => 'Không tìm thấy cấp học THCS'], 400);
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        $level = $this->reportService->determineDisplayLevel($user, $geoIds);
        
        // Lấy dữ liệu từ các bảng secondary mới
        $reports = $this->getSecondaryReportsForWard($year, $geoIds, $level, $secondaryLevelIds);
        
        $templatePath = resource_path('excel/3.4.THCS.DoiNguGiaoVien.xlsx');
        if (!file_exists($templatePath)) {
            return response()->json(['error' => 'Không tìm thấy file template Excel'], 400);
        }

        $spreadsheet = IOFactory::load($templatePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $provinceName = $this->getProvinceName($geoIds['province']);
        $wardName = $this->getWardName($geoIds['ward']);

        // Điền thông tin tỉnh/thành và phường/xã
        $worksheet->setCellValue('C2', $provinceName);
        $worksheet->setCellValue('C3', $wardName);
        $worksheet->setCellValue('C6', $year); // Năm học
        
        // Đặt font in đậm cho tên tỉnh và xã
        $worksheet->getStyle('C2')->getFont()->setBold(true);
        $worksheet->getStyle('C3')->getFont()->setBold(true);
        
        // Ghi ngày tháng năm hiện tại vào ô G4
        $currentDate = 'Ngày ' . date('d') . ' tháng ' . date('m') . ' năm ' . date('Y');
        $worksheet->setCellValue('G4', $currentDate);
        $worksheet->getStyle('G4')->getFont()->setBold(true);
        $worksheet->getStyle('G4')->getFont()->setItalic(true);

        // Cài đặt rows to repeat at top cho tiêu đề (dòng 6-9)
        $this->setupPrintTitles($worksheet);

        // Điền dữ liệu cho tất cả trường trong xã
        $this->fillTeacherDataForWard($worksheet, $reports, $level, $wardName);

        $filename = 'bao_cao_doi_ngu_giao_vien_thcs_xa_' . $wardName . '_' . $year . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'secondary_teacher_ward_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Lấy dữ liệu từ các bảng secondary mới cho xuất Excel xã
     */
    private function getSecondaryReportsForWard($year, $geoIds, $level, $secondaryLevelIds)
    {
        // Xác định điều kiện tìm kiếm
        $whereConditions = ['academic_year' => $year];
        
        if ($level === 'ward' && $geoIds['ward']) {
            $whereConditions['ward_id'] = $geoIds['ward'];
        } elseif ($level === 'province' && $geoIds['province']) {
            // Lấy tất cả ward trong tỉnh
            $wardIds = \App\Models\GeoUnit::where('parent_id', $geoIds['province'])
                ->where('type_level', 3)
                ->pluck('id')
                ->toArray();
            $whereConditions['ward_id'] = $wardIds;
        }

        // Lấy danh sách trường THCS (chỉ trường đang hoạt động)
        $schools = \App\Models\School::active()
            ->whereIn('educational_level_id', $secondaryLevelIds)
            ->when($geoIds['ward'], function($query) use ($geoIds) {
                return $query->where('new_ward_id', $geoIds['ward']);
            })
            ->when($geoIds['province'] && !$geoIds['ward'], function($query) use ($geoIds) {
                $wardIds = \App\Models\GeoUnit::where('parent_id', $geoIds['province'])
                    ->where('type_level', 3)
                    ->pluck('id')
                    ->toArray();
                return $query->whereIn('new_ward_id', $wardIds);
            })
            ->get();

        $reports = [];

        foreach ($schools as $school) {
            $schoolWhereConditions = array_merge($whereConditions, ['school_id' => $school->id]);

            // Lấy dữ liệu từ các bảng
            $teacherReport = \App\Models\SecondaryTeacherReport::where($schoolWhereConditions)->first();
            $contractTeacherReport = \App\Models\SecondaryContractTeachersReport::where($schoolWhereConditions)->first();
            $managementReport = \App\Models\SecondaryManagementReport::where($schoolWhereConditions)->first();
            $staffReport = \App\Models\SecondaryStaffReport::where($schoolWhereConditions)->first();

            // Tạo object tổng hợp dữ liệu
            $report = new \stdClass();
            $report->school_name = $school->name;
            $report->school_id = $school->id;
            $report->ward_id = $school->new_ward_id;

            // Hạng trường, Tỷ lệ GV/Lớp từ secondary_teacher_report
            $report->school_rank = $teacherReport->school_rank ?? 0;
            $report->teacher_student_ratio = $teacherReport->teacher_class_ratio ?? 0;

            // Hiệu trưởng, P.Hiệu trưởng từ secondary_management_report
            $report->principal = $managementReport->principal ?? 0;
            $report->vice_principal = $managementReport->vice_principal ?? 0;

            // Tổng từ 2 bảng secondary_teacher_report và secondary_contract_teachers_report
            $report->total_teachers = ($teacherReport->total ?? 0) + ($contractTeacherReport->total ?? 0);
            $report->staff_quota = $teacherReport->total ?? 0; // Biên chế
            $report->contract_teachers = $contractTeacherReport->total ?? 0; // Hợp đồng
            $report->female_teachers = ($teacherReport->female ?? 0) + ($contractTeacherReport->female ?? 0);
            $report->ethnic_teachers = ($teacherReport->ethnic ?? 0) + ($contractTeacherReport->ethnic ?? 0);

            // Trình độ đào tạo (tổng từ 2 bảng)
            $report->training_above_uni = ($teacherReport->above_university ?? 0) + ($contractTeacherReport->above_university ?? 0);
            $report->training_uni = ($teacherReport->university ?? 0) + ($contractTeacherReport->university ?? 0);
            $report->training_college = ($teacherReport->college ?? 0) + ($contractTeacherReport->college ?? 0);
            $report->training_ped = ($teacherReport->secondary ?? 0) + ($contractTeacherReport->secondary ?? 0);

            // Chuyên ngành đào tạo (tổng từ 2 bảng)
            $report->spec_math = ($teacherReport->mathematics ?? 0) + ($contractTeacherReport->mathematics ?? 0);
            $report->spec_literature = ($teacherReport->literature ?? 0) + ($contractTeacherReport->literature ?? 0);
            $report->spec_physics = ($teacherReport->physics ?? 0) + ($contractTeacherReport->physics ?? 0);
            $report->spec_chemistry = ($teacherReport->chemistry ?? 0) + ($contractTeacherReport->chemistry ?? 0);
            $report->spec_biology = ($teacherReport->biology ?? 0) + ($contractTeacherReport->biology ?? 0);
            $report->spec_history = ($teacherReport->history ?? 0) + ($contractTeacherReport->history ?? 0);
            $report->spec_geography = ($teacherReport->geography ?? 0) + ($contractTeacherReport->geography ?? 0);
            $report->spec_music = ($teacherReport->music ?? 0) + ($contractTeacherReport->music ?? 0);
            $report->spec_art = ($teacherReport->art ?? 0) + ($contractTeacherReport->art ?? 0);
            $report->spec_pe = ($teacherReport->physical_education ?? 0) + ($contractTeacherReport->physical_education ?? 0);
            $report->spec_english = ($teacherReport->english ?? 0) + ($contractTeacherReport->english ?? 0);
            $report->spec_russian = ($teacherReport->russian ?? 0) + ($contractTeacherReport->russian ?? 0);
            $report->spec_french = ($teacherReport->french ?? 0) + ($contractTeacherReport->french ?? 0);
            $report->spec_other_lang = ($teacherReport->other_language ?? 0) + ($contractTeacherReport->other_language ?? 0);
            $report->spec_civic_edu = ($teacherReport->civic_education ?? 0) + ($contractTeacherReport->civic_education ?? 0);
            $report->spec_technology = ($teacherReport->technology ?? 0) + ($contractTeacherReport->technology ?? 0);
            $report->spec_informatics = ($teacherReport->informatics ?? 0) + ($contractTeacherReport->informatics ?? 0);

            // TPT.Đội và chuẩn nghề nghiệp (tổng từ 2 bảng)
            $report->tpt_team = ($teacherReport->tpt_team ?? 0) + ($contractTeacherReport->tpt_team ?? 0);
            $report->standard_good = ($teacherReport->standard_good ?? 0) + ($contractTeacherReport->standard_good ?? 0);
            $report->standard_quite = ($teacherReport->standard_quite ?? 0) + ($contractTeacherReport->standard_quite ?? 0);
            $report->standard_reach = ($teacherReport->standard_reach ?? 0) + ($contractTeacherReport->standard_reach ?? 0);
            $report->standard_not_reach = ($teacherReport->standard_not_reach ?? 0) + ($contractTeacherReport->standard_not_reach ?? 0);

            // Nhân viên từ secondary_staff_report
            $report->library_staff = $staffReport->library_staff ?? 0;
            $report->lab_staff = $staffReport->equipment_lab_staff ?? 0;
            $report->office_staff = $staffReport->office_staff ?? 0;
            $report->medical_staff = $staffReport->medical_staff ?? 0;

            $reports[] = $report;
        }

        return collect($reports);
    }

    /**
     * Điền dữ liệu đội ngũ giáo viên THCS vào template cho xã
     */
    private function fillTeacherDataForWard($worksheet, $reports, $level, $wardName)
    {
        $startRow = 11; // Bắt đầu từ dòng 11
        $currentRow = $startRow;
        $stt = 1;
        $dataRows = [];

        foreach ($reports as $report) {
            if (isset($report->school_name) && $report->school_name === 'TỔNG CỘNG') {
                continue;
            }

            $worksheet->setCellValue('A' . $currentRow, $stt++);
            
            // Cột B,C: Đơn vị (tên trường) - merge 2 cột
            $worksheet->setCellValue('B' . $currentRow, $report->school_name ?? '');
            $worksheet->mergeCells('B' . $currentRow . ':C' . $currentRow);
            
            // Cột D: Hạng trường
            $worksheet->setCellValue('D' . $currentRow, $report->school_rank ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'D' . $currentRow);
            
            // CBQL - Hiệu trưởng
            $worksheet->setCellValue('E' . $currentRow, $report->principal ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'E' . $currentRow);
            
            // CBQL - P.Hiệu trưởng
            $worksheet->setCellValue('F' . $currentRow, $report->vice_principal ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'F' . $currentRow);
            
            // CBQL - Tổng số
            $worksheet->setCellValue('G' . $currentRow, $report->total_teachers ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'G' . $currentRow);
            
            // Giáo viên - Biên chế
            $worksheet->setCellValue('H' . $currentRow, $report->staff_quota ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'H' . $currentRow);
            
            // Giáo viên - Hợp đồng
            $worksheet->setCellValue('I' . $currentRow, $report->contract_teachers ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'I' . $currentRow);
            
            // Giáo viên - Nữ
            $worksheet->setCellValue('J' . $currentRow, $report->female_teachers ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'J' . $currentRow);
            
            // Giáo viên - Dân tộc
            $worksheet->setCellValue('K' . $currentRow, $report->ethnic_teachers ?: '');
            $this->setNumberFormatAndCenter($worksheet, 'K' . $currentRow);
            
            // Giáo viên - Tỷ lệ GV/Lớp
            $worksheet->setCellValue('L' . $currentRow, $report->teacher_student_ratio ?: '');
            $worksheet->getStyle('L' . $currentRow)->getNumberFormat()->setFormatCode('0.00');
            $worksheet->getStyle('L' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $worksheet->getStyle('L' . $currentRow)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            
            // Trình độ đào tạo - Trên ĐH
            $worksheet->setCellValue('M' . $currentRow, $report->training_above_uni ?: '');
            
            // Trình độ đào tạo - ĐH
            $worksheet->setCellValue('N' . $currentRow, $report->training_uni ?: '');
            
            // Trình độ đào tạo - CĐ
            $worksheet->setCellValue('O' . $currentRow, $report->training_college ?: '');
            
            // Trình độ đào tạo - THSP
            $worksheet->setCellValue('P' . $currentRow, $report->training_ped ?: '');
            
            // Chuyên ngành đào tạo - Toán
            $worksheet->setCellValue('Q' . $currentRow, $report->spec_math ?: '');
            
            // Chuyên ngành đào tạo - Ngữ văn
            $worksheet->setCellValue('R' . $currentRow, $report->spec_literature ?: '');
            
            // KHTN - Lí
            $worksheet->setCellValue('S' . $currentRow, $report->spec_physics ?: '');
            
            // KHTN - Hóa
            $worksheet->setCellValue('T' . $currentRow, $report->spec_chemistry ?: '');
            
            // KHTN - Sinh
            $worksheet->setCellValue('U' . $currentRow, $report->spec_biology ?: '');
            
            // KHXH - Sử
            $worksheet->setCellValue('V' . $currentRow, $report->spec_history ?: '');
            
            // KHXH - Địa
            $worksheet->setCellValue('W' . $currentRow, $report->spec_geography ?: '');
            
            // HĐGD - Nhạc
            $worksheet->setCellValue('X' . $currentRow, $report->spec_music ?: '');
            
            // HĐGD - MT
            $worksheet->setCellValue('Y' . $currentRow, $report->spec_art ?: '');
            
            // HĐGD - TD
            $worksheet->setCellValue('Z' . $currentRow, $report->spec_pe ?: '');
            
            // Ngoại ngữ - Anh
            $worksheet->setCellValue('AA' . $currentRow, $report->spec_english ?: '');
            
            // Ngoại ngữ - Nga
            $worksheet->setCellValue('AB' . $currentRow, $report->spec_russian ?: '');
            
            // Ngoại ngữ - Pháp
            $worksheet->setCellValue('AC' . $currentRow, $report->spec_french ?: '');
            
            // Ngoại ngữ - Khác
            $worksheet->setCellValue('AD' . $currentRow, $report->spec_other_lang ?: '');
            
            // GDCD
            $worksheet->setCellValue('AE' . $currentRow, $report->spec_civic_edu ?: '');
            
            // Công nghệ
            $worksheet->setCellValue('AF' . $currentRow, $report->spec_technology ?: '');
            
            // Tin học
            $worksheet->setCellValue('AG' . $currentRow, $report->spec_informatics ?: '');
            
            // TPT.Đội
            $worksheet->setCellValue('AH' . $currentRow, $report->tpt_team ?: '');
            
            // Chuẩn nghề nghiệp - Tốt
            $worksheet->setCellValue('AI' . $currentRow, $report->standard_good ?: '');
            
            // Chuẩn nghề nghiệp - Khá
            $worksheet->setCellValue('AJ' . $currentRow, $report->standard_quite ?: '');
            
            // Chuẩn nghề nghiệp - Đạt
            $worksheet->setCellValue('AK' . $currentRow, $report->standard_reach ?: '');
            
            // Chuẩn nghề nghiệp - Chưa đạt
            $worksheet->setCellValue('AL' . $currentRow, $report->standard_not_reach ?: '');
            
            // Nhân viên - Thư viện
            $worksheet->setCellValue('AM' . $currentRow, $report->library_staff ?: '');
            
            // Nhân viên - TBị - Thí nghiệm
            $worksheet->setCellValue('AN' . $currentRow, $report->lab_staff ?: '');
            
            // Nhân viên - Văn phòng
            $worksheet->setCellValue('AO' . $currentRow, $report->office_staff ?: '');
            
            // Nhân viên - Y tế
            $worksheet->setCellValue('AP' . $currentRow, $report->medical_staff ?: '');
            
            // Thêm format number cho tất cả các cột từ M đến AP
            for ($col = 'M'; $col <= 'AP'; $col++) {
                $this->setNumberFormatAndCenter($worksheet, $col . $currentRow);
            }
            
            // Format dòng dữ liệu
            $this->formatDataRow($worksheet, $currentRow);
            $dataRows[] = $report;
            $currentRow++;
        }

        // Luôn thêm dòng tổng cộng
        if (!empty($dataRows)) {
            $totalRow = $this->addTotalRowForWard($worksheet, $currentRow, $dataRows, $level);
            
            // Thêm phần tiêu chí cách 2 dòng từ Tổng cộng
            $this->addCriteriaHeadersForWard($worksheet, $totalRow + 2);
            $this->addCriteriaDataForWard($worksheet, $totalRow + 2, $dataRows);
            
            // Thêm phần ký tên cách 2 dòng từ Tổng cộng
            $this->addSignatureSectionForWard($worksheet, $totalRow + 2, $wardName);
        }
    }

    /**
     * Thêm dòng tổng cộng cho xã
     */
    private function addTotalRowForWard($worksheet, $row, $dataRows, $level)
    {
        // Cột TT để trống
        $worksheet->setCellValue('A' . $row, '');
        
        $worksheet->setCellValue('B' . $row, 'TỔNG CỘNG');
        $worksheet->mergeCells('B' . $row . ':C' . $row);
        
        // In đậm toàn bộ dòng tổng cộng và thêm border
        $totalRowRange = 'A' . $row . ':AP' . $row;
        $totalRowStyle = $worksheet->getStyle($totalRowRange);
        $totalRowStyle->getFont()->setBold(true);
        $totalRowStyle->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $totalRowStyle->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $totalRowStyle->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        $totals = $this->calculateTeacherTotals(collect($dataRows));

        // CBQL - Hiệu trưởng
        $worksheet->setCellValue('E' . $row, $totals->principal ?? 0);
        
        // CBQL - P.Hiệu trưởng
        $worksheet->setCellValue('F' . $row, $totals->vice_principal ?? 0);
        
        // CBQL - Tổng số
        $worksheet->setCellValue('G' . $row, $totals->total_teachers ?? 0);
        
        // Giáo viên - Biên chế
        $worksheet->setCellValue('H' . $row, $totals->staff_quota ?? 0);
        
        // Giáo viên - Hợp đồng
        $worksheet->setCellValue('I' . $row, $totals->contract_teachers ?? 0);
        
        // Giáo viên - Nữ
        $worksheet->setCellValue('J' . $row, $totals->female_teachers ?? 0);
        
        // Giáo viên - Dân tộc
        $worksheet->setCellValue('K' . $row, $totals->ethnic_teachers ?? 0);
        
        // Giáo viên - Tỷ lệ GV/Lớp (tính trung bình)
        $worksheet->setCellValue('L' . $row, $totals->teacher_student_ratio ?? 0);
        $worksheet->getStyle('L' . $row)->getNumberFormat()->setFormatCode('0.00');
        
        // Trình độ đào tạo - Trên ĐH
        $worksheet->setCellValue('M' . $row, $totals->training_above_uni ?? 0);
        
        // Trình độ đào tạo - ĐH
        $worksheet->setCellValue('N' . $row, $totals->training_uni ?? 0);
        
        // Trình độ đào tạo - CĐ
        $worksheet->setCellValue('O' . $row, $totals->training_college ?? 0);
        
        // Trình độ đào tạo - THSP
        $worksheet->setCellValue('P' . $row, $totals->training_ped ?? 0);
        
        // Chuyên ngành đào tạo - Toán
        $worksheet->setCellValue('Q' . $row, $totals->spec_math ?? 0);
        
        // Chuyên ngành đào tạo - Ngữ văn
        $worksheet->setCellValue('R' . $row, $totals->spec_literature ?? 0);
        
        // KHTN - Lí
        $worksheet->setCellValue('S' . $row, $totals->spec_physics ?? 0);
        
        // KHTN - Hóa
        $worksheet->setCellValue('T' . $row, $totals->spec_chemistry ?? 0);
        
        // KHTN - Sinh
        $worksheet->setCellValue('U' . $row, $totals->spec_biology ?? 0);
        
        // KHXH - Sử
        $worksheet->setCellValue('V' . $row, $totals->spec_history ?? 0);
        
        // KHXH - Địa
        $worksheet->setCellValue('W' . $row, $totals->spec_geography ?? 0);
        
        // HĐGD - Nhạc
        $worksheet->setCellValue('X' . $row, $totals->spec_music ?? 0);
        
        // HĐGD - MT
        $worksheet->setCellValue('Y' . $row, $totals->spec_art ?? 0);
        
        // HĐGD - TD
        $worksheet->setCellValue('Z' . $row, $totals->spec_pe ?? 0);
        
        // Ngoại ngữ - Anh
        $worksheet->setCellValue('AA' . $row, $totals->spec_english ?? 0);
        
        // Ngoại ngữ - Nga
        $worksheet->setCellValue('AB' . $row, $totals->spec_russian ?? 0);
        
        // Ngoại ngữ - Pháp
        $worksheet->setCellValue('AC' . $row, $totals->spec_french ?? 0);
        
        // Ngoại ngữ - Khác
        $worksheet->setCellValue('AD' . $row, $totals->spec_other_lang ?? 0);
        
        // GDCD
        $worksheet->setCellValue('AE' . $row, $totals->spec_civic_edu ?? 0);
        
        // Công nghệ
        $worksheet->setCellValue('AF' . $row, $totals->spec_technology ?? 0);
        
        // Tin học
        $worksheet->setCellValue('AG' . $row, $totals->spec_informatics ?? 0);
        
        // TPT.Đội
        $worksheet->setCellValue('AH' . $row, $totals->tpt_team ?? 0);
        
        // Chuẩn nghề nghiệp - Tốt
        $worksheet->setCellValue('AI' . $row, $totals->standard_good ?? 0);
        
        // Chuẩn nghề nghiệp - Khá
        $worksheet->setCellValue('AJ' . $row, $totals->standard_quite ?? 0);
        
        // Chuẩn nghề nghiệp - Đạt
        $worksheet->setCellValue('AK' . $row, $totals->standard_reach ?? 0);
        
        // Chuẩn nghề nghiệp - Chưa đạt
        $worksheet->setCellValue('AL' . $row, $totals->standard_not_reach ?? 0);
        
        // Nhân viên - Thư viện
        $worksheet->setCellValue('AM' . $row, $totals->library_staff ?? 0);
        
        // Nhân viên - TBị - Thí nghiệm
        $worksheet->setCellValue('AN' . $row, $totals->lab_staff ?? 0);
        
        // Nhân viên - Văn phòng
        $worksheet->setCellValue('AO' . $row, $totals->office_staff ?? 0);
        
        // Nhân viên - Y tế
        $worksheet->setCellValue('AP' . $row, $totals->medical_staff ?? 0);
        
        return $row;
    }

    /**
     * Thêm phần tiêu chí với tiêu đề và border cho xã
     */
    private function addCriteriaHeadersForWard($worksheet, $startRow)
    {
        // Dòng tiêu đề
        // Merge A(startRow):I(startRow) và ghi 'Tiêu chí'
        $worksheet->setCellValue('A' . $startRow, 'Tiêu chí');
        $worksheet->mergeCells('A' . $startRow . ':I' . $startRow);
        
        // Merge J(startRow):K(startRow) và ghi 'SL'
        $worksheet->setCellValue('J' . $startRow, 'SL');
        $worksheet->mergeCells('J' . $startRow . ':K' . $startRow);
        
        // Merge L(startRow):M(startRow) và ghi 'Tỷ lệ'
        $worksheet->setCellValue('L' . $startRow, 'Tỷ lệ');
        $worksheet->mergeCells('L' . $startRow . ':M' . $startRow);
        
        // Căn giữa A(startRow):M(startRow) -  in đậm
        $headerRange = 'A' . $startRow . ':M' . $startRow;
        $headerStyle = $worksheet->getStyle($headerRange);
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $headerStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $headerStyle->getFont()->setBold(true); // in đậm
        $headerStyle->getFont()->setName('Tahoma');
        $headerStyle->getFont()->setSize(8);
        
        // Dòng 1: GV đạt chuẩn trình độ đào tạo
        $worksheet->setCellValue('A' . ($startRow + 1), 'GV đạt chuẩn trình độ đào tạo');
        $worksheet->mergeCells('A' . ($startRow + 1) . ':I' . ($startRow + 1));
        $worksheet->mergeCells('J' . ($startRow + 1) . ':K' . ($startRow + 1)); // Merge J:K
        $worksheet->mergeCells('L' . ($startRow + 1) . ':M' . ($startRow + 1)); // Merge L:M
        
        // Dòng 2: GV đạt trên chuẩn trình độ đào tạo
        $worksheet->setCellValue('A' . ($startRow + 2), 'GV đạt trên chuẩn trình độ đào tạo');
        $worksheet->mergeCells('A' . ($startRow + 2) . ':I' . ($startRow + 2));
        $worksheet->mergeCells('J' . ($startRow + 2) . ':K' . ($startRow + 2)); // Merge J:K
        $worksheet->mergeCells('L' . ($startRow + 2) . ':M' . ($startRow + 2)); // Merge L:M
        
        // Dòng 3: GV đạt yêu cầu chuẩn nghề nghiệp
        $worksheet->setCellValue('A' . ($startRow + 3), 'GV đạt yêu cầu chuẩn nghề nghiệp');
        $worksheet->mergeCells('A' . ($startRow + 3) . ':I' . ($startRow + 3));
        $worksheet->mergeCells('J' . ($startRow + 3) . ':K' . ($startRow + 3)); // Merge J:K
        $worksheet->mergeCells('L' . ($startRow + 3) . ':M' . ($startRow + 3)); // Merge L:M
        
        // Format font và border cho các dòng tiêu chí
        for ($i = 0; $i <= 3; $i++) {
            $row = $startRow + $i;
            $criteriaRange = 'A' . $row . ':M' . $row;
            $criteriaStyle = $worksheet->getStyle($criteriaRange);
            $criteriaStyle->getFont()->setName('Tahoma');
            $criteriaStyle->getFont()->setSize(8);
            $criteriaStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            
            if ($i == 0) {
                // Dòng tiêu đề: căn giữa và in đậm
                $criteriaStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $criteriaStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $criteriaStyle->getFont()->setBold(true);
            } else {
                // Các dòng nội dung: căn trái cho cột A-I, căn giữa cho cột J-M
                $criteriaStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                
                // Cột A-I: căn trái
                $worksheet->getStyle('A' . $row . ':I' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                
                // Cột J-M: căn giữa
                $worksheet->getStyle('J' . $row . ':M' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }
    }

    /**
     * Thêm dữ liệu tiêu chí cho xã
     */
    private function addCriteriaDataForWard($worksheet, $startRow, $dataRows)
    {
        $totals = $this->calculateTeacherTotals(collect($dataRows));
        
        // Tính tổng giáo viên để tính tỷ lệ
        $totalTeachers = $totals->total_teachers ?? 0;
        
        // 1. GV đạt chuẩn trình độ đào tạo (Trên ĐH + ĐH + CĐ)
        $gvDatChuanTD = ($totals->training_above_uni ?? 0) + ($totals->training_uni ?? 0) + 
                        ($totals->training_college ?? 0);
        $tyLeDatChuanTD = $totalTeachers > 0 ? ($gvDatChuanTD / $totalTeachers) * 100 : 0;
        
        // 2. GV đạt trên chuẩn trình độ đào tạo (Trên ĐH + ĐH)
        $gvTrenChuanTD = ($totals->training_above_uni ?? 0) + ($totals->training_uni ?? 0);
        $tyLeTrenChuanTD = $totalTeachers > 0 ? ($gvTrenChuanTD / $totalTeachers) * 100 : 0;
        
        // 3. GV đạt yêu cầu chuẩn nghề nghiệp (Tốt + Khá + Đạt)
        $gvDatChuanNN = ($totals->standard_good ?? 0) + ($totals->standard_quite ?? 0) + 
                        ($totals->standard_reach ?? 0);
        $tyLeDatChuanNN = $totalTeachers > 0 ? ($gvDatChuanNN / $totalTeachers) * 100 : 0;
        
        // Ghi số liệu vào cột J (SL) - merge J:K
        $worksheet->setCellValue('J' . ($startRow + 1), $gvDatChuanTD);        // Dòng 1
        $worksheet->setCellValue('J' . ($startRow + 2), $gvTrenChuanTD);        // Dòng 2  
        $worksheet->setCellValue('J' . ($startRow + 3), $gvDatChuanNN);          // Dòng 3
        
        // Ghi tỷ lệ vào cột L (Tỷ lệ) - merge L:M
        $worksheet->setCellValue('L' . ($startRow + 1), $tyLeDatChuanTD);       // Dòng 1
        $worksheet->setCellValue('L' . ($startRow + 2), $tyLeTrenChuanTD);       // Dòng 2
        $worksheet->setCellValue('L' . ($startRow + 3), $tyLeDatChuanNN);        // Dòng 3
        
        // Format decimal places 2 cho tỷ lệ
        for ($i = 1; $i <= 3; $i++) {
            $row = $startRow + $i;
            $worksheet->getStyle('L' . $row)->getNumberFormat()->setFormatCode('0.00');
        }
    }

    /**
     * Thêm phần ký tên cho xã
     */
    private function addSignatureSectionForWard($worksheet, $startRow, $wardName)
    {
        // Tên xã, ngày ... tháng ... năm ... vào ô AI(startRow)
        $worksheet->setCellValue('AI' . $startRow, $wardName . ', ngày ... tháng ... năm ...');
        
        // 'NGƯỜI LẬP BIỂU' vào ô U(startRow+1), in đậm
        $worksheet->setCellValue('U' . ($startRow + 1), 'NGƯỜI LẬP BIỂU');
        $worksheet->getStyle('U' . ($startRow + 1))->getFont()->setBold(true);
        
        // 'TM. BAN CHỈ ĐẠO PCGD, XMC' vào ô AI(startRow+1), in đậm
        $worksheet->setCellValue('AI' . ($startRow + 1), 'TM. BAN CHỈ ĐẠO PCGD, XMC');
        $worksheet->getStyle('AI' . ($startRow + 1))->getFont()->setBold(true);
        
        // '(Ký và ghi rõ họ tên)' vào ô U(startRow+2), in nghiêng
        $worksheet->setCellValue('U' . ($startRow + 2), '(Ký và ghi rõ họ tên)');
        $worksheet->getStyle('U' . ($startRow + 2))->getFont()->setItalic(true);
        
        // 'TRƯỞNG BAN' vào ô AI(startRow+2), in đậm
        $worksheet->setCellValue('AI' . ($startRow + 2), 'TRƯỞNG BAN');
        $worksheet->getStyle('AI' . ($startRow + 2))->getFont()->setBold(true);
        
        // '(Ký tên, đóng dấu)' vào ô AI(startRow+3), in nghiêng
        $worksheet->setCellValue('AI' . ($startRow + 3), '(Ký tên, đóng dấu)');
        $worksheet->getStyle('AI' . ($startRow + 3))->getFont()->setItalic(true);
        
        // Đặt font mặc định cho tất cả các ô ký tên
        $signatureRange = 'U' . ($startRow + 1) . ':AI' . ($startRow + 3);
        $worksheet->getStyle($signatureRange)->getFont()->setName('Tahoma');
        $worksheet->getStyle($signatureRange)->getFont()->setSize(8);
        
        // Đặt chiều cao dòng và căn giữa cho các dòng ký tên
        for ($row = $startRow; $row <= $startRow + 3; $row++) {
            $worksheet->getRowDimension($row)->setRowHeight(18.60);
            $worksheet->getStyle('U' . $row . ':AI' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $worksheet->getStyle('U' . $row . ':AI' . $row)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }
    }

    /**
     * Cài đặt rows to repeat at top cho tiêu đề
     */
    private function setupPrintTitles($worksheet)
    {
        // Cài đặt rows to repeat at top cho dòng 6-10 (tiêu đề)
        $worksheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(6, 10);
    }

    /**
     * Đặt font và căn lề nhất quán cho dòng
     */
    private function setConsistentFont($worksheet, $row)
    {
        // Đặt font mặc định cho toàn bộ dòng (A đến AP)
        $range = 'A' . $row . ':AP' . $row;
        $style = $worksheet->getStyle($range);
        
        // Reset font về mặc định
        $style->getFont()->setBold(false);
        $style->getFont()->setItalic(false);
        $style->getFont()->setName('Tahoma');
        $style->getFont()->setSize(8);
        
        // Đặt căn lề nhất quán
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        
        // Đặt padding cho các dòng từ dòng 11 trở đi
        if ($row >= 11) {
            $style->getAlignment()->setIndent(0);
            $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        // Xử lý từng cột cụ thể
        $columns = [
            'A' => 'center',    // TT - căn giữa
            'B' => 'left',      // Đơn vị - căn trái (merge B-C)
            'C' => 'left',      // Đơn vị (merged) - căn trái
            'D' => 'center',    // Hạng trường - căn giữa
            'E' => 'center',
            'F' => 'center',
            'G' => 'center',
            'H' => 'center',
            'I' => 'center',
            'J' => 'center',
            'K' => 'center',
            'L' => 'center',
            'M' => 'center',
            'N' => 'center',
            'O' => 'center',
            'P' => 'center',
            'Q' => 'center',
            'R' => 'center',
            'S' => 'center',
            'T' => 'center',
            'U' => 'center',
            'V' => 'center',
            'W' => 'center',
            'X' => 'center',
            'Y' => 'center',
            'Z' => 'center',
            'AA' => 'center',
            'AB' => 'center',
            'AC' => 'center',
            'AD' => 'center',
            'AE' => 'center',
            'AF' => 'center',
            'AG' => 'center',
            'AH' => 'center',
            'AI' => 'center',
            'AJ' => 'center',
            'AK' => 'center',
            'AL' => 'center',
            'AM' => 'center',
            'AN' => 'center',
            'AO' => 'center',
            'AP' => 'center'    // Cột cuối - căn giữa
        ];
        
        foreach ($columns as $col => $alignment) {
            $cellStyle = $worksheet->getStyle($col . $row);
            
            // Font settings
            $cellStyle->getFont()->setBold(false);
            $cellStyle->getFont()->setItalic(false);
            $cellStyle->getFont()->setName('Arial');
            $cellStyle->getFont()->setSize(10);
            
            // Alignment settings
            if ($alignment === 'left') {
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            } else {
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $cellStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            
            // Padding cho các dòng từ dòng 9 trở đi
            if ($row >= 9) {
                $cellStyle->getAlignment()->setIndent(0);
                $cellStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            }
            
            // Number format cho các cột số
            if (in_array($col, ['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP'])) {
                $cellStyle->getNumberFormat()->setFormatCode('0');
            }
        }
    }

    /**
     * Lấy tên tỉnh
     */
    private function getProvinceName($provinceId)
    {
        if (!$provinceId) {
            return 'Tỉnh Đắk Lắk';
        }
        
        $province = \App\Models\GeoUnit::find($provinceId);
        return $province ? $province->name : 'Tỉnh Đắk Lắk';
    }

    /**
     * Lấy tên xã/phường
     */
    private function getWardName($wardId)
    {
        if (!$wardId) {
            return 'Xã Cư Huê';
        }
        
        $ward = \App\Models\GeoUnit::find($wardId);
        return $ward ? $ward->name : 'Xã Cư Huê';
    }

    /**
     * Tính tổng cộng cho đội ngũ giáo viên
     */
    private function calculateTeacherTotals($reports)
    {
        $total = new $this->modelClass;
        $total->school_name = 'TỔNG CỘNG';
        $total->school_rank = 0;
        
        // Tính tổng cho tất cả các trường số
        $reportFields = $this->getReportFields();
        foreach ($reportFields as $field) {
            if (in_array($field, ['teacher_student_ratio'])) {
                // Trường tỷ lệ tính trung bình chỉ của những trường có dữ liệu hợp lệ
                $validValues = $reports->filter(function($report) use ($field) {
                    return isset($report->$field) && 
                           $report->$field !== null && 
                           $report->$field !== '' && 
                           $report->$field > 0;
                });
                
                if ($validValues->isNotEmpty()) {
                    $total->$field = $validValues->avg($field);
                } else {
                    $total->$field = 0;
                }
            } else {
                // Các trường khác tính tổng
                $total->$field = $reports->sum($field);
            }
        }

        return $total;
    }

    /**
     * Lưu dữ liệu cấp trường cho 6 bảng secondary
     */
    public function saveSchoolData(Request $request)
    {
        $request->validate([
            'academic_year' => 'required|string',
            'school_id' => 'required|integer|min:1',
            'ward_id' => 'required|integer|min:1',
            'school_rank' => 'nullable|numeric|min:0',
            'teacher_class_ratio' => 'nullable|numeric|min:0',
            
            // Validation cho từng vùng
            'quan_ly' => 'nullable|array',
            'giao_vien' => 'nullable|array',
            'nhan_vien' => 'nullable|array',
            'giao_vien_hop_dong' => 'nullable|array',
            'nhu_cau_giao_vien' => 'nullable|array',
        ], [
            'school_rank.numeric' => 'Hạng trường phải là số',
            'school_rank.min' => 'Hạng trường phải lớn hơn hoặc bằng 0',
            'teacher_class_ratio.numeric' => 'Tỷ lệ GV/Lớp phải là số',
            'teacher_class_ratio.min' => 'Tỷ lệ GV/Lớp phải lớn hơn hoặc bằng 0',
        ]);

        try {
            // Kiểm tra school_id và ward_id có hợp lệ không
            if ($request->school_id <= 0 || $request->ward_id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Thông tin trường học hoặc xã/phường không hợp lệ'
                ], 400);
            }

            // Lấy thông tin trường học trước
            $school = \App\Models\School::find($request->school_id);
            if (!$school) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy trường học'
                ], 404);
            }

            // Lấy thông tin xã/phường
            $ward = \App\Models\GeoUnit::find($request->ward_id);
            if (!$ward) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy xã/phường'
                ], 404);
            }

            // Dữ liệu cơ bản cho tất cả bảng
            $baseData = [
                'academic_year' => $request->academic_year,
                'school_id' => $request->school_id,
                'ward_id' => $request->ward_id,
                'school_name' => $school->name, // Lấy từ database
            ];

            // 1. Lưu dữ liệu TRONG BIÊN CHẾ - bỏ không lưu nữa (tính từ tổng 3 dòng con)

            // 2. Lưu dữ liệu QUẢN LÝ (secondary_management_report)
            if ($request->has('quan_ly')) {
                $this->saveToTable(\App\Models\SecondaryManagementReport::class, $baseData, $request->quan_ly, $request);
            }

            // 3. Lưu dữ liệu GIÁO VIÊN (secondary_teacher_report)
            if ($request->has('giao_vien')) {
                $this->saveToTable(\App\Models\SecondaryTeacherReport::class, $baseData, $request->giao_vien, $request);
            }

            // 4. Lưu dữ liệu NHÂN VIÊN (secondary_staff_report)
            if ($request->has('nhan_vien')) {
                $this->saveToTable(\App\Models\SecondaryStaffReport::class, $baseData, $request->nhan_vien, $request);
            }

            // 5. Lưu dữ liệu GIÁO VIÊN HỢP ĐỒNG (secondary_contract_teachers_report)
            if ($request->has('giao_vien_hop_dong')) {
                $this->saveToTable(\App\Models\SecondaryContractTeachersReport::class, $baseData, $request->giao_vien_hop_dong, $request);
            }

            // 6. Lưu dữ liệu NHU CẦU GIÁO VIÊN (secondary_teacher_demand)
            if ($request->has('nhu_cau_giao_vien')) {
                $this->saveToTable(\App\Models\SecondaryTeacherDemand::class, $baseData, $request->nhu_cau_giao_vien, $request);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lưu dữ liệu thành công!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lưu dữ liệu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method để lưu dữ liệu vào bảng cụ thể
     */
    private function saveToTable($modelClass, $baseData, $formData, $request = null)
    {
        // Tìm hoặc tạo record
        $record = $modelClass::where('academic_year', $baseData['academic_year'])
            ->where('school_id', $baseData['school_id'])
            ->where('ward_id', $baseData['ward_id'])
            ->first();

        if (!$record) {
            $record = new $modelClass();
            $record->academic_year = $baseData['academic_year'];
            $record->school_id = $baseData['school_id'];
            $record->ward_id = $baseData['ward_id'];
            $record->school_name = $baseData['school_name'];
        }

        // Cập nhật dữ liệu từ form
        foreach ($formData as $field => $value) {
            if (in_array($field, $record->getFillable())) {
                $record->$field = $value ?? 0;
            }
        }

        // Xử lý các trường đặc biệt cho một số bảng
        if ($request) {
            // Chỉ áp dụng cho SecondaryTeacherReport và SecondaryContractTeachersReport
            if (in_array($modelClass, [
                \App\Models\SecondaryTeacherReport::class,
                \App\Models\SecondaryContractTeachersReport::class
            ])) {
                if (in_array('school_rank', $record->getFillable())) {
                    $record->school_rank = $request->school_rank ?? 0;
                }
                if (in_array('teacher_class_ratio', $record->getFillable())) {
                    $record->teacher_class_ratio = $request->teacher_class_ratio ?? 0;
                }
            }
        }

        $record->save();
        return $record;
    }

    /**
     * Nhập dữ liệu từ Excel cho giáo viên THCS (cấu trúc mới)
     */
    public function importExcel(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
                'academic_year' => 'required|string|regex:/^\d{4}-\d{4}$/',
            ]);

            $file = $request->file('excel_file');
            $academicYear = $request->input('academic_year');
            
            // Lấy thông tin từ request hoặc từ user hiện tại
            $user = Auth::user();
            $school = $user->school;
            
            if (!$school) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy thông tin trường của tài khoản'], 400);
            }

            $schoolId = $school->id;
            $wardId = $school->getWardId();
            $provinceId = $school->getProvinceId();
            
            // Validate năm học format
            if (!$academicYear || !preg_match('/^\d{4}-\d{4}$/', $academicYear)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Năm học không hợp lệ. Vui lòng chọn năm học đúng định dạng.'
                ], 400);
            }

            // Kiểm tra trường có phải THCS không
            $secondaryLevelIds = $this->getSecondaryLevelIds();
            if (!in_array($school->educational_level_id, $secondaryLevelIds)) {
                return response()->json(['success' => false, 'message' => 'Trường này không phải là trường THCS'], 400);
            }
            
            // Đọc file Excel
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Thông tin cơ bản cho tất cả bảng
            $baseData = [
                'academic_year' => $academicYear,
                'school_id' => $schoolId,
                'ward_id' => $wardId,
                'school_name' => $school->name,
            ];

            $importedData = [];

            // 1. Dòng 10: Trong biên chế - bỏ không đọc nữa (tính từ tổng 3 dòng con)

            // 2. Dòng 11: + Quản lý - secondary_management_report
            $quanLyData = $this->readManagementRowData($worksheet, 11);
            if ($quanLyData) {
                $record = \App\Models\SecondaryManagementReport::updateOrCreate(
                    [
                        'academic_year' => $academicYear,
                        'school_id' => $schoolId,
                        'ward_id' => $wardId,
                    ],
                    array_merge($baseData, $quanLyData)
                );
                $importedData['quan_ly'] = $record;
            }

            // 3. Dòng 12: + Giáo viên - secondary_teacher_report
            $giaoVienData = $this->readTeacherRowData($worksheet, 12);
            if ($giaoVienData) {
                $record = \App\Models\SecondaryTeacherReport::updateOrCreate(
                    [
                        'academic_year' => $academicYear,
                        'school_id' => $schoolId,
                        'ward_id' => $wardId,
                    ],
                    array_merge($baseData, $giaoVienData)
                );
                $importedData['giao_vien'] = $record;
            }

            // 4. Dòng 13: + Nhân viên - secondary_staff_report
            $nhanVienData = $this->readStaffRowData($worksheet, 13);
            if ($nhanVienData) {
                $record = \App\Models\SecondaryStaffReport::updateOrCreate(
                    [
                        'academic_year' => $academicYear,
                        'school_id' => $schoolId,
                        'ward_id' => $wardId,
                    ],
                    array_merge($baseData, $nhanVienData)
                );
                $importedData['nhan_vien'] = $record;
            }

            // 5. Dòng 14: Giáo viên hợp đồng - secondary_contract_teachers_report
            $giaoVienHopDongData = $this->readContractTeacherRowData($worksheet, 14);
            if ($giaoVienHopDongData) {
                $record = \App\Models\SecondaryContractTeachersReport::updateOrCreate(
                    [
                        'academic_year' => $academicYear,
                        'school_id' => $schoolId,
                        'ward_id' => $wardId,
                    ],
                    array_merge($baseData, $giaoVienHopDongData)
                );
                $importedData['giao_vien_hop_dong'] = $record;
            }

            // 6. Dòng 15: Nhu cầu giáo viên - secondary_teacher_demand
            $nhuCauGiaoVienData = $this->readBasicRowData($worksheet, 15);
            if ($nhuCauGiaoVienData) {
                $record = \App\Models\SecondaryTeacherDemand::updateOrCreate(
                    [
                    'academic_year' => $academicYear,
                        'school_id' => $schoolId,
                        'ward_id' => $wardId,
                    ],
                    array_merge($baseData, $nhuCauGiaoVienData)
                );
                $importedData['nhu_cau_giao_vien'] = $record;
            }

            // 7. Dòng 17: Hạng trường, Dòng 18: Tỷ lệ GV/Lớp
            $schoolRank = $worksheet->getCell('D17')->getCalculatedValue() ?? 0;
            $teacherClassRatio = $worksheet->getCell('D18')->getCalculatedValue() ?? 0;

            // Cập nhật hạng trường và tỷ lệ vào bảng giáo viên
            if (isset($importedData['giao_vien'])) {
                $importedData['giao_vien']->update([
                    'school_rank' => $schoolRank,
                    'teacher_class_ratio' => $teacherClassRatio,
                ]);
            }

            // Cập nhật hạng trường và tỷ lệ vào bảng giáo viên hợp đồng
            if (isset($importedData['giao_vien_hop_dong'])) {
                $importedData['giao_vien_hop_dong']->update([
                    'school_rank' => $schoolRank,
                    'teacher_class_ratio' => $teacherClassRatio,
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Nhập dữ liệu thành công!',
                'data' => $importedData
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi nhập dữ liệu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Đọc dữ liệu cơ bản từ Excel (Trong biên chế, Nhu cầu giáo viên)
     */
    private function readBasicRowData($worksheet, $row)
    {
        $data = [];
        
        // Mapping từ cột Excel đến field name (D-AA) - không có Dân tộc
        $columnMapping = [
            4 => 'total',           // D
            5 => 'female',         // E
            6 => 'party_member',   // F
            7 => 'above_university',  // G
            8 => 'university',     // H
            9 => 'college',        // I
            10 => 'secondary',     // J
            11 => 'mathematics',   // K
            12 => 'physics',       // L
            13 => 'chemistry',     // M
            14 => 'biology',       // N
            15 => 'literature',    // O
            16 => 'history',       // P
            17 => 'geography',     // Q
            18 => 'english',       // R
            19 => 'french',        // S
            20 => 'russian',       // T
            21 => 'other_language', // U
            22 => 'civic_education', // V
            23 => 'technology',    // W
            24 => 'physical_education', // X
            25 => 'music',         // Y
            26 => 'art',           // Z
            27 => 'informatics',   // AA
        ];

        foreach ($columnMapping as $colIndex => $field) {
            $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex) . $row;
            $data[$field] = $worksheet->getCell($cellCoordinate)->getCalculatedValue() ?? 0;
        }

        return $data;
    }

    /**
     * Đọc dữ liệu quản lý từ Excel (+ Quản lý)
     */
    private function readManagementRowData($worksheet, $row)
    {
        $data = $this->readBasicRowData($worksheet, $row);
        
        // Thêm Hiệu trưởng, P.Hiệu trưởng (AB-AC)
        $data['principal'] = $worksheet->getCell('AB' . $row)->getCalculatedValue() ?? 0;
        $data['vice_principal'] = $worksheet->getCell('AC' . $row)->getCalculatedValue() ?? 0;

        return $data;
    }

    /**
     * Đọc dữ liệu giáo viên từ Excel (+ Giáo viên)
     */
    private function readTeacherRowData($worksheet, $row)
    {
        $data = $this->readBasicRowData($worksheet, $row);
        
        // Thêm Dân tộc (AD) - được thêm vào trước TPT.Đội
        $data['ethnic'] = $worksheet->getCell('AD' . $row)->getCalculatedValue() ?? 0;
        
        // Thêm TPT.đội, chuẩn nghề nghiệp (AE-AI) - dịch chuyển 1 cột do thêm cột Dân tộc
        $data['tpt_team'] = $worksheet->getCell('AE' . $row)->getCalculatedValue() ?? 0;
        $data['standard_good'] = $worksheet->getCell('AF' . $row)->getCalculatedValue() ?? 0;
        $data['standard_quite'] = $worksheet->getCell('AG' . $row)->getCalculatedValue() ?? 0;
        $data['standard_reach'] = $worksheet->getCell('AH' . $row)->getCalculatedValue() ?? 0;
        $data['standard_not_reach'] = $worksheet->getCell('AI' . $row)->getCalculatedValue() ?? 0;

        return $data;
    }

    /**
     * Đọc dữ liệu nhân viên từ Excel (+ Nhân viên)
     */
    private function readStaffRowData($worksheet, $row)
    {
        $data = $this->readBasicRowData($worksheet, $row);
        
        // Thêm nhân viên (AJ-AM) - không có cột Dân tộc, TPT.đội, chuẩn nghề nghiệp
        $data['library_staff'] = $worksheet->getCell('AJ' . $row)->getCalculatedValue() ?? 0;
        $data['equipment_lab_staff'] = $worksheet->getCell('AK' . $row)->getCalculatedValue() ?? 0;
        $data['medical_staff'] = $worksheet->getCell('AL' . $row)->getCalculatedValue() ?? 0;
        $data['office_staff'] = $worksheet->getCell('AM' . $row)->getCalculatedValue() ?? 0;

        return $data;
    }

    /**
     * Đọc dữ liệu giáo viên hợp đồng từ Excel
     */
    private function readContractTeacherRowData($worksheet, $row)
    {
        // Giống giáo viên nhưng không có hạng trường, tỷ lệ
        return $this->readTeacherRowData($worksheet, $row);
    }

    /**
     * Format dòng dữ liệu với border và căn lề
     */
    private function formatDataRow($worksheet, $row)
    {
        $dataRowRange = 'A' . $row . ':AP' . $row;
        $dataRowStyle = $worksheet->getStyle($dataRowRange);
        
        // Font
        $dataRowStyle->getFont()->setName('Tahoma');
        $dataRowStyle->getFont()->setSize(8);
        
        // Border
        $dataRowStyle->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // Alignment
        $dataRowStyle->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $dataRowStyle->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        
        // Căn trái cho cột tên trường (B-C)
        $worksheet->getStyle('B' . $row . ':C' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
    }

    /**
     * Set format số nguyên và căn giữa cho ô
     */
    private function setNumberFormatAndCenter($worksheet, $cell)
    {
        $style = $worksheet->getStyle($cell);
        $style->getNumberFormat()->setFormatCode('0');
        $style->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $style->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    }

    /**
     * Export Excel báo cáo 3.5 Thống kê đội ngũ quản lý, giáo viên THCS cho cấp xã
     */
    public function exportExcelForWard35(Request $request)
    {
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        $year = $request->get('year', date('Y') . '-' . (date('Y') + 1));
        $geoIn = (array) $request->input('geo', []);
        $geoIds = [
            'province' => (int) ($geoIn['province_id'] ?? 0) ?: null,
            'district' => (int) ($geoIn['district_id'] ?? 0) ?: null,
            'ward'     => (int) ($geoIn['ward_id'] ?? 0) ?: null,
            'school'   => null, // Không lọc theo trường cụ thể
        ];

        $secondaryLevelIds = $this->getSecondaryLevelIds();
        if (empty($secondaryLevelIds)) {
            return response()->json(['error' => 'Không tìm thấy cấp học THCS'], 400);
        }

        // Lấy dữ liệu từ các bảng secondary mới
        $user = \Illuminate\Support\Facades\Auth::user();
        $level = $this->reportService->determineDisplayLevel($user, $geoIds);
        $reports = $this->getSecondaryReportsForWard($year, $geoIds, $level, $secondaryLevelIds);
        
        $templatePath = resource_path('excel/3.5.THCS.DoiNguQuanLy.xlsx');
        if (!file_exists($templatePath)) {
            return response()->json(['error' => 'Không tìm thấy file template Excel'], 400);
        }

        $spreadsheet = IOFactory::load($templatePath);
        $worksheet = $spreadsheet->getActiveSheet();

        // Lấy thông tin địa lý
        $provinceName = $this->getProvinceName($geoIds['province']);
        $wardName = $this->getWardName($geoIds['ward']);

        // Điền thông tin tỉnh/thành và phường/xã
        $worksheet->setCellValue('C2', $provinceName);
        $worksheet->setCellValue('C3', $wardName);
        
        // In đậm tên tỉnh và tên xã
        $worksheet->getStyle('C2')->getFont()->setBold(true);
        $worksheet->getStyle('C3')->getFont()->setBold(true);
        
        // Ghi ngày xuất file vào ô G4
        $currentDay = date('d');
        $currentMonth = date('m');
        $currentYear = date('Y');
        $worksheet->setCellValue('G4', 'Ngày ' . $currentDay . ' tháng ' . $currentMonth . ' năm ' . $currentYear);
        
        // In đậm và in nghiêng ngày xuất file
        $worksheet->getStyle('G4')->getFont()->setBold(true);
        $worksheet->getStyle('G4')->getFont()->setItalic(true);

        // Điền dữ liệu thống kê
        $this->fillManagementTeacherData($worksheet, $reports, $wardName);

        $filename = 'bao_cao_doi_ngu_quan_ly_giao_vien_thcs_' . $wardName . '_' . $year . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'secondary_management_teacher_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Điền dữ liệu thống kê đội ngũ quản lý, giáo viên THCS
     */
    private function fillManagementTeacherData($worksheet, $reports, $wardName)
    {
        // Tính tổng cộng cho tất cả trường trong xã
        $totals = $this->calculateManagementTeacherTotals($reports);

        // Dòng 10: Trong biên chế
        $row = 10;
        $this->fillManagementTeacherRow($worksheet, $row, 'Trong biên chế', $totals['permanent']);

        // Dòng 11: + Quản lý
        $row = 11;
        $this->fillManagementTeacherRow($worksheet, $row, '+ Quản lý', $totals['management']);

        // Dòng 12: + Giáo viên
        $row = 12;
        $this->fillManagementTeacherRow($worksheet, $row, '+ Giáo viên', $totals['teachers']);

        // Dòng 13: + Nhân viên
        $row = 13;
        $this->fillManagementTeacherRow($worksheet, $row, '+ Nhân viên', $totals['staff']);

        // Dòng 14: Giáo viên hợp đồng
        $row = 14;
        $this->fillManagementTeacherRow($worksheet, $row, 'Giáo viên hợp đồng', $totals['contract_teachers']);

        // Dòng 15: Cộng (tổng của trong biên chế + giáo viên hợp đồng)
        $row = 15;
        $totalRow = $this->calculateTotalRow($totals['permanent'], $totals['contract_teachers']);
        $this->fillManagementTeacherRow($worksheet, $row, 'Cộng', $totalRow);

        // Dòng 16: Nhu cầu giáo viên (để trống)
        $row = 16;
        $this->fillManagementTeacherRow($worksheet, $row, 'Nhu cầu giáo viên', $totals['demand']);

        // Thêm phần ký tên
        $this->addSignatureSectionForWard35($worksheet, $wardName);
    }

    /**
     * Điền dữ liệu cho một dòng thống kê
     */
    private function fillManagementTeacherRow($worksheet, $row, $label, $data)
    {
        // Cột A: TT (để trống cho các dòng con)
        if (strpos($label, '+') === 0) {
            $worksheet->setCellValue('A' . $row, '');
        } else {
            $worksheet->setCellValue('A' . $row, $this->getRowNumber($label));
        }

        // Cột B: Đơn vị
        $worksheet->setCellValue('B' . $row, $label);

        // Các cột dữ liệu từ D đến AA (vì cột Đơn vị merge B-C, nên D mới là Tổng số)
        $columns = ['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA'];
        $fieldNames = [
            'total', 'female', 'party_member', 'above_university', 'university', 'college', 'secondary',
            'math', 'physics', 'chemistry', 'biology', 'literature', 'history', 'geography',
            'english', 'french', 'russian', 'other_lang', 'civic_education', 'technology', 
            'physical_education', 'music', 'fine_arts', 'informatics'
        ];

        for ($i = 0; $i < count($columns); $i++) {
            $column = $columns[$i];
            $fieldName = $fieldNames[$i] ?? '';
            $value = isset($data[$fieldName]) ? $data[$fieldName] : 0;
            
            // Hiển thị rỗng nếu giá trị là 0 hoặc không có
            $displayValue = ($value && $value != 0) ? $value : '';
            $worksheet->setCellValue($column . $row, $displayValue);
        }
    }

    /**
     * Lấy số thứ tự cho dòng
     */
    private function getRowNumber($label)
    {
        switch ($label) {
            case 'Trong biên chế':
                return 1;
            case 'Giáo viên hợp đồng':
                return 2;
            case 'Cộng':
                return 3;
            case 'Nhu cầu giáo viên':
                return 4;
            default:
                return '';
        }
    }

    /**
     * Tính tổng cộng cho đội ngũ quản lý, giáo viên
     */
    private function calculateManagementTeacherTotals($reports)
    {
        $totals = [
            'permanent' => [],
            'management' => [],
            'teachers' => [],
            'staff' => [],
            'contract_teachers' => [],
            'demand' => []
        ];

        // Khởi tạo các trường
        $fields = [
            'total', 'female', 'party_member', 'above_university', 'university', 'college', 'secondary',
            'math', 'physics', 'chemistry', 'biology', 'literature', 'history', 'geography',
            'english', 'french', 'russian', 'other_lang', 'civic_education', 'technology', 
            'physical_education', 'music', 'fine_arts', 'informatics'
        ];

        foreach ($fields as $field) {
            $totals['permanent'][$field] = 0;
            $totals['management'][$field] = 0;
            $totals['teachers'][$field] = 0;
            $totals['staff'][$field] = 0;
            $totals['contract_teachers'][$field] = 0;
            $totals['demand'][$field] = 0;
        }

        // Lấy dữ liệu từ các bảng secondary tương ứng
        $year = request()->get('year', date('Y') . '-' . (date('Y') + 1));
        $geoIn = (array) request()->input('geo', []);
        $geoIds = [
            'province' => (int) ($geoIn['province_id'] ?? 0) ?: null,
            'district' => (int) ($geoIn['district_id'] ?? 0) ?: null,
            'ward'     => (int) ($geoIn['ward_id'] ?? 0) ?: null,
        ];

        $secondaryLevelIds = $this->getSecondaryLevelIds();
        
        // Lấy danh sách trường THCS trong xã (chỉ trường đang hoạt động)
        $schools = \App\Models\School::active()
            ->whereIn('educational_level_id', $secondaryLevelIds)
            ->when($geoIds['ward'], function($query) use ($geoIds) {
                return $query->where('new_ward_id', $geoIds['ward']);
            })
            ->get();

        foreach ($schools as $school) {
            $whereConditions = [
                'academic_year' => $year,
                'school_id' => $school->id
            ];

            // Dòng 10: Trong biên chế - tính từ tổng 3 dòng con

            // Dòng 11: Quản lý (từ secondary_management_report)
            $managementReport = \App\Models\SecondaryManagementReport::where($whereConditions)->first();
            if ($managementReport) {
                $totals['management']['total'] += $managementReport->total ?? 0;
                $totals['management']['female'] += $managementReport->female ?? 0;
                $totals['management']['party_member'] += $managementReport->party_member ?? 0;
                $totals['management']['above_university'] += $managementReport->above_university ?? 0;
                $totals['management']['university'] += $managementReport->university ?? 0;
                $totals['management']['college'] += $managementReport->college ?? 0;
                $totals['management']['secondary'] += $managementReport->secondary ?? 0;
                $totals['management']['math'] += $managementReport->mathematics ?? 0;
                $totals['management']['physics'] += $managementReport->physics ?? 0;
                $totals['management']['chemistry'] += $managementReport->chemistry ?? 0;
                $totals['management']['biology'] += $managementReport->biology ?? 0;
                $totals['management']['literature'] += $managementReport->literature ?? 0;
                $totals['management']['history'] += $managementReport->history ?? 0;
                $totals['management']['geography'] += $managementReport->geography ?? 0;
                $totals['management']['english'] += $managementReport->english ?? 0;
                $totals['management']['french'] += $managementReport->french ?? 0;
                $totals['management']['russian'] += $managementReport->russian ?? 0;
                $totals['management']['other_lang'] += $managementReport->other_language ?? 0;
                $totals['management']['civic_education'] += $managementReport->civic_education ?? 0;
                $totals['management']['technology'] += $managementReport->technology ?? 0;
                $totals['management']['physical_education'] += $managementReport->physical_education ?? 0;
                $totals['management']['music'] += $managementReport->music ?? 0;
                $totals['management']['fine_arts'] += $managementReport->art ?? 0;
                $totals['management']['informatics'] += $managementReport->informatics ?? 0;
            }

            // Dòng 12: Giáo viên (từ secondary_teacher_report)
            $teacherReport = \App\Models\SecondaryTeacherReport::where($whereConditions)->first();
            if ($teacherReport) {
                $totals['teachers']['total'] += $teacherReport->total ?? 0;
                $totals['teachers']['female'] += $teacherReport->female ?? 0;
                $totals['teachers']['party_member'] += $teacherReport->party_member ?? 0;
                $totals['teachers']['above_university'] += $teacherReport->above_university ?? 0;
                $totals['teachers']['university'] += $teacherReport->university ?? 0;
                $totals['teachers']['college'] += $teacherReport->college ?? 0;
                $totals['teachers']['secondary'] += $teacherReport->secondary ?? 0;
                $totals['teachers']['math'] += $teacherReport->mathematics ?? 0;
                $totals['teachers']['physics'] += $teacherReport->physics ?? 0;
                $totals['teachers']['chemistry'] += $teacherReport->chemistry ?? 0;
                $totals['teachers']['biology'] += $teacherReport->biology ?? 0;
                $totals['teachers']['literature'] += $teacherReport->literature ?? 0;
                $totals['teachers']['history'] += $teacherReport->history ?? 0;
                $totals['teachers']['geography'] += $teacherReport->geography ?? 0;
                $totals['teachers']['english'] += $teacherReport->english ?? 0;
                $totals['teachers']['french'] += $teacherReport->french ?? 0;
                $totals['teachers']['russian'] += $teacherReport->russian ?? 0;
                $totals['teachers']['other_lang'] += $teacherReport->other_language ?? 0;
                $totals['teachers']['civic_education'] += $teacherReport->civic_education ?? 0;
                $totals['teachers']['technology'] += $teacherReport->technology ?? 0;
                $totals['teachers']['physical_education'] += $teacherReport->physical_education ?? 0;
                $totals['teachers']['music'] += $teacherReport->music ?? 0;
                $totals['teachers']['fine_arts'] += $teacherReport->art ?? 0;
                $totals['teachers']['informatics'] += $teacherReport->informatics ?? 0;
            }

            // Dòng 13: Nhân viên (từ secondary_staff_report)
            $staffReport = \App\Models\SecondaryStaffReport::where($whereConditions)->first();
            if ($staffReport) {
                $totals['staff']['total'] += $staffReport->total ?? 0;
                $totals['staff']['female'] += $staffReport->female ?? 0;
                $totals['staff']['party_member'] += $staffReport->party_member ?? 0;
                $totals['staff']['above_university'] += $staffReport->above_university ?? 0;
                $totals['staff']['university'] += $staffReport->university ?? 0;
                $totals['staff']['college'] += $staffReport->college ?? 0;
                $totals['staff']['secondary'] += $staffReport->secondary ?? 0;
                $totals['staff']['math'] += $staffReport->mathematics ?? 0;
                $totals['staff']['physics'] += $staffReport->physics ?? 0;
                $totals['staff']['chemistry'] += $staffReport->chemistry ?? 0;
                $totals['staff']['biology'] += $staffReport->biology ?? 0;
                $totals['staff']['literature'] += $staffReport->literature ?? 0;
                $totals['staff']['history'] += $staffReport->history ?? 0;
                $totals['staff']['geography'] += $staffReport->geography ?? 0;
                $totals['staff']['english'] += $staffReport->english ?? 0;
                $totals['staff']['french'] += $staffReport->french ?? 0;
                $totals['staff']['russian'] += $staffReport->russian ?? 0;
                $totals['staff']['other_lang'] += $staffReport->other_language ?? 0;
                $totals['staff']['civic_education'] += $staffReport->civic_education ?? 0;
                $totals['staff']['technology'] += $staffReport->technology ?? 0;
                $totals['staff']['physical_education'] += $staffReport->physical_education ?? 0;
                $totals['staff']['music'] += $staffReport->music ?? 0;
                $totals['staff']['fine_arts'] += $staffReport->art ?? 0;
                $totals['staff']['informatics'] += $staffReport->informatics ?? 0;
            }

            // Dòng 14: Giáo viên hợp đồng (từ secondary_contract_teachers_report)
            $contractReport = \App\Models\SecondaryContractTeachersReport::where($whereConditions)->first();
            if ($contractReport) {
                $totals['contract_teachers']['total'] += $contractReport->total ?? 0;
                $totals['contract_teachers']['female'] += $contractReport->female ?? 0;
                $totals['contract_teachers']['party_member'] += $contractReport->party_member ?? 0;
                $totals['contract_teachers']['above_university'] += $contractReport->above_university ?? 0;
                $totals['contract_teachers']['university'] += $contractReport->university ?? 0;
                $totals['contract_teachers']['college'] += $contractReport->college ?? 0;
                $totals['contract_teachers']['secondary'] += $contractReport->secondary ?? 0;
                $totals['contract_teachers']['math'] += $contractReport->mathematics ?? 0;
                $totals['contract_teachers']['physics'] += $contractReport->physics ?? 0;
                $totals['contract_teachers']['chemistry'] += $contractReport->chemistry ?? 0;
                $totals['contract_teachers']['biology'] += $contractReport->biology ?? 0;
                $totals['contract_teachers']['literature'] += $contractReport->literature ?? 0;
                $totals['contract_teachers']['history'] += $contractReport->history ?? 0;
                $totals['contract_teachers']['geography'] += $contractReport->geography ?? 0;
                $totals['contract_teachers']['english'] += $contractReport->english ?? 0;
                $totals['contract_teachers']['french'] += $contractReport->french ?? 0;
                $totals['contract_teachers']['russian'] += $contractReport->russian ?? 0;
                $totals['contract_teachers']['other_lang'] += $contractReport->other_language ?? 0;
                $totals['contract_teachers']['civic_education'] += $contractReport->civic_education ?? 0;
                $totals['contract_teachers']['technology'] += $contractReport->technology ?? 0;
                $totals['contract_teachers']['physical_education'] += $contractReport->physical_education ?? 0;
                $totals['contract_teachers']['music'] += $contractReport->music ?? 0;
                $totals['contract_teachers']['fine_arts'] += $contractReport->art ?? 0;
                $totals['contract_teachers']['informatics'] += $contractReport->informatics ?? 0;
            }

            // Dòng 16: Nhu cầu giáo viên (từ secondary_teacher_demand)
            $demandReport = \App\Models\SecondaryTeacherDemand::where($whereConditions)->first();
            if ($demandReport) {
                $totals['demand']['total'] += $demandReport->total ?? 0;
                $totals['demand']['female'] += $demandReport->female ?? 0;
                $totals['demand']['party_member'] += $demandReport->party_member ?? 0;
                $totals['demand']['above_university'] += $demandReport->above_university ?? 0;
                $totals['demand']['university'] += $demandReport->university ?? 0;
                $totals['demand']['college'] += $demandReport->college ?? 0;
                $totals['demand']['secondary'] += $demandReport->secondary ?? 0;
                $totals['demand']['math'] += $demandReport->mathematics ?? 0;
                $totals['demand']['physics'] += $demandReport->physics ?? 0;
                $totals['demand']['chemistry'] += $demandReport->chemistry ?? 0;
                $totals['demand']['biology'] += $demandReport->biology ?? 0;
                $totals['demand']['literature'] += $demandReport->literature ?? 0;
                $totals['demand']['history'] += $demandReport->history ?? 0;
                $totals['demand']['geography'] += $demandReport->geography ?? 0;
                $totals['demand']['english'] += $demandReport->english ?? 0;
                $totals['demand']['french'] += $demandReport->french ?? 0;
                $totals['demand']['russian'] += $demandReport->russian ?? 0;
                $totals['demand']['other_lang'] += $demandReport->other_language ?? 0;
                $totals['demand']['civic_education'] += $demandReport->civic_education ?? 0;
                $totals['demand']['technology'] += $demandReport->technology ?? 0;
                $totals['demand']['physical_education'] += $demandReport->physical_education ?? 0;
                $totals['demand']['music'] += $demandReport->music ?? 0;
                $totals['demand']['fine_arts'] += $demandReport->art ?? 0;
                $totals['demand']['informatics'] += $demandReport->informatics ?? 0;
            }
        }

        // Tính dòng "Trong biên chế" từ tổng của 3 dòng con (Quản lý + Giáo viên + Nhân viên)
        foreach ($fields as $field) {
            $totals['permanent'][$field] = $totals['management'][$field] + $totals['teachers'][$field] + $totals['staff'][$field];
        }

        return $totals;
    }

    /**
     * Tính dòng tổng cộng (trong biên chế + giáo viên hợp đồng)
     */
    private function calculateTotalRow($permanent, $contractTeachers)
    {
        $total = [];
        $fields = [
            'total', 'female', 'party_member', 'above_university', 'university', 'college', 'secondary',
            'math', 'physics', 'chemistry', 'biology', 'literature', 'history', 'geography',
            'english', 'french', 'russian', 'other_lang', 'civic_education', 'technology', 
            'physical_education', 'music', 'fine_arts', 'informatics'
        ];

        foreach ($fields as $field) {
            $total[$field] = ($permanent[$field] ?? 0) + ($contractTeachers[$field] ?? 0);
        }

        return $total;
    }


    /**
     * Thêm phần ký tên cho báo cáo cấp xã (báo cáo 3.5)
     */
    private function addSignatureSectionForWard35($worksheet, $wardName)
    {
        // Tên xã, ngày ... tháng ... năm ... vào ô S18
        $dateRow = 18;
        $worksheet->setCellValue('S' . $dateRow, $wardName . ', ngày ... tháng ... năm ...');
        
        // 'NGƯỜI LẬP BIỂU' vào ô D19, in đậm
        $signatureRow1 = 19;
        $worksheet->setCellValue('D' . $signatureRow1, 'NGƯỜI LẬP BIỂU');
        $worksheet->getStyle('D' . $signatureRow1)->getFont()->setBold(true);
        
        // 'TM. BAN CHỈ ĐẠO PCGD, XMC' vào ô S19, in đậm
        $worksheet->setCellValue('S' . $signatureRow1, 'TM. BAN CHỈ ĐẠO PCGD, XMC');
        $worksheet->getStyle('S' . $signatureRow1)->getFont()->setBold(true);
        
        // '(Ký và ghi rõ họ tên)' vào ô D20, in nghiêng
        $signatureRow2 = 20;
        $worksheet->setCellValue('D' . $signatureRow2, '(Ký và ghi rõ họ tên)');
        $worksheet->getStyle('D' . $signatureRow2)->getFont()->setItalic(true);
        
        // '(Ký tên, đóng dấu)' vào ô S20, in nghiêng (bỏ dòng TRƯỞNG BAN)
        $worksheet->setCellValue('S' . $signatureRow2, '(Ký tên, đóng dấu)');
        $worksheet->getStyle('S' . $signatureRow2)->getFont()->setItalic(true);
        
        // Đặt font mặc định cho tất cả các ô ký tên
        $signatureRange = 'D' . $signatureRow1 . ':S' . $signatureRow2;
        $worksheet->getStyle($signatureRange)->getFont()->setName('Tahoma');
        $worksheet->getStyle($signatureRange)->getFont()->setSize(8);
        
        // Đặt chiều cao dòng và căn giữa cho các dòng ký tên
        for ($row = $dateRow; $row <= $signatureRow2; $row++) {
            $worksheet->getRowDimension($row)->setRowHeight(18.60);
            $worksheet->getStyle('D' . $row . ':S' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $worksheet->getStyle('D' . $row . ':S' . $row)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }
    }
}