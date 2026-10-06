<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Issuer extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code'];

    public function documents() {
        return $this->hasMany(Document::class);
    }
}
