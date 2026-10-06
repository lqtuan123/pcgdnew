<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResidenceType extends Model
{
    protected $fillable = ['code','name','is_phai_pho_cap'];

    protected $casts = [
        'is_phai_pho_cap' => 'boolean',
    ];

    public function residences()
    {
        return $this->hasMany(Residence::class, 'residence_type_id');
    }

    // Scopes
    public function scopeMustCover($q)     { return $q->where('is_phai_pho_cap', true); }
    public function scopeNotMustCover($q)  { return $q->where('is_phai_pho_cap', false); }
    public function scopeCode($q, $code)   { return $q->where('code', $code); }
}
