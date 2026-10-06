<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\ChuongTrinh;
use App\Models\ChiTieu;
use App\Models\ChiTieuGiaTri;
use App\Models\GeoUnit;
use Illuminate\Http\Request;

class PcgdChiTieuController extends Controller
{
    /**
     * Hiển thị danh sách chỉ tiêu PCGD (Read-only)
     * Có thể chọn chương trình để xem
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Lấy tất cả chương trình
        $chuongTrinhs = ChuongTrinh::active()->get();
        
        // Mặc định chọn PCGD THCS nếu không có chọn
        $selectedChuongTrinh = null;
        $chiTieus = collect();
        
        if ($request->filled('chuong_trinh_id')) {
            $selectedChuongTrinh = ChuongTrinh::findOrFail($request->chuong_trinh_id);
        } else {
            // Mặc định là PCGD THCS
            $selectedChuongTrinh = ChuongTrinh::where('ma_chuong_trinh', 'pcgd_thcs')->first();
        }
        
        if ($selectedChuongTrinh) {
            // Lấy danh sách chỉ tiêu với giá trị
            $chiTieus = ChiTieuGiaTri::where('chuong_trinh_id', $selectedChuongTrinh->id)
                ->with('chiTieu')
                ->orderBy('thu_tu')
                ->get()
                ->map(function($giaTri) {
                    return (object)[
                        'id' => $giaTri->chiTieu->id,
                        'ma_chi_tieu' => $giaTri->chiTieu->ma_chi_tieu,
                        'ten_chi_tieu' => $giaTri->chiTieu->ten_chi_tieu,
                        'khong_kk_dbkk' => $giaTri->khong_kk_dbkk,
                        'vung_kk_dbkk' => $giaTri->vung_kk_dbkk,
                    ];
                });
        }
        
        // Kiểm tra xem user có phải cấp xã không
        $isWardLevel = $user->ward_id && !$user->school_id;
        $ward = null;
        
        if ($isWardLevel) {
            $ward = GeoUnit::find($user->ward_id);
        }
        
        return view('front.pcgd-chi-tieu.index', compact('chiTieus', 'isWardLevel', 'ward', 'chuongTrinhs', 'selectedChuongTrinh'));
    }
    
    /**
     * Toggle trạng thái vùng khó khăn cho xã
     */
    public function toggleDifficultArea(Request $request)
    {
        $user = $request->user();
        
        // Kiểm tra quyền: chỉ tài khoản cấp xã mới được phép
        if (!$user->ward_id || $user->school_id) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền thực hiện chức năng này'
            ], 403);
        }
        
        $ward = GeoUnit::find($user->ward_id);
        
        if (!$ward) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông tin xã/phường'
            ], 404);
        }
        
        // Toggle trạng thái
        $ward->is_difficult_area = !$ward->is_difficult_area;
        $ward->save();
        
        return response()->json([
            'success' => true,
            'is_difficult_area' => $ward->is_difficult_area,
            'message' => $ward->is_difficult_area 
                ? 'Đã chuyển sang vùng khó khăn/ĐBKK' 
                : 'Đã chuyển sang vùng không khó khăn/ĐBKK'
        ]);
    }
}
