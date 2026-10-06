<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\GeoUnit;
use App\Models\Person;
use App\Models\School;
use App\Models\SurveyForm;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Hiển thị trang dashboard admin với thống kê tổng hợp
     */
    public function index(): View
    {
        // Thống kê cơ bản
        $stats = [
            'total_households' => Household::where('is_deleted', false)->count(),
            'total_wards' => GeoUnit::where('type_level', 3)->count(),
            'total_people' => Person::where('is_deleted', false)->count(),
            'total_schools' => School::where('is_dissolved', false)->count(),
            'latest_academic_year' => $this->getLatestAcademicYear(),
        ];

        // Thống kê người dùng theo cấp
        $userStats = [
            'total_users' => \App\Models\User::count(),
            'province_users' => \App\Models\User::where('role_level', 'province')->count(),
            'ward_users' => \App\Models\User::where('role_level', 'ward')->count(),
            'school_users' => \App\Models\User::where('role_level', 'school')->count(),
            'admin_users' => \App\Models\User::whereHas('userRole', function($q) { 
                $q->where('name', 'admin'); 
            })->count(),
        ];

        // Người dùng gần đây
        $recentUsers = \App\Models\User::latest()->take(8)->get();

        return view('admin.dashboard', compact('stats', 'userStats', 'recentUsers'));
    }

    /**
     * Lấy năm học mới nhất từ dữ liệu survey
     */
    private function getLatestAcademicYear(): string
    {
        $latestYear = SurveyForm::whereNotNull('academic_year')
            ->where('academic_year', '!=', '')
            ->orderBy('academic_year', 'desc')
            ->value('academic_year');

        if (!$latestYear) {
            // Fallback: tính năm học hiện tại
            $currentYear = (int) now()->format('Y');
            $currentMonth = (int) now()->format('n');
            return $currentMonth >= 8 ? "{$currentYear}-" . ($currentYear + 1) : ($currentYear - 1) . "-{$currentYear}";
        }

        return $latestYear;
    }
}
