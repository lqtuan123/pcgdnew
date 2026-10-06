<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Hiển thị trang báo cáo tổng hợp
     */
    public function summary(): View
    {
        return view('front.reports.summary');
    }

    /**
     * Hiển thị trang xuất báo cáo
     */
    public function export(): View
    {
        return view('front.reports.export');
    }
}
