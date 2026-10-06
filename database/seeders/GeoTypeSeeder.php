<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GeoTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['level' => 1, 'key' => 'province', 'variant' => 'vn', 'title_vi' => 'Tỉnh/Thành phố', 'short_vi' => 'Tỉnh/TP', 'use_prefix' => 1, 'is_default' => 1],
            ['level' => 2, 'key' => 'district', 'variant' => 'vn', 'title_vi' => 'Quận/Huyện/Thị xã/TP', 'short_vi' => 'QH/TX/TP', 'use_prefix' => 1, 'is_default' => 1],
            ['level' => 3, 'key' => 'ward',     'variant' => 'vn', 'title_vi' => 'Phường/Xã/Thị trấn', 'short_vi' => 'P/X/TT', 'use_prefix' => 1, 'is_default' => 1],
            ['level' => 4, 'key' => 'village', 'variant' => 'vn','title_vi'   => 'Thôn/Đội/Tổ', 'short_vi'   => 'Thôn',  'use_prefix' => 1,      'is_default' => 1,
            ]
        ];

        DB::table('geo_types')->upsert(
            $types,
            ['key','variant'],
            ['level','title_vi','short_vi','use_prefix','is_default']
        );
    }
}
