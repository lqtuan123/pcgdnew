<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\ReportDetail;
use App\Services\ReportViewerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ReportEntryController extends Controller
{
    public function __construct(
        protected ReportViewerService $viewerService
    ) {}

    public function searchreport(Request $req)
    {
        $key = (int)$req->report_group_id . ':' . (int)$req->report_detail_id;

        $routes = config('reports.routes', []);
        if (!isset($routes[$key])) {
            if ($req->input('mode') === 'preview') {
                return $this->renderErrorHtml('Không tìm thấy mẫu báo cáo đã chọn hoặc hệ thống chưa hỗ trợ mẫu này.');
            }
            return view('front.reports.notfoundreport');
        }

        // Tự động gán province_id nếu chưa chọn (cho admin hoặc khi người dùng chưa chọn tỉnh)
        if (!$req->filled('geo.province_id')) {
            $defaultProvId = auth()->user()?->province_id
                ?? DB::table('survey_forms')->whereNotNull('new_province_id')->value('new_province_id')
                ?? 10831;
            
            $geo = (array) $req->input('geo', []);
            $geo['province_id'] = (int) $defaultProvId;
            $req->merge(['geo' => $geo]);
        }

        // Validate chung + riêng
        $base  = config('reports.base_rules', []);
        $extra = config("reports.extra_rules.$key", []);
        $validator = Validator::make($req->all(), $base + $extra);

        if ($validator->fails()) {
            if ($req->input('mode') === 'preview') {
                $errMessages = [];
                foreach ($validator->errors()->all() as $err) {
                    if (str_contains($err, 'geo.province_id')) {
                        $errMessages[] = 'Vui lòng chọn Tỉnh/Thành phố';
                    } elseif (str_contains($err, 'geo.ward_id')) {
                        $errMessages[] = 'Vui lòng chọn Phường/Xã';
                    } else {
                        $errMessages[] = $err;
                    }
                }
                return $this->renderErrorHtml('Chưa đủ thông tin tra cứu: ' . implode(', ', array_unique($errMessages)));
            }

            $validator->validate();
        }

        // Thêm level suy ra từ group (nếu handler cần)
        $level = match ((int)$req->report_group_id) {
            1 => 'mamnon', 2 => 'primary', 3 => 'lower', default => null,
        };
        $req->merge(['_level' => $level]);

        [$class, $method] = explode('@', $routes[$key], 2);
        $handler = app($class);

        try {
            $response = $handler->$method($req);

            // Chế độ xem trực tiếp trên Web
            if ($req->input('mode') === 'preview') {
                $detail = ReportDetail::find($req->report_detail_id);
                $title = $detail ? $detail->name : 'Báo cáo thống kê';

                $html = $this->viewerService->convertResponseToHtml($response, $title);
                return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
            }

            return $response;
        } catch (\Throwable $e) {
            Log::error('Lỗi sinh báo cáo thống kê', [
                'key' => $key,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'params' => $req->all()
            ]);

            if ($req->input('mode') === 'preview') {
                return $this->renderErrorHtml($e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Hiển thị trang thông báo lỗi đẹp mắt trong iframe preview
     */
    private function renderErrorHtml(string $message): \Illuminate\Http\Response
    {
        $safeMsg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $html = <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thông báo</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            background: #f8fafc;
        }
        .card {
            background: white;
            padding: 32px 36px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            max-width: 520px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .icon {
            font-size: 40px;
            margin-bottom: 12px;
        }
        h2 {
            margin: 0 0 10px 0;
            color: #b45309;
            font-size: 18px;
            font-weight: 700;
        }
        p {
            color: #475569;
            font-size: 14px;
            line-height: 1.6;
            margin: 0 0 16px 0;
            background: #fffbeb;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #fde68a;
        }
        .note {
            font-size: 13px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">📍</div>
        <h2>Cần bổ sung thông tin</h2>
        <p>{$safeMsg}</p>
        <div class="note">Vui lòng kiểm tra lại bộ lọc Năm học, Địa bàn hoặc Biểu mẫu rồi bấm <b>"Xem trực tiếp trên web"</b> lại.</div>
    </div>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
