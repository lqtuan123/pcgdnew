<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyMamnon extends Model
{
    protected $table = 'survey_mamnon';

    protected $fillable = [
        'person_id',
        'person_mamnon_id',
        'person_education_id',
        'survey_form_detail_id',
        'so_phieu',
        'academic_year',
        'editor_id',
        'is_deleted',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function personMamnon()
    {
        return $this->belongsTo(PersonMamnon::class);
    }

    public function personEducation()
    {
        return $this->belongsTo(PersonEducation::class);
    }

    public function surveyFormDetail()
    {
        return $this->belongsTo(SurveyFormDetail::class);
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    // Scope để lọc các record chưa bị xóa
    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    // Scope để lọc theo năm học
    public function scopeByAcademicYear($query, $academicYear)
    {
        return $query->where('academic_year', $academicYear);
       
    }

    // Scope để lọc theo khu vực
    public function scopeByWard($query, $wardId, $scheme = 'old')
    {
        return $query->whereHas('person', function($q) use ($wardId, $scheme) {
            if ($scheme === 'old') {
                $q->where('old_ward_id', $wardId);
            } else {
                $q->where('new_ward_id', $wardId);
            }
        });
    }

    // Scope để lọc theo làng/xóm
    public function scopeByVillage($query, $villageId)
    {
        return $query->whereHas('person', function($q) use ($villageId) {
            $q->where('village_id', $villageId);
        });
    }
}
