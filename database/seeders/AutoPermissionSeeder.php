<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\UserRole;
use App\Models\SystemFunction;
use App\Models\RoleFunctionPermission;

class AutoPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Bắt đầu tự động cấp quyền cho tất cả function...');

        // Lấy tất cả function đang hoạt động
        $functions = SystemFunction::where('is_active', true)->get();
        
        if ($functions->isEmpty()) {
            $this->command->warn('⚠️  Không tìm thấy function nào để cấp quyền');
            return;
        }

        // Lấy tất cả role
        $roles = UserRole::all();
        
        if ($roles->isEmpty()) {
            $this->command->warn('⚠️  Không tìm thấy role nào để cấp quyền');
            return;
        }

        $this->command->info("📊 Tìm thấy {$functions->count()} function và {$roles->count()} role");

        // Định nghĩa quyền mặc định cho từng role
        $defaultPermissions = $this->getDefaultPermissions();

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($functions as $function) {
            $this->command->info("🔧 Xử lý function: {$function->name} ({$function->code})");

            foreach ($roles as $role) {
                $permissions = $defaultPermissions[$role->name] ?? $defaultPermissions['default'];
                
                // Kiểm tra xem đã có quyền chưa
                $existingPermission = RoleFunctionPermission::where('role_id', $role->id)
                    ->where('function_id', $function->id)
                    ->first();

                if ($existingPermission) {
                    $this->command->line("   ⏭️  Role '{$role->name}' đã có quyền, bỏ qua");
                    $totalSkipped++;
                    continue;
                }

                // Tạo quyền mới
                RoleFunctionPermission::create([
                    'role_id' => $role->id,
                    'function_id' => $function->id,
                    'can_view' => $permissions['view'],
                    'can_create' => $permissions['create'],
                    'can_edit' => $permissions['edit'],
                    'can_delete' => $permissions['delete'],
                ]);

                $this->command->line("   ✅ Đã cấp quyền cho role '{$role->name}': " . $this->formatPermissions($permissions));
                $totalCreated++;
            }
        }

        $this->command->info("\n🎉 HOÀN THÀNH!");
        $this->command->info("📊 Thống kê:");
        $this->command->info("   - Đã tạo: {$totalCreated} quyền mới");
        $this->command->info("   - Đã bỏ qua: {$totalSkipped} quyền đã tồn tại");
        $this->command->info("   - Tổng function: {$functions->count()}");
        $this->command->info("   - Tổng role: {$roles->count()}");
    }

    /**
     * Định nghĩa quyền mặc định cho từng role
     */
    private function getDefaultPermissions(): array
    {
        return [
            'superadmin' => [
                'view' => true,
                'create' => true,
                'edit' => true,
                'delete' => true,
            ],
            'admin' => [
                'view' => true,
                'create' => true,
                'edit' => true,
                'delete' => true,
            ],
            'user' => [
                'view' => true,
                'create' => false,
                'edit' => false,
                'delete' => false,
            ],
            // Quyền mặc định cho role không xác định
            'default' => [
                'view' => false,
                'create' => false,
                'edit' => false,
                'delete' => false,
            ],
        ];
    }

    /**
     * Format quyền để hiển thị
     */
    private function formatPermissions(array $permissions): string
    {
        $formatted = [];
        foreach ($permissions as $action => $allowed) {
            if ($allowed) {
                $formatted[] = $action;
            }
        }
        return implode(', ', $formatted) ?: 'không có quyền';
    }
}
