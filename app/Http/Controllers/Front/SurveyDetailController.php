<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class SurveyDetailController extends Controller
{
    public function show($survey)
    {
        // Kiểm tra quyền xem chi tiết đối tượng điều tra
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_doi_tuong_dieu_tra', 'view')) {
            abort(403, 'Bạn không có quyền xem chi tiết đối tượng điều tra.');
        }

        return response()->json(['message' => 'Survey detail not implemented yet']);
    }

    public function data($survey)
    {
        // Kiểm tra quyền xem dữ liệu đối tượng điều tra
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_doi_tuong_dieu_tra', 'view')) {
            return response()->json(['error' => 'Bạn không có quyền xem dữ liệu đối tượng điều tra.'], 403);
        }

        return response()->json(['message' => 'Survey detail data not implemented yet']);
    }
}
