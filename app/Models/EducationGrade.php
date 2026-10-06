<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EducationGrade extends Model
{
    protected $fillable = ['code','name','sort_order','is_active'];
    protected $casts = ['is_active'=>'boolean','sort_order'=>'integer'];

   
}
