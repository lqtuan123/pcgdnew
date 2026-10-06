<?php
// app/Services/Survey/SplitHouseholdService.php

namespace App\Services\Survey;

use App\Models\SurveyForm;
use App\Models\SurveyFormDetail;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

class SplitHouseholdService
{
    /**
     * @param SurveyForm $oldSurvey
     * @param array $newForms  [
     *   ['so_phieu'=>'...', 'head_id'=>123, 'member_ids'=>[1,2,3]],
     *   ...
     * ]
     * @param string $academicYear
     */
    // public function split(SurveyForm $oldSurvey, array $newForms, string $academicYear): array
    // {
    //     return DB::transaction(function () use ($oldSurvey, $newForms, $academicYear) {
    //         $created = [];

    //         foreach ($newForms as $nf) {
    //             $soPhieu   = trim($nf['so_phieu']);
    //             $headId    = (int) $nf['head_id'];
    //             $memberIds = array_values(array_unique(array_map('intval', $nf['member_ids'])));

    //             // 1) Tạo hộ mới: clone địa bàn theo SurveyForm cũ
    //             $newHh = Household::create([
    //                 'name'              => null, // tuỳ cột của bạn
    //                 'head_person_id'    => $headId,
    //                 // Clone địa bàn theo phiếu cũ:
    //                 'old_province_id'   => $oldSurvey->old_province_id,
    //                 'old_district_id'   => $oldSurvey->old_district_id,
    //                 'old_ward_id'       => $oldSurvey->old_ward_id,
    //                 'new_province_id'   => $oldSurvey->new_province_id,
    //                 'new_ward_id'       => $oldSurvey->new_ward_id,
    //                 'village_id'        => $oldSurvey->village_id,
    //             ]);

    //             // 2) Tạo SurveyForm mới
    //             $newSf = SurveyForm::create([
    //                 'household_id'      => $newHh->id,
    //                 'head_person_id'    => $headId,
    //                 'so_phieu'          => $soPhieu,
    //                 'academic_year'     => $academicYear,
    //                 'old_province_id'   => $oldSurvey->old_province_id,
    //                 'old_district_id'   => $oldSurvey->old_district_id,
    //                 'old_ward_id'       => $oldSurvey->old_ward_id,
    //                 'new_province_id'   => $oldSurvey->new_province_id,
    //                 'new_ward_id'       => $oldSurvey->new_ward_id,
    //                 'village_id'        => $oldSurvey->village_id,
    //                 'is_deleted'        => false,
    //             ]);

    //             // 3) Di chuyển thành viên: pivot hộ + detail phiếu + cập nhật person.so_phieu (nếu bạn dùng)
    //             foreach ($memberIds as $pid) {
    //                 // pivot household_members
    //                 $pivot = HouseholdMember::firstOrNew([
    //                     'household_id' => $newHh->id,
    //                     'person_id'    => $pid,
    //                 ]);
    //                 if (!$pivot->exists) $pivot->role = null; // giữ nguyên hoặc set sau
    //                 $pivot->save();

    //                 // detail cũ -> đánh dấu xoá mềm (nếu có)
    //                 $oldDetail = SurveyFormDetail::where('survey_form_id', $oldSurvey->id)
    //                     ->where('person_id', $pid)->first();
    //                 if ($oldDetail && !$oldDetail->is_deleted) {
    //                     $oldDetail->is_deleted = true;
    //                     $oldDetail->save();
    //                 }

    //                 // detail mới (nếu chưa có)
    //                 SurveyFormDetail::firstOrCreate([
    //                     'survey_form_id' => $newSf->id,
    //                     'person_id'      => $pid,
    //                 ], [
    //                     'is_deleted' => false,
    //                 ]);

    //                 // Person.so_phieu (nếu bạn đang dùng liên kết này để tiện tìm nhanh)
    //                 Person::where('id', $pid)->update(['so_phieu' => $soPhieu]);
    //             }

    //             $created[] = [
    //                 'household_id'  => $newHh->id,
    //                 'survey_form_id'=> $newSf->id,
    //                 'so_phieu'      => $soPhieu,
    //             ];
    //         }

    //         // Chủ hộ cũ vẫn giữ nguyên (theo yêu cầu). Các thành viên không được chọn sẽ ở lại phiếu cũ.
    //         // Có thể cập nhật lại head của phiếu cũ (giữ nguyên), hoặc nếu cần tính lại member_count ở nơi hiển thị.

    //         return ['created' => $created];
    //     });
    // }

   public function split(SurveyForm $source, array $forms, string $year): array
    {
        return DB::transaction(function () use ($source, $forms, $year) {
            $created = [];
            $oldHousehold = $source->household()->first();

            foreach ($forms as $nf) {
                $code   = trim($nf['so_phieu']);
                $headId = (int) $nf['head_id'];

                // ---- Normalize member IDs + roles ----
                $ids   = [];
                $roles = [];

                if (!empty($nf['members']) && is_array($nf['members'])) {
                    foreach ($nf['members'] as $m) {
                        $pid = (int)($m['person_id'] ?? 0);
                        if ($pid) {
                            $ids[] = $pid;
                            $roles[$pid] = trim((string)($m['role'] ?? ''));
                        }
                    }
                } else {
                    // fallback dạng cũ: member_ids + roles[pid] => role
                    foreach ((array)($nf['member_ids'] ?? []) as $pid) {
                        $pid = (int)$pid;
                        if ($pid) {
                            $ids[] = $pid;
                            $roles[$pid] = trim((string)($nf['roles'][$pid] ?? ''));
                        }
                    }
                }

                // ---- Tạo hộ mới ----
                $head = Person::find($headId);
                $newHh = Household::create([
                    'name'            => 'Hộ '.($head?->full_name ?? ''),
                    'head_person_id'  => $headId,
                    'scheme'          => $source->scheme ?? 'old',
                    'old_province_id' => $source->old_province_id,
                    'old_district_id' => $source->old_district_id,
                    'old_ward_id'     => $source->old_ward_id,
                    'new_province_id' => $source->new_province_id,
                    'new_ward_id'     => $source->new_ward_id,
                    'village_id'      => $source->village_id,
                    'is_deleted'      => false,
                ]);

                // ---- Gán thành viên vào hộ mới với ROLE (không để null) ----
                foreach ($ids as $pid) {
                        $role = $roles[$pid] ?? null;

                        // nếu client không gửi role, lấy role cũ ở hộ gốc (nếu có)
                        if (!$role && $oldHousehold) {
                            $role = \App\Models\HouseholdMember::query()
                                ->where('person_id', $pid) // vì person_id là unique
                                ->value('role');
                        }

                        // fallback cuối cùng
                        if (!$role) {
                            $role = ($pid === $headId) ? 'Chủ hộ' : 'Thành viên';
                        }

                        // person_id là unique -> phải UPDATE, không được INSERT nếu đã tồn tại
                        $hm = \App\Models\HouseholdMember::where('person_id', $pid)->first();

                        if ($hm) {
                            // chuyển người này sang hộ mới + cập nhật role
                            $hm->household_id = $newHh->id;
                            $hm->role = $role;
                            $hm->save();
                        } else {
                            // chỉ khi trước đó người này CHƯA có membership
                            \App\Models\HouseholdMember::create([
                                'household_id' => $newHh->id,
                                'person_id'    => $pid,
                                'role'         => $role,
                            ]);
                        }
                    }

                // ---- Tạo phiếu mới ----
                $newSurvey = SurveyForm::create([
                    'so_phieu'       => $code,
                    'academic_year'  => $year,
                    'household_id'   => $newHh->id,
                    'head_person_id' => $headId,
                    'old_province_id'=> $source->old_province_id,
                    'old_district_id'=> $source->old_district_id,
                    'old_ward_id'    => $source->old_ward_id,
                    'new_province_id'=> $source->new_province_id,
                    'new_ward_id'    => $source->new_ward_id,
                    'village_id'     => $source->village_id,
                    'surveyed_at'=> now(), 
                    'is_deleted'     => false,
                ]);

                // ---- Chuyển các SurveyFormDetail sang phiếu mới ----
                foreach ($ids as $pid) {
                    $detail = SurveyFormDetail::where('survey_form_id', $source->id)
                        ->where('person_id', $pid)
                        ->where('academic_year', $year)
                        ->first();

                    if ($detail) {
                        $detail->survey_form_id = $newSurvey->id;
                        $detail->save();
                    } else {
                        SurveyFormDetail::firstOrCreate([
                            'survey_form_id' => $newSurvey->id,
                            'person_id'      => $pid,
                            'academic_year'  => $year,
                        ], ['is_deleted' => false]);
                    }
                }

                // ---- Bỏ các thành viên đã chuyển khỏi hộ cũ (HasMany => delete bản ghi pivot) ----
                // if ($oldHousehold) {
                //     HouseholdMember::where('household_id', $oldHousehold->id)
                //         ->whereIn('person_id', $ids)
                //         ->delete();
                // }

                $created[] = [
                    'household_id'   => $newHh->id,
                    'survey_form_id' => $newSurvey->id,
                    'so_phieu'       => $code,
                ];
            }

            return ['created' => $created];
        });
    }
}
