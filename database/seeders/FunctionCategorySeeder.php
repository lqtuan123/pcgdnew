<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FunctionCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo các danh mục chức năng
        $categories = [
            [
                'code' => 'education',
                'name' => 'Giáo dục',
                'description' => 'Các chức năng liên quan đến giáo dục',
                'color' => '#3B82F6',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'household',
                'name' => 'Hộ gia đình',
                'description' => 'Các chức năng quản lý hộ gia đình',
                'color' => '#10B981',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'school',
                'name' => 'Trường học',
                'description' => 'Các chức năng quản lý trường học',
                'color' => '#F59E0B',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'code' => 'report',
                'name' => 'Báo cáo thống kê',
                'description' => 'Các chức năng báo cáo và thống kê',
                'color' => '#8B5CF6',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'code' => 'system',
                'name' => 'Quản lý hệ thống',
                'description' => 'Các chức năng quản lý hệ thống',
                'color' => '#EF4444',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'code' => 'reference',
                'name' => 'Danh mục tham chiếu',
                'description' => 'Các danh mục tham chiếu trong hệ thống',
                'color' => '#06B6D4',
                'sort_order' => 6,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            DB::table('function_categories')->updateOrInsert(
                ['code' => $category['code']],
                $category
            );
        }
    }
}

