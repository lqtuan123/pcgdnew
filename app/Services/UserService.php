<?php

namespace App\Services;

use App\Models\User;
use App\Models\GeoUnit;
use App\Models\UserRole;
use App\Models\School;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UserService
{
    private array $dropdownRanges = [];

    /**
     * Đăng nhập admin với email và password (sử dụng quy tắc 3 cấp độ địa bàn)
     */
    public function loginAdmin(string $email, string $password, bool $remember = false): bool
    {
        $user = User::where('email', $email)->where('role_id', 1)->first();
        
        if (!$user || !Hash::check($password, $user->password)) {
            return false;
        }

        if ($user->school_id && !$user->isSuperadmin()) {
            $school = School::find($user->school_id);
            if ($school && $school->is_dissolved) {
                throw ValidationException::withMessages([
                    'email' => 'Trường học thuộc tài khoản này đã bị giải thể. Không thể đăng nhập vào hệ thống.',
                ]);
            }
        }

        Auth::login($user, $remember);
        return true;
    }

    /**
     * Đăng nhập với email và password (legacy method)
     */
    public function login(string $email, string $password, bool $remember = false): bool
    {
        $user = User::where('email', $email)->first();
        if ($user && $user->school_id && !$user->isSuperadmin()) {
            $school = School::find($user->school_id);
            if ($school && $school->is_dissolved) {
                throw ValidationException::withMessages([
                    'email' => 'Trường học thuộc tài khoản này đã bị giải thể. Không thể đăng nhập vào hệ thống.',
                ]);
            }
        }

        $credentials = [
            'email' => $email,
            'password' => $password,
        ];

        return Auth::attempt($credentials, $remember);
    }

    /**
     * Đăng xuất
     */
    public function logout(): void
    {
        Auth::logout();
    }

    /**
     * Tạo user mới
     */
    public function createUser(array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Đảm bảo các trường role_level, province_id, ward_id, school_id được lưu
        $userData = array_merge($data, [
            'role_level' => $data['role_level'] ?? null,
            'province_id' => $data['province_id'] ?? null,
            'ward_id' => $data['ward_id'] ?? null,
            'school_id' => $data['school_id'] ?? null,
        ]);

        return User::create($userData);
    }

    /**
     * Tìm user theo ID
     */
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Tìm user theo email
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Tìm user theo Google ID
     */
    public function findByGoogleId(string $googleId): ?User
    {
        return User::where('google_id', $googleId)->first();
    }

    /**
     * Cập nhật thông tin user
     */
    public function updateUser(User $user, array $data): bool
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return $user->update($data);
    }

    /**
     * Lấy user hiện tại đang đăng nhập
     */
    public function getCurrentUser(): ?User
    {
        return Auth::user();
    }

    /**
     * Kiểm tra user đã đăng nhập chưa
     */
    public function isLoggedIn(): bool
    {
        return Auth::check();
    }

    /**
     * Xử lý đăng nhập Google OAuth
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = \Laravel\Socialite\Facades\Socialite::driver('google')->user();
            
            Log::info('Google OAuth callback received', [
                'email' => $googleUser->getEmail(),
                'name' => $googleUser->getName(),
                'id' => $googleUser->getId()
            ]);
            
            // Kiểm tra email có trong danh sách được phép không
            $user = User::where('email', $googleUser->getEmail())->first();
            
            if (!$user) {
                Log::warning('User not found for email: ' . $googleUser->getEmail());
                throw ValidationException::withMessages([
                    'email' => 'Email này chưa được cấp quyền đăng nhập. Vui lòng liên hệ admin.',
                ]);
            }

            if ($user->school_id && !$user->isSuperadmin()) {
                $school = School::find($user->school_id);
                if ($school && $school->is_dissolved) {
                    throw ValidationException::withMessages([
                        'email' => 'Trường học thuộc tài khoản này đã bị giải thể. Không thể đăng nhập vào hệ thống.',
                    ]);
                }
            }

            // Cập nhật thông tin từ Google
            $user->update([
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'name' => $googleUser->getName(),
            ]);

            Auth::login($user);
            Log::info('User logged in successfully', ['user_id' => $user->id]);
            return $user;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Google OAuth Error: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            throw ValidationException::withMessages([
                'email' => 'Đăng nhập Google thất bại: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Tạo user mới với quyền (sử dụng quy tắc 3 cấp độ địa bàn)
     */
    public function createUserWithRole(array $data, string $role = 'user'): User
    {
        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'] ?? null,
            'role' => $role,
        ];

        // Chỉ admin mới có password
        if ($role === 'admin' && isset($data['password'])) {
            $userData['password'] = Hash::make($data['password']);
        }

        return User::create($userData);
    }

    /**
     * Cập nhật quyền user (sử dụng quy tắc 3 cấp độ địa bàn)
     */
    public function updateUserRole(User $user, string $newRole, User $currentUser): bool
    {
        // Kiểm tra quyền xem user (theo quy tắc 3 cấp độ địa bàn)
        if (!$currentUser->canSee($user)) {
            return false;
        }

        return $user->update(['role' => $newRole]);
    }

    /**
     * Kiểm tra user hiện tại có thể cấp quyền này không
     * Sử dụng quy tắc 3 cấp độ địa bàn, role_id không ảnh hưởng
     */
    public function canGrantRole(User $currentUser, string $targetRole): bool
    {
        // Với quy tắc mới, chỉ cần kiểm tra quyền xem user
        // Không cần kiểm tra role hierarchy nữa
        return true; // Tạm thời cho phép tất cả, có thể điều chỉnh sau
    }

    /**
     * Lấy danh sách user theo quyền và phạm vi địa giới (chỉ dùng quy tắc 3 cấp độ địa bàn)
     * Role_id không ảnh hưởng đến việc hiển thị (trừ role_id=1 không hiển thị)
     */
    public function getUsersByRole(string $role = null): \Illuminate\Database\Eloquent\Collection
    {
        $currentUser = $this->getCurrentUser();
        
        // Nếu không có user đăng nhập, trả về collection rỗng
        if (!$currentUser) {
            return collect();
        }
        
        // Lấy tất cả user và lọc theo quyền xem (chỉ dùng quy tắc 3 cấp độ địa bàn)
        $allUsers = User::with(['userRole', 'province', 'ward', 'school'])->get();
        
        $accessibleUsers = $allUsers->filter(function($user) use ($currentUser) {
            return $currentUser->canSee($user);
        });
        
        // Lọc theo role nếu có (nhưng role_id không ảnh hưởng đến quyền xem)
        if ($role) {
            $accessibleUsers = $accessibleUsers->filter(function($user) use ($role) {
                return $user->userRole && $user->userRole->name === $role;
            });
        }
        
        return $accessibleUsers->sortByDesc('created_at');
    }
    
    /**
     * Lấy danh sách người dùng trong phạm vi địa giới của user hiện tại
     * Sử dụng quy tắc 3 cấp độ địa bàn mới (province_id, ward_id, school_id)
     */
    private function getUsersInGeoScope(User $currentUser): \Illuminate\Database\Eloquent\Collection
    {
        // Sử dụng logic mới với quy tắc 3 cấp độ địa bàn
        $allUsers = User::with(['userRole', 'province', 'ward', 'school'])->get();
        
        return $allUsers->filter(function($user) use ($currentUser) {
            return $currentUser->canSee($user);
        });
    }
    
    /**
     * Lấy ID của tất cả người dùng trong tỉnh (bao gồm xã/phường con)
     */
    
    /**
     * Lấy tất cả đơn vị con của một đơn vị địa giới
     */
    private function getAllChildUnits(GeoUnit $parentUnit): \Illuminate\Database\Eloquent\Collection
    {
        $childUnits = collect();
        $queue = collect([$parentUnit]);
        
        while ($queue->isNotEmpty()) {
            $currentUnit = $queue->shift();
            $children = GeoUnit::where('parent_id', $currentUnit->id)->get();
            
            foreach ($children as $child) {
                $childUnits->push($child);
                $queue->push($child);
            }
        }
        
        // Chuyển đổi từ Support Collection sang Eloquent Collection
        return GeoUnit::whereIn('id', $childUnits->pluck('id'))->get();
    }


    /**
     * Lấy thống kê user theo phạm vi địa giới (sử dụng quy tắc 3 cấp độ địa bàn)
     */
    public function getUserStatsByGeoScope(): array
    {
        $currentUser = $this->getCurrentUser();
        
        if (!$currentUser) {
            return [
                'total' => 0,
                'by_role' => [],
                'geo_permission' => null
            ];
        }

        // Sử dụng logic mới với quy tắc 3 cấp độ địa bàn
        $manageableUsers = $this->getUsersInGeoScope($currentUser);
        $geoPermission = $currentUser->getGeoPermissionDisplay();

        return [
            'total' => $manageableUsers->count(),
            'by_role' => [
                'admin' => $manageableUsers->where('role_id', 1)->count(),
                'user' => $manageableUsers->where('role_id', '!=', 1)->count(),
            ],
            'geo_permission' => $geoPermission
        ];
    }

    /**
     * Import users từ file Excel
     */
    public function import(string $uploadedPath, PermissionService $permissionService, GeoService $geo): array
    {
        // Kiểm tra quyền admin
        $currentUser = $this->getCurrentUser();
        if (!$currentUser || ($currentUser->role_id != 1 && $currentUser->role_id != 2)) {
            throw ValidationException::withMessages([
                'file' => 'Bạn không có quyền import người dùng.'
            ]);
        }

        // Tránh hết thời gian với file lớn
        @ini_set('memory_limit', '-1');
        @set_time_limit(0);
        DB::disableQueryLog();

        // Đọc Excel
        $spreadsheet = IOFactory::load($uploadedPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true); // keys: A,B,C,...

        if (count($rows) < 2) {
            throw ValidationException::withMessages([
                'file' => 'Tệp không có dữ liệu.'
            ]);
        }

        // Dò hàng tiêu đề trong 15 dòng đầu
        [$headers, $dataRows, $headerRowIndex] = $this->extractHeader($rows, 15);
        $idx = fn(string $name) => $headers[$name] ?? null;

        // Các cột chuẩn
        $colName = $idx('ho_ten');
        $colEmail = $idx('email');
        $colPhone = $idx('so_dien_thoai');
        $colRole = $idx('vai_tro');
        $colRoleLevel = $idx('cap_do_vai_tro');
        $colProvince = $idx('tinh_thanh');
        $colWard = $idx('phuong_xa');
        $colSchool = $idx('truong_hoc');
        $colPassword = $idx('mat_khau');

        if (is_null($colName) || is_null($colEmail) || is_null($colRole)) {
            throw ValidationException::withMessages([
                'file' => 'Không tìm thấy hàng tiêu đề phù hợp: cần có "Họ tên", "Email" và "Vai trò".'
            ]);
        }

        $stats = ['total' => 0, 'created' => 0, 'updated' => 0, 'errors' => []];

        foreach ($dataRows as $i => $row) {
            $stats['total']++;
            $excelRowNo = $headerRowIndex + 1 + ($i + 1);

            $name = trim((string)($row[$colName] ?? ''));
            $email = trim((string)($row[$colEmail] ?? ''));
            $role = trim((string)($row[$colRole] ?? ''));

            if ($name === '' || $email === '' || $role === '') {
                $stats['errors'][] = "Dòng {$excelRowNo}: thiếu họ tên, email hoặc vai trò.";
                continue;
            }

            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stats['errors'][] = "Dòng {$excelRowNo}: email không hợp lệ.";
                continue;
            }

            // Kiểm tra email đã tồn tại
            $existingUser = User::where('email', $email)->first();
            if ($existingUser) {
                $stats['errors'][] = "Dòng {$excelRowNo}: email đã tồn tại.";
                continue;
            }

            // Lấy thông tin vai trò
            $userRole = UserRole::where('name', $role)->first();
            if (!$userRole) {
                $stats['errors'][] = "Dòng {$excelRowNo}: vai trò '{$role}' không tồn tại.";
                continue;
            }

            // Kiểm tra quyền cấp quyền
            if (!$currentUser->canGrantRole($role)) {
                $stats['errors'][] = "Dòng {$excelRowNo}: bạn không có quyền cấp quyền '{$role}'.";
                continue;
            }

            try {
                // Xử lý thông tin địa lý
                $provinceId = null;
                $wardId = null;
                $schoolId = null;
                $roleLevelRaw = trim((string)($row[$colRoleLevel] ?? 'province'));
                $roleLevel = mb_strtolower($roleLevelRaw);
                if (!in_array($roleLevel, ['province', 'ward', 'school'], true)) {
                    $stats['errors'][] = "Dòng {$excelRowNo}: cấp độ vai trò '{$roleLevelRaw}' không hợp lệ (chỉ nhận province/ward/school).";
                    continue;
                }

                // Xử lý theo cấp độ vai trò
                if ($roleLevel === 'province') {
                    if (!$colProvince) {
                        $stats['errors'][] = "Dòng {$excelRowNo}: thiếu cột Tỉnh/Thành phố cho cấp province.";
                        continue;
                    }
                    $provinceName = trim((string)($row[$colProvince] ?? ''));
                    if ($provinceName === '') {
                        $stats['errors'][] = "Dòng {$excelRowNo}: thiếu Tỉnh/Thành phố cho cấp province.";
                        continue;
                    }
                    $province = GeoUnit::where('type_level', 1)
                        ->where('name', 'like', "%{$provinceName}%")
                        ->first();
                    if (!$province) {
                        $stats['errors'][] = "Dòng {$excelRowNo}: không tìm thấy Tỉnh/Thành phố '{$provinceName}'.";
                        continue;
                    }
                    $provinceId = $province->id;
                } elseif ($roleLevel === 'ward') {
                    if (!$colWard) {
                        $stats['errors'][] = "Dòng {$excelRowNo}: thiếu cột Phường/Xã cho cấp ward.";
                        continue;
                    }
                    $wardName = trim((string)($row[$colWard] ?? ''));
                    if ($wardName === '') {
                        $stats['errors'][] = "Dòng {$excelRowNo}: thiếu Phường/Xã cho cấp ward.";
                        continue;
                    }
                    $ward = GeoUnit::where('type_level', 3)
                        ->where('name', 'like', "%{$wardName}%")
                        ->first();
                    if (!$ward) {
                        $stats['errors'][] = "Dòng {$excelRowNo}: không tìm thấy Phường/Xã '{$wardName}'.";
                        continue;
                    }
                    $wardId = $ward->id;
                    $provinceId = $ward->getProvinceId();
                } elseif ($roleLevel === 'school') {
                    if (!$colSchool) {
                        $stats['errors'][] = "Dòng {$excelRowNo}: thiếu cột Trường học cho cấp school.";
                        continue;
                    }
                    $schoolName = trim((string)($row[$colSchool] ?? ''));
                    if ($schoolName === '') {
                        $stats['errors'][] = "Dòng {$excelRowNo}: thiếu Trường học cho cấp school.";
                        continue;
                    }
                    $school = School::where('name', 'like', "%{$schoolName}%")->first();
                    if (!$school) {
                        $stats['errors'][] = "Dòng {$excelRowNo}: không tìm thấy Trường học '{$schoolName}'.";
                        continue;
                    }
                    $schoolId = $school->id;
                    $geoInfo = $school->getGeoInfo();
                    $provinceId = $geoInfo['province_id'];
                    $wardId = $geoInfo['ward_id'];
                }

                // Tạo user mới
                $userData = [
                    'name' => $name,
                    'email' => $email,
                    'phone_number' => $colPhone ? trim((string)($row[$colPhone] ?? '')) : null,
                    'role_id' => $userRole->id,
                    'role_level' => $roleLevel,
                    'province_id' => $provinceId,
                    'ward_id' => $wardId,
                    'school_id' => $schoolId,
                    'is_active' => true,
                ];

                // Password: nếu không có/không nhập thì dùng mặc định password123
                $plainPassword = '';
                if ($colPassword) {
                    $plainPassword = trim((string)($row[$colPassword] ?? ''));
                }
                if ($plainPassword === '') {
                    $plainPassword = 'password123';
                }
                $userData['password'] = Hash::make($plainPassword);

                $user = User::create($userData);
                $stats['created']++;

                // (lấy danh sách function theo role_function_permissions của role đó)
                try {
                    $rolePermissions = $userRole->getAllPermissions(); // [functionCode => [view/create/edit/delete]]
                    $fullPermissions = [];
                    foreach ($rolePermissions as $functionCode => $perms) {
                        // Nếu role có ít nhất 1 quyền với function này thì cấp full 4 quyền
                        $hasAny = (bool)($perms['view'] ?? false) ||
                                  (bool)($perms['create'] ?? false) ||
                                  (bool)($perms['edit'] ?? false) ||
                                  (bool)($perms['delete'] ?? false);
                        if ($hasAny) {
                            $fullPermissions[$functionCode] = [
                                'view' => true,
                                'create' => true,
                                'edit' => true,
                                'delete' => true,
                            ];
                        }
                    }
                    if (!empty($fullPermissions)) {
                        $permissionService->assignUserPermissions($user->id, $fullPermissions);
                    }
                } catch (\Throwable $e) {
                    // Không làm fail import nếu gán quyền lỗi; vẫn tạo user thành công
                    Log::warning('Failed to assign full permissions for imported user', [
                        'user_id' => $user->id,
                        'role_id' => $userRole->id ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }

                Log::info('User imported successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $role,
                    'role_level' => $roleLevel
                ]);

            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                if (mb_strlen($msg) > 300) $msg = mb_substr($msg, 0, 300) . '…';
                $stats['errors'][] = "Dòng {$excelRowNo}: " . $msg;
            }

            if (($stats['total'] % 100) === 0) { 
                usleep(10000); 
            }
        }

        return $stats;
    }

    /**
     * Tải template Excel mẫu với dữ liệu động từ database
     */
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Import Users');

        // Lấy dữ liệu thực từ database
        $roles = UserRole::select('name', 'display_name')->get();
        $provinces = GeoUnit::where('type_level', 1)->select('name')->orderBy('name')->get();
        $wards = GeoUnit::where('type_level', 3)->select('name')->orderBy('name')->get();
        $schools = School::select('name')->orderBy('name')->get();

        // Headers
        $headers = [
            'A1' => 'Họ tên',
            'B1' => 'Email', 
            'C1' => 'Số điện thoại',
            'D1' => 'Vai trò',
            'E1' => 'Cấp độ vai trò',
            'F1' => 'Tỉnh/Thành phố',
            'G1' => 'Phường/Xã',
            'H1' => 'Trường học',
            'I1' => 'Mật khẩu',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Sample data với dữ liệu thực
        $sampleData = [
            ['A2' => 'Nguyễn Văn A', 'B2' => 'nguyenvana@example.com', 'C2' => '0123456789', 'D2' => 'user', 'E2' => 'province', 'F2' => $provinces->first()->name ?? 'Đắk Lắk', 'G2' => '', 'H2' => '', 'I2' => 'password123'],
            ['A3' => 'Trần Thị B', 'B3' => 'tranthib@example.com', 'C3' => '0987654321', 'D3' => 'user', 'E3' => 'ward', 'F3' => '', 'G3' => $wards->first()->name ?? 'Phường 1', 'H3' => '', 'I3' => 'password123'],
            ['A4' => 'Lê Văn C', 'B4' => 'levanc@example.com', 'C4' => '0369258147', 'D4' => 'user', 'E4' => 'school', 'F4' => '', 'G4' => '', 'H4' => $schools->first()->name ?? 'Trường THCS ABC', 'I4' => 'password123']
        ];

        foreach ($sampleData as $row) {
            foreach ($row as $cell => $value) {
                $sheet->setCellValue($cell, $value);
            }
        }

        // Sheet ẩn + named ranges + dropdown list (giống template front)
        $this->createUserImportDropdownDataSheet($spreadsheet);
        $this->addUserImportDropdownValidation($sheet);

        // Style headers
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Thêm hướng dẫn đơn giản
        $this->addSimpleInstructions($sheet, $roles, $provinces, $wards, $schools);

        $writer = new Xlsx($spreadsheet);
        $filename = 'user_import_template_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function createUserImportDropdownDataSheet(Spreadsheet $spreadsheet): void
    {
        $this->dropdownRanges = [];

        $dropdownSheet = $spreadsheet->createSheet();
        $dropdownSheet->setTitle('DropdownData');
        $dropdownSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        $col = 'A';

        // Vai trò
        $roles = DB::table('user_roles')->orderBy('id')->pluck('name')->toArray();
        if (count($roles) > 0) {
            foreach ($roles as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('UserRoleList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($roles))
            );
            $this->dropdownRanges['role'] = 'UserRoleList';
        }
        $col++;

        // Cấp độ vai trò
        $roleLevels = ['province', 'ward', 'school'];
        foreach ($roleLevels as $i => $name) {
            $dropdownSheet->setCellValue($col . ($i + 1), $name);
        }
        $spreadsheet->addNamedRange(
            new NamedRange('UserRoleLevelList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($roleLevels))
        );
        $this->dropdownRanges['role_level'] = 'UserRoleLevelList';
        $col++;

        // Tỉnh/Thành phố
        $provinces = DB::table('geo_units')->where('type_level', 1)->orderBy('name')->pluck('name')->toArray();
        if (count($provinces) > 0) {
            foreach ($provinces as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('ProvinceList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($provinces))
            );
            $this->dropdownRanges['province'] = 'ProvinceList';
        }
        $col++;

        // Phường/Xã
        $wards = DB::table('geo_units')->where('type_level', 3)->orderBy('name')->pluck('name')->toArray();
        if (count($wards) > 0) {
            foreach ($wards as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('WardList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($wards))
            );
            $this->dropdownRanges['ward'] = 'WardList';
        }
        $col++;

        // Trường học
        $schools = DB::table('schools')->orderBy('name')->pluck('name')->toArray();
        if (count($schools) > 0) {
            foreach ($schools as $i => $name) {
                $dropdownSheet->setCellValue($col . ($i + 1), $name);
            }
            $spreadsheet->addNamedRange(
                new NamedRange('SchoolList', $dropdownSheet, '$' . $col . '$1:$' . $col . '$' . count($schools))
            );
            $this->dropdownRanges['school'] = 'SchoolList';
        }
    }

    private function addUserImportDropdownValidation($sheet): void
    {
        // áp dụng dropdown cho 500 dòng để nhập thêm
        $startRow = 2;
        $maxRows = 500;
        $endRow = $startRow + $maxRows - 1;

        // D: Vai trò
        if (isset($this->dropdownRanges['role'])) {
            $validation = $sheet->getDataValidation('D' . $startRow . ':D' . $endRow);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['role']);
            $validation->setAllowBlank(false);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn vai trò từ danh sách có sẵn.');
        }

        // E: Cấp độ vai trò
        if (isset($this->dropdownRanges['role_level'])) {
            $validation = $sheet->getDataValidation('E' . $startRow . ':E' . $endRow);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['role_level']);
            $validation->setAllowBlank(false);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Lỗi nhập liệu');
            $validation->setError('Vui lòng chọn cấp độ vai trò từ danh sách có sẵn.');
        }

        // F: Tỉnh/Thành phố (cho province)
        if (isset($this->dropdownRanges['province'])) {
            $validation = $sheet->getDataValidation('F' . $startRow . ':F' . $endRow);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['province']);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
        }

        // G: Phường/Xã (cho ward)
        if (isset($this->dropdownRanges['ward'])) {
            $validation = $sheet->getDataValidation('G' . $startRow . ':G' . $endRow);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['ward']);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
        }

        // H: Trường học (cho school)
        if (isset($this->dropdownRanges['school'])) {
            $validation = $sheet->getDataValidation('H' . $startRow . ':H' . $endRow);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1($this->dropdownRanges['school']);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
        }
    }

    /**
     * Tạo các sheet tham chiếu chứa dữ liệu
     */
    private function createReferenceSheets($spreadsheet, $roles, $provinces, $wards, $schools)
    {
        // Sheet vai trò
        $roleSheet = $spreadsheet->createSheet();
        $roleSheet->setTitle($this->cleanSheetTitle('Vai tro'));
        $roleSheet->setCellValue('A1', 'Ma vai tro');
        $roleSheet->setCellValue('B1', 'Ten hien thi');
        
        $row = 2;
        foreach ($roles as $role) {
            $roleSheet->setCellValue('A' . $row, $role->name);
            $roleSheet->setCellValue('B' . $row, $role->display_name);
            $row++;
        }

        // Sheet tỉnh/thành phố
        $provinceSheet = $spreadsheet->createSheet();
        $provinceSheet->setTitle($this->cleanSheetTitle('Tinh Thanh pho'));
        $provinceSheet->setCellValue('A1', 'Ten tinh thanh pho');
        
        $row = 2;
        foreach ($provinces as $province) {
            $provinceSheet->setCellValue('A' . $row, $province->name);
            $row++;
        }

        // Sheet phường/xã
        $wardSheet = $spreadsheet->createSheet();
        $wardSheet->setTitle($this->cleanSheetTitle('Phuong Xa'));
        $wardSheet->setCellValue('A1', 'Ten phuong xa');
        
        $row = 2;
        foreach ($wards as $ward) {
            $wardSheet->setCellValue('A' . $row, $ward->name);
            $row++;
        }

        // Sheet trường học
        $schoolSheet = $spreadsheet->createSheet();
        $schoolSheet->setTitle($this->cleanSheetTitle('Truong hoc'));
        $schoolSheet->setCellValue('A1', 'Ten truong hoc');
        
        $row = 2;
        foreach ($schools as $school) {
            $schoolSheet->setCellValue('A' . $row, $school->name);
            $row++;
        }
    }

    /**
     * Làm sạch tên sheet để tránh ký tự không hợp lệ
     */
    private function cleanSheetTitle($title)
    {
        // Loại bỏ ký tự không hợp lệ cho Excel sheet title
        $title = preg_replace('/[\\\\\/\?\*\[\]]/', '', $title);
        
        // Giới hạn độ dài tối đa 31 ký tự
        $title = mb_substr($title, 0, 31);
        
        // Đảm bảo không bắt đầu hoặc kết thúc bằng dấu cách
        $title = trim($title);
        
        // Nếu tên rỗng, đặt tên mặc định
        if (empty($title)) {
            $title = 'Sheet';
        }
        
        return $title;
    }

    /**
     * Thêm validation cơ bản cho các cột có selectbox
     */
    private function addBasicValidation($sheet, $roles, $provinces, $wards, $schools)
    {
        // Vai trò (cột D)
        $roleValues = $roles->pluck('name')->implode(',');
        $this->addBasicDropdownValidation($sheet, 'D', 2, 50, $roleValues);

        // Cấp độ vai trò (cột E)
        $this->addBasicDropdownValidation($sheet, 'E', 2, 50, 'province,ward,school');

        // Tỉnh/Thành phố (cột F)
        $provinceValues = $provinces->pluck('name')->implode(',');
        $this->addBasicDropdownValidation($sheet, 'F', 2, 50, $provinceValues);

        // Phường/Xã (cột G)
        $wardValues = $wards->pluck('name')->implode(',');
        $this->addBasicDropdownValidation($sheet, 'G', 2, 50, $wardValues);

        // Trường học (cột H)
        $schoolValues = $schools->pluck('name')->implode(',');
        $this->addBasicDropdownValidation($sheet, 'H', 2, 50, $schoolValues);
    }

    /**
     * Thêm dropdown validation cơ bản cho một cột
     */
    private function addBasicDropdownValidation($sheet, $column, $startRow, $endRow, $values)
    {
        // Giới hạn số lượng giá trị để tránh lỗi Excel
        $valueList = is_array($values) ? implode(',', array_slice($values, 0, 20)) : $values;
        
        // Chỉ áp dụng cho một vài ô đầu để test
        for ($row = $startRow; $row <= min($startRow + 5, $endRow); $row++) {
            $cell = $column . $row;
            
            try {
                $validation = $sheet->getDataValidation($cell);
                $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(true);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true);
                $validation->setErrorTitle('Lỗi nhập liệu');
                $validation->setError('Vui lòng chọn giá trị từ danh sách.');
                $validation->setPromptTitle('Chọn giá trị');
                $validation->setPrompt('Click vào ô để xem danh sách chọn.');
                $validation->setFormula1('"' . $valueList . '"');
            } catch (\Exception $e) {
                // Bỏ qua lỗi validation nếu có
                continue;
            }
        }
    }

    /**
     * Thêm hướng dẫn đơn giản
     */
    private function addSimpleInstructions($sheet, $roles, $provinces, $wards, $schools)
    {
        $row = 6;
        
        $sheet->setCellValue('A' . $row, 'HƯỚNG DẪN SỬ DỤNG:');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        
        $sheet->setCellValue('A' . $row, '1. Vai trò: ' . $roles->pluck('name')->implode(', '));
        $row++;
        
        $sheet->setCellValue('A' . $row, '2. Cấp độ vai trò: province, ward, school');
        $row++;
        
        $sheet->setCellValue('A' . $row, '3. Tỉnh/Thành phố: ' . $provinces->take(5)->pluck('name')->implode(', ') . '...');
        $row++;
        
        $sheet->setCellValue('A' . $row, '4. Phường/Xã: ' . $wards->take(5)->pluck('name')->implode(', ') . '...');
        $row++;
        
        $sheet->setCellValue('A' . $row, '5. Trường học: ' . $schools->take(5)->pluck('name')->implode(', ') . '...');
        $row++;
        
        $sheet->setCellValue('A' . $row, '6. Mật khẩu: Nếu để trống hệ thống sẽ dùng mặc định password123');
    }

    /**
     * Thêm hướng dẫn với dữ liệu thực (method cũ)
     */
    private function addInstructions($sheet, $roles, $provinces, $wards, $schools)
    {
        $row = 6;
        $sheet->setCellValue('A' . $row, 'Hướng dẫn:');
        $row++;
        
        $sheet->setCellValue('A' . $row, '- Họ tên: Bắt buộc');
        $row++;
        $sheet->setCellValue('A' . $row, '- Email: Bắt buộc, phải là email hợp lệ');
        $row++;
        $sheet->setCellValue('A' . $row, '- Số điện thoại: Tùy chọn');
        $row++;
        
        $sheet->setCellValue('A' . $row, '- Vai trò: Bắt buộc, chọn từ: ' . $roles->pluck('name')->implode(', '));
        $row++;
        $sheet->setCellValue('A' . $row, '- Cấp độ vai trò: province, ward, school');
        $row++;
        $sheet->setCellValue('A' . $row, '- Tỉnh/Thành phố: Chọn từ danh sách (cho cấp province)');
        $row++;
        $sheet->setCellValue('A' . $row, '- Phường/Xã: Chọn từ danh sách (cho cấp ward)');
        $row++;
        $sheet->setCellValue('A' . $row, '- Trường học: Chọn từ danh sách (cho cấp school)');
        $row++;
        $sheet->setCellValue('A' . $row, '- Mật khẩu: Tùy chọn, nếu để trống hệ thống sẽ dùng mặc định password123');
        $row++;
        
        $sheet->setCellValue('A' . $row, '');
        $row++;
        $sheet->setCellValue('A' . $row, 'Luu y: Su dung cac sheet tham chieu de xem danh sach day du cac gia tri co the chon.');
    }

    /**
     * Quét 1..$scan dòng đầu để tìm row header
     */
    private function extractHeader(array $rows, int $scan = 15): array
    {
        // PhpSpreadsheet::toArray() có thể trả về mảng row bắt đầu từ index 1,
        // nếu dùng array_slice trực tiếp sẽ dễ bị rớt mất dòng dữ liệu đầu tiên.
        // Re-index về 0..n-1 để offset hoạt động đúng.
        $rows = array_values($rows);

        $aliases = [
            'ho_ten' => ['ho_ten', 'ho ten', 'họ tên', 'ten', 'name', 'full_name'],
            'email' => ['email', 'e_mail', 'mail'],
            'so_dien_thoai' => ['so_dien_thoai', 'so dien thoai', 'số điện thoại', 'phone', 'phone_number', 'sdt'],
            'vai_tro' => ['vai_tro', 'vai tro', 'vai trò', 'role', 'user_role'],
            'cap_do_vai_tro' => ['cap_do_vai_tro', 'cap do vai tro', 'cấp độ vai trò', 'role_level', 'level'],
            'tinh_thanh' => ['tinh_thanh', 'tinh thanh', 'tỉnh thành', 'province', 'tinh'],
            'phuong_xa' => ['phuong_xa', 'phuong xa', 'phường xã', 'ward', 'phuong', 'xa'],
            'truong_hoc' => ['truong_hoc', 'truong hoc', 'trường học', 'school', 'truong'],
            'mat_khau' => ['mat_khau', 'mat khau', 'mật khẩu', 'password', 'pass']
        ];

        $max = min($scan, count($rows));
        for ($i = 0; $i < $max; $i++) {
            $candidate = $rows[$i] ?? [];
            if (!is_array($candidate)) continue;

            $map = [];
            foreach ($candidate as $colLetter => $label) {
                $norm = $this->normalizeHeaderKey((string)$label);
                if ($norm === '') continue;

                foreach ($aliases as $canonical => $vars) {
                    if (in_array($norm, $vars, true) && empty($map[$canonical])) {
                        $map[$canonical] = $colLetter;
                    }
                }
            }

            // Chỉ cần có 3 cột bắt buộc là ok
            if (!empty($map['ho_ten']) && !empty($map['email']) && !empty($map['vai_tro'])) {
                $dataRows = array_slice($rows, $i + 1);
                return [$map, $dataRows, $i];
            }
        }

        throw ValidationException::withMessages([
            'file' => 'Không tìm thấy hàng tiêu đề phù hợp trong 15 dòng đầu.'
        ]);
    }

    /**
     * Chuẩn hoá nhãn cột
     */
    private function normalizeHeaderKey(string $label): string
    {
        $s = trim(mb_strtolower($label));

        // Bỏ ký tự thừa
        $s = str_replace(['*','.',',',':',';','/','\\','(',')','[',']','{','}','|','"',"'",'`'],'', $s);
        // Chuẩn hoá khoảng trắng
        $s = preg_replace('/\s+/u', ' ', $s);
        // Bỏ dấu tiếng Việt đơn giản
        $from = ['à','á','ả','ã','ạ','ă','ằ','ắ','ẳ','ẵ','ặ','â','ầ','ấ','ẩ','ẫ','ậ','è','é','ẻ','ẽ','ẹ','ê','ề','ế','ể','ễ','ệ','ì','í','ỉ','ĩ','ị','ò','ó','ỏ','õ','ọ','ô','ồ','ố','ổ','ỗ','ộ','ơ','ờ','ớ','ở','ỡ','ợ','ù','ú','ủ','ũ','ụ','ư','ừ','ứ','ử','ữ','ự','ỳ','ý','ỷ','ỹ','ỵ','đ'];
        $to   = ['a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','e','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y','d'];
        $s = str_replace($from, $to, $s);

        // Chuẩn hóa phân cách
        $s = str_replace([' - ','-',' '], '_', $s);

        // Gom bớt biến thể phổ biến
        $s = preg_replace('/_+/','_', $s);
        return trim($s, '_');
    }
}
