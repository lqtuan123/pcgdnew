<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GdmnFinance;
use App\Models\GeoUnit;
use Illuminate\Support\Facades\Auth;
use App\Services\GdmnFinanceExportService;
use App\Services\GdmnFinanceImportService;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GdmnFinanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $year = $request->get('year', date('Y'));
        
        // Lấy danh sách xã mà user có quyền truy cập
        $accessibleWards = $this->getAccessibleWards($user);
        
        // Xác định ward_id để hiển thị
        $isNonAdmin = $user && $user->role_id != 1 && $user->role_id != 2;
        $selectedWardId = null;
        
        // Nếu user bị khóa xã, tự động chọn xã của user
        if ($isNonAdmin && $user->ward_id) {
            $selectedWardId = $user->ward_id;
        } 
        // Nếu có chọn xã trong request
        elseif ($request->filled('ward_id')) {
            $selectedWardId = $request->ward_id;
        }
        // Nếu chỉ có 1 xã accessible, tự động chọn
        elseif (count($accessibleWards) == 1) {
            $selectedWardId = $accessibleWards[0];
        }
        
        // Chỉ load dữ liệu nếu đã chọn xã
        if ($selectedWardId) {
            $query = GdmnFinance::with(['province', 'ward', 'ward.parent', 'ward.parent.parent'])
                ->byWard($selectedWardId)
                ->byYear($year);
                
            $finances = $query->orderBy('main_item')->orderBy('sub_item')->get();
        } else {
            $finances = collect(); // Empty collection
        }
        
        // Nếu user bị khóa tỉnh, chỉ hiển thị tỉnh của user
        if ($isNonAdmin && $user->province_id) {
            $provinces = GeoUnit::where('id', $user->province_id)
                ->where('type_level', 1)
                ->get(['id', 'name']);
        } else {
            $provinces = GeoUnit::where('type_level', 1)
                ->orderBy('name')
                ->get(['id', 'name']);
        }
        
        // Nếu user bị khóa xã, chỉ hiển thị xã của user
        if ($isNonAdmin && $user->ward_id) {
            $wards = GeoUnit::where('id', $user->ward_id)
                ->where('type_level', 3)
                ->get(['id', 'name']);
        } else {
            $wards = GeoUnit::whereIn('id', $accessibleWards)
                ->where('type_level', 3)
                ->orderBy('name')
                ->get(['id', 'name']);
        }
            
        return view('gdmn-finance.index', compact('finances', 'wards', 'provinces', 'year', 'selectedWardId'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = Auth::user();
        $accessibleWards = $this->getAccessibleWards($user);
        
        // Nếu user bị khóa tỉnh, chỉ hiển thị tỉnh của user
        $isNonAdmin = $user && $user->role_id != 1 && $user->role_id != 2;
        if ($isNonAdmin && $user->province_id) {
            $provinces = GeoUnit::where('id', $user->province_id)
                ->where('type_level', 1)
                ->get(['id', 'name']);
        } else {
            $provinces = GeoUnit::where('type_level', 1)
                ->orderBy('name')
                ->get(['id', 'name']);
        }
        
        // Nếu user bị khóa xã, chỉ hiển thị xã của user
        if ($isNonAdmin && $user->ward_id) {
            $wards = GeoUnit::where('id', $user->ward_id)
                ->where('type_level', 3)
                ->get(['id', 'name']);
        } else {
            $wards = GeoUnit::whereIn('id', $accessibleWards)
                ->where('type_level', 3)
                ->orderBy('name')
                ->get(['id', 'name']);
        }
        
        return view('gdmn-finance.create', compact('wards', 'provinces'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Xử lý sub_item trước khi validate
        $requestData = $request->all();
        if (isset($requestData['sub_item']) && $requestData['sub_item'] === '') {
            $requestData['sub_item'] = null;
            $request->merge($requestData);
        }
        
        $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
            'province_id' => 'nullable|exists:geo_units,id',
            'ward_id' => 'required|exists:geo_units,id',
            'main_item' => 'required|integer|between:1,7',
            'sub_item' => 'nullable|integer|min:1|max:10',
            'amount' => 'required|string|max:50', // Đổi thành string để lưu đúng format
            'unit' => 'required|string|max:50',
            'note' => 'nullable|string|max:1000',
            'existing_id' => 'nullable|exists:gdmn_finance,id', // ID của bản ghi cần cập nhật
        ]);

        // Kiểm tra quyền truy cập xã
        $user = Auth::user();
        $accessibleWards = $this->getAccessibleWards($user);
        
        if (!in_array($request->ward_id, $accessibleWards)) {
            return back()->withErrors(['ward_id' => 'Bạn không có quyền nhập dữ liệu cho xã này.']);
        }

        try {
            $data = $request->all();
            
            // Xử lý sub_item nếu là empty string thì set null
            if (isset($data['sub_item']) && $data['sub_item'] === '') {
                $data['sub_item'] = null;
            }
            
            // Luôn tự động lấy province_id từ ward_id
            $ward = GeoUnit::find($data['ward_id']);
            if ($ward) {
                $data['province_id'] = $ward->getProvinceId();
            }
            
            // Tự động gán unit theo main_item nếu chưa có
            if (empty($data['unit']) && isset($data['main_item'])) {
                $data['unit'] = GdmnFinance::UNITS[$data['main_item']] ?? 'Tr.đồng';
            }
            
            // Nếu có existing_id, update thay vì create
            if ($request->existing_id) {
                $gdmnFinance = GdmnFinance::findOrFail($request->existing_id);
                $gdmnFinance->update($data);
                $message = 'Cập nhật dữ liệu thành công!';
            } else {
                // Kiểm tra dữ liệu đã tồn tại (chỉ khi tạo mới)
                $exists = GdmnFinance::where('year', $request->year)
                    ->where('ward_id', $request->ward_id)
                    ->where('main_item', $request->main_item)
                    ->where('sub_item', $request->sub_item)
                    ->exists();
                    
                if ($exists) {
                    return back()->withErrors(['main_item' => 'Dữ liệu cho mục này đã tồn tại. Vui lòng chọn lại để cập nhật.']);
                }
                
                $gdmnFinance = GdmnFinance::create($data);
                $message = 'Thêm dữ liệu thành công!';
            }
            
            // Nếu là AJAX request, trả về JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'id' => $gdmnFinance->id,
                    'data' => $gdmnFinance
                ]);
            }
            
            return redirect()->route('gdmn-finance.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            
            // Nếu là AJAX request, trả về JSON error
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Có lỗi xảy ra khi lưu dữ liệu: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->withErrors(['error' => 'Có lỗi xảy ra khi lưu dữ liệu: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(GdmnFinance $gdmnFinance)
    {
        // Kiểm tra quyền truy cập
        $user = Auth::user();
        $accessibleWards = $this->getAccessibleWards($user);
        
        if (!in_array($gdmnFinance->ward_id, $accessibleWards)) {
            abort(403, 'Bạn không có quyền xem dữ liệu này.');
        }
        
        $gdmnFinance->load(['province', 'ward']);
        return view('gdmn-finance.show', compact('gdmnFinance'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(GdmnFinance $gdmnFinance)
    {
        // Kiểm tra quyền truy cập
        $user = Auth::user();
        $accessibleWards = $this->getAccessibleWards($user);
        
        if (!in_array($gdmnFinance->ward_id, $accessibleWards)) {
            abort(403, 'Bạn không có quyền chỉnh sửa dữ liệu này.');
        }
        
        // Nếu user bị khóa tỉnh, chỉ hiển thị tỉnh của user
        $isNonAdmin = $user && $user->role_id != 1 && $user->role_id != 2;
        if ($isNonAdmin && $user->province_id) {
            $provinces = GeoUnit::where('id', $user->province_id)
                ->where('type_level', 1)
                ->get(['id', 'name']);
        } else {
            $provinces = GeoUnit::where('type_level', 1)
                ->orderBy('name')
                ->get(['id', 'name']);
        }
        
        // Nếu user bị khóa xã, chỉ hiển thị xã của user
        if ($isNonAdmin && $user->ward_id) {
            $wards = GeoUnit::where('id', $user->ward_id)
                ->where('type_level', 3)
                ->get(['id', 'name']);
        } else {
            $wards = GeoUnit::whereIn('id', $accessibleWards)
                ->where('type_level', 3)
                ->orderBy('name')
                ->get(['id', 'name']);
        }
            
        return view('gdmn-finance.edit', compact('gdmnFinance', 'wards', 'provinces'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, GdmnFinance $gdmnFinance)
    {
        // Kiểm tra quyền truy cập
        $user = Auth::user();
        $accessibleWards = $this->getAccessibleWards($user);
        
        if (!in_array($gdmnFinance->ward_id, $accessibleWards)) {
            abort(403, 'Bạn không có quyền cập nhật dữ liệu này.');
        }
        
        // Nếu chỉ cập nhật amount (từ AJAX)
        if ($request->has('amount') && $request->input('amount') !== null) {
            $request->validate([
                'amount' => 'required|string|max:50', // Đổi thành string để lưu đúng format
            ]);
            
            $gdmnFinance->update(['amount' => $request->amount]);
            
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Đã cập nhật số tiền thành công.']);
            }
            
            return redirect()->route('gdmn-finance.index')
                ->with('success', 'Đã cập nhật dữ liệu báo cáo tài chính thành công.');
        }
        
        // Cập nhật đầy đủ
        // Xử lý sub_item trước khi validate
        $requestData = $request->all();
        if (isset($requestData['sub_item']) && $requestData['sub_item'] === '') {
            $requestData['sub_item'] = null;
            $request->merge($requestData);
        }
        
        $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
            'province_id' => 'nullable|exists:geo_units,id',
            'ward_id' => 'required|exists:geo_units,id',
            'main_item' => 'required|integer|between:1,7',
            'sub_item' => 'nullable|integer|min:1|max:10',
            'amount' => 'required|string|max:50', // Đổi thành string để lưu đúng format
            'unit' => 'required|string|max:50',
            'note' => 'nullable|string|max:1000',
        ]);

        // Kiểm tra quyền truy cập xã
        if (!in_array($request->ward_id, $accessibleWards)) {
            return back()->withErrors(['ward_id' => 'Bạn không có quyền cập nhật dữ liệu cho xã này.']);
        }

        // Kiểm tra dữ liệu đã tồn tại (trừ bản ghi hiện tại)
        $exists = GdmnFinance::where('year', $request->year)
            ->where('ward_id', $request->ward_id)
            ->where('main_item', $request->main_item)
            ->where('sub_item', $request->sub_item)
            ->where('id', '!=', $gdmnFinance->id)
            ->exists();
            
        if ($exists) {
            return back()->withErrors(['main_item' => 'Dữ liệu cho mục này đã tồn tại.']);
        }

        try {
            $data = $request->all();
            
            // Xử lý sub_item nếu là empty string thì set null
            if (isset($data['sub_item']) && $data['sub_item'] === '') {
                $data['sub_item'] = null;
            }
            
            // Luôn tự động lấy province_id từ ward_id
            $ward = GeoUnit::find($data['ward_id']);
            if ($ward) {
                $data['province_id'] = $ward->getProvinceId();
            }
            
            // Tự động gán unit theo main_item nếu chưa có
            if (empty($data['unit']) && isset($data['main_item'])) {
                $data['unit'] = GdmnFinance::UNITS[$data['main_item']] ?? 'Tr.đồng';
            }
            
            $gdmnFinance->update($data);
            
            return redirect()->route('gdmn-finance.index')
                ->with('success', 'Đã cập nhật dữ liệu báo cáo tài chính thành công.');
        } catch (\Exception $e) {
            
            return back()->withErrors(['error' => 'Có lỗi xảy ra khi cập nhật dữ liệu: ' . $e->getMessage()]);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GdmnFinance $gdmnFinance)
    {
        // Kiểm tra quyền truy cập
        $user = Auth::user();
        $accessibleWards = $this->getAccessibleWards($user);
        
        if (!in_array($gdmnFinance->ward_id, $accessibleWards)) {
            abort(403, 'Bạn không có quyền xóa dữ liệu này.');
        }
        
        $gdmnFinance->delete();

        return redirect()->route('gdmn-finance.index')
            ->with('success', 'Đã xóa dữ liệu báo cáo tài chính thành công.');
    }

    /**
     * Lấy danh sách xã mà user có quyền truy cập
     */
    private function getAccessibleWards($user)
    {
        // Nếu user có quyền superadmin, trả về tất cả xã
        if ($user->isSuperadmin()) {
            return GeoUnit::where('type_level', 3)->pluck('id')->toArray();
        }
        
        // Lấy danh sách xã theo quyền địa lý của user
        $accessibleWards = [];
        
        // Kiểm tra quyền theo tỉnh
        if ($user->province_id) {
            $province = GeoUnit::find($user->province_id);
            if ($province) {
                $accessibleWards = array_merge($accessibleWards, 
                    $this->getWardsInProvince($province->id)
                );
            }
        }
        
        // Kiểm tra quyền theo huyện
        if ($user->district_id) {
            $district = GeoUnit::find($user->district_id);
            if ($district) {
                $accessibleWards = array_merge($accessibleWards,
                    $district->children()->where('type_level', 3)->pluck('id')->toArray()
                );
            }
        }
        
        // Kiểm tra quyền theo xã cụ thể
        if ($user->ward_id) {
            $accessibleWards[] = $user->ward_id;
        }
        
        return array_unique($accessibleWards);
    }
    
    /**
     * Lấy tất cả xã trong tỉnh
     */
    private function getWardsInProvince($provinceId)
    {
        $province = GeoUnit::find($provinceId);
        if (!$province) return [];
        
        $wards = [];
        
        // Lấy xã trực tiếp thuộc tỉnh (scheme mới)
        $directWards = $province->children()->where('type_level', 3)->pluck('id')->toArray();
        $wards = array_merge($wards, $directWards);
        
        // Lấy xã qua huyện (scheme cũ)
        $districts = $province->children()->where('type_level', 2)->get();
        foreach ($districts as $district) {
            $districtWards = $district->children()->where('type_level', 3)->pluck('id')->toArray();
            $wards = array_merge($wards, $districtWards);
        }
        
        return array_unique($wards);
    }

    /**
     * Export dữ liệu ra Excel
     */
    public function export(Request $request, GdmnFinanceExportService $exportService)
    {
        $user = Auth::user();
        $year = $request->get('year', date('Y'));
        $provinceId = $request->get('province_id');
        $wardId = $request->get('ward_id');

        // Kiểm tra quyền truy cập
        // Chỉ Super Admin (role_id = 1) mới không bị khóa
        $isSuperAdmin = $user && $user->role_id == 1;
        
        if (!$isSuperAdmin) {
            // User thường chỉ export dữ liệu của địa bàn mình quản lý
            // Ưu tiên: ward_id > province_id
            if (!$wardId && $user->ward_id) {
                $wardId = $user->ward_id;
            }
            
            if (!$provinceId && $user->province_id) {
                $provinceId = $user->province_id;
            }
            
            // Nếu có ward mà chưa có province, tự động lấy province từ ward
            if (!$provinceId && $wardId) {
                $ward = \App\Models\GeoUnit::find($wardId);
                if ($ward) {
                    $provinceId = $ward->getProvinceId();
                }
            }
        }

        $spreadsheet = $exportService->export($year, $provinceId, $wardId);

        $filename = 'bao_cao_tai_chinh_gdmn_' . $year . '_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Download file Excel mẫu
     */
    public function exportTemplate(Request $request, GdmnFinanceExportService $exportService)
    {
        $user = Auth::user();
        $year = $request->get('year', date('Y'));
        $provinceId = $request->get('province_id');
        $wardId = $request->get('ward_id');
        
        // Lấy thông tin tỉnh/xã
        $provinceName = null;
        $wardName = null;
        
        // Ưu tiên lấy từ request parameters
        if ($provinceId) {
            $province = GeoUnit::find($provinceId);
            $provinceName = $province->name ?? null;
        }
        
        if ($wardId) {
            $ward = GeoUnit::find($wardId);
            $wardName = $ward->name ?? null;
        }
        
        // Lấy từ user nếu chưa có từ request
        // Chỉ role_id = 1 (Super Admin) mới không bị khóa
        $isSuperAdmin = $user && $user->role_id == 1;
        
        if (!$isSuperAdmin) {
            // Lấy province từ user nếu chưa có
            if (!$provinceName && $user->province_id) {
                $province = GeoUnit::find($user->province_id);
                $provinceName = $province->name ?? null;
            }
            
            // Lấy ward từ user nếu chưa có
            if (!$wardName && $user->ward_id) {
                $ward = GeoUnit::find($user->ward_id);
                $wardName = $ward->name ?? null;
            }
            
            // Nếu có ward_id nhưng chưa có province_id, lấy province từ ward
            if (!$provinceName && $user->ward_id) {
                $ward = GeoUnit::find($user->ward_id);
                if ($ward) {
                    $provinceId = $ward->getProvinceId();
                    $province = GeoUnit::find($provinceId);
                    $provinceName = $province->name ?? null;
                }
            }
        }
        
        $spreadsheet = $exportService->exportTemplate($provinceName, $wardName, $year);

        $filename = 'mau_bao_cao_tai_chinh_gdmn_' . $year . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Show form import
     */
    public function importForm(Request $request)
    {
        $user = Auth::user();
        $year = $request->get('year', date('Y'));
        
        // Lấy danh sách tỉnh và xã
        $provinces = [];
        $wards = [];
        $selectedProvinceId = null;
        $selectedWardId = null;
        
        // Kiểm tra quyền của user
        $isSuperAdmin = $user && $user->role_id == 1;
        
        if ($isSuperAdmin) {
            // Super Admin: Xem tất cả
            $provinces = GeoUnit::province()->orderBy('name')->get();
        } else {
            // User khác: Chỉ xem địa bàn được gán
            if ($user->province_id) {
                $province = GeoUnit::find($user->province_id);
                if ($province) {
                    $provinces = collect([$province]);
                    $selectedProvinceId = $user->province_id;
                }
            }
        }
        
        // Lấy danh sách xã
        $provinceIdForWards = $request->get('province_id') ?: $selectedProvinceId;
        
        if ($provinceIdForWards) {
            $wards = $this->getWardsInProvince($provinceIdForWards);
            $wards = GeoUnit::whereIn('id', $wards)->orderBy('name')->get();
        }
        
        // Lock ward nếu user có ward_id
        if (!$isSuperAdmin && $user->ward_id) {
            $selectedWardId = $user->ward_id;
        } elseif ($request->get('ward_id')) {
            $selectedWardId = $request->get('ward_id');
        }
        
        return view('gdmn-finance.import', compact(
            'year',
            'provinces',
            'wards',
            'selectedProvinceId',
            'selectedWardId'
        ));
    }

    /**
     * Import dữ liệu từ Excel
     */
    public function import(Request $request, GdmnFinanceImportService $importService)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // Max 10MB
            'year' => 'required|integer|min:2000|max:2100',
            'ward_id' => 'required|exists:geo_units,id',
        ]);

        try {
            $file = $request->file('file');
            $path = $file->getRealPath();
            $year = $request->input('year');
            $wardId = $request->input('ward_id');
            
            // Kiểm tra quyền truy cập xã
            $user = Auth::user();
            $accessibleWards = $this->getAccessibleWards($user);
            
            if (!in_array($wardId, $accessibleWards)) {
                return back()->withErrors(['ward_id' => 'Bạn không có quyền import dữ liệu cho xã này.']);
            }
            
            // Lấy province_id từ ward
            $ward = GeoUnit::find($wardId);
            $provinceId = $ward ? $ward->getProvinceId() : null;

            $stats = $importService->import($path, $year, $provinceId, $wardId);

            $message = "Import hoàn tất: {$stats['created']} bản ghi mới, {$stats['updated']} bản ghi cập nhật";
            
            if (!empty($stats['errors'])) {
                $errorMessage = "Có " . count($stats['errors']) . " lỗi:\n" . implode("\n", array_slice($stats['errors'], 0, 10));
                if (count($stats['errors']) > 10) {
                    $errorMessage .= "\n... và " . (count($stats['errors']) - 10) . " lỗi khác";
                }
                return back()->with('warning', $message)->with('errors_detail', $errorMessage);
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'Lỗi khi import: ' . $e->getMessage()]);
        }
    }
}
