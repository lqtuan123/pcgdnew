<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SchoolType extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function schools()
    {
        return $this->hasMany(School::class);
    }
}
