<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpecialCircumstance extends Model
{
    protected $fillable = [
        'name',
    ];
    public function personLinks()
    {
        return $this->hasMany(PersonSpecialCircumstance::class, 'sc_id');
    }

    public function people()
    {
        return $this->belongsToMany(Person::class, 'person_special_circumstances', 'sc_id', 'person_id')
                    ->withPivot(['chi_tiet'])
                    ->withTimestamps();
    }


}
