<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResidenceStatus extends Model
{
    protected $fillable = [
        'name',
    ];
    public function residences()
    {
        return $this->hasMany(Residence::class, 'residence_status_id');
    }
}
