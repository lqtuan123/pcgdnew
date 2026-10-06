<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\ReportGroup;
use App\Models\ReportDetail;
use App\Models\ReportConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportConfigController extends Controller
{
    /**
     * Hiển thị trang cấu hình báo cáo
     */
    public function index()
    {
        // Kiểm tra quyền xem
        if (!auth()->user()->hasPermission('cau_hinh_bao_cao', 'view')) {
            abort(403, 'Bạn không có quyền xem trang cấu hình báo cáo.');
        }

        $reportGroups = ReportGroup::active()
            ->ordered()
            ->with(['reportDetails' => function ($query) {
                $query->active()->ordered()->with('reportConfig');
            }])
            ->get();

        // Kiểm tra quyền chỉnh sửa
        $canEdit = auth()->user()->hasPermission('cau_hinh_bao_cao', 'edit');

        return view('front.report-config.index', compact('reportGroups', 'canEdit'));
    }

    /**
     * Cập nhật cấu hình báo cáo
     */
    public function update(Request $request)
    {
        // Kiểm tra quyền chỉnh sửa
        if (!auth()->user()->hasPermission('cau_hinh_bao_cao', 'edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền sửa cấu hình báo cáo.'
            ], 403);
        }

        $request->validate([
            'report_detail_id' => 'required|exists:report_details,id',
            'status' => 'required|in:required,optional,disabled',
        ]);

        try {
            DB::beginTransaction();

            $reportDetailId = $request->report_detail_id;
            $status = $request->status;

            // Tạo hoặc cập nhật cấu hình
            ReportConfig::updateOrCreate(
                ['report_detail_id' => $reportDetailId],
                ['status' => $status]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật cấu hình báo cáo thành công!'
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
     * Cập nhật hàng loạt cấu hình báo cáo
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'configs' => 'required|array',
            'configs.*.report_detail_id' => 'required|exists:report_details,id',
            'configs.*.status' => 'required|in:required,optional,disabled',
        ]);

        try {
            DB::beginTransaction();

            foreach ($request->configs as $config) {
                ReportConfig::updateOrCreate(
                    ['report_detail_id' => $config['report_detail_id']],
                    ['status' => $config['status']]
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật cấu hình báo cáo thành công!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }
}
