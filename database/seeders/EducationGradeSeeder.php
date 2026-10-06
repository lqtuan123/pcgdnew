<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GraduationLevel;
use App\Models\EducationGrade;

class EducationGradeSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Đảm bảo có 4 khối cơ bản
        $blocks = [
            'MN' => ['MN3' => 'Mầm 3 tuổi', 'MN4' => 'Mầm 4 tuổi', 'MN5' => 'Mầm 5 tuổi'],
            'TH' => ['1' => 'Lớp 1', '2' => 'Lớp 2', '3' => 'Lớp 3', '4' => 'Lớp 4', '5' => 'Lớp 5'],
            'THCS' => ['6' => 'Lớp 6', '7' => 'Lớp 7', '8' => 'Lớp 8', '9' => 'Lớp 9'],
            'THPT' => ['10' => 'Lớp 10', '11' => 'Lớp 11', '12' => 'Lớp 12'],
        ];

        foreach ($blocks as $blockName => $grades) {
            $block = GraduationLevel::firstOrCreate(['name' => $blockName]);

            $i = 0;
            foreach ($grades as $code => $name) {
                EducationGrade::updateOrCreate(
                    ['education_level_id' => $block->id, 'code' => (string)$code],
                    ['name' => $name, 'sort_order' => $i++, 'is_active' => true]
                );
            }
        }
    }
}
