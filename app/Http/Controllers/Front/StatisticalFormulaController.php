<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\ReportGroup;
use App\Models\ReportDetail;
use App\Models\StatisticalFormula;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticalFormulaController extends Controller
{
    /**
     * Hiển thị trang cấu hình công thức thống kê
     */
    public function index()
    {
        // Kiểm tra quyền xem
        if (!auth()->user()->hasPermission('cong_thuc_thong_ke', 'view')) {
            abort(403, 'Bạn không có quyền xem trang công thức thống kê.');
        }

        // Lấy tất cả báo cáo để hiển thị trong modal chọn
        $allReportGroups = ReportGroup::active()
            ->ordered()
            ->with(['reportDetails' => function ($query) {
                $query->active()->ordered();
            }])
            ->get();

        // Lấy chỉ những báo cáo có tiêu chí đang kích hoạt để hiển thị trong bảng chính
        $reportGroups = ReportGroup::active()
            ->ordered()
            ->with(['reportDetails' => function ($query) {
                $query->active()->ordered()->with(['statisticalFormulas' => function ($q) {
                    $q->where('is_active', true)->ordered();
                }])->whereHas('statisticalFormulas', function ($q) {
                    $q->where('is_active', true);
                });
            }])
            ->get();

        // Kiểm tra quyền chỉnh sửa
        $canEdit = auth()->user()->hasPermission('cong_thuc_thong_ke', 'edit');

        return view('front.statistical-formula.index', compact('reportGroups', 'allReportGroups', 'canEdit'));
    }

    /**
     * Hiển thị form chỉnh sửa công thức
     */
    public function edit($reportDetailId)
    {
        $reportDetail = ReportDetail::with(['statisticalFormulas' => function ($query) {
            $query->ordered();
        }])->findOrFail($reportDetailId);

        return view('front.statistical-formula.edit', compact('reportDetail'));
    }

    /**
     * Cập nhật công thức thống kê
     */
    public function update(Request $request, $reportDetailId)
    {
        $request->validate([
            'formulas' => 'required|array',
            'formulas.*.id' => 'nullable|exists:statistical_formulas,id',
            'formulas.*.criteria' => 'required|string|max:255',
            'formulas.*.formula' => 'required|string|max:255',
            'formulas.*.description' => 'nullable|string',
            'formulas.*.is_active' => 'boolean',
            'formulas.*.sort_order' => 'integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            $reportDetail = ReportDetail::findOrFail($reportDetailId);

            // Xóa tất cả công thức cũ
            $reportDetail->statisticalFormulas()->delete();

            // Tạo công thức mới
            foreach ($request->formulas as $formulaData) {
                StatisticalFormula::create([
                    'report_detail_id' => $reportDetailId,
                    'criteria' => $formulaData['criteria'],
                    'formula' => $formulaData['formula'],
                    'description' => $formulaData['description'] ?? null,
                    'is_active' => $formulaData['is_active'] ?? true,
                    'sort_order' => $formulaData['sort_order'] ?? 0,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật công thức thống kê thành công!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Thêm công thức mới
     */
    public function addFormula(Request $request, $reportDetailId)
    {
        $request->validate([
            'criteria' => 'required|string|max:255',
            'formula' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'integer|min:0',
        ]);

        try {
            StatisticalFormula::create([
                'report_detail_id' => $reportDetailId,
                'criteria' => $request->criteria,
                'formula' => $request->formula,
                'description' => $request->description,
                'is_active' => true,
                'sort_order' => $request->sort_order ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Thêm công thức thành công!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa công thức
     */
    public function deleteFormula($formulaId)
    {
        try {
            StatisticalFormula::findOrFail($formulaId)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa công thức thành công!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hiển thị trang quản lý tiêu chí
     */
    public function criteria()
    {
        $reportGroups = ReportGroup::active()
            ->ordered()
            ->with(['reportDetails' => function ($query) {
                $query->active()->ordered()->with(['statisticalFormulas' => function ($q) {
                    $q->ordered();
                }]);
            }])
            ->get();

        return view('front.statistical-formula.criteria', compact('reportGroups'));
    }

    /**
     * Bật/tắt tiêu chí
     */
    public function toggleCriteria(Request $request, $formulaId)
    {
        try {
            $formula = StatisticalFormula::findOrFail($formulaId);
            $formula->is_active = !$formula->is_active;
            $formula->save();

            return response()->json([
                'success' => true,
                'message' => $formula->is_active ? 'Đã kích hoạt tiêu chí' : 'Đã tắt tiêu chí',
                'is_active' => $formula->is_active
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }
}
