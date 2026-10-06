<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserRole;

class SuperadminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tìm superadmin role
        $superadminRole = UserRole::where('name', 'superadmin')->first();
        
        if (!$superadminRole) {
            $this->command->error('Superadmin role not found. Please run RoleSeeder first.');
            return;
        }

        // Kiểm tra xem đã có superadmin user chưa
        $existingSuperadmin = User::where('role_id', $superadminRole->id)->first();
        
        if ($existingSuperadmin) {
            $this->command->info('Superadmin user already exists.');
            return;
        }

        // Tạo superadmin user
        $superadminUser = User::create([
            'name' => 'Super Administrator',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('admin123'),
            'role_id' => $superadminRole->id,
            'is_active' => true,
            'role_level' => null, // Superadmin không ràng buộc địa giới
        ]);

        $this->command->info('Superadmin user created successfully!');
        $this->command->info('Email: admin@gmail.com');
        $this->command->info('Password: admin123');
        $this->command->warn('Please change the password after first login!');
    }
}