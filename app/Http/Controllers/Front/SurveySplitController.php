<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\SurveyForm;
use App\Models\SurveyFormDetail;
use App\Models\Person;
use App\Models\HouseholdMember;
use App\Services\Survey\SplitHouseholdService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SurveySplitController extends Controller
{
    /** Hiển thị modal tách hộ */
    public function modalSplit(SurveyForm $survey, Request $request)
    {
        // Kiểm tra quyền tách hộ phiếu điều tra
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_phieu_dieu_tra', 'create')) {
            abort(403, 'Bạn không có quyền tách hộ trong phiếu điều tra.');
        }

        // Lấy năm học từ request hoặc sử dụng năm của phiếu gốc
        $academicYear = $request->input('academic_year', $survey->academic_year);

        // Lấy danh sách thành viên hiện tại của phiếu theo năm học
        $details = SurveyFormDetail::query()
            ->where('survey_form_id', $survey->id)
            ->where('academic_year', $academicYear)
            ->where('is_deleted', false)
            ->with([
                'person:id,ho_dem,ten,is_female,dob_day,dob_month,dob_year,identity_number,phone',
                'person.householdMemberships:id,household_id,person_id,role',
            ])->orderBy('id')->get();

        $members = $details->map(fn($d) => [
            'person_id' => $d->person->id,
            'name'      => trim(($d->person->ho_dem ? $d->person->ho_dem.' ' : '').($d->person->ten ?? '')),
            'role'      => $d->person->roleInHousehold($survey->household_id) ?? null,
            'gender'    => is_null($d->person->is_female) ? '—' : ($d->person->is_female ? 'Nữ' : 'Nam'),
            'dob'       => collect([$d->person->dob_day,$d->person->dob_month,$d->person->dob_year])->filter()->implode('/'),
            'identity'  => $d->person->identity_number,
            'phone'     => $d->person->phone,
        ])->values();

        $relationships = \App\Models\HouseRelationship::orderBy('id')->get(['id','name']);
        return view('front.surveyforms.modals.split', compact('survey','members','relationships','academicYear'));
    }

    /** API kiểm tra nhanh số phiếu (realtime trước khi submit) */
    public function checkSoPhieu(Request $request)
    {
        // Kiểm tra quyền kiểm tra số phiếu
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_phieu_dieu_tra', 'view')) {
            return response()->json(['error' => 'Bạn không có quyền kiểm tra số phiếu.'], 403);
        }

        $codes = (array) $request->input('codes', []);
        $year  = $request->input('year'); // có thể kèm academic_year để kiểm tra trong năm đó

        if (!$codes) {
            return response()->json(['ok'=>true,'results'=>[]]);
        }

        $q = \App\Models\SurveyForm::query()->whereIn('so_phieu', $codes)->where('is_deleted', false);
        if ($year) $q->where('academic_year', $year);

        $exist = $q->pluck('so_phieu')->all();
        $map   = [];
        foreach ($codes as $c) {
            $c = trim((string)$c);
            $map[$c] = in_array($c, $exist, true);
        }
        $allFree = !in_array(true, array_values($map), true);

        return response()->json(['ok'=>true, 'results'=>$map, 'all_free'=>$allFree]);
    }

    /** Lưu tách hộ */
    public function splitStore(Request $request, SurveyForm $survey, SplitHouseholdService $service)
    {
        // Kiểm tra quyền tách hộ phiếu điều tra
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_phieu_dieu_tra', 'create')) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền tách hộ trong phiếu điều tra.'
            ], 403);
        }

        // 1) Validate: nhận 'members' (mới) hoặc 'member_ids' (cũ)
        $data = $request->validate([
            'academic_year'            => ['nullable','string','max:20'],
            'new_forms'                => ['required','array','min:1'],
            'new_forms.*.so_phieu'     => ['required','string','max:50'],
            'new_forms.*.head_id'      => ['required','integer','exists:people,id'],

            // hỗ trợ payload mới
            'new_forms.*.members'                  => ['nullable','array','min:1'],
            'new_forms.*.members.*.person_id'      => ['required_with:new_forms.*.members','integer','exists:people,id'],
            'new_forms.*.members.*.role'           => ['nullable','string','max:100'],

            // vẫn hỗ trợ payload cũ
            'new_forms.*.member_ids'               => ['nullable','array','min:1'],
            'new_forms.*.member_ids.*'             => ['integer','exists:people,id'],
        ]);

        $year = $data['academic_year'] ?: (string) $survey->academic_year;

        // 2) Chuẩn hoá forms => luôn có: so_phieu, head_id, member_ids, roles (map person_id => role)
        $normalized = [];
        $codes      = [];
        $seenPeople = []; // để chặn 1 person xuất hiện ở >1 hộ
        foreach ($data['new_forms'] as $idx => $nf) {
            // Ưu tiên mảng mới 'members', fallback 'member_ids'
            $membersArr = $nf['members'] ?? null;
            $memberIds  = is_array($membersArr)
                ? array_map(fn($m) => (int) $m['person_id'], $membersArr)
                : array_map('intval', $nf['member_ids'] ?? []);

            if (empty($memberIds)) {
                return response()->json([
                    'ok' => false,
                    'message' => "Hộ mới #".($idx+1)." chưa có danh sách thành viên.",
                ], 422);
            }

            // Lưu role (nếu có) thành map [person_id => role]
            $rolesMap = [];
            if (is_array($membersArr)) {
                foreach ($membersArr as $m) {
                    $pid = (int) ($m['person_id'] ?? 0);
                    if ($pid) {
                        $rolesMap[$pid] = isset($m['role']) ? (string) $m['role'] : '';
                    }
                }
            }

            // 3) Chủ hộ phải nằm trong memberIds của chính hộ đó
            $headId = (int) $nf['head_id'];
            if (!in_array($headId, $memberIds, true)) {
                return response()->json([
                    'ok' => false,
                    'message' => "Chủ hộ của hộ mới #".($idx+1)." chưa nằm trong danh sách thành viên.",
                ], 422);
            }

            // 4) Không cho 1 person xuất hiện ở >1 hộ
            foreach ($memberIds as $pid) {
                if (isset($seenPeople[$pid])) {
                    return response()->json([
                        'ok' => false,
                        'message' => "Thành viên ID {$pid} được chọn ở từ 2 hộ mới trở lên.",
                    ], 422);
                }
                $seenPeople[$pid] = true;
            }

            $code = trim((string)$nf['so_phieu']);
            $codes[] = $code;

            $normalized[] = [
                'so_phieu'   => $code,
                'head_id'    => $headId,
                'member_ids' => $memberIds,  // service cũ có thể chỉ cần mảng IDs
                'roles'      => $rolesMap,   // nếu muốn lưu role theo hộ mới thì service có thể dùng thêm
            ];
        }

        // 5) Không cho trùng code trong payload
        if (count($codes) !== count(array_unique($codes))) {
            return response()->json([
                'ok' => false,
                'message' => 'Các số phiếu mới bị trùng nhau.',
            ], 422);
        }

        // 6) Check trùng trong DB (cùng năm)
        $dup = \App\Models\SurveyForm::query()
            ->whereIn('so_phieu', $codes)
            ->where('is_deleted', false)
            ->when($year, fn($q) => $q->where('academic_year', $year))
            ->pluck('so_phieu')->all();

        if (!empty($dup)) {
            return response()->json([
                'ok' => false,
                'message' => 'Số phiếu đã tồn tại: '.implode(', ', $dup),
            ], 422);
        }

        try {
            // 7) Gọi service – nếu service cũ chỉ nhận member_ids, vẫn ok do đã normalize
            $result = $service->split($survey, $normalized, $year);

            return response()->json([
                'ok'       => true,
                'created'  => $result['created'] ?? [],
                'redirect' => route('front.surveyforms.index'),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok'      => false,
                'message' => 'Không tách được hộ: '.$e->getMessage(),
            ], 500);
        }
    }

}
