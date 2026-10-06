<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use App\Traits\ReportTrait;

class ElementaryInfrastructureReport extends Model
{
    use HasFactory, ReportTrait;

    protected $table = 'elementary_infrastructure_reports';

    protected $fillable = [
        'academic_year',
        'ward_id',
        'school_id',
        'school_name',
        'school_branch',
        'school_rank',
        'number_of_schools',
        'total_classes',
        'combined_classes',
        'permanent_classrooms',
        'semi_permanent_classrooms',
        'temporary_classrooms',
        'rented_classrooms',
        'room_class_ratio',
        'principal_room',
        'vice_principal_room',
        'office_room',
        'medical_room',
        'youth_union_room',
        'meeting_room',
        'meeting_room_quantity',
        'meeting_room_area',
        'library_room',
        'library_room_quantity',
        'library_room_area',
        'equipment_room_quantity',
        'equipment_room_area',
        'laboratory_quantity',
        'laboratory_area',
        'teacher_toilet_quantity',
        'teacher_toilet_area',
        'student_toilet_quantity',
        'student_toilet_area',
        'playground_quantity',
        'playground_area',
        'practice_field_quantity',
        'practice_field_area',
    ];

    protected $casts = [
        'academic_year' => 'string',
        'school_rank' => 'integer',
        'number_of_schools' => 'integer',
        'school_branch' => 'integer',
        'total_classes' => 'integer',
        'combined_classes' => 'integer',
        'permanent_classrooms' => 'integer',
        'semi_permanent_classrooms' => 'integer',
        'temporary_classrooms' => 'integer',
        'rented_classrooms' => 'integer',
        'room_class_ratio' => 'decimal:2',
        'principal_room' => 'integer',
        'vice_principal_room' => 'integer',
        'office_room' => 'integer',
        'medical_room' => 'integer',
        'youth_union_room' => 'integer',
        'meeting_room_quantity' => 'integer',
        'meeting_room_area' => 'decimal:2',
        'library_room_quantity' => 'integer',
        'library_room_area' => 'decimal:2',
        'equipment_room_quantity' => 'integer',
        'equipment_room_area' => 'decimal:2',
        'laboratory_quantity' => 'integer',
        'laboratory_area' => 'decimal:2',
        'teacher_toilet_quantity' => 'integer',
        'teacher_toilet_area' => 'decimal:2',
        'student_toilet_quantity' => 'integer',
        'student_toilet_area' => 'decimal:2',
        'playground_quantity' => 'integer',
        'playground_area' => 'decimal:2',
        'practice_field_quantity' => 'integer',
        'practice_field_area' => 'decimal:2',
    ];

    public function ward(): BelongsTo
    {
        return $this->belongsTo(GeoUnit::class, 'ward_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function scopeByAcademicYear($query, $year)
    {
        return $query->where('academic_year', $year);
    }

    public function scopeByWard($query, $wardId)
    {
        return $query->where('ward_id', $wardId);
    }

    public function scopeBySchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeByProvince($query, $provinceId)
    {
        return $query->whereHas('ward', function($q) use ($provinceId) {
            $q->whereHas('parent', function($subQ) use ($provinceId) {
                $subQ->where('id', $provinceId);
            });
        });
    }

    public function scopeBySchoolName($query, $schoolName)
    {
        return $query->where('school_name', 'like', "%{$schoolName}%");
    }

    public function scopeBySchoolRank($query, $rank)
    {
        return $query->where('school_rank', $rank);
    }

    public function getTotalClassroomsAttribute()
    {
        return $this->permanent_classrooms + $this->semi_permanent_classrooms + $this->temporary_classrooms;
    }

    public function getTotalFunctionalRoomsAttribute()
    {
        return $this->principal_room + $this->vice_principal_room + $this->office_room + 
               $this->medical_room + $this->meeting_room + $this->library_room;
    }

    public function getTotalToiletsAttribute()
    {
        return $this->teacher_toilet_quantity + $this->student_toilet_quantity;
    }

    public function getTotalToiletAreaAttribute()
    {
        return $this->teacher_toilet_area + $this->student_toilet_area;
    }

    public function getTotalPlayAreaAttribute()
    {
        return $this->playground_area + $this->practice_field_area;
    }

    public function getPermanentClassroomRatioAttribute()
    {
        if ($this->total_classrooms == 0) {
            return 0;
        }
        return round(($this->permanent_classrooms / $this->total_classrooms) * 100, 2);
    }

    public function setRoomClassRatioAttribute($value)
    {
        $this->attributes['room_class_ratio'] = is_numeric($value) ? (float) $value : 0;
    }

    public function setSchoolNameAttribute($value)
    {
        $this->attributes['school_name'] = trim($value);
    }

    public function scopeByUserAccess($query, $user)
    {
        if ($user->isSuperadmin() || $user->isAdmin()) {
            return $query;
        }

        if ($user->isProvince()) {
            return $query->byProvince($user->province_id);
        }

        if ($user->isWard()) {
            return $query->byWard($user->ward_id);
        }

        if ($user->isSchool()) {
            return $query->bySchool($user->school_id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function scopeOrderBySchoolName($query, $direction = 'asc')
    {
        return $query->orderBy('school_name', $direction);
    }

    public function scopeOrderByTotalClassrooms($query, $direction = 'desc')
    {
        return $query->orderByRaw('(permanent_classrooms + semi_permanent_classrooms + temporary_classrooms) ' . $direction);
    }

    public function scopeOrderByAcademicYear($query, $direction = 'desc')
    {
        return $query->orderBy('academic_year', $direction);
    }
}


