<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\ReportGroup;
use App\Models\ReportDetail;
use App\Models\ReportSubmission;
use App\Models\ReportDeadline;
use App\Models\GeoUnit;
use App\Models\School;

class ReportManagementFrController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $level = $user->role_level; // school, ward, province, admin, superadmin
        $groups = ReportGroup::with('reportDetails')->active()->ordered()->get();
        $submissionsQuery = ReportSubmission::query()->with(['group', 'detail', 'user', 'school', 'ward', 'province']);

        // Apply filters from request
        if ($request->filled('report_group_id')) {
            $submissionsQuery->where('report_group_id', $request->report_group_id);
        }
        
        if ($request->filled('report_detail_id')) {
            $submissionsQuery->where('report_detail_id', $request->report_detail_id);
        }
        
        if ($request->filled('academic_year')) {
            $submissionsQuery->where('academic_year', 'like', '%' . $request->academic_year . '%');
        }
        
        if ($request->filled('status')) {
            $submissionsQuery->where('status', $request->status);
        }

        // Apply province reports filter (for province view only)
        if ($level === 'province') {
            if ($request->filled('province_reports_filter_group_id')) {
                $submissionsQuery->where('report_group_id', $request->province_reports_filter_group_id);
            }
            
            if ($request->filled('province_reports_filter_detail_id')) {
                $submissionsQuery->where('report_detail_id', $request->province_reports_filter_detail_id);
            }
            
            // Apply academic year filter - default to current academic year if not specified
            $currentYear = date('Y');
            $currentAcademicYear = $currentYear . '-' . ($currentYear + 1);
            $academicYearFilter = $request->get('province_reports_filter_academic_year', $currentAcademicYear);
            if ($academicYearFilter) {
                $submissionsQuery->where('academic_year', $academicYearFilter);
            }
            
            if ($request->filled('province_reports_filter_status')) {
                $submissionsQuery->where('status', $request->province_reports_filter_status);
            }
        }

        // Apply geo-based filtering based on user's role level
        if ($level === 'school') {
            $submissionsQuery->whereIn('school_id', $user->getAccessibleSchoolIds());
        } elseif ($level === 'ward') {
            // Ward user sees reports from schools in their ward
            $submissionsQuery->whereIn('ward_id', $user->getAccessibleWardIds());
        } elseif ($level === 'province') {
            // Province user sees reports from schools in their province
            $submissionsQuery->whereIn('province_id', $user->getAccessibleProvinceIds());
        }

        // Filter submissions for the main table based on the current user's level
        // Ward user should see reports at 'ward' stage (ready for their review)
        // Province user should see reports at 'province' stage (ready for their review)
        if ($level === 'school') {
            $submissions = (clone $submissionsQuery)->where('stage', 'school')->latest()->paginate(10);
        } elseif ($level === 'ward') {
            $submissions = (clone $submissionsQuery)->where('stage', 'ward')->latest()->paginate(10);
        } elseif ($level === 'province') {
            // For Province: Group by Ward for better management
            $submissions = (clone $submissionsQuery)->where('stage', 'province')->latest()->paginate(10);
            
            // Get submissions grouped by Ward for Province view
            $submissionsByWard = (clone $submissionsQuery)
                ->where('stage', 'province')
                ->with(['ward', 'school', 'group', 'detail', 'user'])
                ->get()
                ->groupBy('ward_id');
        } else {
            $submissions = (clone $submissionsQuery)->latest()->paginate(10);
        }

        // Debug: Log the filtering results
        // \Log::info("User {$user->id} with level '{$level}' sees " . $submissions->count() . " submissions");
        if ($submissions->count() > 0) {
            $stages = $submissions->pluck('stage')->unique()->toArray();
            // \Log::info("Stages found: " . implode(', ', $stages));
        }

        // For 'Báo cáo đã gửi' section (for school and ward level)
        $sentSubmissions = null;
        if ($level === 'school') {
            $sentSubmissionsQuery = ReportSubmission::where('submitted_by_user_id', $user->id)
                                                    ->with(['group', 'detail']);
            
            // Apply filters for sent submissions
            if ($request->filled('report_group_id')) {
                $sentSubmissionsQuery->where('report_group_id', $request->report_group_id);
            }
            if ($request->filled('report_detail_id')) {
                $sentSubmissionsQuery->where('report_detail_id', $request->report_detail_id);
            }
            if ($request->filled('academic_year')) {
                $sentSubmissionsQuery->where('academic_year', $request->academic_year);
            }
            if ($request->filled('status')) {
                $sentSubmissionsQuery->where('status', $request->status);
            }
            
            $sentSubmissions = $sentSubmissionsQuery->latest()->paginate(10, ['*'], 'sent_page');
        } elseif ($level === 'ward') {
            // For ward level: show submissions from schools in their area, but only ward and province stages
            $schoolIds = $user->getAccessibleSchoolIds() ?? [];
            $sentSubmissionsQuery = ReportSubmission::whereIn('school_id', $schoolIds)
                                                    ->whereIn('stage', ['ward', 'province'])
                                                    ->with(['group', 'detail', 'school']);
            
            // Apply filters for sent submissions
            if ($request->filled('report_group_id')) {
                $sentSubmissionsQuery->where('report_group_id', $request->report_group_id);
            }
            if ($request->filled('report_detail_id')) {
                $sentSubmissionsQuery->where('report_detail_id', $request->report_detail_id);
            }
            // Apply academic year filter - default to current academic year if not specified
            $currentYear = date('Y');
            $currentAcademicYear = $currentYear . '-' . ($currentYear + 1);
            $academicYearFilter = $request->get('academic_year', $currentAcademicYear);
            if ($academicYearFilter) {
                $sentSubmissionsQuery->where('academic_year', $academicYearFilter);
            }
            if ($request->filled('status')) {
                $sentSubmissionsQuery->where('status', $request->status);
            }
            
            $sentSubmissions = $sentSubmissionsQuery->latest()->paginate(10, ['*'], 'sent_page');
            
            // Load ward's own submissions (school_id = null, submitted by ward user)
            $wardSentSubmissionsQuery = ReportSubmission::where('submitted_by_user_id', $user->id)
                                                        ->whereNull('school_id')
                                                        ->with(['group', 'detail']);
            
            // Apply filters for ward sent submissions
            if ($request->filled('report_group_id')) {
                $wardSentSubmissionsQuery->where('report_group_id', $request->report_group_id);
            }
            if ($request->filled('report_detail_id')) {
                $wardSentSubmissionsQuery->where('report_detail_id', $request->report_detail_id);
            }
            // Apply academic year filter - default to current academic year if not specified
            $currentYear = date('Y');
            $currentAcademicYear = $currentYear . '-' . ($currentYear + 1);
            $academicYearFilter = $request->get('academic_year', $currentAcademicYear);
            if ($academicYearFilter) {
                $wardSentSubmissionsQuery->where('academic_year', $academicYearFilter);
            }
            if ($request->filled('status')) {
                $wardSentSubmissionsQuery->where('status', $request->status);
            }
            
            $wardSentSubmissions = $wardSentSubmissionsQuery->latest()->paginate(10, ['*'], 'ward_sent_page');
            
            // Calculate statistics for school reports only (exclude ward's own reports)
            $schoolStatsQuery = ReportSubmission::whereIn('school_id', $schoolIds)
                                                ->whereIn('stage', ['ward', 'province'])
                                                ->whereNotNull('school_id'); // Exclude ward's own reports
            
            // Apply same filters as sentSubmissions for consistency
            if ($request->filled('report_group_id')) {
                $schoolStatsQuery->where('report_group_id', $request->report_group_id);
            }
            if ($request->filled('report_detail_id')) {
                $schoolStatsQuery->where('report_detail_id', $request->report_detail_id);
            }
            // Apply academic year filter - default to current academic year if not specified
            $currentYear = date('Y');
            $currentAcademicYear = $currentYear . '-' . ($currentYear + 1);
            $academicYearFilter = $request->get('academic_year', $currentAcademicYear);
            if ($academicYearFilter) {
                $schoolStatsQuery->where('academic_year', $academicYearFilter);
            }
            if ($request->filled('status')) {
                $schoolStatsQuery->where('status', $request->status);
            }
            
            $schoolTotal = $schoolStatsQuery->count();
            $schoolApproved = (clone $schoolStatsQuery)->where('status', 'approved')->count();
            $schoolUnderReview = (clone $schoolStatsQuery)->where('status', 'under_review')->count();
            $schoolReturned = (clone $schoolStatsQuery)->where('status', 'returned')->count();
            
            $schoolStats = [
                'total' => $schoolTotal,
                'approved' => $schoolApproved,
                'under_review' => $schoolUnderReview,
                'returned' => $schoolReturned,
            ];
        }

        // Calculate statistics for dashboard cards (without filters)
        $statsQuery = ReportSubmission::query()->with(['group', 'detail', 'user', 'school', 'ward', 'province']);
        
        // Apply geo-based filtering based on user's role level (but not other filters)
        if ($level === 'school') {
            $statsQuery->whereIn('school_id', $user->getAccessibleSchoolIds());
        } elseif ($level === 'ward') {
            // Ward user sees reports from schools in their ward, but only ward and province stages
            $statsQuery->whereIn('ward_id', $user->getAccessibleWardIds())
                      ->whereIn('stage', ['ward', 'province']);
        } elseif ($level === 'province') {
            // Province user sees reports from schools in their province, but only province stage
            $statsQuery->whereIn('province_id', $user->getAccessibleProvinceIds())
                      ->where('stage', 'province');
        }
        
        $totalSubmissions = $statsQuery->count();
        $approvedSubmissions = (clone $statsQuery)->where('status', 'approved')->count();
        $underReviewSubmissions = (clone $statsQuery)->where('status', 'under_review')->count();
        $returnedSubmissions = (clone $statsQuery)->where('status', 'returned')->count();

        $stats = [
            'total' => $totalSubmissions,
            'approved' => $approvedSubmissions,
            'under_review' => $underReviewSubmissions,
            'needs_attention' => $returnedSubmissions, // Using 'returned' for 'Cần xem xét'
        ];

        // Pass submissionsByWard for Province view
        if ($level === 'province') {
            $wards = GeoUnit::whereIn('id', $user->getAccessibleWardIds() ?? [])->orderBy('name')->get(['id','name']);
            // Calculate detailed statistics for Province overview
            $provinceStats = $this->calculateProvinceStats($submissionsByWard, $request);
            
            // Build deadlines query with filters
            $deadlinesQuery = ReportDeadline::whereIn('target_ward_id', $user->getAccessibleWardIds() ?? [])
                ->with(['ward']);
            
            // Apply filters
            if ($request->filled('filter_academic_year')) {
                $deadlinesQuery->where('academic_year', $request->filter_academic_year);
            }
            
            if ($request->filled('filter_ward_id')) {
                $deadlinesQuery->where('target_ward_id', $request->filter_ward_id);
            }
            
            $deadlines = $deadlinesQuery->orderByDesc('updated_at')
                ->paginate(10, ['*'], 'deadline_page');
                
            return view('front.reports.management.index', compact('groups', 'submissions', 'level', 'stats', 'sentSubmissions', 'submissionsByWard', 'provinceStats', 'wards', 'deadlines'));
        }

        // For Ward view, provide schools list for deadline setup
        if ($level === 'ward') {
            $schools = School::whereIn('id', $user->getAccessibleSchoolIds() ?? [])->orderBy('name')->get(['id','name']);
            $deadlines = ReportDeadline::whereIn('target_school_id', $user->getAccessibleSchoolIds() ?? [])
                ->with(['school'])
                ->orderByDesc('updated_at')
                ->paginate(10, ['*'], 'deadline_page');
            
            // Load ward deadlines (deadlines from province to ward)
            $wardDeadlinesQuery = ReportDeadline::where('target_ward_id', $user->ward_id)
                ->where('is_active', true)
                ->with(['group', 'detail']);
            
            // Apply year filter - default to current academic year if not specified
            $currentYear = date('Y');
            $currentAcademicYear = $currentYear . '-' . ($currentYear + 1);
            $filterYear = $request->input('ward_deadline_year', $currentAcademicYear);
            
            if ($filterYear) {
                $wardDeadlinesQuery->where('academic_year', $filterYear);
            }
            
            $wardDeadlines = $wardDeadlinesQuery->orderBy('deadline')->get();
            
            // Load school deadlines with filters
            $schoolDeadlinesQuery = ReportDeadline::whereIn('target_school_id', $user->getAccessibleSchoolIds() ?? [])
                ->with(['school', 'group', 'detail']);
            
            // Apply school deadline filters
            if ($request->filled('school_filter_academic_year')) {
                $schoolDeadlinesQuery->where('academic_year', $request->school_filter_academic_year);
            }
            
            if ($request->filled('school_filter_school_id')) {
                $schoolDeadlinesQuery->where('target_school_id', $request->school_filter_school_id);
            }
            
            $schoolDeadlines = $schoolDeadlinesQuery->orderByDesc('updated_at')
                ->paginate(10, ['*'], 'school_deadline_page');
                
            return view('front.reports.management.index', compact('groups', 'submissions', 'level', 'stats', 'sentSubmissions', 'wardSentSubmissions', 'schools', 'deadlines', 'wardDeadlines', 'schoolDeadlines', 'schoolStats'));
        }

        // For School view, load school deadlines (deadlines from ward to school)
        if ($level === 'school') {
            $schoolDeadlinesQuery = ReportDeadline::where('target_school_id', $user->school_id)
                ->where('is_active', true)
                ->with(['group', 'detail']);
            
            // Apply year filter - default to current academic year if not specified
            $currentYear = date('Y');
            $currentAcademicYear = $currentYear . '-' . ($currentYear + 1);
            $filterYear = $request->input('school_deadline_year', $currentAcademicYear);
            
            if ($filterYear) {
                $schoolDeadlinesQuery->where('academic_year', $filterYear);
            }
            
            $schoolDeadlines = $schoolDeadlinesQuery->orderBy('deadline')->get();
            
            // Load sent submissions with filters
            $sentSubmissionsQuery = ReportSubmission::where('submitted_by_user_id', $user->id)
                ->where('school_id', $user->school_id)
                ->with(['group', 'detail']);
            
            // Apply filters for school sent submissions
            if ($request->filled('school_report_group_id')) {
                $sentSubmissionsQuery->where('report_group_id', $request->school_report_group_id);
            }
            if ($request->filled('school_report_detail_id')) {
                $sentSubmissionsQuery->where('report_detail_id', $request->school_report_detail_id);
            }
            // Apply academic year filter - default to current academic year if not specified
            $currentYear = date('Y');
            $currentAcademicYear = $currentYear . '-' . ($currentYear + 1);
            $academicYearFilter = $request->get('school_academic_year', $currentAcademicYear);
            if ($academicYearFilter) {
                $sentSubmissionsQuery->where('academic_year', $academicYearFilter);
            }
            if ($request->filled('school_status')) {
                $sentSubmissionsQuery->where('status', $request->school_status);
            }
            
            $sentSubmissions = $sentSubmissionsQuery->latest()->paginate(10, ['*'], 'sent_page');
                
            return view('front.reports.management.index', compact('groups', 'submissions', 'level', 'stats', 'sentSubmissions', 'schoolDeadlines'));
        }

        return view('front.reports.management.index', compact('groups', 'submissions', 'level', 'stats', 'sentSubmissions'));
    }

    public function setWardDeadline(Request $request)
    {
        $user = auth()->user();
        if (!$user || $user->role_level !== 'province') {
            return back()->with('error', 'Chỉ cấp tỉnh/thành phố mới được cài đặt hạn nộp cho Ward.');
        }

        $validated = $request->validate([
            'report_group_id' => ['required','integer','exists:report_groups,id'],
            'report_detail_id' => ['nullable','integer','exists:report_details,id'],
            'academic_year' => ['required','string','regex:/^\d{4}-\d{4}$/'],
            'target_ward_ids' => ['nullable','array'],
            'target_ward_ids.*' => ['integer'],
            'apply_all' => ['nullable','boolean'],
            'deadline' => ['required','date','after:today'],
            'note' => ['nullable','string','max:2000'],
        ]);

        $accessibleWardIds = $user->getAccessibleWardIds() ?? [];
        $targetWardIds = [];
        if ($request->boolean('apply_all')) {
            $targetWardIds = $accessibleWardIds;
        } else {
            $targetWardIds = array_values(array_intersect($validated['target_ward_ids'] ?? [], $accessibleWardIds));
        }
        if (empty($targetWardIds)) {
            return back()->with('error', 'Vui lòng chọn Ward hợp lệ hoặc bật áp dụng tất cả.');
        }

        foreach ($targetWardIds as $wardId) {
            ReportDeadline::updateOrCreate([
                'report_group_id' => $validated['report_group_id'],
                'report_detail_id' => $validated['report_detail_id'] ?? null,
                'target_ward_id' => $wardId,
                'academic_year' => $validated['academic_year'],
            ], [
                'set_by_user_id' => $user->id,
                'deadline' => $validated['deadline'],
                'academic_year' => $validated['academic_year'],
                'note' => $validated['note'] ?? null,
                'is_active' => true,
            ]);
        }

        return back()->with('success', 'Đã cài đặt hạn nộp cho các Ward đã chọn.');
    }

    public function setSchoolDeadline(Request $request)
    {
        $user = auth()->user();
        if (!$user || $user->role_level !== 'ward') {
            return back()->with('error', 'Chỉ cấp xã/phường mới được cài đặt hạn nộp cho Trường.');
        }

        $validated = $request->validate([
            'report_group_id' => ['required','integer','exists:report_groups,id'],
            'school_report_detail_id' => ['nullable','integer','exists:report_details,id'],
            'target_school_ids' => ['nullable','array'],
            'target_school_ids.*' => ['integer'],
            'apply_all' => ['nullable','boolean'],
            'school_academic_year' => ['required','string'],
            'school_deadline' => ['required','date','after:today'],
            'note' => ['nullable','string','max:2000'],
        ]);

        $accessibleSchoolIds = $user->getAccessibleSchoolIds() ?? [];
        $targetSchoolIds = [];
        if ($request->boolean('apply_all')) {
            $targetSchoolIds = $accessibleSchoolIds;
        } else {
            $targetSchoolIds = array_values(array_intersect($validated['target_school_ids'] ?? [], $accessibleSchoolIds));
        }
        if (empty($targetSchoolIds)) {
            return back()->with('error', 'Vui lòng chọn Trường hợp lệ hoặc bật áp dụng tất cả.');
        }

        foreach ($targetSchoolIds as $schoolId) {
            ReportDeadline::updateOrCreate([
                'report_group_id' => $validated['report_group_id'],
                'report_detail_id' => $validated['school_report_detail_id'] ?? null,
                'target_school_id' => $schoolId,
                'academic_year' => $validated['school_academic_year'],
            ], [
                'set_by_user_id' => $user->id,
                'deadline' => $validated['school_deadline'],
                'academic_year' => $validated['school_academic_year'],
                'note' => $validated['note'] ?? null,
                'is_active' => true,
            ]);
        }

        return back()->with('success', 'Đã cài đặt hạn nộp cho các Trường đã chọn.');
    }

    public function updateDeadline(Request $request, ReportDeadline $deadline)
    {
        $user = auth()->user();
        if (!$user) return back()->with('error', 'Bạn cần đăng nhập.');

        // Scope check
        if ($user->role_level === 'province') {
            if (!$deadline->target_ward_id || !in_array($deadline->target_ward_id, $user->getAccessibleWardIds() ?? [])) {
                return back()->with('error', 'Không có quyền với hạn nộp này.');
            }
        } elseif ($user->role_level === 'ward') {
            if (!$deadline->target_school_id || !in_array($deadline->target_school_id, $user->getAccessibleSchoolIds() ?? [])) {
                return back()->with('error', 'Không có quyền với hạn nộp này.');
            }
        } else {
            return back()->with('error', 'Chỉ Ward/Province được chỉnh hạn nộp.');
        }

        $validated = $request->validate([
            'deadline' => ['nullable','date','after:today'],
            'note' => ['nullable','string','max:2000'],
        ]);

        $updates = [
            'is_active' => true,
        ];
        if (array_key_exists('deadline', $validated) && !empty($validated['deadline'])) {
            $updates['deadline'] = $validated['deadline'];
        }
        if (array_key_exists('note', $validated)) {
            $updates['note'] = $validated['note'];
        }
        $deadline->update($updates);

        return back()->with('success', 'Đã cập nhật hạn nộp.');
    }

    public function deactivateDeadline(ReportDeadline $deadline)
    {
        $user = auth()->user();
        if (!$user) return back()->with('error', 'Bạn cần đăng nhập.');
        if ($user->role_level === 'province') {
            if (!$deadline->target_ward_id || !in_array($deadline->target_ward_id, $user->getAccessibleWardIds() ?? [])) {
                return back()->with('error', 'Không có quyền với hạn nộp này.');
            }
        } elseif ($user->role_level === 'ward') {
            if (!$deadline->target_school_id || !in_array($deadline->target_school_id, $user->getAccessibleSchoolIds() ?? [])) {
                return back()->with('error', 'Không có quyền với hạn nộp này.');
            }
        } else {
            return back()->with('error', 'Chỉ Ward/Province được chỉnh hạn nộp.');
        }

        $deadline->update(['is_active' => false]);
        return back()->with('success', 'Đã tắt hạn nộp.');
    }

    public function activateDeadline(ReportDeadline $deadline)
    {
        $user = auth()->user();
        if (!$user) return back()->with('error', 'Bạn cần đăng nhập.');
        if ($user->role_level === 'province') {
            if (!$deadline->target_ward_id || !in_array($deadline->target_ward_id, $user->getAccessibleWardIds() ?? [])) {
                return back()->with('error', 'Không có quyền với hạn nộp này.');
            }
        } elseif ($user->role_level === 'ward') {
            if (!$deadline->target_school_id || !in_array($deadline->target_school_id, $user->getAccessibleSchoolIds() ?? [])) {
                return back()->with('error', 'Không có quyền với hạn nộp này.');
            }
        } else {
            return back()->with('error', 'Chỉ Ward/Province được chỉnh hạn nộp.');
        }

        $deadline->update(['is_active' => true]);
        return back()->with('success', 'Đã bật hạn nộp.');
    }

    public function destroyDeadline(ReportDeadline $deadline)
    {
        $user = auth()->user();
        if (!$user) return back()->with('error', 'Bạn cần đăng nhập.');
        if ($user->role_level === 'province') {
            if (!$deadline->target_ward_id || !in_array($deadline->target_ward_id, $user->getAccessibleWardIds() ?? [])) {
                return back()->with('error', 'Không có quyền xóa hạn nộp này.');
            }
        } elseif ($user->role_level === 'ward') {
            if (!$deadline->target_school_id || !in_array($deadline->target_school_id, $user->getAccessibleSchoolIds() ?? [])) {
                return back()->with('error', 'Không có quyền xóa hạn nộp này.');
            }
        } else {
            return back()->with('error', 'Chỉ Ward/Province được xóa hạn nộp.');
        }

        $deadline->delete();
        return back()->with('success', 'Đã xóa hạn nộp.');
    }

    public function bulkDeadlinesAction(Request $request)
    {
        $user = auth()->user();
        if (!$user) return back()->with('error', 'Bạn cần đăng nhập.');

        $validated = $request->validate([
            'action' => ['required','in:update,deactivate,activate,delete'],
            'selected_ids' => ['sometimes','array'],
            'selected_ids.*' => ['integer'],
            'apply_all' => ['nullable','boolean'],
            'deadline' => ['nullable','date','after:today'],
            'note' => ['nullable','string','max:2000'],
        ]);

        // Check if any deadlines are selected
        $selectedIds = $request->input('selected_ids', []);
        
        // Debug: Log the request data
        // \Log::info('Bulk action request:', [
        //     'action' => $request->input('action'),
        //     'selected_ids' => $selectedIds,
        //     'apply_all' => $request->boolean('apply_all'),
        //     'all_request' => $request->all()
        // ]);
        
        // Check if we have selected IDs or apply_all flag
        // selected_ids can be empty array [] when no checkboxes are checked
        if (empty($selectedIds) && !$request->has('apply_all')) {
            return back()->with('error', 'Vui lòng chọn ít nhất một hạn nộp để thực hiện thao tác.');
        }

        // Scope deadlines to user's jurisdiction
        $deadlinesQuery = ReportDeadline::query();
        if (!$request->boolean('apply_all')) {
            $deadlinesQuery->whereIn('id', $selectedIds);
        }
        if ($user->role_level === 'province') {
            $deadlinesQuery->whereIn('target_ward_id', $user->getAccessibleWardIds() ?? []);
        } elseif ($user->role_level === 'ward') {
            $deadlinesQuery->whereIn('target_school_id', $user->getAccessibleSchoolIds() ?? []);
        } else {
            return back()->with('error', 'Chỉ Ward/Province được thao tác hạn nộp.');
        }

        $deadlines = $deadlinesQuery->get();
        if ($deadlines->isEmpty()) {
            return back()->with('error', 'Không tìm thấy hạn nộp hợp lệ trong phạm vi.');
        }

        $affected = 0;
        switch ($validated['action']) {
            case 'update':
                if (empty($validated['deadline'])) {
                    return back()->with('error', 'Vui lòng nhập hạn nộp mới khi cập nhật.');
                }
                foreach ($deadlines as $dl) {
                    $dl->update([
                        'deadline' => $validated['deadline'],
                        'note' => $validated['note'] ?? $dl->note,
                        'is_active' => true,
                    ]);
                    $affected++;
                }
                return back()->with('success', "Đã cập nhật {$affected} hạn nộp.");
            case 'deactivate':
                foreach ($deadlines as $dl) {
                    $dl->update(['is_active' => false]);
                    $affected++;
                }
                return back()->with('success', "Đã tắt {$affected} hạn nộp.");
            case 'activate':
                foreach ($deadlines as $dl) {
                    $dl->update(['is_active' => true]);
                    $affected++;
                }
                return back()->with('success', "Đã bật {$affected} hạn nộp.");
            case 'delete':
                foreach ($deadlines as $dl) {
                    $dl->delete();
                    $affected++;
                }
                return back()->with('success', "Đã xóa {$affected} hạn nộp.");
        }

        return back();
    }

    private function calculateProvinceStats($submissionsByWard, $request = null)
    {
        // Apply filters to submissions if request is provided
        $filteredSubmissionsByWard = $submissionsByWard;
        
        if ($request) {
            // Apply academic year filter - default to current academic year if not specified
            $currentYear = date('Y');
            $currentAcademicYear = $currentYear . '-' . ($currentYear + 1);
            $academicYearFilter = $request->get('province_reports_filter_academic_year', $currentAcademicYear);
            
            if ($academicYearFilter) {
                $filteredSubmissionsByWard = $submissionsByWard->map(function ($submissions) use ($academicYearFilter) {
                    return $submissions->filter(function ($submission) use ($academicYearFilter) {
                        return $submission->academic_year === $academicYearFilter;
                    });
                })->filter(function ($submissions) {
                    return $submissions->count() > 0;
                });
            }
            
            // Apply other filters
            if ($request->filled('province_reports_filter_group_id')) {
                $filteredSubmissionsByWard = $filteredSubmissionsByWard->map(function ($submissions) use ($request) {
                    return $submissions->filter(function ($submission) use ($request) {
                        return $submission->report_group_id == $request->province_reports_filter_group_id;
                    });
                })->filter(function ($submissions) {
                    return $submissions->count() > 0;
                });
            }
            
            if ($request->filled('province_reports_filter_detail_id')) {
                $filteredSubmissionsByWard = $filteredSubmissionsByWard->map(function ($submissions) use ($request) {
                    return $submissions->filter(function ($submission) use ($request) {
                        return $submission->report_detail_id == $request->province_reports_filter_detail_id;
                    });
                })->filter(function ($submissions) {
                    return $submissions->count() > 0;
                });
            }
            
            if ($request->filled('province_reports_filter_status')) {
                $filteredSubmissionsByWard = $filteredSubmissionsByWard->map(function ($submissions) use ($request) {
                    return $submissions->filter(function ($submission) use ($request) {
                        return $submission->status === $request->province_reports_filter_status;
                    });
                })->filter(function ($submissions) {
                    return $submissions->count() > 0;
                });
            }
        }
        
        $totalWards = $filteredSubmissionsByWard->count();
        $totalSubmissions = $filteredSubmissionsByWard->flatten()->count();
        
        // Statistics by status
        $allSubmissions = $filteredSubmissionsByWard->flatten();
        $statusStats = [
            'submitted' => $allSubmissions->where('status', 'submitted')->count(),
            'under_review' => $allSubmissions->where('status', 'under_review')->count(),
            'approved' => $allSubmissions->where('status', 'approved')->count(),
            'returned' => $allSubmissions->where('status', 'returned')->count(),
            'rejected' => $allSubmissions->where('status', 'rejected')->count(),
        ];
        
        // Statistics by report group
        $groupStats = $allSubmissions->groupBy('report_group_id')->map(function ($submissions) {
            return [
                'name' => $submissions->first()->group->name ?? 'N/A',
                'count' => $submissions->count(),
                'approved' => $submissions->where('status', 'approved')->count(),
                'pending' => $submissions->whereIn('status', ['submitted', 'under_review'])->count(),
            ];
        });
        
        // Ward performance
        $wardPerformance = $filteredSubmissionsByWard->map(function ($submissions, $wardId) {
            $ward = $submissions->first()->ward ?? null;
            $total = $submissions->count();
            $approved = $submissions->where('status', 'approved')->count();
            $pending = $submissions->whereIn('status', ['submitted', 'under_review'])->count();
            $returned = $submissions->where('status', 'returned')->count();
            
            return [
                'ward_id' => $wardId,
                'ward_name' => $ward ? $ward->name : "Ward ID: {$wardId}",
                'total' => $total,
                'approved' => $approved,
                'pending' => $pending,
                'returned' => $returned,
                'completion_rate' => $total > 0 ? round(($approved / $total) * 100, 1) : 0,
            ];
        })->sortByDesc('completion_rate');
        
        // Recent activity (last 7 days)
        $recentActivity = $allSubmissions->filter(function ($submission) {
            return $submission->created_at && $submission->created_at->isAfter(now()->subDays(7));
        })->count();
        
        return [
            'total_wards' => $totalWards,
            'total_submissions' => $totalSubmissions,
            'status_stats' => $statusStats,
            'group_stats' => $groupStats,
            'ward_performance' => $wardPerformance,
            'recent_activity' => $recentActivity,
            'completion_rate' => $totalSubmissions > 0 ? round(($statusStats['approved'] / $totalSubmissions) * 100, 1) : 0,
        ];
    }

    // School Deadline Management Methods
    public function activateSchoolDeadline(ReportDeadline $deadline)
    {
        $user = auth()->user();
        if (!$user) return back()->with('error', 'Bạn cần đăng nhập.');
        if ($user->role_level === 'ward') {
            if (!$deadline->target_school_id || !in_array($deadline->target_school_id, $user->getAccessibleSchoolIds() ?? [])) {
                return back()->with('error', 'Không có quyền với hạn nộp này.');
            }
        } else {
            return back()->with('error', 'Chỉ Ward được chỉnh hạn nộp.');
        }

        $deadline->update(['is_active' => true]);
        return back()->with('success', 'Đã bật hạn nộp.');
    }

    public function deactivateSchoolDeadline(ReportDeadline $deadline)
    {
        $user = auth()->user();
        if (!$user) return back()->with('error', 'Bạn cần đăng nhập.');
        if ($user->role_level === 'ward') {
            if (!$deadline->target_school_id || !in_array($deadline->target_school_id, $user->getAccessibleSchoolIds() ?? [])) {
                return back()->with('error', 'Không có quyền với hạn nộp này.');
            }
        } else {
            return back()->with('error', 'Chỉ Ward được chỉnh hạn nộp.');
        }

        $deadline->update(['is_active' => false]);
        return back()->with('success', 'Đã tắt hạn nộp.');
    }

    public function deleteSchoolDeadline(ReportDeadline $deadline)
    {
        $user = auth()->user();
        if (!$user) return back()->with('error', 'Bạn cần đăng nhập.');
        if ($user->role_level === 'ward') {
            if (!$deadline->target_school_id || !in_array($deadline->target_school_id, $user->getAccessibleSchoolIds() ?? [])) {
                return back()->with('error', 'Không có quyền xóa hạn nộp này.');
            }
        } else {
            return back()->with('error', 'Chỉ Ward được xóa hạn nộp.');
        }

        $deadline->delete();
        return back()->with('success', 'Đã xóa hạn nộp.');
    }

    public function bulkSchoolDeadlineAction(Request $request)
    {
        $user = auth()->user();
        if (!$user) return back()->with('error', 'Bạn cần đăng nhập.');
        if ($user->role_level !== 'ward') {
            return back()->with('error', 'Chỉ Ward được thao tác hạn nộp.');
        }

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:activate,deactivate,delete'],
            'selected_ids' => ['sometimes', 'array'],
            'selected_ids.*' => ['integer'],
        ]);

        $selectedIds = $request->input('selected_ids', []);
        
        if (empty($selectedIds)) {
            return back()->with('error', 'Vui lòng chọn ít nhất một hạn nộp để thực hiện thao tác.');
        }

        $deadlinesQuery = ReportDeadline::query();
        if (!$request->boolean('apply_all')) {
            $deadlinesQuery->whereIn('id', $selectedIds);
        }
        $deadlinesQuery->whereIn('target_school_id', $user->getAccessibleSchoolIds() ?? []);

        $deadlines = $deadlinesQuery->get();
        if ($deadlines->isEmpty()) {
            return back()->with('error', 'Không tìm thấy hạn nộp hợp lệ trong phạm vi.');
        }

        $action = $validated['action'];
        $count = 0;

        foreach ($deadlines as $deadline) {
            switch ($action) {
                case 'activate':
                    $deadline->update(['is_active' => true]);
                    $count++;
                    break;
                case 'deactivate':
                    $deadline->update(['is_active' => false]);
                    $count++;
                    break;
                case 'delete':
                    $deadline->delete();
                    $count++;
                    break;
            }
        }

        $messages = [
            'activate' => "Đã bật {$count} hạn nộp.",
            'deactivate' => "Đã tắt {$count} hạn nộp.",
            'delete' => "Đã xóa {$count} hạn nộp.",
        ];

        return back()->with('success', $messages[$action]);
    }
}


