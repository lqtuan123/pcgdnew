<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonLiteracy extends Model
{
    protected $table = 'person_literacy';

    protected $fillable = [
        'person_id',
        'dang_hoc_lop',
        'hoan_thanh_lop',
        'tai_mu_chu_muc',
    ];

    protected $casts = [
        'dang_hoc_lop'    => 'integer',
        'hoan_thanh_lop'  => 'integer',
        'tai_mu_chu_muc'  => 'integer', // đổi thành 'string' nếu dùng kiểu chuỗi
    ];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    /* Scopes tiện dụng */
    public function scopeDangHoc($q)      { return $q->whereNotNull('dang_hoc_lop'); }
    public function scopeDaHoanThanh($q)  { return $q->whereNotNull('hoan_thanh_lop'); }
    public function scopeTaiMuMuc($q, int $muc) { return $q->where('tai_mu_chu_muc', $muc); }
}
