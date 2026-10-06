<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonSpecialCircumstance extends Model
{
    protected $fillable = [
        'person_id',
        'sc_id',
        'chi_tiet',
    ];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function specialCircumstance()
    {
        return $this->belongsTo(SpecialCircumstance::class, 'sc_id');
    }

    /* Scopes tiện dụng */
    public function scopeOfPerson($q, int $personId) { return $q->where('person_id', $personId); }
    public function scopeOfCircumstance($q, int $scId) { return $q->where('sc_id', $scId); }
}
