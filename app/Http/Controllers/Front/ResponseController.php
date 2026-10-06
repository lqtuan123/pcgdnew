<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResponseController extends Controller
{
    /**
     * Hiển thị trang nhập liệu
     */
    public function index(): View
    {
        return view('front.responses.index');
    }
}
