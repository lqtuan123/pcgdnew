<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChiTieuGiaTri extends Model
{
    protected $table = 'chi_tieu_gia_tri';

    protected $fillable = [
        'chuong_trinh_id',
        'chi_tieu_id',
        'khong_kk_dbkk',
        'vung_kk_dbkk',
        'thu_tu',
    ];

    protected $casts = [
        'khong_kk_dbkk' => 'decimal:2',
        'vung_kk_dbkk' => 'decimal:2',
        'thu_tu' => 'integer',
    ];

    /**
     * Giá trị thuộc một chương trình
     */
    public function chuongTrinh(): BelongsTo
    {
        return $this->belongsTo(ChuongTrinh::class, 'chuong_trinh_id');
    }

    /**
     * Giá trị thuộc một chỉ tiêu
     */
    public function chiTieu(): BelongsTo
    {
        return $this->belongsTo(ChiTieu::class, 'chi_tieu_id');
    }

    /**
     * Lấy giá trị theo vùng
     * 
     * @param bool $isKhoKhan Có phải vùng khó khăn không
     * @return float|null
     */
    public function getGiaTriTheoVung(bool $isKhoKhan = false): ?float
    {
        return $isKhoKhan ? $this->vung_kk_dbkk : $this->khong_kk_dbkk;
    }
}
