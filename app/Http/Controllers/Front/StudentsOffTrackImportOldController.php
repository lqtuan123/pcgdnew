<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Services\StudentsOffTrackSimpleImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

use App\Services\AccessGuardService;

class StudentsOffTrackImportOldController extends Controller
{
    public function __construct(
        private AccessGuardService $guard
    ) {}

    public function create()
    {
        // Kiểm tra quyền import học sinh trái tuyến
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_hoc_sinh_trai_tuyen', 'create')) {
            abort(403, 'Bạn không có quyền import học sinh trái tuyến.');
        }

        return view('front.students-off-track.import');
    }

    public function store(Request $request, StudentsOffTrackSimpleImportService $importService)
    {
        // Enforce quyền và địa bàn import
        $ctx = $this->guard->enforce($request, 'quan_ly_hoc_sinh_trai_tuyen', 'create', [
            'force_scheme' => null,
        ]);

        @set_time_limit(600);
        @ini_set('memory_limit','512M');

        // Hỗ trợ cả form-submit (rows là JSON string) lẫn JSON body
        if (is_string($request->input('rows'))) {
            $decoded = json_decode($request->input('rows'), true);
            $request->merge(['rows' => $decoded ?: []]);
        }

        // Nếu non-admin, ép geo theo địa bàn được phân quyền
        if (!$ctx['isAdmin']) {
            $geoReq = (array) $request->input('geo', []);
            $request->merge([
                'geo' => array_merge($geoReq, array_filter([
                    'province_id' => $ctx['geo']['province_id'] ?? null,
                    'ward_id' => $ctx['geo']['ward_id'] ?? null,
                    'village_id' => $ctx['geo']['village_id'] ?? null,
                ]))
            ]);
        }

        // Validation cơ bản
        $rules = [
            'academic_year' => 'required|string',
            'rows' => 'required|array|min:1',
            'scheme' => 'nullable|string|in:old,new',
            'geo' => 'nullable|array',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        try {
            // Chuẩn bị payload
            $payload = [
                'rows' => $request->input('rows'),
                'academic_year' => $request->input('academic_year'),
                'scheme' => $request->input('scheme', 'old'),
                'geo' => $request->input('geo', []),
            ];
            
            // Import dữ liệu (service sẽ tự validate)
            $stats = $importService->import($payload);

            if ($request->expectsJson()) {
                // Format giống SurveyImportController
                $res = [
                    'ok' => $stats['fail_count'] ? false : true,
                    'ok_count' => $stats['ok_count'] ?? 0,
                    'fail_count' => $stats['fail_count'] ?? 0,
                    'row_errors' => $stats['row_errors'] ?? [],
                    'message_errors' => $stats['message_errors'] ?? [],
                    'processed_count' => (int)($stats['processed_count'] ?? 0),
                    'created_count' => (int)($stats['created_count'] ?? 0),
                    'updated_count' => (int)($stats['updated_count'] ?? 0),
                    'total_rows' => (int)($stats['total_rows'] ?? 0),
                ];
                
                // Tạo message gọn gàng
                $res['message'] = "Đã xử lý · OK " . ((int)($res['ok_count'] ?? 0)) . " · Lỗi " . ((int)($res['fail_count'] ?? 0));
                
                // Thêm thông tin chi tiết
                if (isset($stats['created_count']) && isset($stats['updated_count'])) {
                    $res['message'] .= " (Thêm mới: {$stats['created_count']}, Cập nhật: {$stats['updated_count']})";
                }
                
                // Giữ format cũ để tương thích với view
                $res['success'] = true;
                $res['stats'] = $stats;
                $res['errors'] = $stats['errors'] ?? [];
                
                return response()->json($res, 200);
            }

            return redirect()->route('front.students-off-track.index')
                ->with('success', "Đã import thành công {$stats['processed_count']} học sinh trái tuyến vào bảng mới");

        } catch (Throwable $e) {
            Log::error('Import error:', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lỗi khi lưu dữ liệu: ' . $e->getMessage(),
                    'errors' => [], // Rỗng để tránh lỗi view
                    'row_errors' => [],
                    'message_errors' => []
                ], 500);
            }

            return back()->withInput()
                ->with('error', 'Lỗi khi lưu dữ liệu: ' . $e->getMessage());
        }
    }
}
