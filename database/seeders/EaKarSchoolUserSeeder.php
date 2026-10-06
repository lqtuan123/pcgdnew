<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserRole;
use App\Models\GeoUnit;
use App\Models\School;

class EaKarSchoolUserSeeder extends Seeder
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

        // Tìm một trường trong xã Ea Kar (ví dụ: trường đầu tiên)
        $schoolInEaKar = School::where('ward_code_new', '24373')
            ->where('is_dissolved', false)
            ->first();
            
        if (!$schoolInEaKar) {
            $this->command->error('Không tìm thấy trường nào trong xã Ea Kar.');
            $this->command->info('Tạo trường mẫu để demo...');
            
            // Tạo trường mẫu
            $schoolInEaKar = School::create([
                'code' => 'TH24373001',
                'name' => 'Trường Tiểu học Ea Kar',
                'ward_code_new' => '24373',
                'province_code_new' => '66',
                'is_dissolved' => false,
            ]);
            
            $this->command->info('Đã tạo trường mẫu: ' . $schoolInEaKar->name);
        }

        // Kiểm tra xem đã có tài khoản trường này chưa
        $existingUser = User::where('email', 'eakar.school@phocapweb.com')->first();
        
        if ($existingUser) {
            $this->command->info('Tài khoản trường Ea Kar đã tồn tại.');
            return;
        }

        // Tạo tài khoản cấp trường
        $schoolUser = User::create([
            'name' => 'Quản lý Trường ' . $schoolInEaKar->name,
            'email' => 'eakar.school@phocapweb.com',
            'password' => Hash::make('eakarschool123'),
            'role_id' => $userRole->id,
            'is_active' => true,
            'role_level' => 'school',
            'province_id' => $dakLakProvince->id,
            'ward_id' => $eaKarWard->id,
            'school_id' => $schoolInEaKar->id,
            'email_verified_at' => now(),
        ]);

        $this->command->info('Đã tạo tài khoản cấp trường thành công!');
        $this->command->info('Email: eakar.school@phocapweb.com');
        $this->command->info('Password: eakarschool123');
        $this->command->info('Trường: ' . $schoolInEaKar->name);
        $this->command->info('Quyền hạn: Chỉ xem dữ liệu của trường này');
        $this->command->warn('Vui lòng đổi mật khẩu sau lần đăng nhập đầu tiên!');
    }
}
