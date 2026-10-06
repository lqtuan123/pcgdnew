<?php
// app/Services/Import/SurveyImportOrchestrator.php

namespace App\Services;

use App\Services\Households\HouseholdMemberService;
use App\Services\Households\HouseholdService;
use App\Services\Households\HouseRelationshipService;
use App\Services\People\PersonService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\People\PersonLiteracyService; 
use App\Services\People\PersonDisabilityService;
use App\Services\People\PersonSpecialCircumstanceService;
use App\Services\SurveyFormService;
use App\Models\SurveyForm;
use App\Models\SurveyFormDetail;
 use App\Services\People\PersonResidenceService;
use App\Models\PersonMamnon;
use App\Models\PersonEducation;
use App\Models\School;
use App\Models\GeoUnit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
           protected \App\Services\SurveyMemberWriter $writer,
    ) {}

public function import(array $payload): array
{
    // 1) Decode rows an toàn (string JSON hoặc array)
    //   Log::info('import orchestrator');
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

    
    foreach ($groups as $ticket => $members) {
        try {
        
            //  $payload['geo'] kiem tra co cot 52,53,54 ko, nếu có ưu tiên lấy

           


            $first     = $members->first();
            $chuHoName = trim(trim(($first['chuho_ho_dem'] ?? '') . ' ' . ($first['chuho_ten'] ?? '')));
            $addr      = trim((string)($first['addressline'] ?? ''));
            $phone     = $first['contact_phone'] ?? null;
            Log::info($payload['geo']);
            // 3) Hộ gia đình theo (scheme, geo, ticket_no)
            // $existing = $this->households->findByGeoAndTicketNo(
            //     $payload['scheme'],
            //     $payload['geo'],
            //     (string) $ticket
            // );
            if(isset($first['contact_province_name']))
            {
                $geoArr = app(\App\Services\GeoService::class)->resolveNewGeoFromNames(
                    $first['contact_province_name'] ?? '',
                    $first['contact_ward_name'] ?? '',
                    $first['contact_hamlet_name'] ?? ''
                );
                $payload['geo'] = $geoArr;
            }
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
            Log::info($payload['geo']);
            $survey = $this->surveyForms->findOrCreateBySoPhieu((string) $ticket, $academicYear, $payload['geo'], $payload['scheme'],$hh);
        //    echo 'Phiếu #' . $survey->id . ' - ' . $survey->so_phieu . '<br/>';
            // 4) Upsert các thành viên + gắn quan hệ + tạo SurveyFormDetail
            $head = null;
              Log::info('import orchestrator');
            // echo json_encode($members).'<br/>';
            foreach ($members as $row) {
                try {
                    Log::info('import orchestrator');
                    if(isset($row['contact_province_name']))
                    {
                        $geoArr = app(\App\Services\GeoService::class)->resolveNewGeoFromNames(
                            $row['contact_province_name'] ?? '',
                            $row['contact_ward_name'] ?? '',
                            $row['contact_hamlet_name'] ?? ''
                        );
                        $payload['geo'] = $geoArr;
                    }
                    
                    $res = $this->writer->upsertMemberIntoSurvey($survey, $row, [
                        'scheme'        => $payload['scheme'],
                        'geo'           => $payload['geo'],
                        'academic_year' => (string)($payload['academic_year'] ?? ''),
                    ]);

                    // xác định chủ hộ tạm thời để cuối vòng set head
                    if ($this->isHead($row, $first)) {
                        $head = $res['p'];
                    }

                    $stats['members_upserted']++;
                    $stats['survey_details']++; // mỗi lần upsert detail thành công, tăng
                    $stats['ok_count']++;
                } catch (\Throwable $e) {
                    // ❗ BẮT LỖI THEO DÒNG — KHÔNG DỪNG CẢ LÔ
                    $stats['row_errors'][]    = $row['row_no'] ?? 'không rõ';
                    $stats['message_errors'][]= $e->getMessage();
                    $stats['fail_count']++;
                    // optional: \Log::warning('Import member failed', ['ticket'=>$ticket, 'row'=>$row, 'ex'=>$e]);
                    continue;
                }
                
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
             
            // throw $e;
        }
    }
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

    /**
     * Kiểm tra xem người có phải tuổi mầm non (1-5 tuổi) trong năm học không
     */
    private function isPreschoolAge($person, string $academicYear): bool
    {
        if (!$person->dob_year || !$person->dob_month || !$person->dob_day) {
            return false;
        }

        // Lấy năm bắt đầu của năm học (ví dụ: 2024-2025 -> 2024)
        $yearStart = (int) explode('-', $academicYear)[0];
        
        // Tính tuổi đơn giản: năm học - năm sinh
        $age = $yearStart - $person->dob_year;
        
        return $age <= 5;
    }

    /**
     * Kiểm tra xem có thông tin trường và lớp học không
     */
    private function hasSchoolAndClass($education): bool
    {
        // Kiểm tra PersonEducation có tồn tại và có thông tin trường lớp
        if (!$education) {
            return false;
        }

        // Kiểm tra có trường học
        if (!$education->school_id) {
            return false;
        }

        // Kiểm tra có lớp học (class_label hoặc education_block_id)
        if (empty($education->class_label) && !$education->education_block_id) {
            return false;
        }

        return true;
    }

    /**
     * Tạo PersonMamnon record cho trẻ mầm non
     */
    private function createPersonMamnon($person, array $row, string $academicYear, array $payload): ?PersonMamnon
    {
        try {
            // Xác định trái tuyến dựa trên so sánh mã tỉnh/quận huyện của trường với khu vực khảo sát
            $traiTuyen = $this->determineTraiTuyen($person, $row, $payload);
            
            $personMamnon = PersonMamnon::updateOrCreate(
                ['person_id' => $person->id],
                [
                    'person_id' => $person->id,
                    'hoc_2_buoi' => $this->toBool($row['hoc_2_buoi'] ?? false),
                    'hoc_ban_tru' => $this->toBool($row['hoc_ban_tru'] ?? false),
                    'hoc_du_ngay_theo_quy_dinh' => $this->toBool($row['hoc_du_ngay'] ?? false),
                    'chuyen_can' => $this->toBool($row['chuyen_can'] ?? false),
                    'theo_doi_bd_can_nang' => $this->toBool($row['theo_doi_can_nang'] ?? false),
                    'sdd_nhe_can' => $this->toBool($row['sdd_nhe_can'] ?? false),
                    'theo_doi_bd_chieu_cao' => $this->toBool($row['theo_doi_chieu_cao'] ?? false),
                    'sdd_thap_coi' => $this->toBool($row['sdd_thap_coi'] ?? false),
                    'dan_toc_chuan_bi_tv' => $this->toBool($row['chuan_bi_tv'] ?? false),
                    'hoan_thanh_chuong_trinh_gdmn' => $this->toBool($row['hoan_thanh_gdmn'] ?? false),
                    'trai_tuyen' => $traiTuyen,
                ]
            );

            return $personMamnon;
        } catch (\Throwable $e) {
            // Log error nhưng không dừng quá trình import
            Log::error('Error creating PersonMamnon for person ' . $person->id . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Xác định trái tuyến dựa trên so sánh mã tỉnh/quận huyện của trường với khu vực khảo sát
     */
    private function determineTraiTuyen($person, array $row, array $payload): bool
    {
        try {
            // Lấy thông tin trường học từ PersonEducation
            $education = PersonEducation::where('person_id', $person->id)
                ->where('academic_year', $payload['academic_year'] ?? '')
                ->first();
            
            if (!$education || !$education->school_id) {
                // Không có thông tin trường học -> không thể xác định trái tuyến
                return false;
            }
            
            $school = School::find($education->school_id);
            if (!$school) {
                return false;
            }

            if (str_starts_with(strtoupper((string)$school->code), 'NGOAI_TINH')) {
                return true;
            }
            
            // Lấy mã tỉnh/quận huyện của khu vực khảo sát
            $surveyGeo = $payload['geo'] ?? [];
            $surveyProvinceId = $surveyGeo['province_id'] ?? null;
            $surveyDistrictId = $surveyGeo['district_id'] ?? null;
            
            // Lấy mã tỉnh/quận huyện của trường học (xử lý cả mã cũ và mới)
            $schoolProvinceId = $this->getSchoolProvinceId($school, $payload['scheme'] ?? 'old');
            $schoolDistrictId = $this->getSchoolDistrictId($school, $payload['scheme'] ?? 'old');
            
            // So sánh mã tỉnh
            if ($surveyProvinceId && $schoolProvinceId) {
                if ($surveyProvinceId !== $schoolProvinceId) {
                    // Khác tỉnh -> trái tuyến
                    return true;
                }
            }
            
            // Nếu cùng tỉnh, so sánh quận/huyện (nếu có thông tin)
            if ($surveyDistrictId && $schoolDistrictId) {
                if ($surveyDistrictId !== $schoolDistrictId) {
                    // Khác quận/huyện -> trái tuyến
                    return true;
                }
            }
            
            // Cùng khu vực -> không trái tuyến
            return false;
            
        } catch (\Throwable $e) {
            // Log error nhưng không dừng quá trình import
            Log::error('Error determining trai_tuyen for person ' . $person->id . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Lấy province_id của trường học (xử lý cả mã cũ và mới)
     */
    private function getSchoolProvinceId(School $school, string $scheme): ?int
    {
        if ($scheme === 'new') {
            // Sử dụng mã mới
            if ($school->province_code_new) {
                $province = GeoUnit::where('code', $school->province_code_new)
                    ->where('scheme', 'new')
                    ->where('type_level', 1)
                    ->first();
                return $province ? $province->id : null;
            }
        } else {
            // Sử dụng mã cũ
            if ($school->province_code) {
                $province = GeoUnit::where('code', $school->province_code)
                    ->where('scheme', 'old')
                    ->where('type_level', 1)
                    ->first();
                return $province ? $province->id : null;
            }
        }
        
        return null;
    }

    /**
     * Lấy district_id của trường học (xử lý cả mã cũ và mới)
     */
    private function getSchoolDistrictId(School $school, string $scheme): ?int
    {
        if ($scheme === 'new') {
            // Sử dụng mã mới - lấy từ ward_code_new
            if ($school->ward_code_new) {
                $ward = GeoUnit::where('code', $school->ward_code_new)
                    ->where('scheme', 'new')
                    ->where('type_level', 3)
                    ->first();
                
                if ($ward && $ward->parent_id) {
                    $district = GeoUnit::find($ward->parent_id);
                    return ($district && $district->type_level === 2) ? $district->id : null;
                }
            }
        } else {
            // Sử dụng mã cũ
            if ($school->district_code) {
                $district = GeoUnit::where('code', $school->district_code)
                    ->where('scheme', 'old')
                    ->where('type_level', 2)
                    ->first();
                return $district ? $district->id : null;
            }
        }
        
        return null;
    }

    /**
     * Helper method để convert giá trị sang boolean
     */
    private function toBool($value): bool
    {
        if (is_bool($value)) return $value;
        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['1', 'x', 'v', 'yes', 'y', 'true', 't', 'co', 'có'], true);
        }
        if (is_numeric($value)) return (int)$value > 0;
        return false;
    }
}
