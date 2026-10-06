<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Front\BaseMultiLevelReportController;
use App\Models\PrimarySchoolInfrastructureReport;
use App\Models\EducationalLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class PrimarySchoolInfrastructureController extends BaseMultiLevelReportController
{
    protected $modelClass = PrimarySchoolInfrastructureReport::class;
    protected $viewName = 'front.primary-school-infrastructure.index';
    protected $routePrefix = 'primary-school-infrastructure';

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

        // Lấy ID của các cấp học THCS
        $secondaryLevelIds = $this->getSecondaryLevelIds();
        if (empty($secondaryLevelIds)) {
            $reports = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
            $columns = $this->columns('province');
            $groups = $this->columnGroups('province');
            $stats = collect([]);
            $level = 'province';
            $routePrefix = $this->routePrefix;

            return view($this->viewName, compact(
                'reports', 'year', 'q', 'stats', 'level', 'perPage', 'routePrefix', 'geoIds', 'scheme'
            ));
        }

        // Xác định cấp độ hiển thị dựa trên user role và filter
        $user = \Illuminate\Support\Facades\Auth::user();
        $level = $this->reportService->determineDisplayLevel($user, $geoIds);
        
        $reports = $this->getReportsByLevelWithFilter($level, $year, $q, $geoIds, $scheme, $perPage, $secondaryLevelIds);
        $columns = $this->columns($level);
        $groups = $this->columnGroups($level);
        $stats = $this->getStatistics($year, $geoIds['ward']);
        $routePrefix = $this->routePrefix;

        return view($this->viewName, compact(
            'reports', 'year', 'q', 'stats', 'level', 'perPage', 'routePrefix', 'geoIds', 'scheme'
        ));
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
                $total->ward_name = $ward->name;
                $total->has_data = true;
                return $total;
            } else {
                // Chưa có báo cáo, tạo object rỗng
                return $this->createEmptyWardObject($ward, $year);
            }
        });
        
        // Tính tổng cộng cho toàn tỉnh
        $provinceTotal = $this->calculateTotals($reports);
        $provinceTotal->ward_name = 'TỔNG CỘNG';
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
            'ward_name' => $ward->name,
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
            if (in_array($field, ['classroom_class_ratio'])) {
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
        $importantFields = ['total_classes', 'solid_classrooms', 'semi_solid_classrooms'];
        
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
            'school_branch', 'total_classes', 'combined_classes', 'solid_classrooms', 'semi_solid_classrooms',
            'temporary_classrooms', 'rented_classrooms', 'classroom_class_ratio',
            'principal_rooms_count', 'vice_principal_rooms_count', 'office_rooms_count',
            'medical_rooms_count', 'youth_union_rooms_count', 'meeting_rooms_count',
            'meeting_rooms_area', 'library_rooms_count', 'library_rooms_area',
            'equipment_rooms_count', 'equipment_rooms_area', 'teacher_rooms_count',
            'teacher_rooms_area', 'student_rooms_count', 'student_rooms_area',
            'playground_count', 'playground_area', 'training_ground_count', 'training_ground_area'
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

    /**
     * Lấy tên file export
     */
    protected function getExportFilename($year)
    {
        return 'bao_cao_co_so_vat_chat_tieu_hoc_' . ($year ?: 'all') . '.xlsx';
    }

    /**
     * Lấy prefix cho temp file
     */
    protected function getTempFilePrefix()
    {
        return 'primary_school_infrastructure_';
    }

    /**
     * Lấy cấu trúc cột theo cấp độ
     */
    protected function columns($level = 'province')
    {
        $baseColumns = [
            // Đơn vị (3 cột)
            'school_name' => 'Trường',
            'school_branch' => 'Điểm trường',
            'academic_year' => 'Năm học',
            
            // Số lớp (2 cột)
            'total_classes' => 'Tổng số',
            'combined_classes' => 'Lớp ghép',
            
            // Số phòng học (5 cột)
            'solid_classrooms' => 'Kiên cố',
            'semi_solid_classrooms' => 'Bán kiên cố',
            'temporary_classrooms' => 'Tạm',
            'rented_classrooms' => 'Thuê/mượn',
            'classroom_class_ratio' => 'Tỷ lệ Ph/L',
            
            // Số phòng chức năng (15 cột)
            'principal_rooms_count' => 'HT',
            'vice_principal_rooms_count' => 'Phó HT',
            'office_rooms_count' => 'VP',
            'medical_rooms_count' => 'Y tế',
            'youth_union_rooms_count' => 'TT HĐ Đội',
            'meeting_rooms_count' => 'Phòng họp (SL)',
            'meeting_rooms_area' => 'Phòng họp (DT)',
            'library_rooms_count' => 'Thư viện (SL)',
            'library_rooms_area' => 'Thư viện (DT)',
            'equipment_rooms_count' => 'Thiết bị (SL)',
            'equipment_rooms_area' => 'Thiết bị (DT)',
            'teacher_rooms_count' => 'Giáo viên (SL)',
            'teacher_rooms_area' => 'Giáo viên (DT)',
            'student_rooms_count' => 'Học sinh (SL)',
            'student_rooms_area' => 'Học sinh (DT)',
            
            // Sân chơi (2 cột)
            'playground_count' => 'Số lượng',
            'playground_area' => 'Diện tích',
            
            // Bãi tập (2 cột)
            'training_ground_count' => 'Số lượng',
            'training_ground_area' => 'Diện tích',
        ];

        // Điều chỉnh cột theo cấp độ
        if ($level === 'province') {
            // Cấp tỉnh: thay "Trường" bằng "Tên xã/phường", bỏ "Điểm trường"
            $baseColumns['school_name'] = 'Tên xã/phường';
            unset($baseColumns['school_branch']);
        } elseif ($level === 'ward') {
            // Cấp xã: hiển thị "Trường", bỏ "Điểm trường"
            unset($baseColumns['school_branch']);
        } else {
            // Cấp trường: bỏ "Trường" và "Điểm trường"
            unset($baseColumns['school_name']);
            unset($baseColumns['school_branch']);
        }

        return $baseColumns;
    }

    /**
     * Lấy nhóm cột
     */
    protected function columnGroups($level = 'province')
    {
        $groups = [
            'don-vi' => [
                'title' => 'Đơn vị',
                'colspan' => $level === 'province' ? 2 : ($level === 'ward' ? 2 : 1),
                'columns' => $level === 'province' ? 
                    ['school_name', 'academic_year'] : 
                    ($level === 'ward' ? 
                        ['school_name', 'academic_year'] : 
                        ['academic_year'])
            ],
            'so-lop' => [
                'title' => 'Số lớp',
                'colspan' => 2,
                'columns' => ['total_classes', 'combined_classes']
            ],
            'so-phong-hoc' => [
                'title' => 'Số phòng học',
                'colspan' => 5,
                'columns' => ['solid_classrooms', 'semi_solid_classrooms', 'temporary_classrooms', 'rented_classrooms', 'classroom_class_ratio']
            ],
            'so-phong-chuc-nang' => [
                'title' => 'Số phòng chức năng',
                'colspan' => 15,
                'columns' => [
                    'principal_rooms_count', 'vice_principal_rooms_count', 'office_rooms_count', 
                    'medical_rooms_count', 'youth_union_rooms_count', 'meeting_rooms_count', 
                    'meeting_rooms_area', 'library_rooms_count', 'library_rooms_area', 
                    'equipment_rooms_count', 'equipment_rooms_area', 'teacher_rooms_count', 
                    'teacher_rooms_area', 'student_rooms_count', 'student_rooms_area'
                ]
            ],
            'san-choi' => [
                'title' => 'Sân chơi',
                'colspan' => 2,
                'columns' => ['playground_count', 'playground_area']
            ],
            'bai-tap' => [
                'title' => 'Bãi tập',
                'colspan' => 2,
                'columns' => ['training_ground_count', 'training_ground_area']
            ]
        ];

        return $groups;
    }





    /**
     * Thống kê tổng hợp theo năm, xã, trường
     */
    protected function getStatistics($year = null, $wardId = null)
    {
        $query = PrimarySchoolInfrastructureReport::query()
            ->select([
                DB::raw('SUM(solid_classrooms + semi_solid_classrooms + temporary_classrooms + rented_classrooms) as total_phong_hoc'),
                DB::raw('AVG(classroom_class_ratio) as avg_ty_le_phong_lop'),
                DB::raw('SUM(solid_classrooms) as total_phong_kien_co'),
                DB::raw('SUM(meeting_rooms_count + library_rooms_count + equipment_rooms_count) as total_phong_chuc_nang'),
                'ward_id',
                'school_name',
            ])
            ->when($year, fn($q) => $q->where('academic_year', $year))
            ->when($wardId, fn($q) => $q->where('ward_id', $wardId))
            ->groupBy('ward_id', 'school_name');

        return $query->get()->groupBy('ward_id');
    }


    /**
     * Lưu dữ liệu cấp trường
     */
    public function saveSchoolData(Request $request)
    {
        try {
            // Validation rules cho PrimarySchoolInfrastructureReport
            $rules = [
                'academic_year' => 'required|string|regex:/^\d{4}-\d{4}$/',
                'school_id' => 'required|integer|min:1',
                'ward_id' => 'required|integer|min:1',
                'school_name' => 'nullable|string',
                'school_branch' => 'nullable|numeric|min:0',
                // Tất cả các trường cơ sở vật chất đều nullable
                'total_classes' => 'nullable|numeric|min:0',
                'combined_classes' => 'nullable|numeric|min:0',
                'solid_classrooms' => 'nullable|numeric|min:0',
                'semi_solid_classrooms' => 'nullable|numeric|min:0',
                'temporary_classrooms' => 'nullable|numeric|min:0',
                'rented_classrooms' => 'nullable|numeric|min:0',
                'classroom_class_ratio' => 'nullable|numeric|min:0',
                'principal_rooms_count' => 'nullable|numeric|min:0',
                'vice_principal_rooms_count' => 'nullable|numeric|min:0',
                'office_rooms_count' => 'nullable|numeric|min:0',
                'medical_rooms_count' => 'nullable|numeric|min:0',
                'youth_union_rooms_count' => 'nullable|numeric|min:0',
                'meeting_rooms_count' => 'nullable|numeric|min:0',
                'meeting_rooms_area' => 'nullable|numeric|min:0',
                'library_rooms_count' => 'nullable|numeric|min:0',
                'library_rooms_area' => 'nullable|numeric|min:0',
                'equipment_rooms_count' => 'nullable|numeric|min:0',
                'equipment_rooms_area' => 'nullable|numeric|min:0',
                'teacher_rooms_count' => 'nullable|numeric|min:0',
                'teacher_rooms_area' => 'nullable|numeric|min:0',
                'student_rooms_count' => 'nullable|numeric|min:0',
                'student_rooms_area' => 'nullable|numeric|min:0',
                'playground_count' => 'nullable|numeric|min:0',
                'playground_area' => 'nullable|numeric|min:0',
                'training_ground_count' => 'nullable|numeric|min:0',
                'training_ground_area' => 'nullable|numeric|min:0',
            ];

            $validated = $request->validate($rules);

            // Đảm bảo tất cả các trường numeric có giá trị mặc định là 0
            $numericFields = [
                'total_classes', 'combined_classes', 'solid_classrooms', 'semi_solid_classrooms',
                'temporary_classrooms', 'rented_classrooms', 'classroom_class_ratio',
                'principal_rooms_count', 'vice_principal_rooms_count', 'office_rooms_count',
                'medical_rooms_count', 'youth_union_rooms_count', 'meeting_rooms_count',
                'meeting_rooms_area', 'library_rooms_count', 'library_rooms_area',
                'equipment_rooms_count', 'equipment_rooms_area', 'teacher_rooms_count',
                'teacher_rooms_area', 'student_rooms_count', 'student_rooms_area',
                'playground_count', 'playground_area', 'training_ground_count', 'training_ground_area'
            ];

            foreach ($numericFields as $field) {
                $validated[$field] = $validated[$field] ?? 0;
            }

            // Lấy thông tin trường học
            $school = null;
            if (!empty($validated['school_id'])) {
                $school = \App\Models\School::find($validated['school_id']);
            }

            if ($school) {
                $validated['school_name'] = $school->name;
                $validated['school_branch'] = $validated['school_branch'] ?? '';
            } else {
                $validated['school_name'] = $validated['school_name'] ?? 'Tên trường không xác định';
                $validated['school_branch'] = $validated['school_branch'] ?? '';
            }

            // Lấy thông tin địa lý từ ward
            $ward = \App\Models\GeoUnit::with('parent.parent')->find($validated['ward_id']);
            if ($ward) {
                $validated['district_id'] = $ward->parent_id;
                $validated['province_id'] = $ward->parent && $ward->parent->parent_id ? $ward->parent->parent_id : null;
                
                // Nếu vẫn thiếu thông tin địa lý, thử lấy từ school
                if (!$validated['province_id'] && $school) {
                    $schoolWard = $school->ward;
                    if ($schoolWard) {
                        $validated['district_id'] = $schoolWard->parent_id;
                        $validated['province_id'] = $schoolWard->parent ? $schoolWard->parent->parent_id : null;
                    }
                }
                
                // Fallback: nếu vẫn không có, sử dụng giá trị mặc định
                if (!$validated['district_id']) {
                    $validated['district_id'] = 1; // ID mặc định cho huyện
                }
                if (!$validated['province_id']) {
                    $validated['province_id'] = 1; // ID mặc định cho tỉnh
                }
            } else {
                // Nếu không tìm thấy ward, sử dụng giá trị mặc định
                $validated['district_id'] = 1;
                $validated['province_id'] = 1;
            }

            // Tạo hoặc cập nhật báo cáo
            $report = PrimarySchoolInfrastructureReport::updateOrCreate(
                [
                    'academic_year' => $validated['academic_year'],
                    'school_id' => $validated['school_id'],
                    'ward_id' => $validated['ward_id'],
                ],
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Lưu dữ liệu thành công!',
                'data' => $report
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
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

        $user = Auth::user();
        $level = $this->reportService->determineDisplayLevel($user, $geoIds);
        
        // Lấy tất cả báo cáo cơ sở vật chất THCS trong xã
        $reports = $this->getReportsByLevelWithFilter($level, $year, '', $geoIds, 'old', 1000, $secondaryLevelIds);
        
        $templatePath = resource_path('excel/3.3.THCS.CoSoVatChat.xlsx');
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
        
        // Ghi ngày xuất file vào ô E3
        $currentDay = date('d');
        $currentMonth = date('m');
        $currentYear = date('Y');
        $worksheet->setCellValue('E3', 'Ngày ' . $currentDay . ' tháng ' . $currentMonth . ' năm ' . $currentYear);
        
        // In đậm tên tỉnh và tên xã
        $worksheet->getStyle('C2')->getFont()->setBold(true);
        $worksheet->getStyle('C3')->getFont()->setBold(true);
        
        // In đậm và in nghiêng ngày xuất file
        $worksheet->getStyle('E3')->getFont()->setBold(true);
        $worksheet->getStyle('E3')->getFont()->setItalic(true);

        // Format lại vùng A15:T17 về bình thường (cấu trúc ký tên)
        $this->resetSignatureAreaFormat($worksheet);

        // Cài đặt rows to repeat at top cho tiêu đề (dòng 6-9)
        $this->setupPrintTitles($worksheet);

        // Điền dữ liệu cho tất cả trường trong xã
        $this->fillSecondaryInfrastructureDataForWard($worksheet, $reports, $level, $wardName);

        $filename = 'bao_cao_co_so_vat_chat_thcs_xa_' . $wardName . '_' . $year . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'secondary_infrastructure_ward_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Export Excel theo mẫu template THCS - chỉ xuất trường đang chọn
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
            'school'   => (int) ($geoIn['school_id'] ?? 0) ?: null,
        ];

        // Lấy thông tin trường từ tài khoản đăng nhập (tài khoản cấp trường)
        $user = \Illuminate\Support\Facades\Auth::user();
        $school = $user->school;
        
        if (!$school) {
            return response()->json(['error' => 'Không tìm thấy thông tin trường của tài khoản'], 400);
        }

        // Kiểm tra trường có phải THCS không
        $secondaryLevelIds = $this->getSecondaryLevelIds();
        if (!in_array($school->educational_level_id, $secondaryLevelIds)) {
            return response()->json(['error' => 'Trường này không phải là trường THCS'], 400);
        }

        // Lấy dữ liệu chỉ của trường này
        $geoIds['school'] = $school->id;
        $geoIds['ward'] = $school->getWardId();
        $reports = $this->getSchoolLevelReportsWithFilter($year, '', $geoIds, 'old', 1000, $secondaryLevelIds);
        
        $templatePath = resource_path('excel/3.3.THCS.CoSoVatChat.xlsx');
        if (!file_exists($templatePath)) {
            return response()->json(['error' => 'Không tìm thấy file template Excel'], 400);
        }

        $spreadsheet = IOFactory::load($templatePath);
        $worksheet = $spreadsheet->getActiveSheet();

        // Lấy thông tin địa lý từ trường của tài khoản (hành chính mới)
        $wardNew = $school->wardNew; // Lấy từ ward_code_new với scheme = 'new'
        
        // Lấy tên tỉnh từ hành chính mới
        if ($wardNew && $wardNew->parent && $wardNew->parent->parent) {
            $provinceName = $wardNew->parent->parent->name;
        } else {
            // Fallback: lấy từ province_code_new trong bảng school
            if ($school->province_code_new) {
                $province = \App\Models\GeoUnit::where('code', $school->province_code_new)
                    ->where('scheme', 'new')
                    ->first();
                $provinceName = $province ? $province->name : '';
            } else {
                $provinceName = '';
            }
        }
        
        // Lấy tên xã từ hành chính mới
        if ($wardNew) {
            $wardName = $wardNew->name;
        } else {
            // Fallback: lấy từ ward_code_new trong bảng school
            if ($school->ward_code_new) {
                $ward = \App\Models\GeoUnit::where('code', $school->ward_code_new)
                    ->where('scheme', 'new')
                    ->first();
                $wardName = $ward ? $ward->name : '';
            } else {
                $wardName = '';
            }
        }

        // Điền thông tin tỉnh/thành và phường/xã
        $worksheet->setCellValue('C2', $provinceName);
        $worksheet->setCellValue('C3', $wardName);
        
        // Ghi ngày xuất file vào ô E3
        $currentDay = date('d');
        $currentMonth = date('m');
        $currentYear = date('Y');
        $worksheet->setCellValue('E3', 'Ngày ' . $currentDay . ' tháng ' . $currentMonth . ' năm ' . $currentYear);
        
        // In đậm tên tỉnh và tên xã
        $worksheet->getStyle('C2')->getFont()->setBold(true);
        $worksheet->getStyle('C3')->getFont()->setBold(true);
        
        // In đậm và in nghiêng ngày xuất file
        $worksheet->getStyle('E3')->getFont()->setBold(true);
        $worksheet->getStyle('E3')->getFont()->setItalic(true);

        $this->fillSecondaryInfrastructureData($worksheet, $reports, 'school', $wardName);

        $filename = 'bao_cao_co_so_vat_chat_' . $school->name . '_' . $year . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'secondary_infrastructure_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Nhập dữ liệu từ Excel
     */
    public function importExcel(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
                'academic_year' => 'required|string|regex:/^\d{4}-\d{4}$/',
            ]);

            $user = Auth::user();
            $school = $user->school;
            
            if (!$school) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy thông tin trường của tài khoản'], 400);
            }

            // Kiểm tra trường có phải THCS không
            $secondaryLevelIds = $this->getSecondaryLevelIds();
            if (!in_array($school->educational_level_id, $secondaryLevelIds)) {
                return response()->json(['success' => false, 'message' => 'Trường này không phải là trường THCS'], 400);
            }

            $file = $request->file('excel_file');
            $academicYear = $request->input('academic_year');
            
            // Validate năm học format
            if (!$academicYear || !preg_match('/^\d{4}-\d{4}$/', $academicYear)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Năm học không hợp lệ. Vui lòng chọn năm học đúng định dạng.'
                ], 400);
            }
            
            // Đọc file Excel
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Đọc dữ liệu từ dòng 9, cột D đến Y
            $row = 9;
            $data = [];
            
            // Cột D: Số điểm trường
            $data['school_branch'] = $worksheet->getCell('D' . $row)->getCalculatedValue() ?? 0;
            
            // Cột E: Số lớp
            $data['total_classes'] = $worksheet->getCell('E' . $row)->getCalculatedValue() ?? 0;
            
            // Cột F: Số phòng học - Kiên cố
            $data['solid_classrooms'] = $worksheet->getCell('F' . $row)->getCalculatedValue() ?? 0;
            
            // Cột G: Số phòng học - Bán kiên cố
            $data['semi_solid_classrooms'] = $worksheet->getCell('G' . $row)->getCalculatedValue() ?? 0;
            
            // Cột H: Số phòng học - Tạm
            $data['temporary_classrooms'] = $worksheet->getCell('H' . $row)->getCalculatedValue() ?? 0;
            
            // Cột I: Tỷ lệ Ph/L
            $data['classroom_class_ratio'] = $worksheet->getCell('I' . $row)->getCalculatedValue() ?? 0;
            
            // Cột J: HT
            $data['principal_rooms_count'] = $worksheet->getCell('J' . $row)->getCalculatedValue() ?? 0;
            
            // Cột K: Phó HT
            $data['vice_principal_rooms_count'] = $worksheet->getCell('K' . $row)->getCalculatedValue() ?? 0;
            
            // Cột L: VP
            $data['office_rooms_count'] = $worksheet->getCell('L' . $row)->getCalculatedValue() ?? 0;
            
            // Cột M: Y tế
            $data['medical_rooms_count'] = $worksheet->getCell('M' . $row)->getCalculatedValue() ?? 0;
            
            // Cột N: Phòng họp
            $data['meeting_rooms_count'] = $worksheet->getCell('N' . $row)->getCalculatedValue() ?? 0;
            
            // Cột O: Thư viện
            $data['library_rooms_count'] = $worksheet->getCell('O' . $row)->getCalculatedValue() ?? 0;
            
            // Cột P: Phòng thí nghiệm - Số lượng
            $data['equipment_rooms_count'] = $worksheet->getCell('P' . $row)->getCalculatedValue() ?? 0;
            
            // Cột Q: Phòng thí nghiệm - Diện tích
            $data['equipment_rooms_area'] = $worksheet->getCell('Q' . $row)->getCalculatedValue() ?? 0;
            
            // Cột R: Công trình vệ sinh - Giáo viên - Số lượng
            $data['teacher_rooms_count'] = $worksheet->getCell('R' . $row)->getCalculatedValue() ?? 0;
            
            // Cột S: Công trình vệ sinh - Giáo viên - Diện tích
            $data['teacher_rooms_area'] = $worksheet->getCell('S' . $row)->getCalculatedValue() ?? 0;
            
            // Cột T: Công trình vệ sinh - Học sinh - Số lượng
            $data['student_rooms_count'] = $worksheet->getCell('T' . $row)->getCalculatedValue() ?? 0;
            
            // Cột U: Công trình vệ sinh - Học sinh - Diện tích
            $data['student_rooms_area'] = $worksheet->getCell('U' . $row)->getCalculatedValue() ?? 0;
            
            // Cột V: Sân chơi - Số lượng
            $data['playground_count'] = $worksheet->getCell('V' . $row)->getCalculatedValue() ?? 0;
            
            // Cột W: Sân chơi - Diện tích
            $data['playground_area'] = $worksheet->getCell('W' . $row)->getCalculatedValue() ?? 0;
            
            // Cột X: Bãi tập - Số lượng
            $data['training_ground_count'] = $worksheet->getCell('X' . $row)->getCalculatedValue() ?? 0;
            
            // Cột Y: Bãi tập - Diện tích
            $data['training_ground_area'] = $worksheet->getCell('Y' . $row)->getCalculatedValue() ?? 0;
            
            // Thêm thông tin bắt buộc
            $data['school_id'] = $school->id;
            $data['ward_id'] = $school->getWardId();
            $data['academic_year'] = $academicYear;
            $data['school_name'] = $school->name;
            
            // Tìm hoặc tạo bản ghi
            $report = PrimarySchoolInfrastructureReport::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'academic_year' => $academicYear,
                ],
                $data
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Nhập dữ liệu thành công!',
                'data' => $report
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi nhập dữ liệu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Điền dữ liệu cơ sở vật chất THCS vào template
     */
    private function fillSecondaryInfrastructureData($worksheet, $reports, $level, $wardName)
    {
        $startRow = 9; // Bắt đầu từ dòng 9
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
                $schoolName = $report->ward_name ?? '';
            } else {
                $schoolName = $report->school_name ?? '';
            }
            
            // Cột B,C: Đơn vị (tên trường) - merge 2 cột
            $worksheet->setCellValue('B' . $currentRow, $schoolName);
            $worksheet->mergeCells('B' . $currentRow . ':C' . $currentRow);
            
            // Cột D: Số điểm trường
            $schoolBranchValue = is_numeric($report->school_branch) ? (float)$report->school_branch : 0;
            $worksheet->setCellValue('D' . $currentRow, $schoolBranchValue);
            
            // Cột E: Số lớp - Tổng số
            $worksheet->setCellValue('E' . $currentRow, $report->total_classes ?? 0);
            
            // Cột F: Số phòng học - Kiên cố
            $worksheet->setCellValue('F' . $currentRow, $report->solid_classrooms ?? 0);
            
            // Cột G: Số phòng học - Bán kiên cố
            $worksheet->setCellValue('G' . $currentRow, $report->semi_solid_classrooms ?? 0);
            
            // Cột H: Số phòng học - Tạm
            $worksheet->setCellValue('H' . $currentRow, $report->temporary_classrooms ?? 0);
            
            // Cột I: Số phòng học - Tỷ lệ Ph/L (format 2 chữ số thập phân)
            $ratioValue = $report->classroom_class_ratio ?? 0;
            $worksheet->setCellValue('I' . $currentRow, round($ratioValue, 2));
            
            // Cột J: Số phòng học - HT
            $worksheet->setCellValue('J' . $currentRow, $report->principal_rooms_count ?? 0);
            
            // Cột K: Số phòng học - P.HT
            $worksheet->setCellValue('K' . $currentRow, $report->vice_principal_rooms_count ?? 0);
            
            // Cột L: Phòng chức năng - VP
            $worksheet->setCellValue('L' . $currentRow, $report->office_rooms_count ?? 0);
            
            // Cột M: Phòng chức năng - Y tế
            $worksheet->setCellValue('M' . $currentRow, $report->medical_rooms_count ?? 0);
            
            // Cột N: Phòng chức năng - Phòng họp
            $worksheet->setCellValue('N' . $currentRow, $report->meeting_rooms_count ?? 0);
            
            // Cột O: Phòng chức năng - Thư viện
            $worksheet->setCellValue('O' . $currentRow, $report->library_rooms_count ?? 0);
            
            // Cột P: Phòng thí nghiệm - Số lượng
            $worksheet->setCellValue('P' . $currentRow, $report->equipment_rooms_count ?? 0);
            
            // Cột Q: Phòng thí nghiệm - Diện tích
            $worksheet->setCellValue('Q' . $currentRow, $report->equipment_rooms_area ?? 0);
            
            // Cột R: Công trình vệ sinh - Giáo viên - Số lượng
            $worksheet->setCellValue('R' . $currentRow, $report->teacher_rooms_count ?? 0);
            
            // Cột S: Công trình vệ sinh - Giáo viên - Diện tích
            $worksheet->setCellValue('S' . $currentRow, $report->teacher_rooms_area ?? 0);
            
            // Cột T: Công trình vệ sinh - Học sinh - Số lượng
            $worksheet->setCellValue('T' . $currentRow, $report->student_rooms_count ?? 0);
            
            // Cột U: Công trình vệ sinh - Học sinh - Diện tích
            $worksheet->setCellValue('U' . $currentRow, $report->student_rooms_area ?? 0);
            
            // Cột V: Sân chơi - Số lượng
            $worksheet->setCellValue('V' . $currentRow, $report->playground_count ?? 0);
            
            // Cột W: Sân chơi - Diện tích
            $worksheet->setCellValue('W' . $currentRow, $report->playground_area ?? 0);
            
            // Cột X: Bãi tập - Số lượng
            $worksheet->setCellValue('X' . $currentRow, $report->training_ground_count ?? 0);
            
            // Cột Y: Bãi tập - Diện tích
            $worksheet->setCellValue('Y' . $currentRow, $report->training_ground_area ?? 0);
            
            $this->setConsistentFont($worksheet, $currentRow);
            $dataRows[] = $report;
            $currentRow++;
        }

        // Chỉ thêm dòng tổng cộng cho cấp xã, không thêm cho cấp trường
        if (!empty($dataRows) && $level !== 'school') {
            $this->addTotalRow($worksheet, $currentRow, $dataRows, $level, $wardName);
        }
    }

    /**
     * Thêm dòng tổng cộng
     */
    private function addTotalRow($worksheet, $row, $dataRows, $level, $wardName)
    {
        // Cột TT để trống
        $worksheet->setCellValue('A' . $row, '');
        
        $worksheet->setCellValue('B' . $row, 'TỔNG CỘNG');
        $worksheet->mergeCells('B' . $row . ':C' . $row);
        
        // In đậm toàn bộ dòng tổng cộng
        $this->setBoldTotalRowFont($worksheet, $row);

        $totals = $this->calculateWardTotals(collect($dataRows));

        // Cột D: Số điểm trường - tính tổng
        $totalSchoolBranch = collect($dataRows)->sum(function($report) {
            return is_numeric($report->school_branch) ? (float)$report->school_branch : 0;
        });
        $worksheet->setCellValue('D' . $row, $totalSchoolBranch);
        
        // Cột E: Số lớp - Tổng số
        $worksheet->setCellValue('E' . $row, $totals->total_classes ?? 0);
        
        // Cột F: Số phòng học - Kiên cố
        $worksheet->setCellValue('F' . $row, $totals->solid_classrooms ?? 0);
        
        // Cột G: Số phòng học - Bán kiên cố
        $worksheet->setCellValue('G' . $row, $totals->semi_solid_classrooms ?? 0);
        
        // Cột H: Số phòng học - Tạm
        $worksheet->setCellValue('H' . $row, $totals->temporary_classrooms ?? 0);
        
        // Cột I: Số phòng học - Tỷ lệ Ph/L (tính trung bình, format 2 chữ số thập phân)
        $avgRatio = $totals->classroom_class_ratio ?? 0;
        $worksheet->setCellValue('I' . $row, round($avgRatio, 2));
        
        // Cột J: Số phòng học - HT
        $worksheet->setCellValue('J' . $row, $totals->principal_rooms_count ?? 0);
        
        // Cột K: Số phòng học - P.HT
        $worksheet->setCellValue('K' . $row, $totals->vice_principal_rooms_count ?? 0);
        
        // Cột L: Phòng chức năng - VP
        $worksheet->setCellValue('L' . $row, $totals->office_rooms_count ?? 0);
        
        // Cột M: Phòng chức năng - Y tế
        $worksheet->setCellValue('M' . $row, $totals->medical_rooms_count ?? 0);
        
        // Cột N: Phòng chức năng - Phòng họp
        $worksheet->setCellValue('N' . $row, $totals->meeting_rooms_count ?? 0);
        
        // Cột O: Phòng chức năng - Thư viện
        $worksheet->setCellValue('O' . $row, $totals->library_rooms_count ?? 0);
        
        // Cột P: Phòng thí nghiệm - Số lượng
        $worksheet->setCellValue('P' . $row, $totals->equipment_rooms_count ?? 0);
        
        // Cột Q: Phòng thí nghiệm - Diện tích
        $worksheet->setCellValue('Q' . $row, $totals->equipment_rooms_area ?? 0);
        
        // Cột R: Công trình vệ sinh - Giáo viên - Số lượng
        $worksheet->setCellValue('R' . $row, $totals->teacher_rooms_count ?? 0);
        
        // Cột S: Công trình vệ sinh - Giáo viên - Diện tích
        $worksheet->setCellValue('S' . $row, $totals->teacher_rooms_area ?? 0);
        
        // Cột T: Công trình vệ sinh - Học sinh - Số lượng
        $worksheet->setCellValue('T' . $row, $totals->student_rooms_count ?? 0);
        
        // Cột U: Công trình vệ sinh - Học sinh - Diện tích
        $worksheet->setCellValue('U' . $row, $totals->student_rooms_area ?? 0);
        
        // Cột V: Sân chơi - Số lượng
        $worksheet->setCellValue('V' . $row, $totals->playground_count ?? 0);
        
        // Cột W: Sân chơi - Diện tích
        $worksheet->setCellValue('W' . $row, $totals->playground_area ?? 0);
        
        // Cột X: Bãi tập - Số lượng
        $worksheet->setCellValue('X' . $row, $totals->training_ground_count ?? 0);
        
        // Cột Y: Bãi tập - Diện tích
        $worksheet->setCellValue('Y' . $row, $totals->training_ground_area ?? 0);
        
        // Thêm phần ký tên từ dòng 12 đến 15
        $this->addSignatureSectionForSchool($worksheet, $wardName);
    }

    /**
     * Thêm phần ký tên cho báo cáo trường THCS (dòng 12-15)
     */
    private function addSignatureSectionForSchool($worksheet, $wardName)
    {
        // Tên xã, ngày ... tháng ... năm ... vào ô T12
        $dateRow = 12;
        $worksheet->setCellValue('T' . $dateRow, $wardName . ', ngày ... tháng ... năm ...');
        
        // 'NGƯỜI LẬP BIỂU' vào ô F13, in đậm
        $signatureRow1 = 13;
        $worksheet->setCellValue('F' . $signatureRow1, 'NGƯỜI LẬP BIỂU');
        $worksheet->getStyle('F' . $signatureRow1)->getFont()->setBold(true);
        
        // 'TM. BAN CHỈ ĐẠO PCGD, XMC' vào ô T13, in đậm
        $worksheet->setCellValue('T' . $signatureRow1, 'TM. BAN CHỈ ĐẠO PCGD, XMC');
        $worksheet->getStyle('T' . $signatureRow1)->getFont()->setBold(true);
        
        // '(Ký và ghi rõ họ tên)' vào ô F14, in nghiêng
        $signatureRow2 = 14;
        $worksheet->setCellValue('F' . $signatureRow2, '(Ký và ghi rõ họ tên)');
        $worksheet->getStyle('F' . $signatureRow2)->getFont()->setItalic(true);
        
        // 'TRƯỞNG BAN' vào ô T14, in đậm
        $worksheet->setCellValue('T' . $signatureRow2, 'TRƯỞNG BAN');
        $worksheet->getStyle('T' . $signatureRow2)->getFont()->setBold(true);
        
        // '(Ký tên, đóng dấu)' vào ô T15, in nghiêng
        $signatureRow3 = 15;
        $worksheet->setCellValue('T' . $signatureRow3, '(Ký tên, đóng dấu)');
        $worksheet->getStyle('T' . $signatureRow3)->getFont()->setItalic(true);
        
        // Đặt font mặc định cho tất cả các ô ký tên
        $signatureRange = 'F' . $signatureRow1 . ':T' . $signatureRow3;
        $worksheet->getStyle($signatureRange)->getFont()->setName('Tahoma');
        $worksheet->getStyle($signatureRange)->getFont()->setSize(8);
        
        // Đặt chiều cao dòng và căn giữa cho các dòng ký tên (dòng 12-15)
        for ($row = $dateRow; $row <= $signatureRow3; $row++) {
            $worksheet->getRowDimension($row)->setRowHeight(18.60);
            $worksheet->getStyle('F' . $row . ':T' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $worksheet->getStyle('F' . $row . ':T' . $row)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }
    }

    /**
     * Đặt font in đậm cho dòng tổng cộng
     */
    private function setBoldTotalRowFont($worksheet, $row)
    {
        // Đặt font in đậm cho toàn bộ dòng (A đến Y)
        $range = 'A' . $row . ':Y' . $row;
        $style = $worksheet->getStyle($range);
        
        // Font settings - in đậm
        $style->getFont()->setBold(true);
        $style->getFont()->setItalic(false);
        $style->getFont()->setName('Tahoma');
        $style->getFont()->setSize(8);
        
        // Đặt căn lề nhất quán
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        
        // Đặt padding cho các dòng từ dòng 9 trở đi
        if ($row >= 9) {
            $style->getAlignment()->setIndent(0);
            $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        // Xử lý từng cột cụ thể
        $columns = [
            'A' => 'center',    // TT - căn giữa
            'B' => 'left',      // Đơn vị - căn trái (merge B-C)
            'C' => 'left',      // Đơn vị (merged) - căn trái
            'D' => 'center',    // Số điểm trường - căn giữa
            'E' => 'center',    // Số liệu - căn giữa
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
            'Y' => 'center'     // Cột cuối - căn giữa
        ];
        
        foreach ($columns as $col => $alignment) {
            $cellStyle = $worksheet->getStyle($col . $row);
            
            // Font settings - in đậm
            $cellStyle->getFont()->setBold(true);
            $cellStyle->getFont()->setItalic(false);
            $cellStyle->getFont()->setName('Tahoma');
            $cellStyle->getFont()->setSize(8);
            
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
            if ($col === 'I') {
                // Cột I: Tỷ lệ Ph/L - format thập phân 2 chữ số + dấu phẩy phân cách hàng nghìn
                $cellStyle->getNumberFormat()->setFormatCode('#,##0.00');
            } elseif (in_array($col, ['Q', 'S', 'U', 'W', 'Y'])) {
                // Các cột diện tích: Phòng thí nghiệm, Giáo viên, Học sinh, Sân chơi, Bãi tập
                // Format thập phân 2 chữ số + dấu phẩy phân cách hàng nghìn
                $cellStyle->getNumberFormat()->setFormatCode('#,##0.00');
            } elseif (in_array($col, ['E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'T', 'V', 'X'])) {
                // Các cột số khác - format số nguyên
                $cellStyle->getNumberFormat()->setFormatCode('0');
            }
        }
    }

    /**
     * Đặt font và căn lề nhất quán cho dòng
     */
    private function setConsistentFont($worksheet, $row)
    {
        // Đặt font mặc định cho toàn bộ dòng (A đến Y)
        $range = 'A' . $row . ':Y' . $row;
        $style = $worksheet->getStyle($range);
        
        // Reset font về mặc định
        $style->getFont()->setBold(false);
        $style->getFont()->setItalic(false);
        $style->getFont()->setName('Tahoma');
        $style->getFont()->setSize(8);
        
        // Đặt căn lề nhất quán
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        
        // Đặt padding cho các dòng từ dòng 9 trở đi
        if ($row >= 9) {
            $style->getAlignment()->setIndent(0);
            $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        // Xử lý từng cột cụ thể
        $columns = [
            'A' => 'center',    // TT - căn giữa
            'B' => 'left',      // Đơn vị - căn trái (merge B-C)
            'C' => 'left',      // Đơn vị (merged) - căn trái
            'D' => 'center',    // Số điểm trường - căn giữa
            'E' => 'center',    // Số liệu - căn giữa
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
            'Y' => 'center'     // Cột cuối - căn giữa
        ];
        
        foreach ($columns as $col => $alignment) {
            $cellStyle = $worksheet->getStyle($col . $row);
            
            // Font settings
            $cellStyle->getFont()->setBold(false);
            $cellStyle->getFont()->setItalic(false);
            $cellStyle->getFont()->setName('Tahoma');
            $cellStyle->getFont()->setSize(8);
            
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
            if ($col === 'I') {
                // Cột I: Tỷ lệ Ph/L - format thập phân 2 chữ số + dấu phẩy phân cách hàng nghìn
                $cellStyle->getNumberFormat()->setFormatCode('#,##0.00');
            } elseif (in_array($col, ['Q', 'S', 'U', 'W', 'Y'])) {
                // Các cột diện tích: Phòng thí nghiệm, Giáo viên, Học sinh, Sân chơi, Bãi tập
                // Format thập phân 2 chữ số + dấu phẩy phân cách hàng nghìn
                $cellStyle->getNumberFormat()->setFormatCode('#,##0.00');
            } elseif (in_array($col, ['E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'T', 'V', 'X'])) {
                // Các cột số khác - format số nguyên
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
     * Tính tổng cộng cho cấp xã
     */
    private function calculateWardTotals($reports)
    {
        $total = new $this->modelClass;
        $total->school_name = 'TỔNG CỘNG';
        $total->school_rank = 0;
        
        // Tính tổng điểm trường
        $total->school_branch = $reports->sum(function($report) {
            if (isset($report->school_branch)) {
                $schoolBranchStr = trim($report->school_branch);
                if (is_numeric($schoolBranchStr) && $schoolBranchStr != 0) {
                    return (float)$schoolBranchStr;
                }
            }
            return 0;
        });
        
        // Tính tổng cho tất cả các trường số
        $reportFields = $this->getReportFields();
        foreach ($reportFields as $field) {
            if (in_array($field, ['classroom_class_ratio'])) {
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
     * Điền dữ liệu cơ sở vật chất THCS cho tất cả trường trong xã
     */
    private function fillSecondaryInfrastructureDataForWard($worksheet, $reports, $level, $wardName)
    {
        $startRow = 9; // Bắt đầu từ dòng 9
        $currentRow = $startRow;
        $stt = 1;
        $dataRows = [];

        foreach ($reports as $report) {
            if (isset($report->school_name) && $report->school_name === 'TỔNG CỘNG') {
                continue;
            }

            $worksheet->setCellValue('A' . $currentRow, $stt++);
            
            // Tên trường
            $schoolName = $report->school_name ?? '';
            $worksheet->setCellValue('B' . $currentRow, $schoolName);
            $worksheet->mergeCells('B' . $currentRow . ':C' . $currentRow);
            
            // Điểm trường
            $schoolBranchValue = '';
            if (isset($report->school_branch)) {
                $schoolBranchStr = trim($report->school_branch);
                if (is_numeric($schoolBranchStr) && $schoolBranchStr != 0) {
                    $schoolBranchValue = (float)$schoolBranchStr;
                }
            }
            $worksheet->setCellValue('D' . $currentRow, $schoolBranchValue);
            
            // Format cột D là Number cho tất cả các ô
            $worksheet->getStyle('D' . $currentRow)->getNumberFormat()->setFormatCode('0');
            
            // Số lớp - Tổng số
            $worksheet->setCellValue('E' . $currentRow, $report->total_classes ?: '');
            
            // Số phòng học - Kiên cố
            $worksheet->setCellValue('F' . $currentRow, $report->solid_classrooms ?: '');
            
            // Số phòng học - Bán kiên cố
            $worksheet->setCellValue('G' . $currentRow, $report->semi_solid_classrooms ?: '');
            
            // Số phòng học - Tạm
            $worksheet->setCellValue('H' . $currentRow, $report->temporary_classrooms ?: '');
            
            // Số phòng học - Tỷ lệ Ph/L
            $ratioValue = '';
            if (isset($report->classroom_class_ratio) && is_numeric($report->classroom_class_ratio) && $report->classroom_class_ratio != 0) {
                $ratioValue = round($report->classroom_class_ratio, 2);
            }
            $worksheet->setCellValue('I' . $currentRow, $ratioValue);
            
            // Số phòng chức năng - HT
            $worksheet->setCellValue('J' . $currentRow, $report->principal_rooms_count ?: '');
            
            // Số phòng chức năng - Phó HT
            $worksheet->setCellValue('K' . $currentRow, $report->vice_principal_rooms_count ?: '');
            
            // Số phòng chức năng - VP
            $worksheet->setCellValue('L' . $currentRow, $report->office_rooms_count ?: '');
            
            // Số phòng chức năng - Y tế
            $worksheet->setCellValue('M' . $currentRow, $report->medical_rooms_count ?: '');
            
            // Phòng họp
            $worksheet->setCellValue('N' . $currentRow, $report->meeting_rooms_count ?: '');
            
            // Thư viện
            $worksheet->setCellValue('O' . $currentRow, $report->library_rooms_count ?: '');
            
            // Phòng thí nghiệm - SL
            $worksheet->setCellValue('P' . $currentRow, $report->equipment_rooms_count ?: '');
            
            // Phòng thí nghiệm - DT
            $worksheet->setCellValue('Q' . $currentRow, $report->equipment_rooms_area ?: '');
            
            // Công trình vệ sinh - Giáo viên - SL
            $worksheet->setCellValue('R' . $currentRow, $report->teacher_rooms_count ?: '');
            
            // Công trình vệ sinh - Giáo viên - DT
            $worksheet->setCellValue('S' . $currentRow, $report->teacher_rooms_area ?: '');
            
            // Công trình vệ sinh - Học sinh - SL
            $worksheet->setCellValue('T' . $currentRow, $report->student_rooms_count ?: '');
            
            // Công trình vệ sinh - Học sinh - DT
            $worksheet->setCellValue('U' . $currentRow, $report->student_rooms_area ?: '');
            
            // Sân chơi - Số lượng
            $worksheet->setCellValue('V' . $currentRow, $report->playground_count ?: '');
            
            // Sân chơi - Diện tích
            $worksheet->setCellValue('W' . $currentRow, $report->playground_area ?: '');
            
            // Bãi tập - Số lượng
            $worksheet->setCellValue('X' . $currentRow, $report->training_ground_count ?: '');
            
            // Bãi tập - Diện tích
            $worksheet->setCellValue('Y' . $currentRow, $report->training_ground_area ?: '');

            $this->setConsistentFont($worksheet, $currentRow);
            $dataRows[] = $report;
            $currentRow++;
        }

        if (!empty($dataRows)) {
            $totalRow = $this->addTotalRowForWard($worksheet, $currentRow, $dataRows, $level);
            // Thêm phần ký tên sau dòng tổng cộng
            $this->addSignatureSection($worksheet, $totalRow, $wardName);
        }
    }

    /**
     * Thêm dòng tổng cộng cho báo cáo xã
     */
    private function addTotalRowForWard($worksheet, $currentRow, $dataRows, $level)
    {
        // Cột A để trống
        $worksheet->setCellValue('A' . $currentRow, '');
        
        $worksheet->setCellValue('B' . $currentRow, 'TỔNG CỘNG');
        $worksheet->mergeCells('B' . $currentRow . ':C' . $currentRow);
        
        // Căn trái cho chữ "TỔNG CỘNG"
        $worksheet->getStyle('B' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        
        $this->setBoldTotalRowFont($worksheet, $currentRow);

        $totals = $this->calculateWardTotals(collect($dataRows));

        // Điểm trường - tính tổng riêng, không dùng calculateWardTotals
        $totalSchoolBranch = collect($dataRows)->sum(function($report) {
            if (isset($report->school_branch)) {
                $schoolBranchStr = trim($report->school_branch);
                if (is_numeric($schoolBranchStr) && $schoolBranchStr != 0) {
                    return (float)$schoolBranchStr;
                }
            }
            return 0;
        });
        $worksheet->setCellValue('D' . $currentRow, $totalSchoolBranch > 0 ? $totalSchoolBranch : '');
        
        // Format cột D là Number cho dòng tổng cộng
        $worksheet->getStyle('D' . $currentRow)->getNumberFormat()->setFormatCode('0');
        
        // Số lớp - Tổng số
        $worksheet->setCellValue('E' . $currentRow, $totals->total_classes ?? 0);
        
        // Số phòng học - Kiên cố
        $worksheet->setCellValue('F' . $currentRow, $totals->solid_classrooms ?? 0);
        
        // Số phòng học - Bán kiên cố
        $worksheet->setCellValue('G' . $currentRow, $totals->semi_solid_classrooms ?? 0);
        
        // Số phòng học - Tạm
        $worksheet->setCellValue('H' . $currentRow, $totals->temporary_classrooms ?? 0);
        
        // Số phòng học - Tỷ lệ Ph/L (tính trung bình)
        $worksheet->setCellValue('I' . $currentRow, $totals->classroom_class_ratio ?? 0);
        
        // Số phòng chức năng - HT
        $worksheet->setCellValue('J' . $currentRow, $totals->principal_rooms_count ?? 0);
        
        // Số phòng chức năng - Phó HT
        $worksheet->setCellValue('K' . $currentRow, $totals->vice_principal_rooms_count ?? 0);
        
        // Số phòng chức năng - VP
        $worksheet->setCellValue('L' . $currentRow, $totals->office_rooms_count ?? 0);
        
        // Số phòng chức năng - Y tế
        $worksheet->setCellValue('M' . $currentRow, $totals->medical_rooms_count ?? 0);
        
        // Phòng họp
        $worksheet->setCellValue('N' . $currentRow, $totals->meeting_rooms_count ?? 0);
        
        // Thư viện
        $worksheet->setCellValue('O' . $currentRow, $totals->library_rooms_count ?? 0);
        
        // Phòng thí nghiệm - SL
        $worksheet->setCellValue('P' . $currentRow, $totals->equipment_rooms_count ?? 0);
        
        // Phòng thí nghiệm - DT
        $worksheet->setCellValue('Q' . $currentRow, $totals->equipment_rooms_area ?? 0);
        
        // Công trình vệ sinh - Giáo viên - SL
        $worksheet->setCellValue('R' . $currentRow, $totals->teacher_rooms_count ?? 0);
        
        // Công trình vệ sinh - Giáo viên - DT
        $worksheet->setCellValue('S' . $currentRow, $totals->teacher_rooms_area ?? 0);
        
        // Công trình vệ sinh - Học sinh - SL
        $worksheet->setCellValue('T' . $currentRow, $totals->student_rooms_count ?? 0);
        
        // Công trình vệ sinh - Học sinh - DT
        $worksheet->setCellValue('U' . $currentRow, $totals->student_rooms_area ?? 0);
        
        // Sân chơi - Số lượng
        $worksheet->setCellValue('V' . $currentRow, $totals->playground_count ?? 0);
        
        // Sân chơi - Diện tích
        $worksheet->setCellValue('W' . $currentRow, $totals->playground_area ?? 0);
        
        // Bãi tập - Số lượng
        $worksheet->setCellValue('X' . $currentRow, $totals->training_ground_count ?? 0);
        
        // Bãi tập - Diện tích
        $worksheet->setCellValue('Y' . $currentRow, $totals->training_ground_area ?? 0);
        
        return $currentRow;
    }

    /**
     * Cài đặt rows to repeat at top cho tiêu đề
     */
    private function setupPrintTitles($worksheet)
    {
        // Cài đặt rows to repeat at top cho dòng 6-9 (tiêu đề)
        $worksheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(6, 9);
    }

    /**
     * Thêm phần ký tên vào cuối báo cáo
     */
    private function addSignatureSection($worksheet, $totalRow, $wardName)
    {
        // Tên xã, ngày ... tháng ... năm ... vào ô T(x+2)
        $dateRow = $totalRow + 2;
        $worksheet->setCellValue('T' . $dateRow, $wardName . ', ngày ... tháng ... năm ...');
        
        // 'NGƯỜI LẬP BIỂU' vào ô F(x+3), in đậm
        $signatureRow1 = $totalRow + 3;
        $worksheet->setCellValue('F' . $signatureRow1, 'NGƯỜI LẬP BIỂU');
        $worksheet->getStyle('F' . $signatureRow1)->getFont()->setBold(true);
        
        // 'TM. BAN CHỈ ĐẠO PCGD, XMC' vào ô T(x+3), in đậm
        $worksheet->setCellValue('T' . $signatureRow1, 'TM. BAN CHỈ ĐẠO PCGD, XMC');
        $worksheet->getStyle('T' . $signatureRow1)->getFont()->setBold(true);
        
        // '(Ký và ghi rõ họ tên)' vào ô F(x+4), in nghiêng
        $signatureRow2 = $totalRow + 4;
        $worksheet->setCellValue('F' . $signatureRow2, '(Ký và ghi rõ họ tên)');
        $worksheet->getStyle('F' . $signatureRow2)->getFont()->setItalic(true);
        
        // 'TRƯỞNG BAN' vào ô T(x+4), in đậm
        $worksheet->setCellValue('T' . $signatureRow2, 'TRƯỞNG BAN');
        $worksheet->getStyle('T' . $signatureRow2)->getFont()->setBold(true);
        
        // '(Ký tên, đóng dấu)' vào ô T(x+5), in nghiêng
        $signatureRow3 = $totalRow + 5;
        $worksheet->setCellValue('T' . $signatureRow3, '(Ký tên, đóng dấu)');
        $worksheet->getStyle('T' . $signatureRow3)->getFont()->setItalic(true);
        
        // Đặt font mặc định cho tất cả các ô ký tên
        $signatureRange = 'F' . $signatureRow1 . ':T' . $signatureRow3;
        $worksheet->getStyle($signatureRange)->getFont()->setName('Tahoma');
        $worksheet->getStyle($signatureRange)->getFont()->setSize(8);
        
        // Đặt chiều cao dòng và căn giữa cho các dòng ký tên
        for ($row = $dateRow; $row <= $signatureRow3; $row++) {
            $worksheet->getRowDimension($row)->setRowHeight(18.60);
            $worksheet->getStyle('F' . $row . ':T' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $worksheet->getStyle('F' . $row . ':T' . $row)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }
    }

    /**
     * Reset format vùng ký tên (A15:T17) về bình thường
     */
    private function resetSignatureAreaFormat($worksheet)
    {
        // Reset format cho vùng A15:T17
        $signatureRange = 'A15:T17';
        $style = $worksheet->getStyle($signatureRange);
        
        // Reset font về bình thường
        $style->getFont()->setBold(false);
        $style->getFont()->setItalic(false);
        $style->getFont()->setName('Tahoma');
        $style->getFont()->setSize(8);
        
        // Reset alignment về mặc định
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $style->getAlignment()->setIndent(0);
        
        // Reset borders về mặc định
        $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_NONE);
        
        // Reset number format về text
        $style->getNumberFormat()->setFormatCode('@');
        
        // Xóa nội dung các ô trong vùng này để giữ nguyên cấu trúc ký tên
        for ($row = 15; $row <= 17; $row++) {
            for ($col = 'A'; $col <= 'T'; $col++) {
                $cell = $col . $row;
                $worksheet->setCellValue($cell, '');
            }
        }
    }
}
