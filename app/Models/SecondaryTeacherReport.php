<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\ReportTrait;

class SecondaryTeacherReport extends Model
{
    use HasFactory, ReportTrait;

    protected $table = 'secondary_teacher_report';

    protected $fillable = [
        'academic_year',
        'ward_id',
        'school_id',
        'school_name',
        'school_rank',
        'teacher_class_ratio',
        'total',
        'female',
        'ethnic',
        'party_member',
        'above_university',
        'university',
        'college',
        'secondary',
        'mathematics',
        'physics',
        'chemistry',
        'biology',
        'literature',
        'history',
        'geography',
        'english',
        'french',
        'russian',
        'other_language',
        'civic_education',
        'technology',
        'physical_education',
        'music',
        'art',
        'informatics',
        'tpt_team',
        'standard_good',
        'standard_quite',
        'standard_reach',
        'standard_not_reach',
    ];

    protected $casts = [
        'academic_year' => 'string',
        'ward_id' => 'integer',
        'school_id' => 'integer',
        'school_name' => 'string',
        'school_rank' => 'integer',
        'teacher_class_ratio' => 'decimal:2',
        'total' => 'integer',
        'female' => 'integer',
        'party_member' => 'integer',
        'above_university' => 'integer',
        'university' => 'integer',
        'college' => 'integer',
        'secondary' => 'integer',
        'mathematics' => 'integer',
        'physics' => 'integer',
        'chemistry' => 'integer',
        'biology' => 'integer',
        'literature' => 'integer',
        'history' => 'integer',
        'geography' => 'integer',
        'english' => 'integer',
        'french' => 'integer',
        'russian' => 'integer',
        'other_language' => 'integer',
        'civic_education' => 'integer',
        'technology' => 'integer',
        'physical_education' => 'integer',
        'music' => 'integer',
        'art' => 'integer',
        'informatics' => 'integer',
        'tpt_team' => 'integer',
        'standard_good' => 'integer',
        'standard_quite' => 'integer',
        'standard_reach' => 'integer',
        'standard_not_reach' => 'integer',
    ];

    /**
     * Get the ward that owns the record.
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(GeoUnit::class, 'ward_id');
    }

    /**
     * Get the school that owns the record.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
