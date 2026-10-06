<?php

namespace App\Traits;

use App\Models\UserRole;
use App\Models\RoleFunctionPermission;

trait AutoGrantPermissions
{
    /**
     * Tự động cấp quyền cho function mới
     */
    public static function bootAutoGrantPermissions()
    {
        // Khi tạo function mới
        static::created(function ($function) {
            if (method_exists($function, 'autoGrantPermissions')) {
                $function->autoGrantPermissions();
            }
        });
    }

    /**
     * Cấp quyền mặc định cho function
     */
    public function autoGrantPermissions()
    {
        $roles = UserRole::all();
        $defaultPermissions = $this->getDefaultPermissions();

        foreach ($roles as $role) {
            $permissions = $defaultPermissions[$role->name] ?? $defaultPermissions['default'];
            
            // Kiểm tra xem đã có quyền chưa
            $existingPermission = RoleFunctionPermission::where('role_id', $role->id)
                ->where('function_id', $this->id)
                ->first();

            if (!$existingPermission) {
                RoleFunctionPermission::create([
                    'role_id' => $role->id,
                    'function_id' => $this->id,
                    'can_view' => $permissions['view'],
                    'can_create' => $permissions['create'],
                    'can_edit' => $permissions['edit'],
                    'can_delete' => $permissions['delete'],
                ]);
            }
        }
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
            'default' => [
                'view' => false,
                'create' => false,
                'edit' => false,
                'delete' => false,
            ],
        ];
    }
}
