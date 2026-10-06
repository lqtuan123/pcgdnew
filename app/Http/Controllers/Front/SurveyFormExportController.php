<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Services\SurveyFormExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SurveyFormExportController extends Controller
{
    // ... giữ nguyên byId/bySoPhieu nếu cần
    public function byId(int $id, SurveyFormExportService $svc)
    {
        // Kiểm tra quyền xuất phiếu điều tra
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_phieu_dieu_tra', 'view')) {
            abort(403, 'Bạn không có quyền xuất phiếu điều tra.');
        }

        $spreadsheet = $svc->makeSpreadsheet($id, false);
        return $this->download($spreadsheet, "phieu_dieu_tra_{$id}.xls");
    }

    public function bySoPhieu(string $so, SurveyFormExportService $svc)
    {
        // Kiểm tra quyền xuất phiếu điều tra
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_phieu_dieu_tra', 'view')) {
            abort(403, 'Bạn không có quyền xuất phiếu điều tra.');
        }

        $spreadsheet = $svc->makeSpreadsheet($so, true);
        $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $so);
        return $this->download($spreadsheet, "phieu_dieu_tra_{$safe}.xls");
    }

    protected function download($spreadsheet, string $fileName)
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = IOFactory::createWriter($spreadsheet, 'Xls'); // đổi 'Xlsx' nếu template là .xlsx
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.ms-excel',
        ]);
    }
   public function exportMany(Request $request, SurveyFormExportService $svc)
{
    // Kiểm tra quyền xuất nhiều phiếu điều tra
    $user = Auth::user();
    if (!$user || !$user->hasPermission('quan_ly_phieu_dieu_tra', 'view')) {
        return response()->json([
            'success' => false,
            'message' => 'Bạn không có quyền xuất phiếu điều tra.'
        ], 403);
    }

    $ids = collect((array)$request->input('ids', []))
        ->map(fn($v) => (int)$v)->filter()->unique()->values()->all();

    if (empty($ids)) {
        return back()->with('status', 'Bạn chưa chọn phiếu nào.');
    }

    $wb = $svc->makeWorkbookForMany($ids);  // 👈 tạo workbook nhiều sheet

    return response()->streamDownload(function () use ($wb) {
        $writer = IOFactory::createWriter($wb, 'Xlsx');
        $writer->setPreCalculateFormulas(false); // nhanh hơn nếu có công thức
        $writer->save('php://output');
    }, 'phieu_dieu_tra_multi_'.now()->format('Ymd_His').'.xlsx', [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);
}
}
