<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Services\MultiLevelReportService;
use App\Services\GeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Base controller cho multi-level reporting
 */
abstract class BaseMultiLevelReportController extends Controller
{
    protected $reportService;
    protected $geoService;
    protected $modelClass;
    protected $viewName;
    protected $routePrefix;

    public function __construct(GeoService $geoService)
    {
        $this->geoService = $geoService;
        $this->reportService = new MultiLevelReportService($this->modelClass);
    }

    /**
     * Hiển thị danh sách báo cáo
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
        $user = Auth::user();
        $level = $this->reportService->determineDisplayLevel($user, $geoIds);
        
        $reports = $this->reportService->getReportsByLevel($level, $year, $q, $geoIds, $scheme, $perPage);
        $columns = $this->columns($level);
        $groups = $this->columnGroups($level);
        $stats = $this->getStatistics($year, $geoIds['ward']);
        $routePrefix = $this->routePrefix;

        return view($this->viewName, compact(
            'reports', 'year', 'q', 'stats', 'level', 'perPage', 'routePrefix', 'geoIds'
        ));
    }

    /**
     * Lưu dữ liệu cấp trường
     */
    public function saveSchoolData(Request $request)
    {
        try {
            $report = $this->reportService->saveSchoolData($request);
            
            return response()->json([
                'success' => true,
                'message' => 'Lưu dữ liệu thành công!',
                'data' => $report
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Lấy thống kê
     */
    protected function getStatistics($year = null, $wardId = null)
    {
        // Xác định các cột thống kê dựa trên model
        $statFields = $this->getStatisticsFields();
        
        $query = $this->modelClass::query()
            ->select(array_merge([
                DB::raw('COUNT(*) as total_reports'),
                'ward_id',
            ], $statFields))
            ->when($year, fn($q) => $q->where('academic_year', $year))
            ->when($wardId, fn($q) => $q->where('ward_id', $wardId))
            ->groupBy('ward_id');

        return $query->get()->groupBy('ward_id');
    }

    /**
     * Lấy các trường thống kê dựa trên model
     */
    protected function getStatisticsFields()
    {
        $modelName = class_basename($this->modelClass);
        
        switch ($modelName) {
            case 'TeacherReport':
                return [
                    DB::raw('SUM(total_teachers) as total_teachers'),
                    DB::raw('SUM(total_classes) as total_classes'),
                    DB::raw('SUM(total_students) as total_students'),
                ];
            case 'SecondaryTeacherReport':
                return [
                    DB::raw('SUM(total_teachers) as total_teachers'),
                    DB::raw('SUM(female_teachers) as total_female_teachers'),
                    DB::raw('AVG(teacher_student_ratio) as avg_teacher_ratio'),
                ];
            case 'KindergartenReport':
                return [
                    DB::raw('SUM(total_teachers) as total_teachers'),
                    DB::raw('SUM(total_all) as total_all'),
                    DB::raw('SUM(total_cbql) as total_cbql'),
                    DB::raw('AVG(teacher_ratio) as avg_teacher_ratio'),
                ];
            case 'KindergartenInfrastructureReport':
                return [
                    DB::raw('SUM(total_classrooms) as total_classrooms'),
                    DB::raw('SUM(total_k5_rooms) as total_k5_rooms'),
                    DB::raw('SUM(solid_rooms_count) as total_solid_rooms'),
                    DB::raw('AVG(room_class_ratio) as avg_room_class_ratio'),
                ];
            case 'PrimarySchoolInfrastructureReport':
                return [
                    DB::raw('SUM(total_classes) as total_classes'),
                    DB::raw('SUM(combined_classes) as total_combined_classes'),
                    DB::raw('SUM(solid_classrooms) as total_solid_classrooms'),
                    DB::raw('SUM(semi_solid_classrooms) as total_semi_solid_classrooms'),
                    DB::raw('AVG(classroom_class_ratio) as avg_classroom_class_ratio'),
                ];
            default:
                return [
                    DB::raw('SUM(total_teachers) as total_teachers'),
                ];
        }
    }

    /**
     * Lấy dữ liệu báo cáo để export
     */
    protected function getReportData($report)
    {
        $data = [];
        
        // Thông tin cơ bản
        $data[] = $report->school_name ?? '';
        $data[] = $report->school_rank ?? '';
        
        // Dữ liệu báo cáo - lấy theo model
        $reportFields = $this->getReportFieldsForExport();
        
        foreach ($reportFields as $field) {
            $data[] = $report->$field ?? 0;
        }
        
        return $data;
    }

    /**
     * Lấy danh sách các trường báo cáo để export
     */
    protected function getReportFieldsForExport()
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
                    'principal', 'vice_principal', 'total_teachers', 'staff_quota',
                    'contract_teachers', 'female_teachers', 'ethnic_teachers', 'teacher_student_ratio',
                    'training_above_uni', 'training_uni', 'training_college', 'training_ped',
                    'training_below_ped', 'training_primary', 'training_an', 'training_mt',
                    'training_td', 'training_informatics', 'training_foreign_lang', 'training_other',
                    'standard_good', 'standard_quite', 'standard_reach', 'standard_not_reach',
                    'tpt_team', 'office_staff', 'library_other_staff'
                ];
            case 'PrimarySchoolInfrastructureReport':
                return [
                    'total_classes', 'combined_classes', 'solid_classrooms', 'semi_solid_classrooms',
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
     * Lấy tên file export
     */
    abstract protected function getExportFilename($year);

    /**
     * Lấy prefix cho temp file
     */
    abstract protected function getTempFilePrefix();

    /**
     * Lấy cấu trúc cột
     */
    abstract protected function columns($level = 'province');

    /**
     * Lấy nhóm cột
     */
    abstract protected function columnGroups($level = 'province');
}
