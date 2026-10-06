<?php

namespace App\Services;

use App\Models\SecondaryTeacherReport;
use App\Models\TeacherReport;
use App\Models\KindergartenReport;
use App\Models\GeoUnit;
use App\Models\School;
use Illuminate\Support\Facades\DB;

class ReportAggregationService
{
    /**
     * Tổng hợp báo cáo giáo viên THCS theo xã/phường
     */
    public function aggregateSecondaryTeacherReportsByWard($wardId, $academicYear = null)
    {
        $query = SecondaryTeacherReport::statisticsByWard($wardId, $academicYear);
        
        $result = $query->first();
        
        if (!$result) {
            return $this->getEmptyWardStatistics($wardId, $academicYear);
        }
        
        // Lấy thông tin xã/phường
        $ward = GeoUnit::find($wardId);
        $result->ward_name = $ward ? $ward->name : 'Không xác định';
        
        // Lấy thông tin tỉnh
        $province = $ward ? $ward->getProvinceId() : null;
        $result->province_id = $province;
        $result->province_name = $province ? GeoUnit::find($province)->name : 'Không xác định';
        
        return $result;
    }

    /**
     * Tổng hợp báo cáo giáo viên tiểu học theo xã/phường
     */
    public function aggregateTeacherReportsByWard($wardId, $academicYear = null)
    {
        $query = TeacherReport::statisticsByWard($wardId, $academicYear);
        
        $result = $query->first();
        
        if (!$result) {
            return $this->getEmptyWardStatistics($wardId, $academicYear);
        }
        
        // Lấy thông tin xã/phường
        $ward = GeoUnit::find($wardId);
        $result->ward_name = $ward ? $ward->name : 'Không xác định';
        
        // Lấy thông tin tỉnh
        $province = $ward ? $ward->getProvinceId() : null;
        $result->province_id = $province;
        $result->province_name = $province ? GeoUnit::find($province)->name : 'Không xác định';
        
        return $result;
    }

    /**
     * Tổng hợp báo cáo giáo viên mầm non theo xã/phường
     */
    public function aggregateKindergartenReportsByWard($wardId, $academicYear = null)
    {
        $query = KindergartenReport::statisticsByWard($wardId, $academicYear);
        
        $result = $query->first();
        
        if (!$result) {
            return $this->getEmptyWardStatistics($wardId, $academicYear);
        }
        
        // Lấy thông tin xã/phường
        $ward = GeoUnit::find($wardId);
        $result->ward_name = $ward ? $ward->name : 'Không xác định';
        
        // Lấy thông tin tỉnh
        $province = $ward ? $ward->getProvinceId() : null;
        $result->province_id = $province;
        $result->province_name = $province ? GeoUnit::find($province)->name : 'Không xác định';
        
        return $result;
    }

    /**
     * Tổng hợp tất cả báo cáo theo xã/phường
     */
    public function aggregateAllReportsByWard($wardId, $academicYear = null)
    {
        $secondary = $this->aggregateSecondaryTeacherReportsByWard($wardId, $academicYear);
        $primary = $this->aggregateTeacherReportsByWard($wardId, $academicYear);
        $kindergarten = $this->aggregateKindergartenReportsByWard($wardId, $academicYear);
        
        return [
            'ward_id' => $wardId,
            'ward_name' => $secondary->ward_name ?? 'Không xác định',
            'province_id' => $secondary->province_id ?? null,
            'province_name' => $secondary->province_name ?? 'Không xác định',
            'academic_year' => $academicYear,
            'secondary' => $secondary,
            'primary' => $primary,
            'kindergarten' => $kindergarten,
            'summary' => $this->calculateSummaryStatistics($secondary, $primary, $kindergarten)
        ];
    }

    /**
     * Tổng hợp báo cáo theo tỉnh
     */
    public function aggregateAllReportsByProvince($provinceId, $academicYear = null)
    {
        $secondary = SecondaryTeacherReport::statisticsByProvince($provinceId, $academicYear)->first();
        $primary = TeacherReport::statisticsByProvince($provinceId, $academicYear)->first();
        $kindergarten = KindergartenReport::statisticsByProvince($provinceId, $academicYear)->first();
        
        $province = GeoUnit::find($provinceId);
        
        return [
            'province_id' => $provinceId,
            'province_name' => $province ? $province->name : 'Không xác định',
            'academic_year' => $academicYear,
            'secondary' => $secondary,
            'primary' => $primary,
            'kindergarten' => $kindergarten,
            'summary' => $this->calculateSummaryStatistics($secondary, $primary, $kindergarten)
        ];
    }

    /**
     * Lấy danh sách báo cáo theo trường học
     */
    public function getReportsBySchool($schoolId, $academicYear = null)
    {
        $school = School::find($schoolId);
        if (!$school) {
            return null;
        }
        
        $ward = $school->wardUnit;
        $province = $ward ? $ward->getProvinceId() : null;
        
        $secondary = SecondaryTeacherReport::statisticsBySchool($schoolId, $academicYear)->first();
        $primary = TeacherReport::statisticsBySchool($schoolId, $academicYear)->first();
        $kindergarten = KindergartenReport::statisticsBySchool($schoolId, $academicYear)->first();
        
        return [
            'school_id' => $schoolId,
            'school_name' => $school->name,
            'school_code' => $school->code,
            'ward_id' => $school->ward_id,
            'ward_name' => $ward ? $ward->name : 'Không xác định',
            'province_id' => $province,
            'province_name' => $province ? GeoUnit::find($province)->name : 'Không xác định',
            'academic_year' => $academicYear,
            'secondary' => $secondary,
            'primary' => $primary,
            'kindergarten' => $kindergarten,
            'summary' => $this->calculateSummaryStatistics($secondary, $primary, $kindergarten)
        ];
    }

    /**
     * Lấy thống kê trống cho xã/phường
     */
    private function getEmptyWardStatistics($wardId, $academicYear = null)
    {
        $ward = GeoUnit::find($wardId);
        $province = $ward ? $ward->getProvinceId() : null;
        
        return (object) [
            'academic_year' => $academicYear,
            'ward_id' => $wardId,
            'ward_name' => $ward ? $ward->name : 'Không xác định',
            'province_id' => $province,
            'province_name' => $province ? GeoUnit::find($province)->name : 'Không xác định',
            'total_schools' => 0,
            'total_teachers' => 0,
            'total_management' => 0,
            'total_female_teachers' => 0,
            'total_ethnic_teachers' => 0,
            'total_contract_teachers' => 0,
            'total_staff_quota' => 0,
            'avg_teacher_student_ratio' => 0,
            'total_standard_teachers' => 0,
            'total_training_teachers' => 0,
            'total_staff' => 0,
        ];
    }

    /**
     * Tính toán thống kê tổng hợp
     */
    private function calculateSummaryStatistics($secondary, $primary, $kindergarten)
    {
        $secondary = $secondary ?: (object) [];
        $primary = $primary ?: (object) [];
        $kindergarten = $kindergarten ?: (object) [];
        
        return [
            'total_schools' => ($secondary->total_schools ?? 0) + ($primary->total_schools ?? 0) + ($kindergarten->total_schools ?? 0),
            'total_teachers' => ($secondary->total_teachers ?? 0) + ($primary->total_teachers ?? 0) + ($kindergarten->total_teachers ?? 0),
            'total_management' => ($secondary->total_management ?? 0) + ($primary->total_management ?? 0) + ($kindergarten->total_management ?? 0),
            'total_female_teachers' => ($secondary->total_female_teachers ?? 0) + ($primary->total_female_teachers ?? 0) + ($kindergarten->total_female_teachers ?? 0),
            'total_ethnic_teachers' => ($secondary->total_ethnic_teachers ?? 0) + ($primary->total_ethnic_teachers ?? 0) + ($kindergarten->total_ethnic_teachers ?? 0),
            'total_contract_teachers' => ($secondary->total_contract_teachers ?? 0) + ($primary->total_contract_teachers ?? 0) + ($kindergarten->total_contract_work ?? 0),
            'total_staff' => ($secondary->total_staff ?? 0) + ($primary->total_staff ?? 0) + ($kindergarten->total_staff ?? 0),
            'total_standard_teachers' => ($secondary->total_standard_teachers ?? 0) + ($primary->total_standard_teachers ?? 0) + ($kindergarten->total_standard_teachers ?? 0),
            'total_training_teachers' => ($secondary->total_training_teachers ?? 0) + ($primary->total_training_teachers ?? 0) + ($kindergarten->total_standard_teachers ?? 0),
        ];
    }

    /**
     * Lấy danh sách xã/phường có báo cáo
     */
    public function getWardsWithReports($academicYear = null, $provinceId = null)
    {
        $wardIds = collect();
        
        // Lấy ward_id từ secondary_teacher_reports
        $secondaryQuery = DB::table('secondary_teacher_reports')
            ->select('ward_id')
            ->when($academicYear, function($q) use ($academicYear) {
                return $q->where('academic_year', $academicYear);
            });
        
        if ($provinceId) {
            $secondaryQuery->whereIn('ward_id', function($subQ) use ($provinceId) {
                $subQ->select('id')
                    ->from('geo_units')
                    ->where('parent_id', $provinceId)
                    ->where('type_level', 3);
            });
        }
        
        $wardIds = $wardIds->merge($secondaryQuery->pluck('ward_id'));
        
        // Lấy ward_id từ teacher_reports
        $primaryQuery = DB::table('teacher_reports')
            ->select('ward_id')
            ->when($academicYear, function($q) use ($academicYear) {
                return $q->where('academic_year', $academicYear);
            });
        
        if ($provinceId) {
            $primaryQuery->whereIn('ward_id', function($subQ) use ($provinceId) {
                $subQ->select('id')
                    ->from('geo_units')
                    ->where('parent_id', $provinceId)
                    ->where('type_level', 3);
            });
        }
        
        $wardIds = $wardIds->merge($primaryQuery->pluck('ward_id'));
        
        // Lấy ward_id từ kindergarten_reports
        $kindergartenQuery = DB::table('kindergarten_reports')
            ->select('ward_id')
            ->when($academicYear, function($q) use ($academicYear) {
                return $q->where('academic_year', $academicYear);
            });
        
        if ($provinceId) {
            $kindergartenQuery->whereIn('ward_id', function($subQ) use ($provinceId) {
                $subQ->select('id')
                    ->from('geo_units')
                    ->where('parent_id', $provinceId)
                    ->where('type_level', 3);
            });
        }
        
        $wardIds = $wardIds->merge($kindergartenQuery->pluck('ward_id'));
        
        return $wardIds->unique()->filter()->values()->toArray();
    }
}
