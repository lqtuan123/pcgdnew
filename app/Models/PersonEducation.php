<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonEducation extends Model
{
    protected $table = 'person_educations';

    protected $fillable = [
        'person_id',
        'education_level_id',
        'class_label',
        'hoc_hai_buoi','hoc_9_10_buoi_tuan','is_repeat_grade','education_block_id',
        'studying_district_id','school_id',
        'school_name_custom', 'studying_district_custom',
        'graduation_level_id','is_bo_tuc','graduation_year','trai_tuyen',
        'vocational_level_id','vocational_grad_year','graduation_certificate_no',
        'hoc_xong_lop','hoc_xong_nam',
        'bo_hoc_lop','bo_hoc_nam',
        'academic_year','note',
    ];

    protected $casts = [
        'trai_tuyen'            => 'boolean',
        'is_bo_tuc'            => 'boolean',
        'hoc_hai_buoi'         => 'boolean',
        'hoc_9_10_buoi_tuan'   => 'boolean',
        'is_repeat_grade'      => 'boolean',
        'is_trai_tuyen'        => 'boolean',
        'hoc_xong_lop'         => 'integer',
        'bo_hoc_lop'           => 'integer',
    ];

    // Quan hệ
    public function person()            { return $this->belongsTo(Person::class); }
    public function level()             { return $this->belongsTo(GraduationLevel::class, 'education_level_id'); }
    public function finishedGrade()     { return $this->belongsTo(EducationGrade::class, 'hoc_xong_lop'); }
    public function graduationLevel()   { return $this->belongsTo(GraduationLevel::class, 'graduation_level_id'); }
    public function vocationalLevel()   { return $this->belongsTo(VocationalLevel::class, 'vocational_level_id'); }
    public function school()            { return $this->belongsTo(School::class); }
    public function block()   { return $this->belongsTo(EducationBlock::class, 'education_block_id'); }
    

    // Hiển thị nhãn lớp kèm dấu * nếu có cờ 2 buổi / 9-10 buổi
    public function getClassLabelWithMarkerAttribute(): ?string
    {
        if (!$this->class_label) return null;
        $marker = ($this->hoc_hai_buoi || $this->hoc_9_10_buoi_tuan) ? '*' : '';
        return $this->class_label.$marker;
    }

    public function getSchoolNameAttribute(): ?string
    {
        return $this->school_name_custom ?: $this->school?->name;
    }

    public function getStudyingDistrictAttribute(): ?string
    {
        if (!empty($this->studying_district_custom)) {
            return $this->studying_district_custom;
        }
        return $this->school?->wardNew?->name ?? $this->school?->ward?->name ?? null;
    }
}
