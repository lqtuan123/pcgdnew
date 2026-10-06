<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EducationalLevel;

class EducationalLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['name' => 'Mầm non', 'age_min' => 3, 'age_max' => 5],
            ['name' => 'Tiểu học', 'age_min' => 6, 'age_max' => 11],
            ['name' => 'Trung học cơ sở', 'age_min' => 11, 'age_max' => 15],
            ['name' => 'Trung học phổ thông', 'age_min' => 15, 'age_max' => 18],
        ];

        foreach ($levels as $level) {
            EducationalLevel::updateOrCreate(
                ['name' => $level['name']],
                $level
            );
        }
    }
}
