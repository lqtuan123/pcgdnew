<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\ChuongTrinh;
use Illuminate\Http\Request;

class ChuongTrinhController extends Controller
{
    /**
     * Hiển thị danh sách chương trình
     */
    public function index()
    {
        $chuongTrinhs = ChuongTrinh::withCount('chiTieuGiaTris')->paginate(20);
        return view('admin.settings.chuong-trinh.index', compact('chuongTrinhs'));
    }

    /**
     * Hiển thị form tạo mới
     */
    public function create()
    {
        return view('admin.settings.chuong-trinh.create');
    }

    /**
     * Lưu chương trình mới
     */
    public function store(Request $request)
    {
        $request->validate([
            'ma_chuong_trinh' => 'required|string|max:255|unique:chuong_trinh,ma_chuong_trinh',
            'ten_chuong_trinh' => 'required|string|max:255',
            'mo_ta' => 'nullable|string',
            'is_active' => 'boolean',
        ], [
            'ma_chuong_trinh.required' => 'Vui lòng nhập mã chương trình',
            'ma_chuong_trinh.unique' => 'Mã chương trình đã tồn tại',
            'ten_chuong_trinh.required' => 'Vui lòng nhập tên chương trình',
        ]);

        ChuongTrinh::create($request->all());

        return redirect()->route('admin.settings.chuong-trinh.index')
            ->with('success', 'Chương trình đã được tạo thành công');
    }

    /**
     * Hiển thị chi tiết
     */
    public function show(ChuongTrinh $chuongTrinh)
    {
        $chuongTrinh->load('chiTieuGiaTris.chiTieu');
        return view('admin.settings.chuong-trinh.show', compact('chuongTrinh'));
    }

    /**
     * Hiển thị form chỉnh sửa
     */
    public function edit(ChuongTrinh $chuongTrinh)
    {
        return view('admin.settings.chuong-trinh.edit', compact('chuongTrinh'));
    }

    /**
     * Cập nhật chương trình
     */
    public function update(Request $request, ChuongTrinh $chuongTrinh)
    {
        $request->validate([
            'ma_chuong_trinh' => 'required|string|max:255|unique:chuong_trinh,ma_chuong_trinh,' . $chuongTrinh->id,
            'ten_chuong_trinh' => 'required|string|max:255',
            'mo_ta' => 'nullable|string',
            'is_active' => 'boolean',
        ], [
            'ma_chuong_trinh.required' => 'Vui lòng nhập mã chương trình',
            'ma_chuong_trinh.unique' => 'Mã chương trình đã tồn tại',
            'ten_chuong_trinh.required' => 'Vui lòng nhập tên chương trình',
        ]);

        $chuongTrinh->update($request->all());

        return redirect()->route('admin.settings.chuong-trinh.index')
            ->with('success', 'Chương trình đã được cập nhật thành công');
    }

    /**
     * Xóa chương trình
     */
    public function destroy(ChuongTrinh $chuongTrinh)
    {
        $chuongTrinh->delete();

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Chương trình đã được xóa thành công'
            ]);
        }

        return redirect()->route('admin.settings.chuong-trinh.index')
            ->with('success', 'Chương trình đã được xóa thành công');
    }

    /**
     * Toggle trạng thái is_active
     */
    public function toggleActive(ChuongTrinh $chuongTrinh)
    {
        $chuongTrinh->is_active = !$chuongTrinh->is_active;
        $chuongTrinh->save();

        return response()->json([
            'success' => true,
            'is_active' => $chuongTrinh->is_active,
            'message' => $chuongTrinh->is_active ? 'Đã kích hoạt' : 'Đã vô hiệu hóa'
        ]);
    }

    /**
     * Xóa hàng loạt chương trình
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:chuong_trinh,id',
        ]);

        try {
            $count = ChuongTrinh::whereIn('id', $request->ids)->delete();

            return response()->json([
                'success' => true,
                'message' => "Đã xóa {$count} chương trình thành công"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }
}

