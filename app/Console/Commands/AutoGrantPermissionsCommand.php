<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserRole;
use App\Models\SystemFunction;
use App\Models\RoleFunctionPermission;

class AutoGrantPermissionsCommand extends Command
{
    protected $signature = 'permission:auto-grant 
                            {--function= : Chỉ cấp quyền cho function cụ thể}
                            {--role= : Chỉ cấp quyền cho role cụ thể}
                            {--force : Cập nhật quyền đã tồn tại}
                            {--dry-run : Chỉ hiển thị sẽ làm gì, không thực hiện}';

    protected $description = 'Tự động cấp quyền cho function mới hoặc function chưa có quyền';

    public function handle()
    {
        $functionCode = $this->option('function');
        $roleName = $this->option('role');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('⚠️  CHẠY THỬ - Không thực hiện thay đổi');
        }

        // Lấy function cần xử lý
        $functions = $this->getFunctions($functionCode);
        if ($functions->isEmpty()) {
            $this->error('Không tìm thấy function nào để xử lý');
            return;
        }

        // Lấy role cần xử lý
        $roles = $this->getRoles($roleName);
        if ($roles->isEmpty()) {
            $this->error('Không tìm thấy role nào để xử lý');
            return;
        }

        $this->info("🔍 Tìm thấy {$functions->count()} function và {$roles->count()} role");

        // Định nghĩa quyền mặc định
        $defaultPermissions = $this->getDefaultPermissions();

        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;

        foreach ($functions as $function) {
            $this->info("\n🔧 Xử lý function: {$function->name} ({$function->code})");

            foreach ($roles as $role) {
                $permissions = $defaultPermissions[$role->name] ?? $defaultPermissions['default'];
                
                // Kiểm tra quyền hiện tại
                $existingPermission = RoleFunctionPermission::where('role_id', $role->id)
                    ->where('function_id', $function->id)
                    ->first();

                if ($existingPermission) {
                    if ($force) {
                        if (!$dryRun) {
                            $existingPermission->update([
                                'can_view' => $permissions['view'],
                                'can_create' => $permissions['create'],
                                'can_edit' => $permissions['edit'],
                                'can_delete' => $permissions['delete'],
                            ]);
                        }
                        $this->line("   🔄 Cập nhật quyền cho role '{$role->name}': " . $this->formatPermissions($permissions));
                        $totalUpdated++;
                    } else {
                        $this->line("   ⏭️  Role '{$role->name}' đã có quyền, bỏ qua (dùng --force để cập nhật)");
                        $totalSkipped++;
                    }
                } else {
                    if (!$dryRun) {
                        RoleFunctionPermission::create([
                            'role_id' => $role->id,
                            'function_id' => $function->id,
                            'can_view' => $permissions['view'],
                            'can_create' => $permissions['create'],
                            'can_edit' => $permissions['edit'],
                            'can_delete' => $permissions['delete'],
                        ]);
                    }
                    $this->line("   ✅ Tạo quyền mới cho role '{$role->name}': " . $this->formatPermissions($permissions));
                    $totalCreated++;
                }
            }
        }

        $this->info("\n🎉 HOÀN THÀNH!");
        $this->info("📊 Thống kê:");
        $this->info("   - Đã tạo: {$totalCreated} quyền mới");
        $this->info("   - Đã cập nhật: {$totalUpdated} quyền");
        $this->info("   - Đã bỏ qua: {$totalSkipped} quyền");
        
        if ($dryRun) {
            $this->warn("⚠️  Đây là chế độ dry-run, không có thay đổi nào được thực hiện");
        }
    }

    private function getFunctions(?string $functionCode)
    {
        if ($functionCode) {
            return SystemFunction::where('code', $functionCode)
                ->where('is_active', true)
                ->get();
        }
        
        return SystemFunction::where('is_active', true)->get();
    }

    private function getRoles(?string $roleName)
    {
        if ($roleName) {
            return UserRole::where('name', $roleName)->get();
        }
        
        return UserRole::all();
    }

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
            'default' => [
                'view' => false,
                'create' => false,
                'edit' => false,
                'delete' => false,
            ],
        ];
    }

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
