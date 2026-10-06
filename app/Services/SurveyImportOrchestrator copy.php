<?php
// app/Services/Import/SurveyImportOrchestrator.php

namespace App\Services;

use App\Services\Households\HouseholdMemberService;
use App\Services\Households\HouseholdService;
use App\Services\Households\HouseRelationshipService;
use App\Services\People\PersonService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\People\PersonLiteracyService; // 👈 thêm
use App\Services\People\PersonDisabilityService;
use App\Services\People\PersonSpecialCircumstanceService;
use App\Services\SurveyFormService;
use App\Models\SurveyForm;
use App\Models\SurveyFormDetail;
 use App\Services\People\PersonResidenceService;
class SurveyImportOrchestrator
{
    public function __construct(
        protected PersonService $people,
        protected HouseholdService $households,
        protected HouseholdMemberService $members,
        protected HouseRelationshipService $rels,
        protected PersonLiteracyService $literacy,
        protected PersonDisabilityService $disabilities,
        protected PersonSpecialCircumstanceService $scService,
        protected \App\Services\People\PersonContactService $contactService,
        protected \App\Services\Import\PersonEducationService $education,
          protected SurveyFormService $surveyForms,
           protected PersonResidenceService $residence, 
    ) {}

public function import(array $payload): array
{
    // 1) Decode rows an toàn (string JSON hoặc array)
    $rowsRaw = $payload['rows'] ?? [];
    if (is_string($rowsRaw)) {
        $rowsRaw = json_decode($rowsRaw, true) ?: [];
    }
    $rows = collect($rowsRaw)->filter(fn ($r) => is_array($r));
    
    // 1.1) Bỏ dòng mapping-toàn-số
    // $rows = $rows->reject(function (array $r) {
    //     $vals = array_values($r);
    //     if (!count($vals)) return true;
    //     $numericOnly = 0;
    //     foreach ($vals as $v) {
    //         $s = trim((string) $v);
    //         if ($s !== '' && preg_match('/^\d+$/', $s)) $numericOnly++;
    //     }
    //     $tenIsNumeric = preg_match('/^\d+$/', (string)($r['ten'] ?? ''));
    //     return ($numericOnly >= 20) || $tenIsNumeric;
    // });
    
    // 2) Gom theo số phiếu
    $groups = $rows
    ->filter(fn ($r) => filled($r['so_phieu']))   // bỏ điều kiện filled($r['ten'])
    ->groupBy(fn ($r) => trim((string) $r['so_phieu']));
  
    // dd($payload, $rows, $groups);
    $stats = [
        
        'ok_count'   => 0,
        'fail_count' => 0,
        'row_errors' => [],
        'message_errors' => [],
        'households_created' => 0,
        'households_updated' => 0,
        'members_upserted'   => 0,
        'survey_forms'       => 0,   // chỉ tính khi tạo mới
        'survey_details'     => 0,   // số chi tiết (tạo mới hoặc cập nhật)
        'tickets'            => $groups->keys()->values()->all(),
    ];

    $dong = 1;

    // DB::transaction(function () use ($payload, $groups, &$stats, &$dong) {
       foreach ($groups as $ticket => $members) {
            try {
            
                $first     = $members->first();
                $chuHoName = trim(trim(($first['chuho_ho_dem'] ?? '') . ' ' . ($first['chuho_ten'] ?? '')));
                $addr      = trim((string)($first['addressline'] ?? ''));
                $phone     = $first['contact_phone'] ?? null;

                // 3) Hộ gia đình theo (scheme, geo, ticket_no)
                $existing = $this->households->findByGeoAndTicketNo(
                    $payload['scheme'],
                    $payload['geo'],
                    (string) $ticket
                );

                [$hh, $isNew, $head] = $this->households->upsertWithHead(
                    $payload['scheme'],
                    $payload['geo'],
                    (string) $ticket,
                   $first
                    // có thể truyền $first hoặc dòng xác định là chủ hộ
                );
                // echo 'Hộ #' . $hh->id . ' - ' . $hh->name . '<br/>';
                 // 3.1) Phiếu điều tra (1 phiếu / so_phieu + academic_year)
                $academicYear = (string) ($payload['academic_year'] ?? '');
                $survey = $this->surveyForms->findOrCreateBySoPhieu((string) $ticket, $academicYear, $payload['geo'], $payload['scheme'],$hh);
            //    echo 'Phiếu #' . $survey->id . ' - ' . $survey->so_phieu . '<br/>';
                // 4) Upsert các thành viên + gắn quan hệ + tạo SurveyFormDetail
                $head = null;
                // echo json_encode($members).'<br/>';
                foreach ($members as $row) {
                    try{
                        DB::beginTransaction();
                        // echo $dong.' - '.$row['ten'].'<br/>';
                        // 4.1) Person
                        $p = $this->people->upsertFromRow($row, $payload['scheme'], $payload['geo']);
                        // echo 'Thành viên #' . $p->id . ' - ' . $p->ten . '<br/>';
                        // 4.2) Quan hệ trong hộ
                        $relModel = $this->rels->getOrCreate($row['relationship_name'] ?? '');
                        $this->members->attachOrUpdate($hh, $p, $relModel);
                        $stats['members_upserted']++;

                        // 4.3) Literacy (Xoá mù chữ)
                        $lit = $this->literacy->upsertFromRow($p, $row); // có thể trả về null

                        // 4.4) Khuyết tật
                        $dis = $this->disabilities->upsertForPerson($p, $row); // có thể null

                        // 4.5) Học tập hiện hành
                        $edu = null;
                        if (!empty($academicYear)) {
                            $edu = $this->education->upsertForPerson(
                                $p->id,
                                $row,
                                $payload['geo'],
                                $payload['scheme'],
                                $academicYear
                            ); // có thể null (nếu rỗng dữ liệu)
                            
                        }

                        // 4.6) Hoàn cảnh đặc biệt
                        $scLink = $this->scService->upsertFromRow($p, $row); // PersonSpecialCircumstance|null

                        // 4.7) Thông tin liên lạc (Cha/Mẹ)
                        // $contact = $this->contactService->upsertFromRow($p, $row, $this->rels); // PersonContact|null
                        // 4.x) Residence (cư trú)
                        // 4.9) Xác định chủ hộ nếu dòng này là chủ hộ
                        $isRowHead = $this->isHead($row, $first);
                        if ($isRowHead) {
                            $head = $p;
                        }
                        // $residence = $this->residence->upsertFromRow($p, $row, $isRowHead,  $hh->head_person_id ?? null);
                        // 4.8) SurveyFormDetail (upsert theo survey_form_id + person_id)
                        $detail = SurveyFormDetail::where('survey_form_id', $survey->id)
                            ->where('person_id', $p->id)
                            ->first();

                        $dataDetail = [
                            'residence_id'                    =>   null,                 // nếu có bảng Residence thì bổ sung
                            'person_education_id'             => $edu?->id,
                            'literacy_id'                      => $lit?->id,
                            'person_disability_id'            => $dis?->id,
                            'person_special_circumstance_id'  => $scLink?->id,
                            'person_contact_id'               => null,
                            'note'                            => $row['note'] ?? null,
                            'is_deleted'                      => false,
                        ];

                        if ($detail) {
                            // cập nhật khi có thay đổi
                            $dirty = false;
                            foreach ($dataDetail as $k => $v) {
                                if ($detail->{$k} !== $v) { $dirty = true; break; }
                            }
                            if ($dirty) { $detail->fill($dataDetail)->save(); }
                        } else {
                            SurveyFormDetail::create($dataDetail + [
                                'survey_form_id' => $survey->id,
                                'person_id'      => $p->id,
                            ]);
                        }
                        $stats['survey_details']++;

                      
                        DB::commit();
                    } catch (\Throwable $e) {
                        // Handle exception
                        DB::rollBack();
                        $stats['row_errors'][] = $row['row_no'] ?? 'không rõ';
                        $stats['message_errors'][] = $e->getMessage();
                        $stats['fail_count']++;
                       
                        //  throw $e;
                    }
                    $dong++;
                   
                }
                //  echo 'Chủ hộ: '.($head ? $head->ten : '(không rõ)').'<br/>';
                // 5) Nếu hộ mới mà chưa có head trong members, tạo head từ thông tin chuho_* trên phiếu
                if ($isNew && !$head) {
                    
                }

                // 6) Set chủ hộ cho Household + SurveyForm
                if ($head) {
                    $this->households->setHead($hh, $head, $this->people);
                    // cập nhật vào phiếu
                    if ($survey->head_person_id !== $head->id) {
                        $survey->head_person_id = $head->id;
                        $survey->save();
                    }
                }
                 $stats['ok_count'] += count($members);
                //  echo 'mem OK<br/>';
            
            } // Rethrow the exception to trigger the transaction rollback}
            catch (\Throwable $e) {
                $stats['row_errors'][] = $row['row_no'] ?? 'không rõ';
                $stats['message_errors'][] = $e->getMessage();
                 $dong++;
                // throw $e;
            }
        }
    // });

    return $stats;
}
 

    private function isHead(array $row, array $firstRow): bool
    {
        $rel = Str::lower(Str::ascii(trim($row['relationship_name'] ?? '')));
        if (str_contains($rel, 'chu ho') || str_contains($rel, 'chủ hộ')) {
            return true;
        }

        $a = Str::lower(Str::ascii(trim(($row['ho_dem'] ?? '').' '.($row['ten'] ?? ''))));
        $b = Str::lower(Str::ascii(trim(($firstRow['chuho_ho_dem'] ?? '').' '.($firstRow['chuho_ten'] ?? ''))));
        return $a !== '' && $a === $b;
    }
}
