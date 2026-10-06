<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GraduationLevelSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = [
            // Bạn có thể chỉnh lại min/max theo nhu cầu thực tế
            ['name' => 'MN',   'agemin' => 3,  'agemax' => 5,  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'TH',  'agemin' => 6,  'agemax' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'THCS',      'agemin' => 11, 'agemax' => 14, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'THPT',      'agemin' => 15, 'agemax' => 17, 'created_at' => $now, 'updated_at' => $now],

           
        ];

        // Chạy nhiều lần vẫn ổn: cập nhật agemin/agemax, updated_at nếu trùng name
        DB::table('graduation_levels')->upsert(
            $rows,
            ['name'],                    // unique key
            ['agemin', 'agemax', 'updated_at'] // các cột cập nhật khi trùng
        );
    }
}
