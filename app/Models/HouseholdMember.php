<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HouseholdMember extends Model
{
    protected $fillable = ['household_id','person_id','role'];

    public function household() {
        return $this->belongsTo(Household::class);
    }

    public function person() {
        return $this->belongsTo(Person::class);
    }
}
