<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('admin.login');
        }

        $user = Auth::user();
        
        // Chỉ cho phép admin (role_id=1,2) vào admin dashboard
        if (!$user || ($user->role_id != 1 && $user->role_id != 2)) {
            abort(403, 'Chỉ quản trị viên mới có quyền truy cập trang này.');
        }

        return $next($request);
    }
}
