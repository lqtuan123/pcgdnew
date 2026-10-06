<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChuongTrinh extends Model
{
    protected $table = 'chuong_trinh';

    protected $fillable = [
        'ma_chuong_trinh',
        'ten_chuong_trinh',
        'mo_ta',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Danh sách chương trình cố định (đã gộp mức vào tên)
    public const DS_CHUONG_TRINH = [
        'pcgd_muc_1' => [
            'ten' => 'PCGD Mức 1',
            'mo_ta' => 'Phổ cập giáo dục - Mức 1',
        ],
        'pcgd_muc_2' => [
            'ten' => 'PCGD Mức 2',
            'mo_ta' => 'Phổ cập giáo dục - Mức 2',
        ],
        'pcgd_muc_3' => [
            'ten' => 'PCGD Mức 3',
            'mo_ta' => 'Phổ cập giáo dục - Mức 3',
        ],
        'xmc_muc_1' => [
            'ten' => 'XMC Mức 1',
            'mo_ta' => 'Xóa mù chữ - Mức 1',
        ],
        'xmc_muc_2' => [
            'ten' => 'XMC Mức 2',
            'mo_ta' => 'Xóa mù chữ - Mức 2',
        ],
        'pcgd_thcs' => [
            'ten' => 'PCGD THCS',
            'mo_ta' => 'Phổ cập giáo dục trung học cơ sở',
        ],
    ];

    /**
     * Một chương trình có nhiều giá trị chỉ tiêu
     */
    public function chiTieuGiaTris(): HasMany
    {
        return $this->hasMany(ChiTieuGiaTri::class, 'chuong_trinh_id')->orderBy('thu_tu');
    }

    /**
     * Lấy chỉ các chương trình đang hoạt động
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Khởi tạo dữ liệu chương trình cố định nếu chưa có
     */
    public static function initDefaultData()
    {
        foreach (self::DS_CHUONG_TRINH as $ma => $data) {
            self::firstOrCreate(
                ['ma_chuong_trinh' => $ma],
                [
                    'ten_chuong_trinh' => $data['ten'],
                    'mo_ta' => $data['mo_ta'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Lấy danh sách chỉ tiêu của chương trình này (với giá trị)
     */
    public function getChiTieusWithValues()
    {
        return $this->chiTieuGiaTris()->with('chiTieu')->get();
    }
}
