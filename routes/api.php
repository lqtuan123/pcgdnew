<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\GeoUnit;
use App\Models\School;
use App\Http\Controllers\Admin\ReportManagementController;
use App\Http\Controllers\ReportAggregationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// API endpoints for loading geo data
Route::get('/wards/{provinceId}', function ($provinceId) {
    try {
        $startTime = microtime(true);
        
        $wards = GeoUnit::where('parent_id', $provinceId)
            ->where('type_level', 3)
            ->orderBy('name')
            ->limit(1000) // Giới hạn 1000 records
            ->get(['id', 'name']);
        
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);
        
        \Illuminate\Support\Facades\Log::info('API /wards/' . $provinceId . ' returned ' . $wards->count() . ' wards in ' . $executionTime . 'ms');
        
        return response()->json($wards);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('API /wards/' . $provinceId . ' error: ' . $e->getMessage());
        return response()->json([]);
    }
});

// API endpoints for GDMN Finance - chỉ cho tài khoản cấp xã
Route::middleware('geo.permission')->group(function () {
    Route::get('/gdmn-finance/wards/{provinceId}', function ($provinceId) {
    try {
        $startTime = microtime(true);
        
        $wards = \App\Models\GeoUnit::where('parent_id', $provinceId)
            ->where('type_level', 3)
            ->orderBy('name')
            ->limit(1000)
            ->get(['id', 'name']);
        
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);
        
        \Illuminate\Support\Facades\Log::info('API /gdmn-finance/wards/' . $provinceId . ' returned ' . $wards->count() . ' wards in ' . $executionTime . 'ms');
        
        return response()->json($wards);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('API /gdmn-finance/wards/' . $provinceId . ' error: ' . $e->getMessage());
        return response()->json([]);
    }
});

// Check existing GDMN Finance data
Route::post('/gdmn-finance/check-existing', function (Request $request) {
    try {
        $year = $request->input('year');
        $provinceId = $request->input('province_id');
        $wardId = $request->input('ward_id');
        $mainItem = $request->input('main_item');
        $subItem = $request->input('sub_item');
        
        $query = \App\Models\GdmnFinance::where('year', $year)
            ->where('ward_id', $wardId)
            ->where('main_item', $mainItem);
            
        if ($provinceId) {
            $query->where('province_id', $provinceId);
        }
        
        if ($subItem) {
            $query->where('sub_item', $subItem);
        } else {
            $query->whereNull('sub_item');
        }
        
        $existing = $query->first();
        
        if ($existing) {
            return response()->json([
                'exists' => true,
                'id' => $existing->id,
                'amount' => $existing->amount,
                'unit' => $existing->unit,
                'note' => $existing->note
            ]);
        }
        
        return response()->json(['exists' => false]);
    } catch (\Exception $e) {
        return response()->json(['exists' => false]);
    }
});

Route::get('/gdmn-finance/data', function (\Illuminate\Http\Request $request) {
    try {
        $year = $request->get('year', date('Y'));
        $wardId = $request->get('ward_id');
        
        $query = \App\Models\GdmnFinance::with('ward')
            ->where('year', $year);
            
        if ($wardId) {
            $query->where('ward_id', $wardId);
        }
        
        $data = $query->orderBy('ward_id')->orderBy('main_item')->orderBy('sub_item')->get();
        
        return response()->json($data);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('API /gdmn-finance/data error: ' . $e->getMessage());
        return response()->json([]);
    }
});
});

Route::get('/schools/{wardId}', function ($wardId) {
    try {
        $startTime = microtime(true);
        
        // Lấy ward code từ ward ID
        $ward = GeoUnit::find($wardId);
        if (!$ward) {
            \Illuminate\Support\Facades\Log::warning('API /schools/' . $wardId . ' - ward not found');
            return response()->json([]);
        }
        
        $schools = School::where('ward_code_new', $ward->code)
            ->orderBy('name')
            ->limit(1000) // Giới hạn 1000 records
            ->get(['id', 'name']);
        
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);
        
        \Illuminate\Support\Facades\Log::info('API /schools/' . $wardId . ' returned ' . $schools->count() . ' schools in ' . $executionTime . 'ms');
        
        return response()->json($schools);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('API /schools/' . $wardId . ' error: ' . $e->getMessage());
        return response()->json([]);
    }
});

// API endpoint để lấy permissions của role
Route::get('/roles/{roleName}/permissions', function ($roleName) {
    $role = \App\Models\UserRole::where('name', $roleName)->first();
    
    if (!$role) {
        return response()->json([
            'ok' => false,
            'message' => 'Role không tồn tại'
        ], 404);
    }
    
    $permissions = $role->getAllPermissions();
    
    return response()->json([
        'ok' => true,
        'data' => [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
            ],
            'permissions' => $permissions
        ]
    ]);
});

// Report Management API Routes
Route::prefix('reports')->group(function () {
    // Nhóm báo cáo
    Route::get('/', [ReportManagementController::class, 'index']); // Lấy tất cả nhóm báo cáo
    Route::get('/groups/{id}', [ReportManagementController::class, 'showGroup']); // Lấy chi tiết nhóm báo cáo
    Route::post('/groups', [ReportManagementController::class, 'storeGroup']); // Tạo nhóm báo cáo mới
    Route::put('/groups/{id}', [ReportManagementController::class, 'updateGroup']); // Cập nhật nhóm báo cáo
    Route::delete('/groups/{id}', [ReportManagementController::class, 'destroyGroup']); // Xóa nhóm báo cáo
    
    // Báo cáo chi tiết
    Route::post('/details', [ReportManagementController::class, 'storeDetail']); // Tạo báo cáo chi tiết mới
    Route::put('/details/{id}', [ReportManagementController::class, 'updateDetail']); // Cập nhật báo cáo chi tiết
    Route::delete('/details/{id}', [ReportManagementController::class, 'destroyDetail']); // Xóa báo cáo chi tiết
    Route::get('/groups/{groupId}/details', [ReportManagementController::class, 'getDetailsByGroup']); // Lấy báo cáo chi tiết theo nhóm
    
    // Cập nhật giá trị báo cáo
    Route::put('/details/{id}/value', [ReportManagementController::class, 'updateReportValue']); // Cập nhật giá trị báo cáo
});

// Frontend Report Search API Routes
Route::prefix('api/reports')->group(function () {
    Route::get('/groups/{groupId}/details', function ($groupId) {
        $reportDetails = \App\Models\ReportDetail::where('report_group_id', $groupId)
            ->active()
            ->ordered()
            ->get(['id', 'name', 'code', 'description']);
        
        return response()->json([
            'success' => true,
            'data' => $reportDetails
        ]);
    });
});

// Report Aggregation API Routes
Route::prefix('report-aggregation')->middleware('auth:sanctum')->group(function () {
    Route::get('/ward-statistics', [ReportAggregationController::class, 'getWardStatistics']);
    Route::get('/province-statistics', [ReportAggregationController::class, 'getProvinceStatistics']);
    Route::get('/school-statistics', [ReportAggregationController::class, 'getSchoolStatistics']);
    Route::get('/wards-with-reports', [ReportAggregationController::class, 'getWardsWithReports']);
    Route::get('/available-academic-years', [ReportAggregationController::class, 'getAvailableAcademicYears']);
    Route::get('/overview-statistics', [ReportAggregationController::class, 'getOverviewStatistics']);
});
