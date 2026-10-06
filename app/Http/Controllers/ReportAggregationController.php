<?php

namespace App\Http\Controllers;

use App\Services\ReportAggregationService;
use App\Models\SecondaryTeacherReport;
use App\Models\TeacherReport;
use App\Models\KindergartenReport;
use App\Models\GeoUnit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReportAggregationController extends Controller
{
    protected $aggregationService;

    public function __construct(ReportAggregationService $aggregationService)
    {
        $this->aggregationService = $aggregationService;
    }

    /**
     * Lấy thống kê tổng hợp theo xã/phường
     */
    public function getWardStatistics(Request $request): JsonResponse
    {
        $request->validate([
            'ward_id' => 'required|integer|exists:geo_units,id',
            'academic_year' => 'nullable|string',
            'report_type' => 'nullable|in:secondary,primary,kindergarten,all'
        ]);

        $wardId = $request->ward_id;
        $academicYear = $request->academic_year;
        $reportType = $request->report_type ?? 'all';

        try {
            switch ($reportType) {
                case 'secondary':
                    $data = $this->aggregationService->aggregateSecondaryTeacherReportsByWard($wardId, $academicYear);
                    break;
                case 'primary':
                    $data = $this->aggregationService->aggregateTeacherReportsByWard($wardId, $academicYear);
                    break;
                case 'kindergarten':
                    $data = $this->aggregationService->aggregateKindergartenReportsByWard($wardId, $academicYear);
                    break;
                case 'all':
                default:
                    $data = $this->aggregationService->aggregateAllReportsByWard($wardId, $academicYear);
                    break;
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy thống kê: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy thống kê tổng hợp theo tỉnh
     */
    public function getProvinceStatistics(Request $request): JsonResponse
    {
        $request->validate([
            'province_id' => 'required|integer|exists:geo_units,id',
            'academic_year' => 'nullable|string',
            'report_type' => 'nullable|in:secondary,primary,kindergarten,all'
        ]);

        $provinceId = $request->province_id;
        $academicYear = $request->academic_year;
        $reportType = $request->report_type ?? 'all';

        try {
            switch ($reportType) {
                case 'secondary':
                    $data = SecondaryTeacherReport::statisticsByProvince($provinceId, $academicYear)->first();
                    break;
                case 'primary':
                    $data = TeacherReport::statisticsByProvince($provinceId, $academicYear)->first();
                    break;
                case 'kindergarten':
                    $data = KindergartenReport::statisticsByProvince($provinceId, $academicYear)->first();
                    break;
                case 'all':
                default:
                    $data = $this->aggregationService->aggregateAllReportsByProvince($provinceId, $academicYear);
                    break;
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy thống kê: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy thống kê theo trường học
     */
    public function getSchoolStatistics(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id',
            'academic_year' => 'nullable|string',
            'report_type' => 'nullable|in:secondary,primary,kindergarten,all'
        ]);

        $schoolId = $request->school_id;
        $academicYear = $request->academic_year;
        $reportType = $request->report_type ?? 'all';

        try {
            switch ($reportType) {
                case 'secondary':
                    $data = SecondaryTeacherReport::statisticsBySchool($schoolId, $academicYear)->first();
                    break;
                case 'primary':
                    $data = TeacherReport::statisticsBySchool($schoolId, $academicYear)->first();
                    break;
                case 'kindergarten':
                    $data = KindergartenReport::statisticsBySchool($schoolId, $academicYear)->first();
                    break;
                case 'all':
                default:
                    $data = $this->aggregationService->getReportsBySchool($schoolId, $academicYear);
                    break;
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy thống kê: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách xã/phường có báo cáo
     */
    public function getWardsWithReports(Request $request): JsonResponse
    {
        $request->validate([
            'academic_year' => 'nullable|string',
            'province_id' => 'nullable|integer|exists:geo_units,id'
        ]);

        try {
            $wardIds = $this->aggregationService->getWardsWithReports(
                $request->academic_year,
                $request->province_id
            );

            $wards = GeoUnit::whereIn('id', $wardIds)
                ->with('parent') // Lấy thông tin tỉnh
                ->get()
                ->map(function ($ward) {
                    return [
                        'id' => $ward->id,
                        'name' => $ward->name,
                        'province_id' => $ward->getProvinceId(),
                        'province_name' => $ward->getProvinceId() ? GeoUnit::find($ward->getProvinceId())->name : null
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $wards
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách xã/phường: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách năm học có dữ liệu
     */
    public function getAvailableAcademicYears(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $years = [];

            // Lấy từ tất cả các bảng báo cáo
            $secondaryYears = SecondaryTeacherReport::getAvailableAcademicYears($user);
            $primaryYears = TeacherReport::getAvailableAcademicYears($user);
            $kindergartenYears = KindergartenReport::getAvailableAcademicYears($user);

            // Gộp và loại bỏ trùng lặp
            $allYears = array_unique(array_merge($secondaryYears, $primaryYears, $kindergartenYears));
            sort($allYears, SORT_STRING | SORT_DESC);

            return response()->json([
                'success' => true,
                'data' => $allYears
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách năm học: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy thống kê tổng quan
     */
    public function getOverviewStatistics(Request $request): JsonResponse
    {
        $request->validate([
            'academic_year' => 'nullable|string',
            'province_id' => 'nullable|integer|exists:geo_units,id'
        ]);

        try {
            $user = $request->user();
            $academicYear = $request->academic_year;
            $provinceId = $request->province_id;

            // Lấy thống kê tổng quan
            $secondaryQuery = SecondaryTeacherReport::query();
            $primaryQuery = TeacherReport::query();
            $kindergartenQuery = KindergartenReport::query();

            if ($user) {
                $secondaryQuery->byUserAccess($user);
                $primaryQuery->byUserAccess($user);
                $kindergartenQuery->byUserAccess($user);
            }

            if ($academicYear) {
                $secondaryQuery->byAcademicYear($academicYear);
                $primaryQuery->byAcademicYear($academicYear);
                $kindergartenQuery->byAcademicYear($academicYear);
            }

            if ($provinceId) {
                $secondaryQuery->byProvince($provinceId);
                $primaryQuery->byProvince($provinceId);
                $kindergartenQuery->byProvince($provinceId);
            }

            $wardIds = $this->aggregationService->getWardsWithReports($academicYear, $provinceId);

            $data = [
                'academic_year' => $academicYear,
                'province_id' => $provinceId,
                'total_wards' => count($wardIds),
                'secondary_reports' => $secondaryQuery->count(),
                'primary_reports' => $primaryQuery->count(),
                'kindergarten_reports' => $kindergartenQuery->count(),
                'total_schools' => $secondaryQuery->distinct('school_id')->count() + 
                                 $primaryQuery->distinct('school_id')->count() + 
                                 $kindergartenQuery->distinct('school_id')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy thống kê tổng quan: ' . $e->getMessage()
            ], 500);
        }
    }
}
