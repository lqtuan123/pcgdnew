<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GeoUnitMapping extends Model
{
    use HasFactory;

    protected $fillable = ['old_unit_id','new_unit_id','relation','weight','note','effective_date'];
    protected $casts = ['weight' => 'float', 'effective_date' => 'date'];

    public function old(){ return $this->belongsTo(GeoUnit::class, 'old_unit_id'); }
    public function new(){ return $this->belongsTo(GeoUnit::class, 'new_unit_id'); }
}
