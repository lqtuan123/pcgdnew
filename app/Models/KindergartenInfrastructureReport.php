<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KindergartenInfrastructureReport extends Model
{
    use HasFactory;

    protected $fillable = [
        // Thông tin cơ bản
        'academic_year',
        'ward_id',
        'province_id',
        'district_id',
        'school_id',
        'school_name',
        'school_branch',
        'total_classrooms',
        
        // Nhóm lớp, nhóm (cột 5-8 trong table)
        'total_k5_classes',           // Tổng số lớp MG 5 tuổi
        'mixed_k5_classes',           // Trong đó, lớp ghép 5 tuổi
        'k5_double_shift_classes',    // Số lớp học 2 buổi/ngày
        'under_5_classes',            // Số lớp, nhóm dưới 5 tuổi
        
        // Nhóm phòng học cho lớp MG 5 tuổi (cột 9-17 trong table)
        'total_k5_rooms',             // Tổng số phòng
        'room_class_ratio',           // Tỷ lệ P/L
        'solid_rooms_count',          // Kiên cố - Tổng số
        'solid_rooms_avg_area',       // Kiên cố - DTBQ phòng SHC
        'semi_solid_rooms_count',     // Bán kiên cố - Tổng số
        'semi_solid_rooms_avg_area',  // Bán kiên cố - DTBQ phòng SHC
        'temporary_rooms_count',      // Phòng học khác - Tổng số
        'temporary_rooms_avg_area',   // Phòng học khác - DTBQ phòng SHC
        'fully_equipped_rooms',       // Đủ bộ TBị, ĐD, ĐC TT
        
        // Nhóm vệ sinh (cột 18-20 trong table)
        'toilet_rooms_count',         // Phòng hoặc khu vệ sinh
        'clean_water_system',         // CT nước sạch
        'kitchen_count',              // Bếp ăn
        
        // Nhóm sân và đồ chơi (cột 21-22 trong table)
        'playground_count',           // Sân chơi
        'equipped_playground_count',  // Tr đồ: Sân có ĐC
    ];

    protected $casts = [
        'academic_year' => 'string',
        'ward_id' => 'integer',
        'province_id' => 'integer',
        'district_id' => 'integer',
        'school_id' => 'integer',
        'total_classrooms' => 'integer',
        'total_k5_classes' => 'integer',
        'mixed_k5_classes' => 'integer',
        'k5_double_shift_classes' => 'integer',
        'under_5_classes' => 'integer',
        'total_k5_rooms' => 'integer',
        'room_class_ratio' => 'decimal:2',
        'solid_rooms_count' => 'integer',
        'solid_rooms_avg_area' => 'decimal:2',
        'semi_solid_rooms_count' => 'integer',
        'semi_solid_rooms_avg_area' => 'decimal:2',
        'temporary_rooms_count' => 'integer',
        'temporary_rooms_avg_area' => 'decimal:2',
        'fully_equipped_rooms' => 'integer',
        'toilet_rooms_count' => 'integer',
        'clean_water_system' => 'integer',
        'kitchen_count' => 'integer',
        'playground_count' => 'integer',
        'equipped_playground_count' => 'integer',
    ];

    /**
     * Quan hệ với đơn vị địa lý (xã)
     */
    public function ward()
    {
        return $this->belongsTo(GeoUnit::class, 'ward_id');
    }

    /**
     * Quan hệ với tỉnh
     */
    public function province()
    {
        return $this->belongsTo(GeoUnit::class, 'province_id');
    }

    /**
     * Quan hệ với huyện
     */
    public function district()
    {
        return $this->belongsTo(GeoUnit::class, 'district_id');
    }

    /**
     * Quan hệ với trường
     */
    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }
}

