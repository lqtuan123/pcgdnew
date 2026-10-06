<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'google_id',
        'avatar',
        'role_id',
        'is_active',
        'role_level',
        'province_id',
        'ward_id',
        'school_id',
        'assignable_roles',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'province_id' => 'integer',
            'ward_id' => 'integer',
            'school_id' => 'integer',
            'assignable_roles' => 'array',
        ];
    }

    // Quan hệ với UserRole
    public function userRole()
    {
        return $this->belongsTo(UserRole::class, 'role_id');
    }

    // Quan hệ với GeoUnit (province)
    public function province()
    {
        return $this->belongsTo(GeoUnit::class, 'province_id');
    }

    // Quan hệ với GeoUnit (ward)
    public function ward()
    {
        return $this->belongsTo(GeoUnit::class, 'ward_id');
    }

    // Quan hệ với School
    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }


    // Quan hệ với phân quyền chức năng (override role permissions)
    public function functionPermissions()
    {
        return $this->belongsToMany(SystemFunction::class, 'user_function_permissions', 'user_id', 'function_id')
            ->withPivot(['can_view', 'can_create', 'can_edit', 'can_delete'])
            ->withTimestamps();
    }

    /**
     * Quan hệ với cấu hình thập phân
     */
    public function decimalFormat()
    {
        return $this->hasOne(UserDecimalFormat::class);
    }

    /**
     * Lấy cấu hình thập phân của user
     */
    public function getDecimalFormat()
    {
        $userFormat = $this->decimalFormat;
        if ($userFormat && $userFormat->decimalFormat) {
            return $userFormat->decimalFormat;
        }
        
        // Trả về cấu hình mặc định nếu user chưa có cấu hình
        return DecimalFormat::getDefaultFormat();
    }







    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        // Chỉ superadmin (role_id=1) mới được coi là admin
        return $this->role_id == 1;
    }

    /**
     * Check if user is superadmin
     */
    public function isSuperadmin(): bool
    {
        return $this->userRole && $this->userRole->isSuperadmin();
    }

    /**
     * Check if user has system role
     */
    public function hasSystemRole(): bool
    {
        return $this->userRole && $this->userRole->isSystemRole();
    }

    /**
     * Check if user is province level
     */
    public function isProvince(): bool
    {
        return $this->role_level === 'province';
    }

    /**
     * Check if user is ward level
     */
    public function isWard(): bool
    {
        return $this->role_level === 'ward';
    }

    /**
     * Check if user is school level
     */
        public function isSchool(): bool
    {
        return $this->role_level === 'school';
    }

    /**
     * Check if user is regular user
     */
    public function isUser(): bool
    {
        return $this->userRole && $this->userRole->name === 'user';
    }

    /**
     * Check if user has admin access
     */
    public function hasAdminAccess(): bool
    {
        return $this->isAdmin();
    }


    /**
     * Kiểm tra user có quyền với chức năng cụ thể không
     */
    public function hasPermission(string $functionCode, string $action = 'view'): bool
    {
        // Superadmin có tất cả quyền
        if ($this->isSuperadmin()) {
            return true;
        }

        // Kiểm tra quyền cá nhân trước (override role permissions)
        $userPermission = $this->functionPermissions()
            ->where('code', $functionCode)
            ->first();
        // dd($userPermission );
        if ($userPermission) {
            return match($action) {
                'view' => $userPermission->pivot->can_view,
                'create' => $userPermission->pivot->can_create,
                'edit' => $userPermission->pivot->can_edit,
                'delete' => $userPermission->pivot->can_delete,
                default => false,
            };
        }

        // Nếu không có quyền cá nhân, kiểm tra quyền role
        if ($this->userRole) {
            return $this->userRole->hasPermission($functionCode, $action);
        }

        return false;
    }

    /**
     * Lấy tất cả quyền của user (bao gồm role permissions và user permissions)
     */
    public function getAllPermissions(): array
    {
        $permissions = [];

        // Lấy quyền từ role
        if ($this->userRole) {
            $permissions = $this->userRole->getAllPermissions();
        }

        // Override với quyền cá nhân
        foreach ($this->functionPermissions as $function) {
            $permissions[$function->code] = [
                'view' => (bool) $function->pivot->can_view,
                'create' => (bool) $function->pivot->can_create,
                'edit' => (bool) $function->pivot->can_edit,
                'delete' => (bool) $function->pivot->can_delete,
            ];
        }

        return $permissions;
    }

    /**
     * Kiểm tra user có thể tạo user không
     */
    public function canCreateUsers(): bool
    {
        if ($this->userRole) {
            return $this->userRole->canCreateUsers();
        }
        return $this->isAdmin();
    }

    /**
     * Kiểm tra user có thể phân quyền không
     */
    public function canAssignPermissions(): bool
    {
        if ($this->userRole) {
            return $this->userRole->canAssignPermissions();
        }
        return $this->isAdmin();
    }

    /**
     * Kiểm tra user có thể quản lý phân quyền địa giới không
     */
    public function canManageGeoPermissions(): bool
    {
        if ($this->userRole) {
            return $this->userRole->canManageGeoPermissions();
        }
        return $this->isAdmin();
    }

    /**
     * Lấy role level của user
     */
    public function getRoleLevel(): int
    {
        if ($this->userRole) {
            return $this->userRole->level;
        }
        return $this->isAdmin() ? 6 : 1;
    }

    /**
     * Lấy danh sách province IDs mà user có thể truy cập
     */
    public function getAccessibleProvinceIds(): array
    {
        // Superadmin có thể truy cập tất cả provinces
        if ($this->isSuperadmin()) {
            return GeoUnit::where('type_level', 1)->pluck('id')->toArray();
        }
        
        if ($this->isAdmin()) {
            return GeoUnit::where('type_level', 1)->pluck('id')->toArray();
        }
        
        if ($this->isProvince()) {
            return [$this->province_id];
        }
        
        if ($this->isWard()) {
            return [$this->province_id];
        }
        
        if ($this->isSchool()) {
            $school = School::find($this->school_id);
            return $school ? [$school->getProvinceId()] : [];
        }
        
        return [];
    }

    /**
     * Lấy danh sách ward IDs mà user có thể truy cập
     */
    public function getAccessibleWardIds(): array
    {
        // Superadmin có thể truy cập tất cả wards
        if ($this->isSuperadmin()) {
            return GeoUnit::where('type_level', 3)->pluck('id')->toArray();
        }
        
        if ($this->isAdmin()) {
            return GeoUnit::where('type_level', 3)->pluck('id')->toArray();
        }
        
        if ($this->isProvince()) {
            return GeoUnit::where('parent_id', $this->province_id)
                ->where('type_level', 3)
                ->pluck('id')
                ->toArray();
        }
        
        if ($this->isWard()) {
            return [$this->ward_id];
        }
        
        if ($this->isSchool()) {
            return [$this->ward_id];
        }
        
        return [];
    }

    /**
     * Lấy danh sách school IDs mà user có thể truy cập
     */
    public function getAccessibleSchoolIds(): array
    {
        // Superadmin có thể truy cập tất cả schools
        if ($this->isSuperadmin()) {
            return School::pluck('id')->toArray();
        }
        
        if ($this->isAdmin()) {
            return School::pluck('id')->toArray();
        }
        
        if ($this->isProvince()) {
            // Lấy tất cả ward codes thuộc tỉnh này
            $wardCodes = \App\Models\GeoUnit::where('parent_id', $this->province_id)
                ->where('type_level', 3)
                ->where('scheme', 'new')
                ->pluck('code')
                ->toArray();
            
            return School::whereIn('ward_code_new', $wardCodes)
                ->pluck('id')
                ->toArray();
        }
        
        if ($this->isWard()) {
            // Lấy ward code từ ward_id
            $ward = \App\Models\GeoUnit::find($this->ward_id);
            if (!$ward) {
                return [];
            }
            
            return School::where('ward_code_new', $ward->code)
                ->pluck('id')
                ->toArray();
        }
        
        if ($this->isSchool()) {
            return [$this->school_id];
        }
        
        return [];
    }

    /**
     * Kiểm tra user có thể truy cập province này không
     */
    public function canAccessProvince(int $provinceId): bool
    {
        // Superadmin có thể truy cập tất cả provinces
        if ($this->isSuperadmin()) return true;
        
        if ($this->isAdmin()) return true;
        
        return in_array($provinceId, $this->getAccessibleProvinceIds());
    }

    /**
     * Kiểm tra user có thể truy cập ward này không
     */
    public function canAccessWard(int $wardId): bool
    {
        // Superadmin có thể truy cập tất cả wards
        if ($this->isSuperadmin()) return true;
        
        if ($this->isAdmin()) return true;
        
        return in_array($wardId, $this->getAccessibleWardIds());
    }

    /**
     * Kiểm tra user có thể truy cập school này không
     */
    public function canAccessSchool(int $schoolId): bool
    {
        // Superadmin có thể truy cập tất cả schools
        if ($this->isSuperadmin()) return true;
        
        if ($this->isAdmin()) return true;
        
        return in_array($schoolId, $this->getAccessibleSchoolIds());
    }

    /**
     * Lấy province_id từ ward_id (tương tự như School model)
     */
    public function getProvinceIdFromWardId(): ?int
    {
        if (!$this->ward_id) {
            return null;
        }
        
        $ward = GeoUnit::find($this->ward_id);
        if (!$ward) {
            return null;
        }
        
        return $ward->getProvinceId();
    }

    /**
     * Lấy thông tin địa lý đầy đủ từ user (tương tự như School model)
     */
    public function getGeoInfo(): array
    {
        return [
            'province_id' => $this->getProvinceIdFromWardId(),
            'ward_id' => $this->ward_id,
            'school_id' => $this->school_id,
        ];
    }

    /**
     * Lấy danh sách ID có thể truy cập theo loại
     */
    public function getAccessibleIds(string $type): array
    {
        switch ($type) {
            case 'school':
                return $this->getAccessibleSchoolIds();
            case 'user':
                // Admin có thể xem tất cả user
                if ($this->isAdmin()) {
                    return User::pluck('id')->toArray();
                }
                // User thường chỉ có thể xem chính mình
                return [$this->id];
            default:
                return [];
        }
    }

    /**
     * Kiểm tra user có thể cấp quyền cho role khác không
     */
    public function canGrantRole(string $targetRole): bool
    {
        $currentLevel = $this->getRoleLevel();
        
        $targetRoleModel = UserRole::where('name', $targetRole)->first();
        if (!$targetRoleModel) return false;

        return $currentLevel >= $targetRoleModel->level;
    }


    /**
     * Get user's role display name
     */
    public function getRoleDisplayName(): string
    {
        if ($this->userRole) {
            return $this->userRole->display_name;
        }
        
        return match($this->role) {
            'admin' => 'Quản trị viên',
            'user' => 'Người dùng',
            default => 'Không xác định'
        };
    }

    /**
     * Kiểm tra user có thể xem user khác không theo quy tắc 3 cấp độ địa bàn
     * 
     * Quy tắc:
     * - Superadmin: Có thể xem tất cả users
     * - Cấp tỉnh (province_id = X, ward_id = null, school_id = null): Xem được tất cả user có province_id = X
     * - Cấp xã/phường (province_id = X, ward_id = Y, school_id = null): Chỉ xem được user có cùng province_id = X và ward_id = Y
     * - Cấp trường (province_id = X, ward_id = Y, school_id = Z): Chỉ xem được user có cùng province_id = X, ward_id = Y và school_id = Z
     * 
     * Lưu ý: Role_id không ảnh hưởng, trừ role_id=1 (admin) không hiển thị ở frontend
     */
    public function canSee(User $target): bool
    {
        // User có thể xem chính mình
        if ($this->id === $target->id) {
            return true;
        }

        // Superadmin có thể xem tất cả users
        if ($this->isSuperadmin()) {
            return true;
        }

        // Admin (role_id=1) có thể xem tất cả user
        if ($this->role_id == 1) {
            return true;
        }

        // Không cho phép xem user có role_id = 1 (admin) ở frontend (trừ admin xem admin)
        if ($target->role_id == 1) {
            return false;
        }

        // Kiểm tra cấp độ địa bàn của viewer
        if ($this->isProvinceLevel()) {
            // Cấp tỉnh: Xem được tất cả user có cùng province_id
            return $this->province_id === $target->province_id;
        }

        if ($this->isWardLevel()) {
            // Cấp xã/phường: Chỉ xem được user có cùng province_id và ward_id
            return $this->province_id === $target->province_id && 
                   $this->ward_id === $target->ward_id;
        }

        if ($this->isSchoolLevel()) {
            // Cấp trường: Chỉ xem được user có cùng province_id, ward_id và school_id
            return $this->province_id === $target->province_id && 
                   $this->ward_id === $target->ward_id && 
                   $this->school_id === $target->school_id;
        }

        // Nếu không có cấp độ địa bàn rõ ràng, không xem được ai
        return false;
    }

    /**
     * Kiểm tra user có phải cấp tỉnh không
     */
    public function isProvinceLevel(): bool
    {
        return $this->province_id !== null && 
               $this->ward_id === null && 
               $this->school_id === null;
    }

    /**
     * Kiểm tra user có phải cấp xã/phường không
     */
    public function isWardLevel(): bool
    {
        return $this->province_id !== null && 
               $this->ward_id !== null && 
               $this->school_id === null;
    }

    /**
     * Kiểm tra user có phải cấp trường không
     */
    public function isSchoolLevel(): bool
    {
        return $this->province_id !== null && 
               $this->ward_id !== null && 
               $this->school_id !== null;
    }

    /**
     * Kiểm tra user hiện tại có thể quản lý (sửa/xóa) user mục tiêu không
     * Dựa trên cấp độ địa lý: Tỉnh > Xã > Trường
     */
    public function canManageUser(User $target): bool
    {
        // Không thể quản lý chính mình
        if ($this->id === $target->id) {
            return false;
        }

        // Superadmin có thể quản lý tất cả
        if ($this->isSuperadmin()) {
            return true;
        }

        // Kiểm tra quyền quản lý người dùng
        if (!$this->hasPermission('quan_ly_nguoi_dung', 'edit') && 
            !$this->hasPermission('quan_ly_nguoi_dung', 'delete')) {
            return false;
        }

        // Cấp Tỉnh có thể quản lý Cấp Xã, Cấp Trường và người cùng Cấp Tỉnh trong cùng tỉnh
        if ($this->isProvinceLevel()) {
            // Kiểm tra target có cùng tỉnh không
            if ($target->province_id !== $this->province_id) {
                return false;
            }
            // Có thể quản lý Cấp Xã, Cấp Trường và cùng Cấp Tỉnh
            return $target->isWardLevel() || $target->isSchoolLevel() || $target->isProvinceLevel();
        }

        // Cấp Xã có thể quản lý Cấp Trường và người cùng Cấp Xã trong cùng xã
        if ($this->isWardLevel()) {
            // Kiểm tra target có cùng tỉnh và xã không
            if ($target->province_id !== $this->province_id || 
                $target->ward_id !== $this->ward_id) {
                return false;
            }
            // Có thể quản lý Cấp Trường và cùng Cấp Xã
            return $target->isSchoolLevel() || $target->isWardLevel();
        }

        // Cấp Trường có thể quản lý người cùng Cấp Trường trong cùng trường
        if ($this->isSchoolLevel()) {
            // Kiểm tra target có cùng tỉnh, xã và trường không
            if ($target->province_id !== $this->province_id || 
                $target->ward_id !== $this->ward_id ||
                $target->school_id !== $this->school_id) {
                return false;
            }
            // Có thể quản lý cùng Cấp Trường
            return $target->isSchoolLevel();
        }

        return false;
    }

    /**
     * Lấy thông tin role level và scope của user
     */
    public function getRoleLevelAndScope(): array
    {
        $roleInfo = [
            'role_name' => $this->userRole ? $this->userRole->display_name : 'Không xác định',
            'role_level' => $this->role_level,
            'scope' => null,
            'scope_name' => null,
            'scope_display' => null // Thêm trường hiển thị đầy đủ
        ];

        switch ($this->role_level) {
            case 'admin':
                $roleInfo['scope'] = 'Toàn hệ thống';
                $roleInfo['scope_name'] = 'Toàn hệ thống';
                $roleInfo['scope_display'] = 'Toàn hệ thống';
                break;
            case 'province':
                if ($this->province) {
                    $roleInfo['scope'] = 'Tỉnh/Thành phố';
                    $roleInfo['scope_name'] = $this->province->name;
                    $roleInfo['scope_display'] = $this->province->name;
                } else {
                    $roleInfo['scope'] = 'Tỉnh/Thành phố';
                    $roleInfo['scope_name'] = 'Chưa được phân quyền';
                    $roleInfo['scope_display'] = 'Chưa được phân quyền';
                }
                break;
            case 'ward':
                if ($this->ward) {
                    $roleInfo['scope'] = 'Xã/Phường';
                    $roleInfo['scope_name'] = $this->ward->name;
                    
                    // Hiển thị: Xã/Phường
                    $roleInfo['scope_display'] = $this->ward->name;
                } else {
                    $roleInfo['scope'] = 'Xã/Phường';
                    $roleInfo['scope_name'] = 'Chưa được phân quyền';
                    $roleInfo['scope_display'] = 'Chưa được phân quyền';
                }
                break;
            case 'school':
                if ($this->school) {
                    $roleInfo['scope'] = 'Trường học';
                    $roleInfo['scope_name'] = $this->school->name;
                    
                    // Hiển thị: Tên trường - Xã/Phường
                    $scopeParts = [$this->school->name];
                    $wardName = null;
                    if ($this->ward) {
                        $wardName = $this->ward->name;
                    } elseif ($this->school->wardUnitNew) {
                        $wardName = $this->school->wardUnitNew->name;
                    } elseif ($this->school->newWardUnit) {
                        $wardName = $this->school->newWardUnit->name;
                    } elseif ($this->school->wardUnit) {
                        $wardName = $this->school->wardUnit->name;
                    }

                    if ($wardName) {
                        $scopeParts[] = $wardName; 
                    }
                    $roleInfo['scope_display'] = implode(' - ', $scopeParts);
                } else {
                    $roleInfo['scope'] = 'Trường học';
                    $roleInfo['scope_name'] = 'Chưa được phân quyền';
                    $roleInfo['scope_display'] = 'Chưa được phân quyền';
                }
                break;
            default:
                $roleInfo['scope'] = 'Chưa được phân quyền';
                $roleInfo['scope_name'] = 'Chưa được phân quyền';
                $roleInfo['scope_display'] = 'Chưa được phân quyền';
        }

        return $roleInfo;
    }

    /**
     * Kiểm tra quyền phân cấp (4 cấp: admin > province > ward > school)
     * Đây là wrapper method cho PermissionService::hasHierarchicalPermission()
     */
    public function hasHierarchicalPermission(string $functionCode, string $action = 'view'): bool
    {
        return $this->hasPermission($functionCode, $action);
    }
}
