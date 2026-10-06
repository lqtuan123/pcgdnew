<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GdmnFinance extends Model
{
    use HasFactory;

    protected $table = 'gdmn_finance';
    
    protected $fillable = [
        'year', 'province_id', 'ward_id', 'main_item', 'sub_item', 'amount', 'unit', 'note'
    ];

    protected $casts = [
        'year' => 'integer',
        // Bỏ cast amount để lưu đúng format người dùng nhập (4, 4.1, 4,1)
    ];

    // Quan hệ với GeoUnit (tỉnh)
    public function province()
    {
        return $this->belongsTo(GeoUnit::class, 'province_id');
    }

    // Quan hệ với GeoUnit (xã)
    public function ward()
    {
        return $this->belongsTo(GeoUnit::class, 'ward_id');
    }

    // Danh sách mục chính
    public const MAIN_ITEMS = [
        1 => 'Tổng chi cho Giáo dục mầm non',
        2 => 'Tỷ lệ chi hoạt động chuyên môn GDMN trong NSTX',
        3 => 'Định mức chi thường xuyên cho trẻ 5 tuổi theo 239 (B.quân)',
        4 => 'Chi đầu tư xây dựng phòng học, phòng chức năng',
        5 => 'Mua thiết bị tối thiểu và thiết bị nội thất dùng chung',
        6 => 'Chi thực hiện chính sách cho trẻ em',
        7 => 'Chi thực hiện chính sách cho giáo viên mầm non',
    ];

    // Tiểu mục của từng mục
    public const SUB_ITEMS = [
        1 => [
            1 => 'Ngân sách thường xuyên',
            2 => 'Ngân sách đầu tư',
            3 => 'Ngân sách từ nguồn Chương trình mục tiêu, dự án',
            4 => 'Từ nguồn xã hội hóa',
        ],
        6 => [
            1 => 'Hỗ trợ chi phí học tập',
            2 => 'Hỗ trợ ăn trưa',
            3 => 'Hỗ trợ chính sách khác của TƯ/địa phương',
        ],
        7 => [
            1 => 'Hỗ trợ GV hợp đồng lao động công lập',
            2 => 'Hỗ trợ GV dân lập',
            3 => 'Hỗ trợ GV tư thục',
        ],
    ];

    // Đơn vị tính mặc định cho từng mục chính
    public const UNITS = [
        1 => 'Tr.đồng',
        2 => '%',  // Tỷ lệ phần trăm
        3 => 'Tr.đồng',
        4 => 'Tr.đồng',
        5 => 'Tr.đồng',
        6 => 'Tr.đồng',
        7 => 'Tr.đồng',
    ];

    public function getMainNameAttribute()
    {
        return self::MAIN_ITEMS[$this->main_item] ?? null;
    }

    public function getSubNameAttribute()
    {
        return self::SUB_ITEMS[$this->main_item][$this->sub_item] ?? null;
    }

    // Scopes
    public function scopeByYear($query, $year)
    {
        return $query->where('year', $year);
    }

    public function scopeByProvince($query, $provinceId)
    {
        return $query->where('province_id', $provinceId);
    }

    public function scopeByWard($query, $wardId)
    {
        return $query->where('ward_id', $wardId);
    }

    public function scopeByMainItem($query, $mainItem)
    {
        return $query->where('main_item', $mainItem);
    }
}
