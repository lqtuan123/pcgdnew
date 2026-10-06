<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'title', 'issued_date', 'issuer_id', 'file_path', 'status'
    ];

    protected $casts = [
        'issued_date' => 'date',
    ];

    public function issuer() {
        return $this->belongsTo(Issuer::class);
    }
}
