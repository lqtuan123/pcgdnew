<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\ChiTieu;
use App\Models\ChuongTrinh;
use App\Models\ChiTieuGiaTri;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChiTieuController extends Controller
{
    /**
     * Hiển thị danh sách chỉ tiêu
     */
    public function index()
    {
        $chiTieus = ChiTieu::withCount('chiTieuGiaTris')->paginate(20);
        return view('admin.settings.chi-tieu.index', compact('chiTieus'));
    }

    /**
     * Hiển thị form tạo mới
     */
    public function create()
    {
        $chuongTrinhs = ChuongTrinh::active()->get();
        return view('admin.settings.chi-tieu.create', compact('chuongTrinhs'));
    }

    /**
     * Lưu chỉ tiêu mới và gắn vào chương trình đã chọn
     */
    public function store(Request $request)
    {
        $request->validate([
            'ma_chi_tieu' => 'required|string|max:255|unique:chi_tieu,ma_chi_tieu',
            'ten_chi_tieu' => 'required|string|max:255',
            'mo_ta' => 'nullable|string',
            'is_active' => 'boolean',
            'chuong_trinh_ids' => 'nullable|array',
            'chuong_trinh_ids.*' => 'exists:chuong_trinh,id',
        ], [
            'ma_chi_tieu.required' => 'Vui lòng nhập mã chỉ tiêu',
            'ma_chi_tieu.unique' => 'Mã chỉ tiêu đã tồn tại',
            'ten_chi_tieu.required' => 'Vui lòng nhập tên chỉ tiêu',
        ]);

        DB::beginTransaction();
        try {
            $chiTieu = ChiTieu::create([
                'ma_chi_tieu' => $request->ma_chi_tieu,
                'ten_chi_tieu' => $request->ten_chi_tieu,
                'mo_ta' => $request->mo_ta,
                'is_active' => $request->has('is_active'),
            ]);

            // Tạo bản ghi chi_tieu_gia_tri cho các chương trình đã chọn
            if ($request->filled('chuong_trinh_ids')) {
                foreach ($request->chuong_trinh_ids as $index => $chuongTrinhId) {
                    ChiTieuGiaTri::create([
                        'chuong_trinh_id' => $chuongTrinhId,
                        'chi_tieu_id' => $chiTieu->id,
                        'khong_kk_dbkk' => null,
                        'vung_kk_dbkk' => null,
                        'thu_tu' => $index + 1,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('admin.settings.chi-tieu.index')
                ->with('success', 'Chỉ tiêu đã được tạo thành công');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Có lỗi: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Hiển thị chi tiết
     */
    public function show(ChiTieu $chiTieu)
    {
        $chiTieu->load('chiTieuGiaTris.chuongTrinh');
        return view('admin.settings.chi-tieu.show', compact('chiTieu'));
    }

    /**
     * Hiển thị form chỉnh sửa
     */
    public function edit(ChiTieu $chiTieu)
    {
        $chuongTrinhs = ChuongTrinh::active()->get();
        $chiTieu->load('chiTieuGiaTris');
        return view('admin.settings.chi-tieu.edit', compact('chiTieu', 'chuongTrinhs'));
    }

    /**
     * Cập nhật chỉ tiêu và các chương trình
     */
    public function update(Request $request, ChiTieu $chiTieu)
    {
        $request->validate([
            'ma_chi_tieu' => 'required|string|max:255|unique:chi_tieu,ma_chi_tieu,' . $chiTieu->id,
            'ten_chi_tieu' => 'required|string|max:255',
            'mo_ta' => 'nullable|string',
            'is_active' => 'boolean',
            'chuong_trinh_ids' => 'nullable|array',
            'chuong_trinh_ids.*' => 'exists:chuong_trinh,id',
        ], [
            'ma_chi_tieu.required' => 'Vui lòng nhập mã chỉ tiêu',
            'ma_chi_tieu.unique' => 'Mã chỉ tiêu đã tồn tại',
            'ten_chi_tieu.required' => 'Vui lòng nhập tên chỉ tiêu',
        ]);

        DB::beginTransaction();
        try {
            $chiTieu->update([
                'ma_chi_tieu' => $request->ma_chi_tieu,
                'ten_chi_tieu' => $request->ten_chi_tieu,
                'mo_ta' => $request->mo_ta,
                'is_active' => $request->has('is_active'),
            ]);

            // Xóa tất cả liên kết cũ
            $chiTieu->chiTieuGiaTris()->delete();

            // Tạo lại liên kết mới
            if ($request->filled('chuong_trinh_ids')) {
                foreach ($request->chuong_trinh_ids as $index => $chuongTrinhId) {
                    ChiTieuGiaTri::create([
                        'chuong_trinh_id' => $chuongTrinhId,
                        'chi_tieu_id' => $chiTieu->id,
                        'khong_kk_dbkk' => null,
                        'vung_kk_dbkk' => null,
                        'thu_tu' => $index + 1,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('admin.settings.chi-tieu.index')
                ->with('success', 'Chỉ tiêu đã được cập nhật thành công');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Có lỗi: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Xóa chỉ tiêu
     */
    public function destroy(ChiTieu $chiTieu)
    {
        $chiTieu->delete();

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Chỉ tiêu đã được xóa thành công'
            ]);
        }

        return redirect()->route('admin.settings.chi-tieu.index')
            ->with('success', 'Chỉ tiêu đã được xóa thành công');
    }

    /**
     * Toggle trạng thái is_active
     */
    public function toggleActive(ChiTieu $chiTieu)
    {
        $chiTieu->is_active = !$chiTieu->is_active;
        $chiTieu->save();

        return response()->json([
            'success' => true,
            'is_active' => $chiTieu->is_active,
            'message' => $chiTieu->is_active ? 'Đã kích hoạt' : 'Đã vô hiệu hóa'
        ]);
    }

    /**
     * Xóa hàng loạt chỉ tiêu
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:chi_tieu,id',
        ]);

        try {
            $count = ChiTieu::whereIn('id', $request->ids)->delete();

            return response()->json([
                'success' => true,
                'message' => "Đã xóa {$count} chỉ tiêu thành công"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }
}
