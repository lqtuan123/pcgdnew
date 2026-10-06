<?php

namespace App\Services;

use App\Models\HouseholdMember;
use App\Models\Person;
use App\Models\SurveyForm;
use App\Models\SurveyFormDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MergeHouseholdService
{
    /**
     * @param int   $primarySurveyId  Phiếu đích (giữ lại)
     * @param int   $sourceSurveyId   Phiếu nguồn (sẽ bị xóa)
     * @param array $data [
     *   'new_head_id' => int,
     *   'roles' => [person_id => string],
     *   'address' => ['new_province_id'=>int,'new_ward_id'=>int,'village_id'=>?int,'addressline'=>?string],
     *   'academic_year' => string
     * ]
     */
    public function merge(int $primarySurveyId, int $sourceSurveyId, array $data): array
    {
        return DB::transaction(function () use ($primarySurveyId, $sourceSurveyId, $data) {

            /** @var SurveyForm $primary */
            $primary = SurveyForm::query()
                ->whereKey($primarySurveyId)
                ->where('is_deleted', false)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var SurveyForm $source */
            $source = SurveyForm::query()
                ->whereKey($sourceSurveyId)
                ->where('is_deleted', false)
                ->lockForUpdate()
                ->firstOrFail();

            if ($primary->id === $source->id) {
                throw ValidationException::withMessages(['other_survey_id' => 'Phiếu nguồn phải khác phiếu đích.']);
            }

            // Lấy năm học từ data hoặc sử dụng năm của phiếu đích
            $academicYear = $data['academic_year'] ?? $primary->academic_year;

            // Lấy toàn bộ person_id của 2 phiếu theo năm học
            $primaryDetails = $primary->details()
                ->where('academic_year', $academicYear)
                ->lockForUpdate()
                ->get(['id','person_id']);
            $sourceDetails  = $source->details()
                ->where('academic_year', $academicYear)
                ->lockForUpdate()
                ->get(['id','person_id']);

            $primaryIds = $primaryDetails->pluck('person_id')->all();
            $sourceIds  = $sourceDetails->pluck('person_id')->all();

            $allIds = collect($primaryIds)->merge($sourceIds)->unique()->values()->all();

            // Validate head nằm trong tập hợp sau ghép
            $newHead = (int)($data['new_head_id'] ?? 0);
            if (!$newHead || !in_array($newHead, $allIds, true)) {
                throw ValidationException::withMessages(['new_head_id' => 'Chủ hộ mới phải thuộc danh sách thành viên sau khi ghép.']);
            }

            // 1) Update địa chỉ mới cho phiếu đích (sử dụng địa chỉ mặc định nếu không có)
            $addr = $data['address'] ?? [];
            $primary->fill([
                'new_province_id' => $addr['new_province_id'] ?? $primary->new_province_id,
                'new_ward_id'     => $addr['new_ward_id'] ?? $primary->new_ward_id,
                'village_id'      => $addr['village_id'] ?? $primary->village_id,
            ]);
            $primary->head_person_id = $newHead;
            $primary->save();

            // 2) Chuyển CHỈ detail của năm hiện tại từ phiếu nguồn sang phiếu đích
            // Giữ nguyên detail của các năm trước ở phiếu nguồn
            $roleMap = $data['roles'] ?? [];

            foreach ($sourceDetails as $d) {
                $pid = (int)$d->person_id;

                // Nếu người này đã có ở phiếu đích (năm hiện tại) -> xóa bản của nguồn, cập nhật role sau
                if (in_array($pid, $primaryIds, true)) {
                    $d->delete();
                } else {
                    // chuyển sang phiếu đích (chỉ detail của năm hiện tại)
                    $d->survey_form_id = $primary->id;
                    $d->save();
                }
            }

            // 3) Cập nhật household_members: đưa thành viên của nguồn về household của phiếu đích
            // Tránh lỗi unique(person_id). Thay vì insert, ta UPDATE household_id theo person_id.
            $targetHouseholdId = $primary->household_id;
            if ($targetHouseholdId) {
                // cập nhật tất cả thành viên (sau ghép) về household đích
                HouseholdMember::query()
                    ->whereIn('person_id', $allIds)
                    ->update(['household_id' => $targetHouseholdId]);
            }

            // 4) Cập nhật role trong household_members sau khi đã chuyển tất cả về household đích
            if ($targetHouseholdId) {
                // Đầu tiên, đặt tất cả thành viên trong household đích thành "Thành viên"
                // để tránh có nhiều chủ hộ
                HouseholdMember::query()
                    ->where('household_id', $targetHouseholdId)
                    ->update(['role' => 'Thành viên']);

                // Sau đó cập nhật role cho chủ hộ mới
                HouseholdMember::query()
                    ->where('person_id', $newHead)
                    ->where('household_id', $targetHouseholdId)
                    ->update(['role' => 'Chủ hộ']);

                // Cập nhật role cho các thành viên khác theo roleMap
                foreach ($roleMap as $personId => $role) {
                    if ($personId != $newHead) {
                        HouseholdMember::query()
                            ->where('person_id', $personId)
                            ->where('household_id', $targetHouseholdId)
                            ->update(['role' => $role]);
                    }
                }
            }

            // 5) Cập nhật địa chỉ People theo địa chỉ mới
            $peopleQ = Person::query()->whereIn('id', $allIds)->lockForUpdate();
            $updates = [
                'new_ward_id' => $primary->new_ward_id,
                'new_province_id' => $primary->new_province_id,
            ];
            // Cập nhật địa chỉ cho people nếu có addressline
            if (array_key_exists('addressline', $addr) && $addr['addressline']) {
                $updates['addressline'] = $addr['addressline'];
            }
            $peopleQ->update($updates);

            // 6) Khóa phiếu nguồn thay vì xóa (giữ nguyên detail các năm trước)
            $source->is_active = false;
            $source->save();

            return [
                'success' => true,
                'message' => 'Ghép hộ thành công.',
                'redirect' => route('front.surveyforms.index') ?? null,
            ];
        });
    }
}
