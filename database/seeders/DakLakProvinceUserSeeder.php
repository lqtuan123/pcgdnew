<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserRole;
use App\Models\GeoUnit;

class DakLakProvinceUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lấy role admin
        $adminRole = UserRole::where('name', 'admin')->first();
        
        if (!$adminRole) {
            $this->command->error('Admin role not found. Please run RoleSeeder first.');
            return;
        }

        // Tìm tỉnh Đắk Lắk
        $dakLakProvince = GeoUnit::where('code', '66')
            ->where('scheme', 'new')
            ->where('type_level', 1)
            ->first();
            
        if (!$dakLakProvince) {
            $this->command->error('Tỉnh Đắk Lắk không tìm thấy trong hệ thống.');
            return;
        }

        // Kiểm tra xem đã có tài khoản tỉnh Đắk Lắk chưa
        $existingUser = User::where('email', 'daklak.province@phocapweb.com')->first();
        
        if ($existingUser) {
            $this->command->info('Tài khoản tỉnh Đắk Lắk đã tồn tại.');
            return;
        }

        // Tạo tài khoản cấp tỉnh Đắk Lắk
        $provinceUser = User::create([
            'name' => 'Quản lý Tỉnh Đắk Lắk',
            'email' => 'daklak.province@phocapweb.com',
            'password' => Hash::make('daklak123'),
            'role_id' => $adminRole->id,
            'is_active' => true,
            'role_level' => 'province',
            'province_id' => $dakLakProvince->id,
            'ward_id' => null,
            'school_id' => null,
            'email_verified_at' => now(),
        ]);

        $this->command->info('Đã tạo tài khoản cấp tỉnh Đắk Lắk thành công!');
        $this->command->info('Email: daklak.province@phocapweb.com');
        $this->command->info('Password: daklak123');
        $this->command->info('Quyền hạn: Chỉ xem dữ liệu của tỉnh Đắk Lắk');
        $this->command->warn('Vui lòng đổi mật khẩu sau lần đăng nhập đầu tiên!');
    }
}
