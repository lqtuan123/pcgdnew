<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GeoTypeLevel4Seeder extends Seeder
{
    public function run(): void
    {
        DB::table('geo_types')->updateOrInsert(
            ['level' => 4, 'key' => 'village', 'variant' => 'vn'],
            [
                'title_vi'   => 'Thôn/Đội/Tổ',
                'short_vi'   => 'Thôn',
                'use_prefix' => 1,
                'is_default' => 1,
            ]
        );
    }
}
