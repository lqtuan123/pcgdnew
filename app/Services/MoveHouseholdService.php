<?php

namespace App\Services;

use App\Models\SurveyForm;
use App\Models\SurveyFormDetail;
use App\Models\Person;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\GeoUnit;
use Illuminate\Support\Facades\DB;

class MoveHouseholdService
{
    /**
     * Chuyển hộ sang thôn/xóm mới
     */
    public function move(int $sourceSurveyId, array $data): array
    {
        return DB::transaction(function () use ($sourceSurveyId, $data) {
            // Lấy phiếu nguồn
            $source = SurveyForm::lockForUpdate()->findOrFail($sourceSurveyId);
            
            // Lấy thông tin thôn/xóm mới (chỉ địa giới mới)
            $newVillage = GeoUnit::where('id', $data['new_village_id'])
                ->where('scheme', 'new')
                ->firstOrFail();
            $newWard = $newVillage->parent;
            $newProvince = $newWard->parent;

            // Tạo phiếu mới
            $newSurvey = SurveyForm::create([
                'so_phieu' => $data['new_so_phieu'],
                'academic_year' => $data['academic_year'],
                'head_person_id' => null, // Sẽ cập nhật sau
                'household_id' => null, // Sẽ tạo household mới
                'investigators' => $source->investigators,
                'is_deleted' => false,
                'is_active' => true,
                'old_province_id' => $source->old_province_id,
                'new_province_id' => $newProvince->id,
                'old_ward_id' => $source->old_ward_id,
                'new_ward_id' => $newWard->id,
                'old_district_id' => $source->old_district_id,
                'village_id' => $newVillage->id,
                'surveyed_at' => $source->surveyed_at,
                'editor_id' => $source->editor_id,
            ]);

            // Tạo household mới
            $newHousehold = Household::create([
                'name' => $data['new_head_name'],
                'old_province_id' => $source->old_province_id,
                'new_province_id' => $newProvince->id,
                'old_ward_id' => $source->old_ward_id,
                'new_ward_id' => $newWard->id,
                'old_district_id' => $source->old_district_id,
                'village_id' => $newVillage->id,
            ]);

            // Cập nhật household_id cho phiếu mới
            $newSurvey->update(['household_id' => $newHousehold->id]);

            // Lấy danh sách thành viên từ phiếu nguồn (chỉ năm hiện tại)
            $sourceDetails = SurveyFormDetail::query()
                ->where('survey_form_id', $source->id)
                ->where('academic_year', $data['academic_year'])
                ->where('is_deleted', false)
                ->lockForUpdate()
                ->get();

            $newHeadPersonId = null;
            $allPersonIds = [];

            // Chuyển từng thành viên sang phiếu mới
            foreach ($data['members'] as $memberData) {
                $personId = (int)$memberData['person_id'];
                $relationship = $memberData['relationship'];
                
                // Tìm detail tương ứng trong phiếu nguồn
                $sourceDetail = $sourceDetails->where('person_id', $personId)->first();
                
                if ($sourceDetail) {
                    // Tạo detail mới trong phiếu đích
                    SurveyFormDetail::create([
                        'survey_form_id' => $newSurvey->id,
                        'person_id' => $personId,
                        'academic_year' => $data['academic_year'],
                        'relationship' => $relationship,
                        'is_deleted' => false,
                    ]);

                    // Xóa detail khỏi phiếu nguồn
                    $sourceDetail->delete();

                    // Nếu là chủ hộ mới
                    if ($relationship === 'Chủ hộ') {
                        $newHeadPersonId = $personId;
                    }

                    $allPersonIds[] = $personId;
                }
            }

            // Cập nhật chủ hộ cho phiếu mới
            if ($newHeadPersonId) {
                $newSurvey->update(['head_person_id' => $newHeadPersonId]);
            }

            // Cập nhật household_members
            if (!empty($allPersonIds)) {
                // Xóa các thành viên cũ khỏi household cũ
                HouseholdMember::whereIn('person_id', $allPersonIds)->delete();

                // Thêm thành viên vào household mới
                foreach ($data['members'] as $memberData) {
                    $personId = (int)$memberData['person_id'];
                    $relationship = $memberData['relationship'];
                    
                    HouseholdMember::create([
                        'person_id' => $personId,
                        'household_id' => $newHousehold->id,
                        'role' => $relationship,
                    ]);
                }
            }

            // Cập nhật địa chỉ mới cho tất cả thành viên
            Person::whereIn('id', $allPersonIds)->update([
                'new_province_id' => $newProvince->id,
                'new_ward_id' => $newWard->id,
                'village_id' => $newVillage->id,
            ]);

            // Khóa phiếu nguồn
            $source->update(['is_active' => false]);

            return [
                'success' => true,
                'message' => 'Chuyển hộ thành công.',
                'redirect' => route('front.surveyforms.index'),
                'new_survey_id' => $newSurvey->id,
                'new_so_phieu' => $newSurvey->so_phieu,
            ];
        });
    }
}
