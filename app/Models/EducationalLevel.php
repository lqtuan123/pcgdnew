<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EducationalLevel extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'age_min', 'age_max',];

    public function schools()
    {
        return $this->hasMany(School::class);
    }
}
