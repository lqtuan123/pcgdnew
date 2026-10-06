<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportDetail;
use App\Models\StatisticalFormula;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticalFormulaController extends Controller
{
    /**
     * Hiển thị danh sách tiêu chí thống kê
     */
    public function index(Request $request)
    {
        $query = StatisticalFormula::with('reportDetail.reportGroup');

        // Filter by report detail
        if ($request->filled('report_detail_id')) {
            $query->where('report_detail_id', $request->report_detail_id);
        }

        $statisticalFormulas = $query->ordered()->paginate(20);
        $reportDetail = null;

        if ($request->filled('report_detail_id')) {
            $reportDetail = ReportDetail::with('reportGroup')->findOrFail($request->report_detail_id);
        }

        return view('admin.statistical-formulas.index', compact('statisticalFormulas', 'reportDetail'));
    }

    /**
     * Hiển thị form tạo tiêu chí mới
     */
    public function create(Request $request)
    {
        $reportDetail = null;
        $reportDetails = collect(); // Empty collection by default

        if ($request->filled('report_detail_id')) {
            $reportDetail = ReportDetail::with('reportGroup')->findOrFail($request->report_detail_id);
        } else {
            // Only load all report details if no specific report is selected
            $reportDetails = ReportDetail::with('reportGroup')->active()->ordered()->get();
        }

        return view('admin.statistical-formulas.create', compact('reportDetail', 'reportDetails'));
    }

    /**
     * Lưu tiêu chí mới
     */
    public function store(Request $request)
    {
        $request->validate([
            'report_detail_id' => 'required|exists:report_details,id',
            'criteria' => 'required|string|max:255',
            'formula' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        StatisticalFormula::create([
            'report_detail_id' => $request->report_detail_id,
            'criteria' => $request->criteria,
            'formula' => $request->formula,
            'description' => $request->description,
            'is_active' => $request->has('is_active') ? $request->is_active : true,
            'sort_order' => $request->sort_order ?? 0,
        ]);

        return redirect()->route('admin.statistical-formulas.index', ['report_detail_id' => $request->report_detail_id])
            ->with('success', 'Tiêu chí thống kê đã được tạo thành công');
    }

    /**
     * Hiển thị form chỉnh sửa tiêu chí
     */
    public function edit(StatisticalFormula $statisticalFormula)
    {
        $statisticalFormula->load('reportDetail.reportGroup');
        $reportDetails = ReportDetail::with('reportGroup')->active()->ordered()->get();

        return view('admin.statistical-formulas.edit', compact('statisticalFormula', 'reportDetails'));
    }

    /**
     * Cập nhật tiêu chí
     */
    public function update(Request $request, StatisticalFormula $statisticalFormula)
    {
        $request->validate([
            'report_detail_id' => 'required|exists:report_details,id',
            'criteria' => 'required|string|max:255',
            'formula' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $statisticalFormula->update([
            'report_detail_id' => $request->report_detail_id,
            'criteria' => $request->criteria,
            'formula' => $request->formula,
            'description' => $request->description,
            'is_active' => $request->has('is_active') ? $request->is_active : $statisticalFormula->is_active,
            'sort_order' => $request->sort_order ?? $statisticalFormula->sort_order,
        ]);

        return redirect()->route('admin.statistical-formulas.index', ['report_detail_id' => $request->report_detail_id])
            ->with('success', 'Tiêu chí thống kê đã được cập nhật thành công');
    }

    /**
     * Xóa tiêu chí
     */
    public function destroy(StatisticalFormula $statisticalFormula)
    {
        $reportDetailId = $statisticalFormula->report_detail_id;
        $statisticalFormula->delete();

        return redirect()->route('admin.statistical-formulas.index', ['report_detail_id' => $reportDetailId])
            ->with('success', 'Tiêu chí thống kê đã được xóa thành công');
    }
}
