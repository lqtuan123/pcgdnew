<?php
// app/Http/Controllers/Front/EthnicityFrontController.php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Ethnicity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EthnicityFrontController extends Controller
{
    public function index()
    {
        // Kiểm tra quyền quản lý dân tộc
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_dan_toc', 'view')) {
            abort(403, 'Bạn không có quyền xem danh mục dân tộc.');
        }
        
        // View sẽ gọi AJAX lấy dữ liệu (data) & export dựa trên query hiện tại
        return view('front.ethnicities.index');
    }

     public function data(Request $req)
    {
        // Kiểm tra quyền quản lý dân tộc
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_dan_toc', 'view')) {
            return response()->json(['error' => 'Bạn không có quyền xem danh mục dân tộc.'], 403);
        }
        
        $q   = trim((string)$req->input('q'));
        $min = $req->input('is_minority');       // '1' | '0' | '' | 'all'
        $per = max(1, min(200, (int)$req->input('per_page', 20)));

        $page = Ethnicity::query()
            ->when($q, function ($qr) use ($q) {
                $qr->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('code', 'like', "%{$q}%")
                      ->orWhere('slug', 'like', "%{$q}%");
                });
            })
            ->when($min !== null && $min !== '' && $min !== 'all', fn($qr) => $qr->where('is_minority', (int)$min))
            ->orderBy('name')
            ->paginate($per);

        // Chỉ trả về 5 trường giống backend
        $page->getCollection()->transform(function (Ethnicity $e) {
            return [
                'id'          => $e->id,
                'code'        => $e->code,                   // có thể null
                'name'        => $e->name,
                'slug'        => $e->slug,
                'is_minority' => (bool)$e->is_minority,
                'note'        => $e->note,
            ];
        });

        return response()->json($page);
    }
    public function export(Request $request): StreamedResponse
    {
        // Kiểm tra quyền quản lý dân tộc
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_dan_toc', 'view')) {
            abort(403, 'Bạn không có quyền xuất danh mục dân tộc.');
        }
        
        $q   = trim((string)$request->input('q'));
        $min = $request->input('is_minority');

        $rows = Ethnicity::query()
            ->when($q, function($qr) use ($q){
                $qr->where(function($w) use ($q){
                    $w->where('name','like',"%{$q}%")
                      ->orWhere('code','like',"%{$q}%")
                      ->orWhere('slug','like',"%{$q}%");
                });
            })
            ->when($min !== null && $min !== '' && $min !== 'all', function($qr) use ($min){
                $qr->where('is_minority', (int)$min);
            })
            ->orderBy('name')
            ->get(['code','name', 'is_minority','note']);

        // Xuất CSV đơn giản (nhanh & thân thiện Excel). Nếu bạn muốn XLSX, mình có thể đổi sang PhpSpreadsheet.
        $filename = 'Danh_muc_Dan_toc.csv';

        return response()->streamDownload(function() use ($rows){
            $out = fopen('php://output', 'w');
            // Header UTF-8 BOM để Excel hiển thị Unicode chuẩn
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            // Tiêu đề
            fputcsv($out, ['STT','Mã dân tộc','Tên dân tộc','Ghi chú','Nhóm']);
            $i = 1;
            foreach ($rows as $r) {
                $note = $r->note ?? '';
                fputcsv($out, [
                    $i++,
                    $r->code,
                    $r->name,
                    $note,
                    $r->is_minority ? 'Thiểu số' : 'Không thiểu số',
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type'              => 'text/csv; charset=UTF-8',
            'Content-Disposition'       => "attachment; filename=\"{$filename}\"",
            'Cache-Control'             => 'no-store, no-cache',
        ]);
    }
}
