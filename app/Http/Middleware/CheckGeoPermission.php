<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckGeoPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Cho phép superadmin hoặc tài khoản cấp xã (có province_id, ward_id, không có school_id)
        if (!$user->isSuperadmin() && (!$user->province_id || !$user->ward_id || $user->school_id)) {
            abort(403, 'Chỉ tài khoản superadmin hoặc cấp xã mới có quyền truy cập chức năng này.');
        }

        // Kiểm tra quyền theo role-based system
        $provinceId = $request->route('provinceId') ?? $request->input('province_id');
        $wardId = $request->route('wardId') ?? $request->input('ward_id');
        $schoolId = $request->route('schoolId') ?? $request->input('school_id');
        
        // Kiểm tra quyền truy cập province
        if ($provinceId && !$user->canAccessProvince($provinceId)) {
            abort(403, 'Bạn không có quyền truy cập tỉnh này.');
        }
        
        // Kiểm tra quyền truy cập ward
        if ($wardId && !$user->canAccessWard($wardId)) {
            abort(403, 'Bạn không có quyền truy cập xã/phường này.');
        }
        
        // Kiểm tra quyền truy cập school
        if ($schoolId && !$user->canAccessSchool($schoolId)) {
            abort(403, 'Bạn không có quyền truy cập trường này.');
        }

        return $next($request);
    }
}

