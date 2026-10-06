<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KindergartenReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year',
        'province_id',
        'district_id', 
        'ward_id',
        'school_name',
        // CBQL, giáo viên, nhân viên - Tổng số toàn bộ
        'total_all',
        'contract_work_all',
        'contract_labor_all',
        // CBQL
        'total_cbql',
        'cbql_policy',
        'cs_policy',
        // Giáo viên
        'total_teachers',
        'vice_principal',
        'ethnic_minority',
        'teacher_ratio',
        // Nhân viên
        'total_staff',
        'contract_work_staff',
        'contract_labor_staff',
        // Giáo viên dạy MG 5 tuổi
        'teacher_ratio_k5',
        'training_standard',
        'training_above_standard',
        'standard_profession',
    ];

    protected $casts = [
        'academic_year' => 'string',
        'province_id' => 'integer',
        'district_id' => 'integer',
        'ward_id' => 'integer',
        'teacher_ratio' => 'decimal:2',
        'teacher_ratio_k5' => 'decimal:2',
        // CBQL, giáo viên, nhân viên
        'total_all' => 'integer',
        'contract_work_all' => 'integer',
        'contract_labor_all' => 'integer',
        // CBQL
        'total_cbql' => 'integer',
        'cbql_policy' => 'integer',
        'cs_policy' => 'integer',
        // Giáo viên
        'total_teachers' => 'integer',
        'vice_principal' => 'integer',
        'ethnic_minority' => 'integer',
        // Nhân viên
        'total_staff' => 'integer',
        'contract_work_staff' => 'integer',
        'contract_labor_staff' => 'integer',
        // Giáo viên dạy MG 5 tuổi
        'training_standard' => 'integer',
        'training_above_standard' => 'integer',
        'standard_profession' => 'integer',
    ];

    /**
     * Quan hệ với tỉnh/thành phố
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(GeoUnit::class, 'province_id');
    }

    /**
     * Quan hệ với quận/huyện
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(GeoUnit::class, 'district_id');
    }

    /**
     * Quan hệ với phường/xã
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(GeoUnit::class, 'ward_id');
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
        return $this->total_cbql;
    }

    /**
     * Tính tổng số cán bộ, giáo viên, nhân viên
     */
    public function getTotalPersonnelAttribute()
    {
        return $this->total_all;
    }

    /**
     * Tính tổng số giáo viên đạt chuẩn
     */
    public function getTotalStandardReachedAttribute()
    {
        return $this->training_standard + $this->training_above_standard;
    }

    /**
     * Tính tổng số giáo viên dạy MG 5 tuổi
     */
    public function getTotalK5TeachersAttribute()
    {
        return $this->training_standard + $this->training_above_standard;
    }
}

