<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\UserRole;
use App\Models\SystemFunction;

class SuperadminPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lấy role superadmin
        $superadminRole = UserRole::where('name', 'superadmin')->first();
        
        if (!$superadminRole) {
            $this->command->error('Role superadmin không tồn tại!');
            return;
        }

        // Lấy function quan_ly_nguoi_dung
        $userManagementFunction = SystemFunction::where('code', 'quan_ly_nguoi_dung')->first();
        
        if (!$userManagementFunction) {
            $this->command->error('Function quan_ly_nguoi_dung không tồn tại!');
            return;
        }

        // Cấp toàn quyền cho superadmin với function quan_ly_nguoi_dung
        DB::table('role_function_permissions')->updateOrInsert(
            [
                'role_id' => $superadminRole->id,
                'function_id' => $userManagementFunction->id,
            ],
            [
                'can_view' => true,
                'can_create' => true,
                'can_edit' => true,
                'can_delete' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('Đã cấp quyền quan_ly_nguoi_dung cho superadmin thành công!');
    }
}