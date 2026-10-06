<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserRole;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lấy các role đã tạo
        $superadminRole = UserRole::where('name', 'superadmin')->first();
        

        if (!$superadminRole) {
            $this->command->error('Vui lòng chạy RoleSeeder trước khi chạy TestUserSeeder!');
            return;
        }

        // Tạo tài khoản Superadmin
        $superadmin = User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin123'),
                'role_id' => $superadminRole->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

      
    }
}