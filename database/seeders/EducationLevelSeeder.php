<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GraduationLevel;

class EducationLevelSeeder extends Seeder
{
    public function run(): void
    {
        // Tùy địa phương bạn có thể thêm/bớt
        $levels = [
            'MN',
            'TH',
            'THCS',
            'THPT',
            
        ];

        foreach ($levels as $name) {
            GraduationLevel::updateOrCreate(['name' => $name], []);
        }
    }
}
