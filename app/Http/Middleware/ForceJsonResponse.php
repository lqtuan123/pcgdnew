<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Chỉ áp dụng cho API routes
        if ($request->is('admin/users/*')) {
            $request->headers->set('Accept', 'application/json');
        }

        $response = $next($request);

        // Nếu response không phải JSON và có lỗi, chuyển đổi thành JSON
        if ($request->is('admin/users/*')) {
            if (!$response->headers->get('Content-Type') || 
                !str_contains($response->headers->get('Content-Type'), 'application/json')) {
                
                if ($response->getStatusCode() >= 400) {
                    $content = $response->getContent();
                    
                    // Nếu content là HTML (có chứa <!DOCTYPE), chuyển thành JSON error
                    if (str_contains($content, '<!DOCTYPE') || str_contains($content, '<html')) {
                        $errorMessage = 'Có lỗi xảy ra trên server';
                        
                        if ($response->getStatusCode() === 403) {
                            $errorMessage = 'Bạn không có quyền truy cập';
                        } elseif ($response->getStatusCode() === 401) {
                            $errorMessage = 'Vui lòng đăng nhập';
                        } elseif ($response->getStatusCode() === 500) {
                            $errorMessage = 'Lỗi server nội bộ';
                        }
                        
                        return response()->json([
                            'ok' => false,
                            'message' => $errorMessage,
                            'status_code' => $response->getStatusCode()
                        ], $response->getStatusCode());
                    }
                }
            }
        }

        return $response;
    }
}
