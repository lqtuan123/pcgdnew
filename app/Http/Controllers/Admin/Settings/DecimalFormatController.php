<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\DecimalFormat;
use Illuminate\Http\Request;

class DecimalFormatController extends Controller
{
    /**
     * Hiển thị danh sách cấu hình
     */
    public function index()
    {
        $formats = DecimalFormat::all();
        return view('admin.settings.decimal-formats.index', compact('formats'));
    }

    /**
     * Hiển thị form tạo mới
     */
    public function create()
    {
        return view('admin.settings.decimal-formats.create');
    }

    /**
     * Lưu cấu hình mới
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'decimal_separator' => 'required|string|max:1',
            'thousands_separator' => 'required|string|max:1',
            'description' => 'nullable|string',
        ]);

        $format = DecimalFormat::create($request->all());

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Cấu hình đã được tạo thành công',
                'data' => $format
            ]);
        }

        return redirect()->route('admin.settings.decimal-formats.index')
            ->with('success', 'Cấu hình đã được tạo thành công');
    }

    /**
     * Hiển thị form chỉnh sửa
     */
    public function edit(DecimalFormat $decimalFormat)
    {
        return view('admin.settings.decimal-formats.edit', compact('decimalFormat'));
    }

    /**
     * Cập nhật cấu hình
     */
    public function update(Request $request, DecimalFormat $decimalFormat)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'decimal_separator' => 'required|string|max:1',
            'thousands_separator' => 'required|string|max:1',
            'description' => 'nullable|string',
        ]);

        $decimalFormat->update($request->all());

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Cấu hình đã được cập nhật thành công',
                'data' => $decimalFormat
            ]);
        }

        return redirect()->route('admin.settings.decimal-formats.index')
            ->with('success', 'Cấu hình đã được cập nhật thành công');
    }

    /**
     * Xóa cấu hình
     */
    public function destroy(DecimalFormat $decimalFormat)
    {
        $decimalFormat->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cấu hình đã được xóa thành công'
        ]);
    }
}
