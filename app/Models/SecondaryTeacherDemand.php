<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecondaryTeacherDemand extends Model
{
    protected $table = 'secondary_teacher_demand';

    protected $fillable = [
        'academic_year',
        'ward_id',
        'school_id',
        'school_name',
        'total',
        'female',
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
    ];

    protected $casts = [
        'academic_year' => 'string',
        'ward_id' => 'integer',
        'school_id' => 'integer',
        'school_name' => 'string',
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