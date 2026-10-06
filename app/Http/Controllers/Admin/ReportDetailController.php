<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportDetail;
use App\Models\ReportGroup;
use Illuminate\Http\Request;

class ReportDetailController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $reportDetails = ReportDetail::with('reportGroup')
            ->active()
            ->ordered()
            ->paginate(20);

        return view('admin.report-details.index', compact('reportDetails'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $reportGroups = ReportGroup::active()->ordered()->get();
        return view('admin.report-details.create', compact('reportGroups'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'report_group_id' => 'required|exists:report_groups,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:report_details',
            'description' => 'nullable|string',
            'value' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        ReportDetail::create([
            'report_group_id' => $request->report_group_id,
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'value' => $request->value,
            'sort_order' => $request->sort_order ?? 0,
        ]);

        return redirect()->route('admin.reports.show', $request->report_group_id)
            ->with('success', 'Báo cáo chi tiết đã được tạo thành công');
    }

    /**
     * Display the specified resource.
     */
    public function show(ReportDetail $reportDetail)
    {
        $reportDetail->load('reportGroup');
        return view('admin.report-details.show', compact('reportDetail'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ReportDetail $reportDetail)
    {
        $reportGroups = ReportGroup::active()->ordered()->get();
        return view('admin.report-details.edit', compact('reportDetail', 'reportGroups'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ReportDetail $reportDetail)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:report_details,code,' . $reportDetail->id,
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

        return redirect()->route('admin.reports.show', $reportDetail->reportGroup)
            ->with('success', 'Báo cáo chi tiết đã được cập nhật thành công');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReportDetail $reportDetail)
    {
        $reportGroup = $reportDetail->reportGroup;
        $reportDetail->delete();

        return redirect()->route('admin.reports.show', $reportGroup)
            ->with('success', 'Báo cáo chi tiết đã được xóa thành công');
    }
}
