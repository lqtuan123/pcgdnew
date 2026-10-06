<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserRole;
use App\Models\GeoUnit;
use App\Models\School;

class TestRoleBasedUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lấy các role đã tạo
        $adminRole = UserRole::where('name', 'admin')->first();
        
        if (!$adminRole) {
            $this->command->error('Vui lòng chạy RoleSeeder trước khi chạy TestRoleBasedUserSeeder!');
            return;
        }

        // Lấy dữ liệu địa lý để tạo tài khoản
        $province = GeoUnit::where('type_level', 1)->first();
        $ward = GeoUnit::where('type_level', 3)->first();
        $school = School::first();

        if (!$province || !$ward || !$school) {
            $this->command->error('Không tìm thấy dữ liệu địa lý cần thiết!');
            return;
        }

        // Tạo tài khoản Province Level (Cấp tỉnh)
        $provinceUser = User::updateOrCreate(
            ['email' => 'province@test.com'],
            [
                'name' => 'Nguyễn Văn Tỉnh',
                'password' => Hash::make('province123'),
                'role_id' => $adminRole->id,
                'role_level' => 'province',
                'province_id' => $province->id,
                'ward_id' => null,
                'school_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Tạo tài khoản Ward Level (Cấp xã/phường)
        $wardUser = User::updateOrCreate(
            ['email' => 'ward@test.com'],
            [
                'name' => 'Trần Thị Xã',
                'password' => Hash::make('ward123'),
                'role_id' => $adminRole->id,
                'role_level' => 'ward',
                'province_id' => $province->id,
                'ward_id' => $ward->id,
                'school_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Tạo tài khoản School Level (Cấp trường)
        $schoolUser = User::updateOrCreate(
            ['email' => 'school@test.com'],
            [
                'name' => 'Lê Văn Trường',
                'password' => Hash::make('school123'),
                'role_id' => $adminRole->id,
                'role_level' => 'school',
                'province_id' => $province->id,
                'ward_id' => $ward->id,
                'school_id' => $school->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Đã tạo thành công 3 tài khoản test với các vai trò khác nhau:');
        $this->command->line('');
        $this->command->line('1. Province Level (Cấp tỉnh):');
        $this->command->line('   Email: province@test.com');
        $this->command->line('   Password: province123');
        $this->command->line('   Phạm vi: ' . $province->name);
        $this->command->line('');
        $this->command->line('2. Ward Level (Cấp xã/phường):');
        $this->command->line('   Email: ward@test.com');
        $this->command->line('   Password: ward123');
        $this->command->line('   Phạm vi: ' . $ward->name . ' - ' . $province->name);
        $this->command->line('');
        $this->command->line('3. School Level (Cấp trường):');
        $this->command->line('   Email: school@test.com');
        $this->command->line('   Password: school123');
        $this->command->line('   Phạm vi: ' . $school->name . ' - ' . $ward->name . ' - ' . $province->name);
        $this->command->line('');
        $this->command->info('Quyền hạn:');
        $this->command->line('- Tất cả tài khoản đều có role ADMIN');
        $this->command->line('- Province Level: Có thể xem dữ liệu của toàn tỉnh');
        $this->command->line('- Ward Level: Chỉ có thể xem dữ liệu của xã/phường');
        $this->command->line('- School Level: Chỉ có thể xem dữ liệu của trường');
    }
}
