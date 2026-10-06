<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrimarySchoolInfrastructureReport extends Model
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
        
        // Cột 4-5: Số lớp
        'total_classes',              // Cột 4: Tổng số
        'combined_classes',           // Cột 5: Lớp ghép
        
        // Cột 6-10: Số phòng học
        'solid_classrooms',           // Cột 6: Kiên cố
        'semi_solid_classrooms',      // Cột 7: Bán kiên cố
        'temporary_classrooms',       // Cột 8: Tạm
        'rented_classrooms',          // Cột 9: Thuê/mượn
        'classroom_class_ratio',      // Cột 10: Tỷ lệ Ph/L
        
        // Cột 11-15: Số phòng chức năng (các phòng đơn)
        'principal_rooms_count',      // Cột 11: HT (SL)
        'vice_principal_rooms_count', // Cột 12: Phó HT (SL)
        'office_rooms_count',         // Cột 13: VP (SL)
        'medical_rooms_count',        // Cột 14: Y tế (SL)
        'youth_union_rooms_count',    // Cột 15: TT HĐ Đội (SL)
        
        // Cột 16-17: Phòng họp (SL + DT)
        'meeting_rooms_count',        // Cột 16: Phòng họp (SL)
        'meeting_rooms_area',         // Cột 17: Phòng họp (DT)
        
        // Cột 18-19: Thư viện (SL + DT)
        'library_rooms_count',        // Cột 18: Thư viện (SL)
        'library_rooms_area',         // Cột 19: Thư viện (DT)
        
        // Cột 20-21: Thiết bị (SL + DT)
        'equipment_rooms_count',      // Cột 20: Thiết bị (SL)
        'equipment_rooms_area',       // Cột 21: Thiết bị (DT)
        
        // Cột 22-23: Giáo viên (SL + DT)
        'teacher_rooms_count',        // Cột 22: Giáo viên (SL)
        'teacher_rooms_area',         // Cột 23: Giáo viên (DT)
        
        // Cột 24-25: Học sinh (SL + DT)
        'student_rooms_count',        // Cột 24: Học sinh (SL)
        'student_rooms_area',         // Cột 25: Học sinh (DT)
        
        // Cột 26-27: Sân chơi (Số lượng + Diện tích)
        'playground_count',           // Cột 26: Sân chơi (Số lượng)
        'playground_area',            // Cột 27: Sân chơi (Diện tích)
        
        // Cột 28-29: Bãi tập (Số lượng + Diện tích)
        'training_ground_count',      // Cột 28: Bãi tập (Số lượng)
        'training_ground_area',       // Cột 29: Bãi tập (Diện tích)
    ];

    protected $casts = [
        'academic_year' => 'string',
        'ward_id' => 'integer',
        'province_id' => 'integer',
        'district_id' => 'integer',
        'school_id' => 'integer',
        'total_classes' => 'integer',
        'combined_classes' => 'integer',
        'solid_classrooms' => 'integer',
        'semi_solid_classrooms' => 'integer',
        'temporary_classrooms' => 'integer',
        'rented_classrooms' => 'integer',
        'classroom_class_ratio' => 'decimal:2',
        'principal_rooms_count' => 'integer',
        'vice_principal_rooms_count' => 'integer',
        'office_rooms_count' => 'integer',
        'medical_rooms_count' => 'integer',
        'youth_union_rooms_count' => 'integer',
        'meeting_rooms_count' => 'integer',
        'meeting_rooms_area' => 'decimal:2',
        'library_rooms_count' => 'integer',
        'library_rooms_area' => 'decimal:2',
        'equipment_rooms_count' => 'integer',
        'equipment_rooms_area' => 'decimal:2',
        'teacher_rooms_count' => 'integer',
        'teacher_rooms_area' => 'decimal:2',
        'student_rooms_count' => 'integer',
        'student_rooms_area' => 'decimal:2',
        'playground_count' => 'integer',
        'playground_area' => 'decimal:2',
        'training_ground_count' => 'integer',
        'training_ground_area' => 'decimal:2',
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
