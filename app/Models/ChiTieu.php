<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChiTieu extends Model
{
    protected $table = 'chi_tieu';

    protected $fillable = [
        'ma_chi_tieu',
        'ten_chi_tieu',
        'mo_ta',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Danh sách chỉ tiêu cố định (từ bảng cũ PcgdThcsChiTieu)
    public const DS_CHI_TIEU = [
        'ty_le_tre_6_tuoi_vao_lop_1' => 'Tỷ lệ % trẻ 6 tuổi vào học lớp 1',
        'ty_le_tre_hoan_thanh_tieu_hoc' => 'Tỷ lệ % trẻ (11-14) tuổi hoàn thành chương trình tiểu học',
        'ty_le_hs_vao_lop_6' => 'Tỷ lệ % HS TN hoàn thành chương trình vào học lớp 6 (2 hệ)',
        'ty_le_hs_thcs_vua_qua' => 'Tỷ lệ % HS TN THCS (2 hệ) năm học (vừa qua)',
        'ty_le_thanh_thieu_nien_tn_thcs' => 'Tỷ lệ % thanh thiếu niên (15-18) tuổi TN.THCS (2 hệ)',
    ];

    // Giá trị mặc định cho PCGD THCS
    public const GIA_TRI_MAC_DINH_PCGD_THCS = [
        'ty_le_tre_6_tuoi_vao_lop_1' => [
            'khong_kk_dbkk' => 90.00,
            'vung_kk_dbkk' => 80.00,
        ],
        'ty_le_tre_hoan_thanh_tieu_hoc' => [
            'khong_kk_dbkk' => 80.00,
            'vung_kk_dbkk' => 70.00,
        ],
        'ty_le_hs_vao_lop_6' => [
            'khong_kk_dbkk' => 95.00,
            'vung_kk_dbkk' => 80.00,
        ],
        'ty_le_hs_thcs_vua_qua' => [
            'khong_kk_dbkk' => 90.00,
            'vung_kk_dbkk' => 75.00,
        ],
        'ty_le_thanh_thieu_nien_tn_thcs' => [
            'khong_kk_dbkk' => 80.00,
            'vung_kk_dbkk' => 70.00,
        ],
    ];

    /**
     * Một chỉ tiêu có nhiều giá trị (theo chương trình)
     */
    public function chiTieuGiaTris(): HasMany
    {
        return $this->hasMany(ChiTieuGiaTri::class, 'chi_tieu_id');
    }

    /**
     * Lấy chỉ các chỉ tiêu đang hoạt động
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Khởi tạo dữ liệu chỉ tiêu cố định nếu chưa có
     */
    public static function initDefaultData()
    {
        foreach (self::DS_CHI_TIEU as $ma => $ten) {
            self::firstOrCreate(
                ['ma_chi_tieu' => $ma],
                [
                    'ten_chi_tieu' => $ten,
                    'mo_ta' => null,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Lấy giá trị cho một chương trình cụ thể
     */
    public function getGiaTriForChuongTrinh($chuongTrinhId)
    {
        return $this->chiTieuGiaTris()->where('chuong_trinh_id', $chuongTrinhId)->first();
    }
}
