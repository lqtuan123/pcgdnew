<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SchoolEnrollmentArea extends Model
{
    use HasFactory;

    protected $fillable = ['school_id','geo_id','year'];

    public function school(){ return $this->belongsTo(School::class); }
    public function geoUnit(){ return $this->belongsTo(GeoUnit::class, 'geo_id'); }

    public function scopeYear($q, int $year){ return $q->where('year',$year); }
}