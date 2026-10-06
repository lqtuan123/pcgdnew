<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportUsersRequest;
use App\Services\UserService;
use App\Services\PermissionService;
use App\Services\GeoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UserImportController extends Controller
{
    public function __construct(
        protected UserService $userService,
        protected PermissionService $permissionService,
        protected GeoService $geo,
    ) {}

    // Trang upload đơn giản
    public function create()
    {
        // Kiểm tra quyền tạo người dùng
        $user = Auth::user();
        if (!$user || ($user->role_id != 1 && $user->role_id != 2)) {
            abort(403, 'Bạn không có quyền tạo người dùng mới.');
        }

        return view('admin.users.import');
    }

    // Xử lý import
    public function store(ImportUsersRequest $req)
    {
        // Kiểm tra quyền tạo người dùng
        $user = Auth::user();
        if (!$user || ($user->role_id != 1 && $user->role_id != 2)) {
            abort(403, 'Bạn không có quyền tạo người dùng mới.');
        }

        @set_time_limit(600);          // 10 phút
        @ini_set('memory_limit','512M');
        $path = $req->file('file')->getRealPath();

        $stats = $this->userService->import($path, $this->permissionService, $this->geo);

        return back()->with('import_stats', $stats);
    }

    // Tải template Excel mẫu
    public function downloadTemplate()
    {
        // Kiểm tra quyền tạo người dùng
        $user = Auth::user();
        if (!$user || ($user->role_id != 1 && $user->role_id != 2)) {
            abort(403, 'Bạn không có quyền tải template.');
        }

        return $this->userService->downloadTemplate();
    }
}
