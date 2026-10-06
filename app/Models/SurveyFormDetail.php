<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyFormDetail extends Model
{
    protected $fillable = [
        'survey_form_id','person_id',
        'residence_id','person_education_id','literacy_id',
        'person_disability_id','person_special_circumstance_id','person_contact_id',
        'person_mamnon_id','note','is_deleted','academic_year'
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    /* Quan hệ */
    public function surveyForm()   { return $this->belongsTo(SurveyForm::class); }
    public function person()       { return $this->belongsTo(Person::class); }
    public function residence()    { return $this->belongsTo(Residence::class); }
    public function education()    { return $this->belongsTo(PersonEducation::class, 'person_education_id'); }
    public function literacy()     { return $this->belongsTo(PersonLiteracy::class, 'literacy_id'); }
    public function personDisability()
    {
        return $this->belongsTo(PersonDisability::class, 'person_disability_id');
    }
    public function specialCircumstance()
    {
        return $this->belongsTo(PersonSpecialCircumstance::class, 'person_special_circumstance_id');
    }
    public function contact()      { return $this->belongsTo(PersonContact::class, 'person_contact_id'); }
    public function personMamnon() { return $this->belongsTo(PersonMamnon::class, 'person_mamnon_id'); }

    /* Scopes tiện dụng */
    public function scopeNotDeleted($q) { return $q->where('is_deleted', false); }

    /* Truy xuất nhanh danh sách ID khuyết tật từ bản ghi PersonDisability (nếu có) */
    public function getDisabilityIdsAttribute(): array
    {
        return $this->personDisability?->disability_ids ?? [];
    }

    public function getHasCertificateAttribute(): ?bool
    {
        return $this->personDisability?->has_certificate;
    }

    public function getCanLearnAttribute(): ?bool
    {
        return $this->personDisability?->can_learn;
    }
   

    public function scopeOfYear($q, string $year)
    {
        return $q->where('academic_year', $year);
    }

    public function scopeCurrentYear($q)
    {
        return $q->ofYear(SurveyForm::currentAcademicYear());
    }
}
