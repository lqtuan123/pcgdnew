<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportGroup;
use App\Models\ReportDetail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReportManagementController extends Controller
{
    /**
     * Lấy danh sách tất cả nhóm báo cáo với chi tiết
     */
    public function index(Request $request)
    {
        $query = ReportGroup::with(['reportDetails' => function ($query) {
            $query->active()->ordered();
        }]);

        // Filter by search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by group
        if ($request->filled('group_id')) {
            $query->where('id', $request->group_id);
        }

        $reportGroups = $query->active()->ordered()->paginate(20);
        $allReportGroups = ReportGroup::active()->ordered()->get();

        // For AJAX requests (partial updates)
        if ($request->has('partial')) {
            return view('admin.reports._table', compact('reportGroups'));
        }

        return view('admin.reports.index', compact('reportGroups', 'allReportGroups'));
    }

    /**
     * Lấy chi tiết một nhóm báo cáo
     */
    public function show(ReportGroup $report)
    {
        $report->load(['reportDetails' => function ($query) {
            $query->active()->ordered()->with('statisticalFormulas');
        }]);

        return view('admin.reports.show', ['reportGroup' => $report]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.reports.create');
    }

    /**
     * Tạo nhóm báo cáo mới
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:report_groups',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $reportGroup = ReportGroup::create([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->has('is_active') ? $request->is_active : true,
        ]);

        return redirect()->route('admin.reports.index')
            ->with('success', 'Nhóm báo cáo đã được tạo thành công');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ReportGroup $report)
    {
        return view('admin.reports.edit', ['reportGroup' => $report]);
    }

    /**
     * Cập nhật nhóm báo cáo
     */
    public function update(Request $request, ReportGroup $report)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:report_groups,code,' . $report->id,
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $report->update([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'sort_order' => $request->sort_order ?? $report->sort_order,
            'is_active' => $request->has('is_active') ? $request->is_active : $report->is_active,
        ]);

        return redirect()->route('admin.reports.index')
            ->with('success', 'Nhóm báo cáo đã được cập nhật thành công');
    }

    /**
     * Xóa nhóm báo cáo
     */
    public function destroy(ReportGroup $report)
    {
        // Xóa tất cả báo cáo chi tiết thuộc nhóm này
        $report->reportDetails()->delete();
        
        // Xóa nhóm báo cáo
        $report->delete();

        return redirect()->route('admin.reports.index')
            ->with('success', 'Nhóm báo cáo và tất cả báo cáo chi tiết đã được xóa thành công');
    }

    /**
     * Tạo báo cáo chi tiết mới
     */
    public function storeDetail(Request $request): JsonResponse
    {
        $request->validate([
            'report_group_id' => 'required|exists:report_groups,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:report_details',
            'description' => 'nullable|string',
            'value' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $reportDetail = ReportDetail::create([
            'report_group_id' => $request->report_group_id,
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'value' => $request->value,
            'sort_order' => $request->sort_order ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Báo cáo chi tiết đã được tạo thành công',
            'data' => $reportDetail->load('reportGroup')
        ], 201);
    }

    /**
     * Cập nhật báo cáo chi tiết
     */
    public function updateDetail(Request $request, $id): JsonResponse
    {
        $reportDetail = ReportDetail::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:report_details,code,' . $id,
            'description' => 'nullable|string',
            'value' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $reportDetail->update([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'value' => $request->value,
            'sort_order' => $request->sort_order ?? $reportDetail->sort_order,
            'is_active' => $request->has('is_active') ? $request->is_active : $reportDetail->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Báo cáo chi tiết đã được cập nhật thành công',
            'data' => $reportDetail->load('reportGroup')
        ]);
    }

    /**
     * Xóa báo cáo chi tiết
     */
    public function destroyDetail($id): JsonResponse
    {
        $reportDetail = ReportDetail::findOrFail($id);
        $reportDetail->delete();

        return response()->json([
            'success' => true,
            'message' => 'Báo cáo chi tiết đã được xóa thành công'
        ]);
    }

    /**
     * Lấy danh sách báo cáo chi tiết theo nhóm
     */
    public function getDetailsByGroup($groupId): JsonResponse
    {
        $reportDetails = ReportDetail::byGroup($groupId)
            ->active()
            ->ordered()
            ->with('reportGroup')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reportDetails
        ]);
    }

    /**
     * Cập nhật giá trị báo cáo
     */
    public function updateReportValue(Request $request, $id): JsonResponse
    {
        $request->validate([
            'value' => 'required|string'
        ]);

        $reportDetail = ReportDetail::findOrFail($id);
        $reportDetail->update(['value' => $request->value]);

        return response()->json([
            'success' => true,
            'message' => 'Giá trị báo cáo đã được cập nhật thành công',
            'data' => $reportDetail
        ]);
    }
}
