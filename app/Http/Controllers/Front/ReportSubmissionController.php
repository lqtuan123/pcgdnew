<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Models\ReportDeadline;
use App\Models\School;
use App\Models\GeoUnit;
use App\Models\ReportSubmission;

class ReportSubmissionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'report_group_id' => ['required','integer','exists:report_groups,id'],
            'report_detail_id' => ['required','integer','exists:report_details,id'],
            'academic_year' => ['nullable','string','max:20'],
            'file' => ['required','file','max:10240'], // 10MB
        ]);

        if (!auth()->check()) {
            return back()->withErrors('Bạn cần đăng nhập để tải báo cáo.');
        }

        $path = $request->file('file')->store('report-submissions','public');

        // Derive ward/province from user's school if available
        $schoolId = $request->user()->school_id ?? null;
        $wardId = null;
        $provinceId = null;
        
        // For Ward level users, set school_id to null to distinguish from school reports
        if ($request->user()->role_level === 'ward') {
            $schoolId = null;
        }
        
        if ($schoolId) {
            $school = School::find($schoolId);
            if ($school) {
                $wardId = $school->getWardId();
                $provinceId = $school->getProvinceId();
                
                // Debug: Log if province_id is still null
                if (!$provinceId) {
                    \Log::warning("School {$schoolId} has no province_id. Ward: {$wardId}");
                }
            }
        }
        
        // Fallback: lấy ward/province trực tiếp từ user nếu chưa suy ra được từ trường
        if (!$wardId) {
            $wardId = $request->user()->ward_id ?? $wardId;
        }
        if (!$provinceId) {
            $provinceId = $request->user()->province_id ?? $provinceId;
            
            // Debug: Log if province_id is still null after fallback
            if (!$provinceId) {
                \Log::warning("User " . $request->user()->id . " has no province_id. Ward: {$wardId}");
            }
        }

        // province_id can be null initially - it will be set when Ward forwards to Province

        // Enforce deadline: Province/Ward configured
        $effectiveDeadline = ReportDeadline::findEffectiveDeadline(
            $validated['report_group_id'],
            $validated['report_detail_id'],
            $schoolId,
            $wardId
        );
        if ($effectiveDeadline && now()->toDateString() > $effectiveDeadline->deadline->toDateString()) {
            return back()->withErrors('Đã quá hạn nộp báo cáo cho loại này. Liên hệ cấp trên để gia hạn.');
        }

        $submission = ReportSubmission::create([
            'report_group_id' => $validated['report_group_id'],
            'report_detail_id' => $validated['report_detail_id'],
            'submitted_by_user_id' => auth()->id(),
            'school_id' => $schoolId,
            'ward_id' => $wardId,
            'province_id' => $provinceId,
            'academic_year' => $validated['academic_year'] ?? null,
            // Lưu hạn nộp hiệu lực (nếu có) để hiển thị/hậu kiểm
            'deadline' => $effectiveDeadline?->deadline,
            'file_path' => $path,
            'original_name' => $request->file('file')->getClientOriginalName(),
            'mime_type' => $request->file('file')->getClientMimeType(),
            'file_size' => $request->file('file')->getSize(),
            // Khởi tạo stage theo role level
            'stage' => $request->user()->role_level === 'ward' ? 'ward' : 'school',
            'status' => 'submitted',
        ]);

        return back()->with('success', 'Tải báo cáo thành công.');
    }

    public function updateStatus(Request $request, ReportSubmission $submission)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['under_review','returned','approved','rejected'])],
            'note' => ['nullable','string','max:2000'],
            'feedback' => ['nullable','string','max:2000'],
        ]);

        $user = auth()->user();
        $userLevel = $user->role_level;
        $newStatus = $validated['status'];

        // Validate user has permission to update status at current stage
        if ($userLevel === 'school' && $submission->stage !== 'school') {
            return back()->with('error', 'Bạn chỉ có thể cập nhật trạng thái báo cáo ở cấp trường.');
        }
        if ($userLevel === 'ward' && $submission->stage !== 'ward') {
            return back()->with('error', 'Bạn chỉ có thể cập nhật trạng thái báo cáo ở cấp xã/phường.');
        }
        if ($userLevel === 'province' && $submission->stage !== 'province') {
            return back()->with('error', 'Bạn chỉ có thể cập nhật trạng thái báo cáo ở cấp tỉnh/thành phố.');
        }

        // Handle rejection - return to previous stage
        if ($newStatus === 'rejected') {
            $oldStage = $submission->stage;
            if ($submission->stage === 'ward') {
                // Ward rejects -> return to school
                $submission->stage = 'school';
                $submission->status = 'returned';
                \Log::info("Report {$submission->id} rejected by Ward user {$user->id}, returned to School stage");
            } elseif ($submission->stage === 'province') {
                // Province rejects -> return to ward
                $submission->stage = 'ward';
                $submission->status = 'returned';
                \Log::info("Report {$submission->id} rejected by Province user {$user->id}, returned to Ward stage");
            }
        } else {
            // Other status updates
            $submission->status = $newStatus;
        }

        $submission->fill([
            'note' => $validated['note'] ?? null,
            'feedback' => $validated['feedback'] ?? null,
            'reviewed_by_user_id' => auth()->id(),
            'reviewed_at' => now(),
            'feedback_at' => $validated['feedback'] ? now() : null,
        ])->save();

        $statusMessages = [
            'under_review' => 'Đang xem xét',
            'returned' => 'Trả lại',
            'approved' => 'Đã duyệt',
            'rejected' => 'Từ chối'
        ];

        $message = "Cập nhật trạng thái thành công: {$statusMessages[$newStatus]}";
        if ($newStatus === 'rejected') {
            $message .= " - Báo cáo đã được trả lại cho cấp dưới";
        }

        return back()->with('success', $message);
    }

    public function forward(Request $request, ReportSubmission $submission)
    {
        $user = auth()->user();
        if (!$user) {
            return back()->with('error', 'Bạn cần đăng nhập để thực hiện thao tác này.');
        }

        $userLevel = $user->role_level;
        
        // Validate user has permission to forward this submission
        if ($userLevel === 'school' && $submission->stage !== 'school') {
            return back()->with('error', 'Bạn chỉ có thể chuyển tiếp báo cáo đang ở cấp trường.');
        }
        if ($userLevel === 'ward' && $submission->stage !== 'ward') {
            return back()->with('error', 'Bạn chỉ có thể chuyển tiếp báo cáo đang ở cấp xã/phường.');
        }
        if ($userLevel === 'province') {
            return back()->with('error', 'Cấp tỉnh/thành phố không thể chuyển tiếp báo cáo.');
        }

        // Determine next stage and ensure hierarchical routing
        $next = $submission->stage === 'school' ? 'ward' : ($submission->stage === 'ward' ? 'province' : null);
        
        if (!$next) {
            return back()->with('error', 'Không thể chuyển tiếp báo cáo này.');
        }

        // Ensure geo-data is properly set
        if ($next === 'ward' && !$submission->ward_id) {
            // Try derive ward again from school
            if ($submission->school_id) {
                $school = School::find($submission->school_id);
                $submission->ward_id = $school?->getWardId();
                $submission->province_id = $school?->getProvinceId();
            }
        }
        if ($next === 'province' && !$submission->province_id) {
            if ($submission->ward_id) {
                $ward = GeoUnit::find($submission->ward_id);
                $submission->province_id = $ward?->getProvinceId();
            }
        }

        $submission->stage = $next;
        $submission->status = 'submitted'; // Reset status to submitted for the next stage
        $submission->forwarded_at = now();
        // Keep feedback and feedback_at when forwarding from school or ward level
        if ($userLevel === 'province') {
            // Only clear feedback when province forwards (which shouldn't happen)
            $submission->feedback = null;
            $submission->feedback_at = null;
        }
        $submission->save();

        $stageNames = [
            'school' => 'trường',
            'ward' => 'xã/phường', 
            'province' => 'tỉnh/thành phố'
        ];

        return back()->with('success', "Báo cáo đã được chuyển tiếp lên cấp {$stageNames[$next]} thành công!");
    }

    public function recall(Request $request, ReportSubmission $submission)
    {
        $user = auth()->user();
        if (!$user) {
            return back()->with('error', 'Bạn cần đăng nhập để thực hiện thao tác này.');
        }

        // Only the submitter can recall their submission
        if ($submission->submitted_by_user_id !== $user->id) {
            return back()->with('error', 'Bạn chỉ có thể thu hồi báo cáo do chính mình gửi.');
        }

        // Can only recall if at school stage
        if ($submission->stage !== 'school') {
            return back()->with('error', 'Không thể thu hồi báo cáo đã được chuyển tiếp.');
        }

        // Delete the file
        if ($submission->file_path && \Storage::disk('public')->exists($submission->file_path)) {
            \Storage::disk('public')->delete($submission->file_path);
        }

        // Delete the submission
        $submission->delete();

        return back()->with('success', 'Báo cáo đã được thu hồi thành công.');
    }

    public function edit(Request $request, ReportSubmission $submission)
    {
        $user = auth()->user();
        if (!$user) {
            return back()->with('error', 'Bạn cần đăng nhập để thực hiện thao tác này.');
        }

        // Only the submitter can edit their submission
        if ($submission->submitted_by_user_id !== $user->id) {
            return back()->with('error', 'Bạn chỉ có thể chỉnh sửa báo cáo do chính mình gửi.');
        }

        // Can only edit if at school or ward stage (for ward's own submissions)
        if ($submission->stage !== 'school' && $submission->stage !== 'ward') {
            return back()->with('error', 'Không thể chỉnh sửa báo cáo đã được chuyển tiếp.');
        }
        
        // For ward stage, only allow editing if it's the ward's own submission
        if ($submission->stage === 'ward' && $submission->submitted_by_user_id !== $user->id) {
            return back()->with('error', 'Không thể chỉnh sửa báo cáo đã được chuyển tiếp.');
        }

        $validated = $request->validate([
            'report_group_id' => ['required','integer','exists:report_groups,id'],
            'report_detail_id' => ['required','integer','exists:report_details,id'],
            'academic_year' => ['nullable','string','max:20'],
            'file' => ['nullable','file','max:10240'], // Optional file update
        ]);

        // Update file if provided
        if ($request->hasFile('file')) {
            // Delete old file
            if ($submission->file_path && \Storage::disk('public')->exists($submission->file_path)) {
                \Storage::disk('public')->delete($submission->file_path);
            }

            // Store new file
            $path = $request->file('file')->store('report-submissions','public');
            $submission->file_path = $path;
            $submission->original_name = $request->file('file')->getClientOriginalName();
            $submission->mime_type = $request->file('file')->getClientMimeType();
            $submission->file_size = $request->file('file')->getSize();
        }

        // Update other fields
        // Cập nhật hạn nộp theo cấu hình hiệu lực thay vì người dùng nhập
        $effectiveDeadline = null;
        if ($submission->school_id) {
            // For school submissions, find school deadline
            $effectiveDeadline = ReportDeadline::findEffectiveDeadline(
                $validated['report_group_id'],
                $validated['report_detail_id'],
                $submission->school_id,
                $submission->ward_id
            );
        } else {
            // For ward submissions (school_id = null), find ward deadline
            $effectiveDeadline = ReportDeadline::findEffectiveDeadline(
                $validated['report_group_id'],
                $validated['report_detail_id'],
                null, // school_id = null for ward submissions
                $submission->ward_id
            );
        }

        $submission->update([
            'report_group_id' => $validated['report_group_id'],
            'report_detail_id' => $validated['report_detail_id'],
            'academic_year' => $validated['academic_year'] ?? null,
            'deadline' => $effectiveDeadline?->deadline,
            'status' => 'submitted', // Reset status
        ]);

        return redirect()->route('front.reports.search.management.index')->with('success', 'Báo cáo đã được cập nhật thành công.');
    }

    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'report_group_id' => ['required','integer','exists:report_groups,id'],
            'report_detail_id' => ['required','integer','exists:report_details,id'],
            'academic_year' => ['nullable','string','max:20'],
            'files' => ['required','array','min:1','max:10'],
            'files.*' => ['required','file','max:10240'], // 10MB per file
        ]);

        if (!auth()->check()) {
            return back()->withErrors('Bạn cần đăng nhập để tải báo cáo.');
        }

        $user = auth()->user();
        $schoolId = $user->school_id ?? null;
        $wardId = null;
        $provinceId = null;
        
        if ($schoolId) {
            $school = School::find($schoolId);
            if ($school) {
                $wardId = $school->getWardId();
                $provinceId = $school->getProvinceId();
            }
        }
        
        if (!$wardId) {
            $wardId = $user->ward_id ?? $wardId;
        }
        if (!$provinceId) {
            $provinceId = $user->province_id ?? $provinceId;
        }

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($validated['files'] as $index => $file) {
            try {
                // Enforce deadline per file (same config applies)
                $effectiveDeadline = ReportDeadline::findEffectiveDeadline(
                    $validated['report_group_id'],
                    $validated['report_detail_id'],
                    $schoolId,
                    $wardId
                );
                if ($effectiveDeadline && now()->toDateString() > $effectiveDeadline->deadline->toDateString()) {
                    throw new \RuntimeException('Đã quá hạn nộp.');
                }
                $path = $file->store('report-submissions','public');
                
                ReportSubmission::create([
                    'report_group_id' => $validated['report_group_id'],
                    'report_detail_id' => $validated['report_detail_id'],
                    'submitted_by_user_id' => auth()->id(),
                    'school_id' => $schoolId,
                    'ward_id' => $wardId,
                    'province_id' => $provinceId,
                    'academic_year' => $validated['academic_year'] ?? null,
                    'deadline' => $effectiveDeadline?->deadline,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'stage' => 'school',
                    'status' => 'submitted',
                ]);
                
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = "File {$file->getClientOriginalName()}: " . $e->getMessage();
            }
        }

        $message = "Đã tải thành công {$successCount} báo cáo.";
        if ($errorCount > 0) {
            $message .= " Có {$errorCount} file lỗi.";
        }

        return back()->with('success', $message)->with('errors', $errors);
    }

    public function multiStore(Request $request)
    {
        $validated = $request->validate([
            'submissions' => ['required','array','min:1','max:10'],
            'submissions.*.report_group_id' => ['required','integer','exists:report_groups,id'],
            'submissions.*.report_detail_id' => ['required','integer','exists:report_details,id'],
            'submissions.*.academic_year' => ['nullable','string','max:20'],
            'submissions.*.file' => ['required','file','max:10240'],
        ]);

        if (!auth()->check()) {
            return back()->withErrors('Bạn cần đăng nhập để tải báo cáo.');
        }

        $user = auth()->user();
        $schoolId = $user->school_id ?? null;
        $wardId = null;
        $provinceId = null;

        if ($schoolId) {
            $school = School::find($schoolId);
            if ($school) {
                $wardId = $school->getWardId();
                $provinceId = $school->getProvinceId();
            }
        }
        if (!$wardId) {
            $wardId = $user->ward_id ?? $wardId;
        }
        if (!$provinceId) {
            $provinceId = $user->province_id ?? $provinceId;
        }

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($validated['submissions'] as $index => $item) {
            try {
                $file = $request->file("submissions.{$index}.file");
                if (!$file) {
                    throw new \RuntimeException('Thiếu file.');
                }

                $effectiveDeadline = ReportDeadline::findEffectiveDeadline(
                    $item['report_group_id'],
                    $item['report_detail_id'],
                    $schoolId,
                    $wardId
                );
                if ($effectiveDeadline && now()->toDateString() > $effectiveDeadline->deadline->toDateString()) {
                    throw new \RuntimeException('Đã quá hạn nộp.');
                }

                $path = $file->store('report-submissions','public');

                ReportSubmission::create([
                    'report_group_id' => $item['report_group_id'],
                    'report_detail_id' => $item['report_detail_id'],
                    'submitted_by_user_id' => auth()->id(),
                    'school_id' => $schoolId,
                    'ward_id' => $wardId,
                    'province_id' => $provinceId,
                    'academic_year' => $item['academic_year'] ?? null,
                    'deadline' => $effectiveDeadline?->deadline,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'stage' => 'school',
                    'status' => 'submitted',
                ]);

                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $fileName = $request->file("submissions.{$index}.file")?->getClientOriginalName() ?: (string)($index+1);
                $errors[] = "Mục {$fileName}: " . $e->getMessage();
            }
        }

        $message = "Đã tạo thành công {$successCount} báo cáo.";
        if ($errorCount > 0) {
            $message .= " Có {$errorCount} mục lỗi.";
        }

        return back()->with('success', $message)->with('errors', $errors);
    }

    /**
     * Delete a report submission
     */
    public function destroy(ReportSubmission $submission)
    {
        $user = auth()->user();
        
        // Authorization check
        if ($user->role_level === 'school') {
            // School users can only delete their own submissions
            if ($submission->submitted_by_user_id !== $user->id) {
                abort(403, 'Bạn không có quyền xóa báo cáo này.');
            }
        } elseif ($user->role_level === 'ward') {
            // Ward users can delete their own submissions or submissions from schools in their area
            $schoolIds = $user->getAccessibleSchoolIds() ?? [];
            if ($submission->submitted_by_user_id !== $user->id && 
                !in_array($submission->school_id, $schoolIds)) {
                abort(403, 'Bạn không có quyền xóa báo cáo này.');
            }
        } elseif ($user->role_level === 'province') {
            // Province users can delete any submission
            // No additional checks needed
        } else {
            abort(403, 'Bạn không có quyền thực hiện thao tác này.');
        }

        try {
            // Delete the file if it exists
            if ($submission->file_path && Storage::exists($submission->file_path)) {
                Storage::delete($submission->file_path);
            }

            // Delete the submission record
            $submission->delete();

            return back()->with('success', 'Đã xóa báo cáo thành công.');
        } catch (\Exception $e) {
            Log::error('Error deleting submission: ' . $e->getMessage());
            return back()->with('error', 'Có lỗi xảy ra khi xóa báo cáo. Vui lòng thử lại.');
        }
    }

    /**
     * View feedback for a report submission
     */
    public function viewFeedback(ReportSubmission $submission)
    {
        $user = auth()->user();
        
        // Authorization check
        if ($user->role_level === 'school') {
            // School users can only view feedback for their own submissions
            if ($submission->submitted_by_user_id !== $user->id) {
                abort(403, 'Bạn không có quyền xem phản hồi của báo cáo này.');
            }
        } elseif ($user->role_level === 'ward') {
            // Ward users can view feedback for their own submissions or submissions from schools in their area
            $schoolIds = $user->getAccessibleSchoolIds() ?? [];
            if ($submission->submitted_by_user_id !== $user->id && 
                !in_array($submission->school_id, $schoolIds)) {
                abort(403, 'Bạn không có quyền xem phản hồi của báo cáo này.');
            }
        } elseif ($user->role_level === 'province') {
            // Province users can view feedback for any submission
            // No additional checks needed
        } else {
            abort(403, 'Bạn không có quyền thực hiện thao tác này.');
        }

        // Return JSON response for AJAX modal
        return response()->json([
            'success' => true,
            'feedback' => $submission->feedback ?? 'Không có phản hồi.',
            'submission_id' => $submission->id,
            'status' => $submission->status,
            'stage' => $submission->stage
        ]);
    }

    /**
     * Edit ward's own report submission
     */
    public function wardEdit(Request $request, ReportSubmission $submission)
    {
        $user = auth()->user();
        if (!$user) {
            return back()->with('error', 'Bạn cần đăng nhập để thực hiện thao tác này.');
        }

        // Only ward users can use this method
        if ($user->role_level !== 'ward') {
            return back()->with('error', 'Chỉ cấp xã mới có thể sử dụng chức năng này.');
        }

        // Only the ward submitter can edit their submission
        if ($submission->submitted_by_user_id !== $user->id) {
            return back()->with('error', 'Bạn chỉ có thể chỉnh sửa báo cáo do chính mình gửi.');
        }

        // Can only edit if at ward stage and status is submitted or returned
        if ($submission->stage !== 'ward' || !in_array($submission->status, ['submitted', 'returned'])) {
            return back()->with('error', 'Không thể chỉnh sửa báo cáo này.');
        }

        $validated = $request->validate([
            'report_group_id' => ['required','integer','exists:report_groups,id'],
            'report_detail_id' => ['required','integer','exists:report_details,id'],
            'academic_year' => ['nullable','string','max:20'],
            'file' => ['nullable','file','max:10240'], // Optional file update
        ]);

        // Update file if provided
        if ($request->hasFile('file')) {
            // Delete old file
            if ($submission->file_path && Storage::disk('public')->exists($submission->file_path)) {
                Storage::disk('public')->delete($submission->file_path);
            }

            // Store new file
            $path = $request->file('file')->store('report-submissions','public');
            $submission->file_path = $path;
            $submission->original_name = $request->file('file')->getClientOriginalName();
            $submission->mime_type = $request->file('file')->getClientMimeType();
            $submission->file_size = $request->file('file')->getSize();
        }

        // Update other fields
        // For ward submissions (school_id = null), find ward deadline
        $effectiveDeadline = ReportDeadline::findEffectiveDeadline(
            $validated['report_group_id'],
            $validated['report_detail_id'],
            null, // school_id = null for ward submissions
            $submission->ward_id
        );

        $submission->update([
            'report_group_id' => $validated['report_group_id'],
            'report_detail_id' => $validated['report_detail_id'],
            'academic_year' => $validated['academic_year'] ?? null,
            'deadline' => $effectiveDeadline?->deadline,
            'status' => 'submitted', // Reset status
        ]);

        return redirect()->route('front.reports.search.management.index')->with('success', 'Báo cáo đã được cập nhật thành công.');
    }
}


