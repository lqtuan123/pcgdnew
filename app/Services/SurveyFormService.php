<?php
// app/Services/Surveys/SurveyFormService.php

namespace App\Services;

use App\Models\SurveyForm;
use App\Models\SurveyFormDetail;
use App\Models\Person;
use App\Services\GeoService;
use App\Models\Household;
use App\Models\HouseholdMembership;
use App\Models\Residence;
use Illuminate\Validation\ValidationException;
use App\Models\HouseholdMember;

use Illuminate\Support\Carbon;
 
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
class SurveyFormService
{
    public function __construct(protected GeoService $geoService) {}
/**
     * Tạo mới: SurveyForm + Household + Person (chủ hộ)
     *
     * @param  'old'|'new'               $scheme
     * @param  array{province_id?:int,district_id?:int,ward_id?:int,village_id?:int} $geoIds
     * @param  string                     $ticketNo             so_phieu
     * @param  array                      $headAttrs            ho_dem, ten, dob_day, dob_month, dob_year, is_female, phone, addressline, residence_type_id, residence_status_id, ...
     * @param  array                      $formAttrs            academic_year, surveyed_at (Y-m-d), ghi chú...
     * @param  bool                       $addHeadToDetails     có tạo dòng detail cho chủ hộ không
     * @return array{survey:SurveyForm, household:Household, head:Person, detail?:SurveyFormDetail|null}
     *
     * @throws \Illuminate\Validation\ValidationException
     */

    public function updateWithHouseholdAndHeadByGeoIds(
        SurveyForm $survey,
        string $scheme,
        array $geoIds,
        string $ticketNo,
        array $headAttrs = [],
        array $formAttrs = []
    ): array {
        // 0) (Option) kiểm tra trùng mã phiếu ở tầng service (controller đã check unique rồi)
        // Nếu vẫn muốn chắc chắn, có thể bật đoạn dưới:
        $dup = SurveyForm::where('so_phieu', $ticketNo)
            ->where('is_deleted', false)
            ->where('id', '<>', $survey->id)
            ->exists();
        if ($dup) {
            throw ValidationException::withMessages(['so_phieu' => 'Số phiếu đã tồn tại.']);
        }
       
        // 1) Chuẩn hoá geo giống hàm create
        $geoOld = [
            'old_province_id' => $scheme === 'old' ? ($geoIds['province_id'] ?? null) : null,
            'old_district_id' => $scheme === 'old' ? ($geoIds['district_id'] ?? null) : null,
            'old_ward_id'     => $scheme === 'old' ? ($geoIds['ward_id']     ?? null) : null,
        ];

        $newWardId = $scheme === 'new'
            ? ($geoIds['ward_id'] ?? null)
            : (!empty($geoIds['ward_id']) ? $this->geoService->mapOldIdToNewId((int)$geoIds['ward_id'], 3) : null);

        $newProvinceId = $scheme === 'new'
            ? ($geoIds['province_id'] ?? null)
            : (!empty($geoIds['province_id']) ? $this->geoService->mapOldIdToNewId((int)$geoIds['province_id'], 1) : null);

        $geoNew = [
            'new_province_id' => $newProvinceId ?? null,
            'new_ward_id'     => $newWardId     ?? null,
        ];
        $geoCommon = ['village_id' => $geoIds['village_id'] ?? null];

        // Khi đổi scheme, cần null-hoá phía còn lại để tránh dữ liệu cũ “kẹt”
        $nullifyOld = $scheme === 'new' ? ['old_province_id'=>null,'old_district_id'=>null,'old_ward_id'=>null] : [];
        $nullifyNew = $scheme === 'old' ? ['new_province_id'=>null,'new_ward_id'=>null] : [];

        // 2) Chuẩn hoá ngày giờ
        $academicYear = (string)($formAttrs['academic_year'] ?? $survey->academic_year ?? SurveyForm::currentAcademicYear());
        $surveyedAt   = !empty($formAttrs['surveyed_at'])
            ? Carbon::parse($formAttrs['surveyed_at'])
            : ($survey->surveyed_at ?: now());

        // 3) Chuẩn hoá chủ hộ
        $hoDem = trim((string)($headAttrs['ho_dem'] ?? $headAttrs['chuho_ho_dem'] ?? ''));
        $ten   = trim((string)($headAttrs['ten']    ?? $headAttrs['chuho_ten']    ?? ''));
        if ($ten === '') {
            throw ValidationException::withMessages([
                'head.ten' => 'Tên chủ hộ là bắt buộc.',
            ]);
        }

        return DB::transaction(function () use (
            $survey, $scheme, $geoOld, $geoNew, $geoCommon, $nullifyOld, $nullifyNew,
            $ticketNo, $headAttrs, $hoDem, $ten, $academicYear, $surveyedAt
        ) {
            /* =======================
            *  HOUSEHOLD
            * =======================*/
            /** @var Household|null $household */
            $household = $survey->household ?? Household::find($survey->household_id);
            if (!$household) {
                // Nếu không có thì tạo mới (giữ đúng cấu trúc như create)
                $hhData = [
                    'name'               => trim(($hoDem ? $hoDem.' ' : '').$ten) ?: null,
                    'residence_type_id'  => $headAttrs['residence_type_id']   ?? null,
                    'residence_status_id'=> $headAttrs['residence_status_id'] ?? null,
                    'phone'              => $headAttrs['phone']       ?? $headAttrs['head_phone']   ?? null,
                    'addressline'        => $headAttrs['addressline'] ?? $headAttrs['head_address'] ?? null,
                    'ticket_no'          => $ticketNo,
                ];
                $household = Household::create($hhData + $geoOld + $geoNew + $geoCommon);
            } else {
                $household->fill([
                    'name'               => trim(($hoDem ? $hoDem.' ' : '').$ten) ?: $household->name,
                    'residence_type_id'  => $headAttrs['residence_type_id']   ?? $household->residence_type_id,
                    'residence_status_id'=> $headAttrs['residence_status_id'] ?? $household->residence_status_id,
                    'phone'              => $headAttrs['phone']       ?? $headAttrs['head_phone']   ?? $household->phone,
                    'addressline'        => $headAttrs['addressline'] ?? $headAttrs['head_address'] ?? $household->addressline,
                    'ticket_no'          => $ticketNo,
                ]);
                // bắt buộc forceFill để null-hoá đúng các cột geo không dùng
                $household->forceFill($geoOld + $geoNew + $geoCommon + $nullifyOld + $nullifyNew)->save();
            }

            /* =======================
            *  PERSON (HEAD)
            * =======================*/
            /** @var Person|null $head */
            $head = Person::find($survey->head_person_id);
            $personPayload = [
                'ho_dem'        => $hoDem,
                'ten'           => $ten,
                'dob_day'       => $headAttrs['dob_day']   ?? $headAttrs['head_dob_day']   ?? null,
                'dob_month'     => $headAttrs['dob_month'] ?? $headAttrs['head_dob_month'] ?? null,
                'dob_year'      => $headAttrs['dob_year']  ?? $headAttrs['head_dob_year']  ?? null,
                'is_female'     => array_key_exists('is_female', $headAttrs) ? (bool)$headAttrs['is_female'] : $head->is_female ?? null,
                'phone'         => $headAttrs['phone']       ?? $headAttrs['head_phone']   ?? ($head->phone ?? null),
                'addressline'   => $headAttrs['addressline'] ?? $headAttrs['head_address'] ?? ($head->addressline ?? null),
                'so_phieu'      => $ticketNo,
                'is_deleted'    => false,
                'identity_number'   => $headAttrs['identity_number'] ?? $head->identity_number ?? null,
                'residence_type_id' => $headAttrs['residence_type_id']   ?? $head->residence_type_id ?? null,
                'residence_status_id'=> $headAttrs['residence_status_id']?? $head->residence_status_id ?? null,
                // nếu bạn có 2 cột này trên people:
                'ethnicity_id'  => $headAttrs['ethnicity_id']  ?? ($head->ethnicity_id  ?? null),
                'religion_id'   => $headAttrs['religion_id']   ?? ($head->religion_id   ?? null),
            ];

            if (!$head) {
                $head = Person::create($personPayload + $geoOld + $geoNew + $nullifyOld + $nullifyNew);
            } else {
                $head->fill($personPayload);
                $head->forceFill($geoOld + $geoNew + $nullifyOld + $nullifyNew)->save();
            }

            /* =======================
            *  LINK HEAD ↔ HOUSEHOLD
            * =======================*/
            if (self::hasColumn('households', 'head_person_id')) {
                $household->forceFill(['head_person_id' => $head->id])->save();
            }
            if (class_exists(HouseholdMembership::class)) {
                HouseholdMembership::updateOrCreate(
                    ['household_id' => $household->id, 'person_id' => $head->id],
                    ['role' => 'Chủ hộ']
                );
            }

            /* =======================
            *  SURVEY FORM
            * =======================*/
            $survey->fill([
                'so_phieu'       => $ticketNo,
                'academic_year'  => $academicYear,
                'surveyed_at'    => $surveyedAt,
                'household_id'   => $household->id,
                'head_person_id' => $head->id,
            ]);
           
            $survey->forceFill($geoOld + $geoNew + $geoCommon + $nullifyOld + $nullifyNew)->save();
           
            // Bảo đảm detail có dòng cho chủ hộ (nếu chưa có)
            
            if (self::hasColumn('survey_form_details', 'person_id')) {
                SurveyFormDetail::firstOrCreate(
                    ['survey_form_id' => $survey->id, 'academic_year' => $academicYear,'person_id' => $head->id],
                    ['residence_id' => null, 'is_deleted' => false]
                );
            }
            // dd($survey);
            return [
                'survey'    => $survey->fresh(),
                'household' => $household->fresh(),
                'head'      => $head->fresh(),
            ];
        });
    }

    public function createWithHouseholdAndHeadByGeoIds(
        string $scheme,
        array $geoIds,
        string $ticketNo,
        array $headAttrs = [],
        array $formAttrs = [],
        bool $addHeadToDetails = true
    ): array {
        // 0) Kiểm tra trùng mã phiếu
        $this->guardDuplicateTicketNo($ticketNo);

        // 1) Chuẩn hóa geo
        $geoOld = [
            'old_province_id' => $scheme === 'old' ? ($geoIds['province_id'] ?? null) : null,
            'old_district_id' => $scheme === 'old' ? ($geoIds['district_id'] ?? null) : null,
            'old_ward_id'     => $scheme === 'old' ? ($geoIds['ward_id']     ?? null) : null,
        ];
         $newWardId      = $scheme === 'new'
            ? ($geoIds['ward_id'] ?? null)
            : (!empty($geoIds['ward_id']) ? $this->geoService->mapOldIdToNewId((int)$geoIds['ward_id'], 3) : null);

        $newProvinceId  = $scheme === 'new'
            ? ($geoIds['province_id'] ?? null)
            : (!empty($geoIds['province_id']) ? $this->geoService->mapOldIdToNewId((int)$geoIds['province_id'], 1) : null);

        $geoNew = [
            'new_province_id' => $newProvinceId  ?? null,
            'new_ward_id'     => $newWardId   ?? null,
        ];
        $geoCommon = ['village_id' => $geoIds['village_id'] ?? null];

        // 2) Chuẩn hóa ngày giờ
        $academicYear = (string)($formAttrs['academic_year'] ?? SurveyForm::currentAcademicYear());
        $surveyedAt   = !empty($formAttrs['surveyed_at'])
            ? Carbon::parse($formAttrs['surveyed_at'])
            : now();

        // 3) Chuẩn hóa chủ hộ
        $hoDem     = trim((string)($headAttrs['ho_dem'] ?? $headAttrs['chuho_ho_dem'] ?? ''));
        $ten       = trim((string)($headAttrs['ten']    ?? $headAttrs['chuho_ten']    ?? ''));
        if ($ten === '') {
            throw ValidationException::withMessages([
                'head.ten' => 'Tên chủ hộ là bắt buộc.',
            ]);
        }

        return DB::transaction(function () use (
            $scheme, $geoOld, $geoNew, $geoCommon,
            $ticketNo, $headAttrs, $hoDem, $ten,
            $academicYear, $surveyedAt, $formAttrs, $addHeadToDetails
        ) {
            // 4) Tạo Household (đơn giản: luôn tạo bản ghi mới)
            //    => nếu dự án bạn cần upsert theo ticketNo + geo thì đổi thành firstOrCreate/upsert.
            $hhData = array_filter([
                'name'            => ($hoDem.' '.$ten) ?? null,  // nếu có
                'residence_type_id'   => $headAttrs['residence_type_id']   ?? null,
                'residence_status_id' => $headAttrs['residence_status_id'] ?? null,
                'phone'        => $headAttrs['phone']       ?? $headAttrs['head_phone']   ?? null,
                'addressline'  => $headAttrs['addressline'] ?? $headAttrs['head_address'] ?? null,
                'ticket_no'     => $ticketNo,
            ] + $geoOld + $geoNew + $geoCommon, fn($v) => !is_null($v));

            /** @var Household $household */
            $household = Household::create($hhData);

           
            $head = Person::create([
                'ho_dem'       => $hoDem,
                'ten'          => $ten,
                'dob_day'      => $headAttrs['dob_day']   ?? $headAttrs['head_dob_day']   ?? null,
                'dob_month'    => $headAttrs['dob_month'] ?? $headAttrs['head_dob_month'] ?? null,
                'dob_year'     => $headAttrs['dob_year']  ?? $headAttrs['head_dob_year']  ?? null,
                'is_female'    => isset($headAttrs['is_female']) ? (bool)$headAttrs['is_female'] : null,
                'phone'        => $headAttrs['phone']       ?? $headAttrs['head_phone']   ?? null,
                'addressline'  => $headAttrs['addressline'] ?? $headAttrs['head_address'] ?? null,
                'so_phieu'     => $ticketNo,
                'is_deleted'   => false,
                'identity_number'=> $headAttrs['identity_number'] ?? $headAttrs['head_identity_number'] ?? null,
                  'residence_type_id'   => $headAttrs['residence_type_id']   ?? null,
                        'residence_status_id' => $headAttrs['residence_status_id'] ?? null,

            ] + $geoOld + $geoNew);
            // $head->phone = $headAttrs['phone'] ?? $headAttrs['head_phone'] ?? null;
            // $head->save();
            
            // 6) (Tuỳ schema) đánh dấu head cho Household + membership
            // Nếu Household có trường head_person_id:
            if (self::hasColumn('households', 'head_person_id')) {
                $household->forceFill(['head_person_id' => $head->id])->save();
            }
            // Nếu có bảng memberships (household_id, person_id, role):
            if (class_exists(HouseholdMembership::class)) {
                HouseholdMembership::updateOrCreate(
                    ['household_id' => $household->id, 'person_id' => $head->id],
                    ['role' => 'Chủ hộ'] // hoặc 1 tuỳ dự án
                );
            }
            // Tạo/đảm bảo membership 'Chủ hộ'
            HouseholdMember::updateOrCreate(
                ['household_id' => $household->id, 'person_id' => $head->id],
                ['role' => 'Chủ hộ']
            );
            

            // 8) Tạo SurveyForm
            /** @var SurveyForm $survey */
            $survey = SurveyForm::create([
                'so_phieu'        => $ticketNo,
                'academic_year'   => $academicYear,
                'surveyed_at'     => $surveyedAt,
                'household_id'    => $household->id,
                'head_person_id'  => $head->id,
                'is_deleted'      => false,
            ] + $geoOld + $geoNew + $geoCommon);

            // 9) (Tuỳ chọn) thêm dòng detail cho chủ hộ để count thành viên ≥1
            $detail = null;
            if ($addHeadToDetails) {
                $detail = SurveyFormDetail::create([
                    'survey_form_id' => $survey->id,
                    'person_id'      => $head->id,
                    'residence_id'   => null,
                    'is_deleted'     => false,
                     'academic_year'   => $academicYear,
                ]);
            }
            $membership = HouseholdMember::where('household_id', $household->id)    
                ->where('person_id', $head->id)->first();
            return [
                'survey'    => $survey->fresh(),
                'household' => $household->fresh(),
                'head'      => $head->fresh(),
                'detail'    => $detail,
                 'membership' => $membership,
            ];
        });
    }

    /**
     * Ném lỗi nếu so_phieu đã tồn tại; nếu tồn tại ở trạng thái xóa mềm thì báo rõ để người dùng quyết định.
     */
    protected function guardDuplicateTicketNo(string $ticketNo): void
    {
        $active = SurveyForm::where('so_phieu', $ticketNo)->where('is_deleted', false)->exists();
        if ($active) {
            throw ValidationException::withMessages([
                'so_phieu' => 'Mã phiếu đã tồn tại.',
            ]);
        }

        $soft = SurveyForm::where('so_phieu', $ticketNo)->where('is_deleted', true)->first();
        if ($soft) {
            throw ValidationException::withMessages([
                'so_phieu' => 'Mã phiếu này đang tồn tại ở trạng thái xóa mềm (ID: '.$soft->id.'). '
                            . 'Bạn có thể phục hồi để sử dụng lại hoặc xóa hẳn rồi tạo mới.',
            ]);
        }
    }

    /**
     * Helper: kiểm tra tồn tại cột (để không lỗi khi schema khác nhau).
     */
    protected static function hasColumn(string $table, string $column): bool
    {
        try {
            return \Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }
    public function deleteByIds(array $formIds): array
    {
        $formIds = collect($formIds)->filter()->unique()->values()->all();
        if (empty($formIds)) {
            return $this->emptyResult();
        }

        // Lấy so_phieu & head_person_id cho việc cập nhật People
        $forms = SurveyForm::query()
            ->whereIn('id', $formIds)
            ->get(['id','so_phieu','head_person_id']);

        return $this->deleteChunk(
            $forms->pluck('id')->all(),
            $forms->pluck('so_phieu')->filter()->unique()->all(),
            $forms->pluck('head_person_id')->filter()->unique()->all()
        );
    }

    /**
     * Xóa mềm theo 1 bản ghi.
     */
    public function deleteOne(SurveyForm $survey): array
    {
        return $this->deleteByIds([$survey->id]);
    }

    /**
     * Xóa mềm theo bộ lọc (query). Dùng chunk để xử lý khối lượng lớn.
     */
    public function deleteByQuery(Builder $base, int $chunkSize = 1000): array
    {
        $result = $this->emptyResult();

        (clone $base)
            ->select(['id','so_phieu','head_person_id'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($chunk) use (&$result) {
                $ids      = $chunk->pluck('id')->all();
                $soPhieus = $chunk->pluck('so_phieu')->filter()->unique()->all();
                $headPids = $chunk->pluck('head_person_id')->filter()->unique()->all();

                $r = $this->deleteChunk($ids, $soPhieus, $headPids);

                // Cộng dồn
                foreach ($result as $k => $v) {
                    $result[$k] += $r[$k];
                }
            });

        return $result;
    }

    /**
     * Xóa mềm một lô phiếu + chi tiết + people liên quan.
     * Trả về số bản ghi tác động.
     */
    protected function deleteChunk(array $formIds, array $soPhieus = [], array $headPersonIds = []): array
    {
        if (empty($formIds)) return $this->emptyResult();

        return DB::transaction(function () use ($formIds, $soPhieus, $headPersonIds) {
            // 1) Forms
            $formsAffected = SurveyForm::whereIn('id', $formIds)
                ->update(['is_deleted' => true]);

            // 2) Details
            $detailsAffected = SurveyFormDetail::whereIn('survey_form_id', $formIds)
                ->update(['is_deleted' => true]);

            // 3) People từ details
            $detailPids = SurveyFormDetail::whereIn('survey_form_id', $formIds)
                ->pluck('person_id')->filter()->unique()->all();

            $personIds = array_values(array_unique(array_merge($detailPids, $headPersonIds)));

            $peopleByIdAffected = 0;
            if (!empty($personIds)) {
                $peopleByIdAffected = Person::whereIn('id', $personIds)
                    ->update(['is_deleted' => true]);
            }

            $peopleBySoPhieuAffected = 0;
            if (!empty($soPhieus)) {
                $peopleBySoPhieuAffected = Person::whereIn('so_phieu', $soPhieus)
                    ->update(['is_deleted' => true]);
            }

            return [
                'forms'        => (int)$formsAffected,
                'details'      => (int)$detailsAffected,
                'people_id'    => (int)$peopleByIdAffected,
                'people_code'  => (int)$peopleBySoPhieuAffected,
            ];
        });
    }

    protected function emptyResult(): array
    {
        return ['forms'=>0,'details'=>0,'people_id'=>0,'people_code'=>0];
    }

    
    public function findOrCreateBySoPhieu(string $soPhieu, string $academicYear, array $geo, string $scheme, $hh): SurveyForm
    {
        if (trim($soPhieu) === '') {
            throw new \InvalidArgumentException("Số phiếu (soPhieu) không được rỗng");
        }
        $newWard_id = $scheme === 'new' ? ($geo['ward_id'] ?? null) : null;
        $new_province_id = $scheme === 'new' ? ($geo['province_id'] ?? null) : null;
        if($scheme === 'old')
        {
            $newWard_id = $this->geoService->mapOldIdToNewId($geo['ward_id'] ?? null);
            $new_province_id = $this->geoService->mapOldIdToNewId($geo['province_id'] ?? null);
        }
        
        $data = [
                'so_phieu'       => (string) $soPhieu,
                'academic_year'  => $academicYear ?: null,
                'household_id'   => $hh->id,
                'head_person_id' => $hh->head_person_id,       // set sau khi xác định chủ hộ
                'investigators'  => [], // có thể bổ sung sau
                'household_id'   => $hh->id,
                'is_deleted'     => false,
                'new_ward_id'     =>  $newWard_id,
                'old_ward_id'     => $scheme === 'old' ? ($geo['ward_id'] ?? null) : null,
                'old_district_id' => $scheme === 'old' ? ($geo['district_id'] ?? null) : null,
                'village_id'      => $geo['village_id'] ?? null,
                'old_province_id' => $scheme === 'old' ? ($geo['province_id'] ?? null) : null,
                'new_province_id' =>  $new_province_id,
            ];
        $survey = SurveyForm::where('so_phieu', (string)$soPhieu)
            ->first();

        if (!$survey) {
            $survey = SurveyForm::create($data);
            $survey->academic_year = ($academicYear ?: null);
        }
        else{
            $survey->update($data);
        }

        return $survey->refresh();
    }
    protected function setHeadHousehold(Household $household, Person $person): void
    {
        if (self::hasColumn('households', 'head_person_id')) {
            $household->forceFill(['head_person_id' => $person->id])->save();
        }
        HouseholdMember::updateOrCreate(
            ['household_id' => $household->id, 'person_id' => $person->id],
            ['role' => 'Chủ hộ']
        );
    }
    public function setHead(SurveyForm $form, Person $head): void
    {
        if ($form->head_person_id !== $head->id) {
            $form->head_person_id = $head->id;
            $form->save();
        }
    }

    /**
     * Thêm chi tiết phiếu:
     * - Nếu đã có detail cho cùng person_id trong cùng survey_form → cập nhật các khóa liên kết còn trống
     * - Nếu chưa có → tạo mới
     */
    // public function addOrUpdateDetail(SurveyForm $form, array $linkIds, ?string $note = null): SurveyFormDetail
    // {
    //     $personId = $linkIds['person_id'] ?? null;

    //     $detail = null;
    //     if ($personId) {
    //         $detail = SurveyFormDetail::where('survey_form_id', $form->id)
    //                     ->where('person_id', $personId)
    //                     ->first();
    //     }

    //     if ($detail) {
    //         // chỉ bổ sung các FK còn thiếu (tránh nhân bản)
    //         $fillable = [
    //             'residence_id','person_education_id','literacy_id',
    //             'person_disability_id','person_special_circumstance_id','person_contact_id',
    //         ];
    //         $changed = false;
    //         foreach ($fillable as $k) {
    //             if (isset($linkIds[$k]) && !empty($linkIds[$k]) && empty($detail->{$k})) {
    //                 $detail->{$k} = $linkIds[$k];
    //                 $changed = true;
    //             }
    //         }
    //         if ($note !== null && $note !== '' && $detail->note !== $note) {
    //             $detail->note = $note;
    //             $changed = true;
    //         }
    //         if ($changed) $detail->save();
    //         return $detail;
    //     }

    //     // tạo mới
    //     return SurveyFormDetail::create([
    //         'survey_form_id'                => $form->id,
    //         'person_id'                     => $personId,
    //         'residence_id'                  => $linkIds['residence_id']                  ?? null,
    //         'person_education_id'           => $linkIds['person_education_id']           ?? null,
    //         'literacy_id'                   => $linkIds['literacy_id']                   ?? null,
    //         'person_disability_id'          => $linkIds['person_disability_id']          ?? null,
    //         'person_special_circumstance_id'=> $linkIds['person_special_circumstance_id']?? null,
    //         'person_contact_id'             => $linkIds['person_contact_id']             ?? null,
    //         'note'                          => $note,
    //         'is_deleted'                    => false,
    //     ]);
    // }
}
