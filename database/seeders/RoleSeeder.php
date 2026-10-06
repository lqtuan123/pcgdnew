<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo các role
        $roles = [
            [
                'name' => 'superadmin',
                'display_name' => 'Siêu quản trị viên',
                'description' => 'Có toàn quyền hệ thống, chỉ 1 tài khoản duy nhất có mọi quyền',
                'level' => 3,
                'is_system' => true, // Đánh dấu là role hệ thống
                'can_create_users' => true,
                'can_assign_permissions' => true,
                'can_manage_geo_permissions' => true,
            ],
            [
                'name' => 'admin',
                'display_name' => 'Quản trị viên',
                'description' => 'Có thể tạo tài khoản mới và có các chức năng được cấp',
                'level' => 2,
                'is_system' => false, // Role có thể quản lý qua CRUD
                'can_create_users' => true,
                'can_assign_permissions' => true,
                'can_manage_geo_permissions' => true,
            ],
            [
                'name' => 'user',
                'display_name' => 'Người dùng',
                'description' => 'Chỉ có các chức năng được cấp, không thể tạo tài khoản mới',
                'level' => 1,
                'is_system' => false, // Role có thể quản lý qua CRUD
                'can_create_users' => false,
                'can_assign_permissions' => false,
                'can_manage_geo_permissions' => false,
            ],
        ];

        foreach ($roles as $role) {
            DB::table('user_roles')->updateOrInsert(
                ['name' => $role['name']],
                $role
            );
        }
    }
}

