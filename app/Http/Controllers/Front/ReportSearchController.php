<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\ReportGroup;
use App\Models\ReportDetail;
use App\Models\GeoUnit;
use Illuminate\Http\Request;

class ReportSearchController extends Controller
{
    /**
     * Hiển thị trang tra cứu thống kê
     */
    public function index(Request $request)
    {
        // Lấy scheme địa chính (mặc định là 'new')
        $scheme = $request->input('scheme', 'new');
        
        // Lấy thông tin geo từ request
        $geoData = $request->input('geo', []);
        $provinceId = $geoData['province_id'] ?? null;
        $districtId = $geoData['district_id'] ?? null;
        $wardId = $geoData['ward_id'] ?? null;
        $villageId = $geoData['village_id'] ?? null;
        
        // Lấy danh sách nhóm báo cáo
        $reportGroups = ReportGroup::active()
            ->ordered()
            ->get();

        // Lấy danh sách báo cáo chi tiết (nếu có chọn nhóm)
        $reportDetails = collect();
        if ($request->filled('report_group_id')) {
            $reportDetails = ReportDetail::where('report_group_id', $request->report_group_id)
                ->active()
                ->ordered()
                ->get();
        }

        return view('front.reports.search', compact(
            'reportGroups', 
            'reportDetails', 
            'scheme',
            'provinceId',
            'districtId', 
            'wardId',
            'villageId'
        ));
    }

    /**
     * API lấy báo cáo chi tiết theo nhóm
     */
    public function getReportDetails(Request $request)
    {
        $reportGroupId = $request->get('report_group_id');
        
        if (!$reportGroupId) {
            return response()->json([]);
        }

        $reportDetails = ReportDetail::where('report_group_id', $reportGroupId)
            ->active()
            ->ordered()
            ->get(['id', 'name', 'code']);

        return response()->json($reportDetails);
    }
}
