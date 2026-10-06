<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EducationBlock;

class EducationBlockSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            // Nhóm tuổi mầm non
            '3-12 tháng', '13-24 tháng', '25-36 tháng',
            '3-4 tuổi', '4-5 tuổi', '5-6 tuổi',

            // Khối/lớp phổ thông
            '1','2','3','4','5','6','7','8','9','10','11','12',

            // Khối định hướng/đào tạo nghề (theo ảnh)
            'TDN1','TDN2','TDN3','TDN3.5','TDN4',

            // Khối trung học chuyên nghiệp
            'THCN1','THCN2','THCN3','THCN3.5','THCN4',

            // Sinh viên
            'SV1','SV2','SV3','SV4','SV5','SV6','SV7',
        ];

        foreach ($names as $name) {
            EducationBlock::firstOrCreate(['name' => $name]);
        }
    }
}
