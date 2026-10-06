<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EducationalLevel;
use Illuminate\Http\Request;

class EducationalLevelController extends Controller
{
    /**
     * Hiển thị danh sách bậc học
     */
    public function index()
    {
        $educationalLevels = EducationalLevel::withCount('schools')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.educational-levels.index', compact('educationalLevels'));
    }

    /**
     * Hiển thị form tạo mới
     */
    public function create()
    {
        return view('admin.educational-levels.create');
    }

    /**
     * Lưu bậc học mới
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:educational_levels,name',
            'age_min' => 'nullable|integer|min:0|max:25',
            'age_max' => 'nullable|integer|min:0|max:25|gte:age_min'
        ], [
            'name.required' => 'Tên bậc học không được để trống',
            'name.unique' => 'Tên bậc học đã tồn tại',
            'age_min.integer' => 'Độ tuổi tối thiểu phải là số nguyên',
            'age_max.integer' => 'Độ tuổi tối đa phải là số nguyên',
            'age_max.gte' => 'Độ tuổi tối đa phải lớn hơn hoặc bằng độ tuổi tối thiểu'
        ]);

        EducationalLevel::create([
            'name' => trim($request->name),
            'age_min' => $request->age_min ?: null,
            'age_max' => $request->age_max ?: null
        ]);

        return redirect()->route('admin.educational-levels.index')
            ->with('toast', 'Đã tạo bậc học thành công');
    }

    /**
     * Hiển thị form chỉnh sửa
     */
    public function edit(EducationalLevel $educationalLevel)
    {
        return view('admin.educational-levels.edit', compact('educationalLevel'));
    }

    /**
     * Cập nhật bậc học
     */
    public function update(Request $request, EducationalLevel $educationalLevel)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:educational_levels,name,' . $educationalLevel->id,
            'age_min' => 'nullable|integer|min:0|max:25',
            'age_max' => 'nullable|integer|min:0|max:25|gte:age_min'
        ], [
            'name.required' => 'Tên bậc học không được để trống',
            'name.unique' => 'Tên bậc học đã tồn tại',
            'age_min.integer' => 'Độ tuổi tối thiểu phải là số nguyên',
            'age_max.integer' => 'Độ tuổi tối đa phải là số nguyên',
            'age_max.gte' => 'Độ tuổi tối đa phải lớn hơn hoặc bằng độ tuổi tối thiểu'
        ]);

        $educationalLevel->update([
            'name' => trim($request->name),
            'age_min' => $request->age_min ?: null,
            'age_max' => $request->age_max ?: null
        ]);

        return redirect()->route('admin.educational-levels.index')
            ->with('toast', 'Đã cập nhật bậc học thành công');
    }

    /**
     * Xóa bậc học
     */
    public function destroy(EducationalLevel $educationalLevel)
    {
        // Kiểm tra xem có trường học nào đang sử dụng bậc học này không
        if ($educationalLevel->schools()->count() > 0) {
            return back()->with('error', 'Không thể xóa bậc học này vì đang có trường học sử dụng');
        }

        $educationalLevel->delete();

        return redirect()->route('admin.educational-levels.index')
            ->with('toast', 'Đã xóa bậc học thành công');
    }

    /**
     * Xóa hàng loạt bậc học
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:educational_levels,id'
        ], [
            'ids.required' => 'Vui lòng chọn ít nhất một bậc học để xóa',
            'ids.array' => 'Dữ liệu không hợp lệ',
            'ids.min' => 'Vui lòng chọn ít nhất một bậc học để xóa'
        ]);

        $educationalLevels = EducationalLevel::whereIn('id', $request->ids)->get();
        $deletedCount = 0;
        $errorMessages = [];

        foreach ($educationalLevels as $level) {
            // Kiểm tra xem có trường học nào đang sử dụng bậc học này không
            if ($level->schools()->count() > 0) {
                $errorMessages[] = "Không thể xóa '{$level->name}' vì đang có trường học sử dụng";
                continue;
            }

            $level->delete();
            $deletedCount++;
        }

        if ($deletedCount > 0) {
            $message = "Đã xóa thành công {$deletedCount} bậc học";
            if (!empty($errorMessages)) {
                $message .= ". " . implode('; ', $errorMessages);
            }
            return redirect()->route('admin.educational-levels.index')
                ->with('toast', $message);
        } else {
            return redirect()->route('admin.educational-levels.index')
                ->with('error', implode('; ', $errorMessages));
        }
    }
}
