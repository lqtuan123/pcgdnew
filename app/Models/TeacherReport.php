<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use App\Traits\ReportTrait;

class TeacherReport extends Model
{
    use HasFactory, ReportTrait;

    protected $fillable = [
        'academic_year',
        'ward_id',
        'school_id',
        // Đơn vị (3 cột)
        'school_name',
        'empty_field',
        'school_rank',
        'sessions_per_day',
        // CBQL (2 cột)
        'principal',
        'vice_principal',
        // Giáo viên - Tổng quát (6 cột)
        'total_teachers',
        'teacher_quota',
        'teacher_contract',
        'female_teachers',
        'ethnic_teachers',
        'teacher_student_ratio',
        // Trình độ đào tạo (5 cột)
        'training_above_uni',
        'training_uni',
        'training_college',
        'training_ped',
        'training_below_ped',
        // Loại hình đào tạo (7 cột)
        'training_primary',
        'training_an',
        'training_mt',
        'training_td',
        'training_informatics',
        'training_foreign_lang',
        'training_other',
        // Chuẩn nghề nghiệp (4 cột)
        'standard_good',
        'standard_quite',
        'standard_reach',
        'standard_not_reach',
        // TPT Đội (1 cột)
        'tpt_team',
        // Nhân viên (2 cột)
        'office_staff',
        'library_other_staff',
    ];

    protected $casts = [
        'teacher_student_ratio' => 'decimal:2',
        // Đơn vị
        'school_rank' => 'integer',
        'sessions_per_day' => 'integer',
        // CBQL
        'principal' => 'integer',
        'vice_principal' => 'integer',
        // Giáo viên
        'total_teachers' => 'integer',
        'teacher_quota' => 'integer',
        'teacher_contract' => 'integer',
        'female_teachers' => 'integer',
        'ethnic_teachers' => 'integer',
        // Trình độ đào tạo
        'training_above_uni' => 'integer',
        'training_uni' => 'integer',
        'training_college' => 'integer',
        'training_ped' => 'integer',
        'training_below_ped' => 'integer',
        // Loại hình đào tạo
        'training_primary' => 'integer',
        'training_an' => 'integer',
        'training_mt' => 'integer',
        'training_td' => 'integer',
        'training_informatics' => 'integer',
        'training_foreign_lang' => 'integer',
        'training_other' => 'integer',
        // Chuẩn nghề nghiệp
        'standard_good' => 'integer',
        'standard_quite' => 'integer',
        'standard_reach' => 'integer',
        'standard_not_reach' => 'integer',
        // TPT Đội
        'tpt_team' => 'integer',
        // Nhân viên
        'office_staff' => 'integer',
        'library_other_staff' => 'integer',
    ];

    /**
     * Quan hệ với đơn vị địa lý (xã/phường)
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(GeoUnit::class, 'ward_id');
    }

    /**
     * Quan hệ với trường học
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    /**
     * Quan hệ với tỉnh/thành phố (thông qua ward)
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(GeoUnit::class, 'ward_id')
            ->join('geo_units as province', function($join) {
                $join->on('geo_units.parent_id', '=', 'province.id')
                     ->where('province.type_level', 1);
            })
            ->select('province.*');
    }

    /**
     * Scope để lọc theo năm học
     */
    public function scopeOfYear($query, $year)
    {
        return $query->where('academic_year', $year);
    }

    /**
     * Scope để lọc theo xã/phường
     */
    public function scopeOfWard($query, $wardId)
    {
        return $query->where('ward_id', $wardId);
    }

    /**
     * Scope để lọc theo trường học
     */
    public function scopeBySchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    /**
     * Scope để lọc theo tỉnh (thông qua ward)
     */
    public function scopeByProvince($query, $provinceId)
    {
        return $query->whereHas('ward', function($q) use ($provinceId) {
            $q->whereHas('parent', function($subQ) use ($provinceId) {
                $subQ->where('id', $provinceId);
            });
        });
    }

    /**
     * Scope để tìm kiếm theo tên trường
     */
    public function scopeSearchBySchool($query, $schoolName)
    {
        return $query->where('school_name', 'like', "%{$schoolName}%");
    }

    /**
     * Tính tổng số cán bộ quản lý
     */
    public function getTotalManagementAttribute()
    {
        return $this->principal + $this->vice_principal;
    }

    /**
     * Tính tổng số nhân viên
     */
    public function getTotalStaffAttribute()
    {
        return $this->tpt_team + $this->office_staff + $this->library_other_staff;
    }

    /**
     * Tính tổng số cán bộ, giáo viên, nhân viên
     */
    public function getTotalPersonnelAttribute()
    {
        return $this->total_management + $this->total_teachers + $this->total_staff;
    }

    /**
     * Scope: Lấy thống kê tổng hợp theo xã/phường
     */
    public function scopeStatisticsByWard($query, $wardId = null, $academicYear = null)
    {
        $query = $query->select([
            'academic_year',
            'ward_id',
            DB::raw('COUNT(DISTINCT school_id) as total_schools'),
            DB::raw('SUM(total_teachers) as total_teachers'),
            DB::raw('SUM(principal + vice_principal) as total_management'),
            DB::raw('SUM(female_teachers) as total_female_teachers'),
            DB::raw('SUM(ethnic_teachers) as total_ethnic_teachers'),
            DB::raw('SUM(teacher_contract) as total_contract_teachers'),
            DB::raw('SUM(teacher_quota) as total_quota_teachers'),
            DB::raw('AVG(teacher_student_ratio) as avg_teacher_student_ratio'),
            DB::raw('SUM(standard_good + standard_quite + standard_reach) as total_standard_teachers'),
            DB::raw('SUM(training_above_uni + training_uni + training_college + training_ped + training_below_ped) as total_training_teachers'),
            DB::raw('SUM(tpt_team + office_staff + library_other_staff) as total_staff'),
        ])->groupBy('academic_year', 'ward_id');

        if ($wardId) {
            $query->where('ward_id', $wardId);
        }

        if ($academicYear) {
            $query->where('academic_year', $academicYear);
        }

        return $query;
    }

    /**
     * Scope: Lấy thống kê tổng hợp theo trường học
     */
    public function scopeStatisticsBySchool($query, $schoolId = null, $academicYear = null)
    {
        $query = $query->select([
            'academic_year',
            'ward_id',
            'school_id',
            'school_name',
            'school_rank',
            'sessions_per_day',
            'total_teachers',
            'principal',
            'vice_principal',
            'female_teachers',
            'ethnic_teachers',
            'teacher_contract',
            'teacher_quota',
            'teacher_student_ratio',
            DB::raw('(standard_good + standard_quite + standard_reach) as total_standard_teachers'),
            DB::raw('(training_above_uni + training_uni + training_college + training_ped + training_below_ped) as total_training_teachers'),
            DB::raw('(tpt_team + office_staff + library_other_staff) as total_staff'),
        ]);

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        if ($academicYear) {
            $query->where('academic_year', $academicYear);
        }

        return $query;
    }

    /**
     * Scope: Lấy thống kê tổng hợp theo tỉnh
     */
    public function scopeStatisticsByProvince($query, $provinceId = null, $academicYear = null)
    {
        $query = $query->select([
            'academic_year',
            DB::raw('COUNT(DISTINCT ward_id) as total_wards'),
            DB::raw('COUNT(DISTINCT school_id) as total_schools'),
            DB::raw('SUM(total_teachers) as total_teachers'),
            DB::raw('SUM(principal + vice_principal) as total_management'),
            DB::raw('SUM(female_teachers) as total_female_teachers'),
            DB::raw('SUM(ethnic_teachers) as total_ethnic_teachers'),
            DB::raw('SUM(teacher_contract) as total_contract_teachers'),
            DB::raw('SUM(teacher_quota) as total_quota_teachers'),
            DB::raw('AVG(teacher_student_ratio) as avg_teacher_student_ratio'),
            DB::raw('SUM(standard_good + standard_quite + standard_reach) as total_standard_teachers'),
            DB::raw('SUM(training_above_uni + training_uni + training_college + training_ped + training_below_ped) as total_training_teachers'),
            DB::raw('SUM(tpt_team + office_staff + library_other_staff) as total_staff'),
        ])->groupBy('academic_year');

        if ($provinceId) {
            $query->whereHas('ward', function($q) use ($provinceId) {
                $q->whereHas('parent', function($subQ) use ($provinceId) {
                    $subQ->where('id', $provinceId);
                });
            });
        }

        if ($academicYear) {
            $query->where('academic_year', $academicYear);
        }

        return $query;
    }

    /**
     * Scope: Lọc theo quyền truy cập của user
     */
    public function scopeByUserAccess($query, $user)
    {
        if ($user->isSuperadmin() || $user->isAdmin()) {
            return $query;
        }

        if ($user->isProvince()) {
            return $query->byProvince($user->province_id);
        }

        if ($user->isWard()) {
            return $query->ofWard($user->ward_id);
        }

        if ($user->isSchool()) {
            return $query->bySchool($user->school_id);
        }

        return $query->whereRaw('1 = 0'); // Không có quyền truy cập
    }
}
