<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\SurveyForm;
use App\Models\SurveyFormDetail;
use App\Models\EducationGrade;

// ========== ĐỔI namespace các service cho khớp dự án ==========
use App\Services\Households\HouseholdService;
use App\Services\SurveyFormService;
use App\Services\People\PersonService;
use App\Services\Households\HouseRelationshipService;
use App\Services\Households\HouseholdMemberService;
use App\Services\People\PersonLiteracyService;
use App\Services\People\PersonDisabilityService;
use \App\Services\Import\PersonEducationService;
use App\Services\People\PersonSpecialCircumstanceService;

class SurveyMemberWriter
{
    public function __construct(
        protected HouseholdService            $households,
        protected SurveyFormService           $surveyForms,
        protected PersonService                $people,
        protected HouseRelationshipService         $rels,
        protected PersonLiteracyService              $literacy,
        protected PersonDisabilityService          $disabilities,
        protected HouseholdMemberService $membership,
        protected PersonEducationService             $education,
        protected PersonSpecialCircumstanceService   $scService,
    ) {}

    /**
     * Đảm bảo hộ + phiếu (khi cần dùng ngoài ngữ cảnh modal).
     * Trả về [ $household, $survey, $isNewHousehold ]
     */
    public function ensureHouseholdAndSurvey(array $ctx, array $firstRow): array
    {
        // $ctx: ['scheme'=>'new|old', 'geo'=>[...], 'ticket_no'=>..., 'academic_year'=>...]
        [$hh, $isNew, $head] = $this->households->upsertWithHead(
            $ctx['scheme'],
            $ctx['geo'],
            (string)$ctx['ticket_no'],
            $firstRow
        );

        $survey = $this->surveyForms->findOrCreateBySoPhieu(
            (string)$ctx['ticket_no'],
            (string)$ctx['academic_year'],
            $ctx['geo'],
            $ctx['scheme'],
            $hh
        );

        return [$hh, $survey, $isNew];
    }

    /**
     * Upsert MỘT thành viên vào survey (đang mở trong modal).
     * - $row: đúng “flat schema” của import (khớp các service hiện tại)
     * - $opt: ['scheme'=>'new', 'geo'=>[], 'academic_year'=>..., 'set_head' => bool|null]
     *
     * Trả về mảng: ['person'=>$p, 'detail'=>$detail, 'education'=>$edu]
     */
    public function upsertMemberIntoSurvey(SurveyForm $survey, array $row, array $opt = []): array
    {
        $scheme        = $opt['scheme']        ?? 'new';
        $geo           = $opt['geo']           ?? [];
        $academicYear  = $opt['academic_year'] ?? (string)$survey->academic_year;

        // Suy ra household từ survey
        $household = $survey->household; // đổi cho khớp quan hệ của bạn
       
        return DB::transaction(function () use ($survey, $household, $row, $scheme, $geo, $academicYear) {
        try{
            // 1) Person
            $p = $this->people->upsertFromRow($row, $scheme, $geo);

            // 2) Quan hệ hộ
            $relModel = $this->rels->getOrCreate($row['relationship_name'] ?? '');
            $this->membership->attachOrUpdate($household, $p, $relModel);

            // 3) Literacy
            $lit = $this->literacy->upsertFromRow($p, $row); // nullable

            // 4) Disability
            $dis = $this->disabilities->upsertForPerson($p, $row); // nullable

            // 5) Education (theo năm học)
            $edu = $this->education->upsertForPerson($p->id, $row, $geo, $scheme, $academicYear); // nullable

            // 6) Special circumstance
            $scLink = $this->scService->upsertFromRow($p, $row); // nullable

            // 7) SurveyFormDetail
            $detail = SurveyFormDetail::updateOrCreate(
                ['survey_form_id' => $survey->id, 'person_id' => $p->id],
                [
                    'residence_id'                   => null,              // nếu có bảng Residence thì bổ sung
                    'person_education_id'            => $edu?->id,
                    'literacy_id'                     => $lit?->id,
                    'person_disability_id'           => $dis?->id,
                    'person_special_circumstance_id' => $scLink?->id,
                    'person_contact_id'              => null,
                    'note'                           => $row['note'] ?? null,
                    'is_deleted'                     => false,
                ]
            );
           
            return compact('p','detail','edu');
            } 
            catch (\Throwable $e) {
                    // Handle exception
                    // DB::rollBack();
                    $stats['row_errors'][] = $row['row_no'] ?? 'không rõ';
                    $stats['message_errors'][] = $e->getMessage();
                    $stats['fail_count']++;
                    dd($e);
                     throw $e;
                }
             
        });
    }
     
}
