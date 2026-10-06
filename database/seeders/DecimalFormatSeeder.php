<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DecimalFormat;

class DecimalFormatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo cấu hình mặc định
        DecimalFormat::createDefaultFormats();
        
        $this->command->info('Đã tạo cấu hình hiển thị thập phân mặc định');
    }
}
