<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\School;
use Symfony\Component\HttpFoundation\Response;

class CheckDissolvedSchool
{
    /**
     * Handle an incoming request.
     * Block login/session if user's associated school is dissolved.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->school_id && !$user->isSuperadmin()) {
            $school = School::find($user->school_id);
            if ($school && $school->is_dissolved) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Trường học thuộc tài khoản này đã bị giải thể. Không thể truy cập hệ thống.'
                    ], 403);
                }

                return redirect()->route('login')->withErrors([
                    'email' => 'Trường học thuộc tài khoản này đã bị giải thể. Không thể đăng nhập vào hệ thống.'
                ]);
            }
        }

        return $next($request);
    }
}
