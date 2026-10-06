<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','geo_unit_id','scheme',
        'line1','line2','postal_code','effective_at',
        'snapshot_old','snapshot_new',
    ];

    protected $casts = [
        'effective_at' => 'date',
    ];

    public function user() { return $this->belongsTo(\App\Models\User::class); }
    public function geo()  { return $this->belongsTo(GeoUnit::class, 'geo_unit_id'); }
}
