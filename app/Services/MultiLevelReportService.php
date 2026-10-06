<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Service tái sử dụng cho multi-level reporting (tỉnh, xã, trường)
 */
class MultiLevelReportService
{
    protected $modelClass;
    protected $modelInstance;
    protected $schoolModel;
    protected $geoModel;
    protected $wardCodeField;
    protected $reportFields;
    protected $fillableFields;

    public function __construct($modelClass, $schoolModel = 'App\Models\School', $geoModel = 'App\Models\GeoUnit', $wardCodeField = 'ward_code_new')
    {
        $this->modelClass = $modelClass;
        $this->modelInstance = new $modelClass;
        $this->schoolModel = $schoolModel;
        $this->geoModel = $geoModel;
        $this->wardCodeField = $wardCodeField;
        $this->reportFields = $this->getReportFields();
        $this->fillableFields = $this->modelInstance->getFillable();
    }

    /**
     * Xác định cấp độ hiển thị dựa trên user role và filter
     */
    public function determineDisplayLevel($user, $geoIds)
    {
        // Ưu tiên filter được chọn
        if ($geoIds['school']) {
            return 'school';
        }
        if ($geoIds['ward']) {
            return 'ward';
        }
        if ($geoIds['province']) {
            return 'province';
        }

        // Fallback theo role của user
        $roleLevel = $user->role_level ?? 1;
        return match($roleLevel) {
            1 => 'province',
            2 => 'ward', 
            3 => 'school',
            default => 'province'
        };
    }

    /**
     * Lấy dữ liệu theo cấp độ
     */
    public function getReportsByLevel($level, $year, $q, $geoIds, $scheme, $perPage)
    {
        return match($level) {
            'school' => $this->getSchoolLevelReports($year, $q, $geoIds, $scheme, $perPage),
            'ward' => $this->getWardLevelReports($year, $q, $geoIds, $scheme, $perPage),
            'province' => $this->getProvinceLevelReports($year, $q, $geoIds, $scheme, $perPage),
            default => new LengthAwarePaginator([], 0, $perPage)
        };
    }

    /**
     * Lấy dữ liệu cấp trường
     */
    public function getSchoolLevelReports($year, $q, $geoIds, $scheme, $perPage)
    {
        if (!$geoIds['school']) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        $school = $this->schoolModel::active()->find($geoIds['school']);
        if (!$school) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        $wardId = $school->getWardId();
        if (!$wardId) {
            return new LengthAwarePaginator([], 0, $perPage);
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

        return new LengthAwarePaginator(
            collect([$report]),
            1,
            $perPage,
            1,
            ['path' => request()->url(), 'pageName' => 'page']
        );
    }

    /**
     * Lấy dữ liệu cấp xã - hiển thị tất cả trường trong xã + tổng cộng
     */
    public function getWardLevelReports($year, $q, $geoIds, $scheme, $perPage)
    {
        if (!$geoIds['ward']) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        // Lấy ward_code từ ward_id
        $ward = $this->geoModel::find($geoIds['ward']);
        if (!$ward) {
            return new LengthAwarePaginator([], 0, $perPage);
        }
        
        // Lấy tất cả trường trong xã theo ward_code_new (chỉ trường đang hoạt động)
        $schoolsQuery = $this->schoolModel::active()->where($this->wardCodeField, $ward->code);
        
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
        
        return new LengthAwarePaginator(
            $items,
            $combinedData->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'pageName' => 'page']
        );
    }

    /**
     * Lấy dữ liệu cấp tỉnh - hiển thị tất cả xã/phường trong tỉnh + tổng cộng
     */
    public function getProvinceLevelReports($year, $q, $geoIds, $scheme, $perPage)
    {
        if (!$geoIds['province']) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        // Lấy tất cả xã/phường trong tỉnh
        $wards = $this->geoModel::where('parent_id', $geoIds['province'])
            ->where('scheme', $scheme)
            ->where('type_level', 3)
            ->orderBy('name')
            ->get();
        
        // Lấy tất cả báo cáo trong tỉnh (chỉ trường đang hoạt động)
        $reports = $this->modelClass::with(['school', 'ward'])
            ->where('academic_year', $year)
            ->whereHas('school', function($sQuery) {
                $sQuery->active();
            })
            ->whereHas('ward', function($query) use ($geoIds) {
                $query->where('parent_id', $geoIds['province']);
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
        
        return new LengthAwarePaginator(
            $items,
            $combinedData->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'pageName' => 'page']
        );
    }

    /**
     * Lưu dữ liệu cấp trường
     */
    public function saveSchoolData($request)
    {
        $validated = $request->validate($this->getValidationRules());
        
        // Lấy school_name từ request trước, nếu không có thì lấy từ School model
        if (empty($validated['school_name'])) {
            $school = \App\Models\School::find($validated['school_id']);
            if ($school) {
                $validated['school_name'] = $school->name;
                // Xử lý school_rank: nếu null hoặc rỗng thì set = 0, ngược lại convert sang int
                $schoolRank = $school->rank ?? 0;
                $validated['school_rank'] = is_numeric($schoolRank) ? (int) $schoolRank : 0;
            }
        } else {
            // Nếu có school_name từ request, vẫn cần lấy school_rank từ School model
            $school = \App\Models\School::find($validated['school_id']);
            if ($school) {
                $schoolRank = $school->rank ?? 0;
                $validated['school_rank'] = is_numeric($schoolRank) ? (int) $schoolRank : 0;
            }
        }
        
        return $this->modelClass::updateOrCreate(
            [
                'academic_year' => $validated['academic_year'],
                'school_id' => $validated['school_id'],
                'ward_id' => $validated['ward_id'],
            ],
            $validated
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
        foreach ($this->reportFields as $field) {
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
        foreach ($this->reportFields as $field) {
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
        foreach ($this->reportFields as $field) {
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
        foreach ($this->reportFields as $field) {
            if (in_array($field, ['teacher_student_ratio'])) {
                // Trường tỷ lệ tính trung bình
                $total->$field = $reports->avg($field);
            } else {
                // Các trường khác tính tổng
                $total->$field = $reports->sum($field);
            }
        }

        return $total;
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
        foreach ($this->reportFields as $field) {
            $data[$field] = 0;
        }

        return $data;
    }

    /**
     * Kiểm tra báo cáo có dữ liệu không
     */
    protected function hasData($report)
    {
        $modelName = class_basename($this->modelClass);
        
        // Kiểm tra các trường quan trọng theo từng loại báo cáo
        $importantFields = match($modelName) {
            'TeacherReport' => ['total_teachers', 'total_classes', 'total_students'],
            'SecondaryTeacherReport' => ['total_teachers', 'principal', 'vice_principal'],
            'KindergartenReport' => ['total_teachers', 'total_all', 'total_cbql'],
            'KindergartenInfrastructureReport' => ['total_classrooms', 'total_k5_rooms', 'solid_rooms_count'],
            'PrimarySchoolInfrastructureReport' => ['total_classes', 'solid_classrooms', 'principal_rooms_count'],
            default => ['total_teachers']
        };
        
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
        $modelName = class_basename($this->modelClass);
        
        switch ($modelName) {
            case 'TeacherReport':
                return [
                    'sessions_per_day', 'principal', 'vice_principal', 'total_teachers',
                    'teacher_quota', 'teacher_contract', 'female_teachers', 'ethnic_teachers',
                    'teacher_student_ratio', 'training_above_uni', 'training_uni', 'training_college',
                    'training_ped', 'training_below_ped', 'training_primary', 'training_an',
                    'training_mt', 'training_td', 'training_informatics', 'training_foreign_lang',
                    'training_other', 'standard_good', 'standard_quite', 'standard_reach',
                    'standard_not_reach', 'tpt_team', 'office_staff', 'library_other_staff',
                    'total_classes', 'total_students', 'total_rooms', 'total_equipment',
                    'total_books', 'total_computers', 'total_internet', 'total_laboratories',
                    'total_libraries', 'total_playgrounds', 'total_cafeterias', 'total_dormitories',
                    'total_other_facilities'
                ];
            case 'SecondaryTeacherReport':
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
            case 'KindergartenReport':
                return [
                    'total_all', 'contract_work_all', 'contract_labor_all', 'cs_policy',
                    'total_cbql', 'vice_principal_cbql', 'total_teachers', 'ethnic_minority',
                    'teacher_ratio', 'total_staff', 'total_k5', 'contract_work_k5',
                    'contract_labor_k5', 'cs_policy_k5', 'teacher_ratio_k5', 'training_standard',
                    'training_above_standard', 'standard_profession'
                ];
            case 'KindergartenInfrastructureReport':
                return [
                    'total_classrooms', 'total_k5_classes', 'mixed_k5_classes', 'k5_double_shift_classes',
                    'under_5_classes', 'total_k5_rooms', 'room_class_ratio', 'solid_rooms_count',
                    'solid_rooms_avg_area', 'semi_solid_rooms_count', 'semi_solid_rooms_avg_area',
                    'temporary_rooms_count', 'temporary_rooms_avg_area', 'fully_equipped_rooms',
                    'toilet_rooms_count', 'clean_water_system', 'kitchen_count', 'playground_count',
                    'equipped_playground_count'
                ];
            case 'PrimarySchoolInfrastructureReport':
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
            default:
                return [
                    'principal', 'vice_principal', 'total_teachers', 'female_teachers',
                    'ethnic_teachers', 'teacher_student_ratio'
                ];
        }
    }

    /**
     * Lấy validation rules
     */
    protected function getValidationRules()
    {
        $rules = [
            'academic_year' => 'required|string',
            'school_id' => 'required|integer|min:1|exists:schools,id',
            'ward_id' => 'required|integer|min:1|exists:geo_units,id',
            'school_name' => 'nullable|string',
            'school_rank' => 'nullable|integer',
        ];

        // Thêm rules cho tất cả các trường báo cáo
        foreach ($this->reportFields as $field) {
            $rules[$field] = 'nullable|numeric|min:0';
        }

        return $rules;
    }
}
