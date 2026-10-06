<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\DecimalFormat;
use App\Models\UserDecimalFormat;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DecimalFormatController extends Controller
{
    /**
     * Hiển thị trang cấu hình thập phân
     */
    public function index()
    {
        $formats = DecimalFormat::all();
        $userFormat = auth()->user()->getDecimalFormat();
        
        return view('front.decimal-format.index', compact('formats', 'userFormat'));
    }

    /**
     * Cập nhật cấu hình thập phân cho user
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'decimal_format_id' => 'required|exists:decimal_formats,id'
        ]);

        $user = auth()->user();
        UserDecimalFormat::updateUserFormat($user->id, $request->decimal_format_id);

        return response()->json([
            'success' => true,
            'message' => 'Cấu hình thập phân đã được cập nhật thành công'
        ]);
    }

    /**
     * API lấy cấu hình thập phân hiện tại của user
     */
    public function getCurrentFormat(): JsonResponse
    {
        $user = auth()->user();
        $format = $user->getDecimalFormat();
        
        return response()->json([
            'success' => true,
            'data' => $format
        ]);
    }

    /**
     * API format số theo cấu hình của user
     */
    public function formatNumber(Request $request): JsonResponse
    {
        $request->validate([
            'number' => 'required|numeric',
            'decimals' => 'nullable|integer|min:0|max:10'
        ]);

        $user = auth()->user();
        $format = $user->getDecimalFormat();
        
        if (!$format) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy cấu hình hiển thị thập phân'
            ], 404);
        }

        $formatted = $format->formatNumber(
            $request->number, 
            $request->decimals ?? 2
        );

        return response()->json([
            'success' => true,
            'data' => [
                'original' => $request->number,
                'formatted' => $formatted,
                'format' => $format
            ]
        ]);
    }
}
