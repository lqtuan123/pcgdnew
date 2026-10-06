<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolTypeController extends Controller
{
    /**
     * Hiển thị danh sách loại hình trường học
     */
    public function index()
    {
        $schoolTypes = SchoolType::withCount('schools')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.school-types.index', compact('schoolTypes'));
    }

    /**
     * Hiển thị form tạo mới
     */
    public function create()
    {
        return view('admin.school-types.create');
    }

    /**
     * Lưu loại hình trường học mới
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:school_types,name'
        ], [
            'name.required' => 'Tên loại hình trường học không được để trống',
            'name.unique' => 'Tên loại hình trường học đã tồn tại'
        ]);

        SchoolType::create([
            'name' => trim($request->name)
        ]);

        return redirect()->route('admin.school-types.index')
            ->with('toast', 'Đã tạo loại hình trường học thành công');
    }

    /**
     * Hiển thị form chỉnh sửa
     */
    public function edit(SchoolType $schoolType)
    {
        return view('admin.school-types.edit', compact('schoolType'));
    }

    /**
     * Cập nhật loại hình trường học
     */
    public function update(Request $request, SchoolType $schoolType)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:school_types,name,' . $schoolType->id
        ], [
            'name.required' => 'Tên loại hình trường học không được để trống',
            'name.unique' => 'Tên loại hình trường học đã tồn tại'
        ]);

        $schoolType->update([
            'name' => trim($request->name)
        ]);

        return redirect()->route('admin.school-types.index')
            ->with('toast', 'Đã cập nhật loại hình trường học thành công');
    }

    /**
     * Xóa loại hình trường học
     */
    public function destroy(SchoolType $schoolType)
    {
        // Kiểm tra xem có trường học nào đang sử dụng loại hình này không
        if ($schoolType->schools()->count() > 0) {
            return back()->with('error', 'Không thể xóa loại hình trường học này vì đang có trường học sử dụng');
        }

        $schoolType->delete();

        return redirect()->route('admin.school-types.index')
            ->with('toast', 'Đã xóa loại hình trường học thành công');
    }

    /**
     * Xóa hàng loạt loại hình trường học
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:school_types,id'
        ], [
            'ids.required' => 'Vui lòng chọn ít nhất một loại hình trường học để xóa',
            'ids.array' => 'Dữ liệu không hợp lệ',
            'ids.min' => 'Vui lòng chọn ít nhất một loại hình trường học để xóa'
        ]);

        $schoolTypes = SchoolType::whereIn('id', $request->ids)->get();
        $deletedCount = 0;
        $errorMessages = [];

        foreach ($schoolTypes as $schoolType) {
            // Kiểm tra xem có trường học nào đang sử dụng loại hình này không
            if ($schoolType->schools()->count() > 0) {
                $errorMessages[] = "Không thể xóa '{$schoolType->name}' vì đang có trường học sử dụng";
                continue;
            }

            $schoolType->delete();
            $deletedCount++;
        }

        if ($deletedCount > 0) {
            $message = "Đã xóa thành công {$deletedCount} loại hình trường học";
            if (!empty($errorMessages)) {
                $message .= ". " . implode('; ', $errorMessages);
            }
            return redirect()->route('admin.school-types.index')
                ->with('toast', $message);
        } else {
            return redirect()->route('admin.school-types.index')
                ->with('error', implode('; ', $errorMessages));
        }
    }
}
