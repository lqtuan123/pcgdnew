<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserRole;
use App\Models\GeoUnit;

class EaKarWardUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lấy role user
        $userRole = UserRole::where('name', 'user')->first();
        
        if (!$userRole) {
            $this->command->error('User role not found. Please run RoleSeeder first.');
            return;
        }

        // Tìm xã Ea Kar
        $eaKarWard = GeoUnit::where('code', '24373')
            ->where('scheme', 'new')
            ->where('type_level', 3)
            ->first();
            
        if (!$eaKarWard) {
            $this->command->error('Xã Ea Kar không tìm thấy trong hệ thống.');
            return;
        }

        // Tìm tỉnh Đắk Lắk (parent của xã)
        $dakLakProvince = GeoUnit::where('id', $eaKarWard->parent_id)
            ->where('type_level', 1)
            ->first();
            
        if (!$dakLakProvince) {
            $this->command->error('Không thể xác định tỉnh cha của xã Ea Kar.');
            return;
        }

        // Kiểm tra xem đã có tài khoản xã Ea Kar chưa
        $existingUser = User::where('email', 'eakar.ward@phocapweb.com')->first();
        
        if ($existingUser) {
            $this->command->info('Tài khoản xã Ea Kar đã tồn tại.');
            return;
        }

        // Tạo tài khoản cấp xã Ea Kar
        $wardUser = User::create([
            'name' => 'Quản lý Xã Ea Kar',
            'email' => 'eakar.ward@phocapweb.com',
            'password' => Hash::make('eakar123'),
            'role_id' => $userRole->id,
            'is_active' => true,
            'role_level' => 'ward',
            'province_id' => $dakLakProvince->id,
            'ward_id' => $eaKarWard->id,
            'school_id' => null,
            'email_verified_at' => now(),
        ]);

        $this->command->info('Đã tạo tài khoản cấp xã Ea Kar thành công!');
        $this->command->info('Email: eakar.ward@phocapweb.com');
        $this->command->info('Password: eakar123');
        $this->command->info('Quyền hạn: Chỉ xem dữ liệu của xã Ea Kar');
        $this->command->warn('Vui lòng đổi mật khẩu sau lần đăng nhập đầu tiên!');
    }
}
