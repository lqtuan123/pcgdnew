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
use App\Models\PersonMamnon;
use App\Models\SurveyMamnon;
use App\Models\EducationBlock;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

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
        $academicYear = (string)($opt['academic_year'] ?? SurveyForm::currentAcademicYear());

        // Suy ra household từ survey
        $household = $survey->household; // đổi cho khớp quan hệ của bạn
       
        return DB::transaction(function () use ($survey, $household, $row, $scheme, $geo, $academicYear) {
        try{
        //    Log::info('1 upsertMemberIntoSurvey',$geo);
            // 1) Person
            $p = $this->people->upsertFromRow($row, $scheme, $geo);
//   Log::info('1 1 upsertMemberIntoSurvey',$geo);
            // 2) Quan hệ hộ
            $relModel = $this->rels->getOrCreate($row['relationship_name'] ?? '');
              Log::info('1 2 upsertMemberIntoSurvey',$geo);
            $this->membership->attachOrUpdate($household, $p, $relModel);
       Log::info('2 attachOrUpdate',$geo);
            // 3) Literacy
            $lit = $this->literacy->upsertFromRow($p, $row); // nullable
Log::info('3 attachOrUpdate',$geo);
            // 4) Disability
            $dis = $this->disabilities->upsertForPerson($p, $row); // nullable
Log::info('4 attachOrUpdate',$geo);
            // 5) Education (theo năm học)
            $edu = $this->education->upsertForPerson($p->id, $row, $geo, $scheme, $academicYear); // nullable
Log::info('5 attachOrUpdate',$geo);
            // 6) Special circumstance
            $scLink = $this->scService->upsertFromRow($p, $row); // nullable
Log::info('6 attachOrUpdate');
Log::info( $scLink);
            // 7) SurveyFormDetail
           $detail = SurveyFormDetail::updateOrCreate(
                ['survey_form_id' => $survey->id, 'person_id' => $p->id, 'academic_year' => $academicYear],
                [
                    'residence_id'                   => null,
                    'person_education_id'            => $edu?->id,
                    'literacy_id'                    => $lit?->id,
                    'person_disability_id'           => $dis?->id,
                    'person_special_circumstance_id' => $scLink?->id,
                    'person_contact_id'              => null,
                    'note'                           => $row['note'] ?? null,
                    'is_deleted'                     => false,
                ]
            );
Log::info('7 attachOrUpdate' );
Log::info( $detail);
            // 8) Tạo PersonMamnon nếu thỏa mãn điều kiện
                $personMamnon = null;
                if ($edu && $this->shouldCreatePersonMamnon($p, $edu, $academicYear)) {
                    $personMamnon = $this->createPersonMamnon($p, $row, $academicYear, compact('scheme', 'geo', 'academicYear'));
                    if ($personMamnon) {
                        $this->createSurveyMamnon($personMamnon, $edu, $detail, $survey, $academicYear);
                        
                        // Cập nhật SurveyFormDetail với person_mamnon_id
                        $detail->update(['person_mamnon_id' => $personMamnon->id]);
                    }
                }
            
                return compact('p','detail','edu','personMamnon');
            } 
            catch (\Throwable $e) {
                    // Handle exception
                    DB::rollBack();
                    // $stats['row_errors'][] = $row['row_no'] ?? 'không rõ';
                    // $stats['message_errors'][] = $e->getMessage();
                    // $stats['fail_count']++;
                    
                     throw $e;
                }
             
        });
    }

    /**
     * Kiểm tra xem có nên tạo PersonMamnon không
     */
    private function shouldCreatePersonMamnon($person, $education, string $academicYear): bool
    {
        // Kiểm tra tuổi mầm non (≤5 tuổi)
        $isPreschoolAge = $this->isPreschoolAge($person, $academicYear);
        
        // Kiểm tra khối học mầm non
        $isPreschoolBlock = $this->isPreschoolBlock($education);
        
        return $isPreschoolAge || $isPreschoolBlock;
    }

    /**
     * Kiểm tra xem người có phải tuổi mầm non (≤5 tuổi) trong năm học không
     * Tính đơn giản: năm học - năm sinh ≤ 5
     */
    private function isPreschoolAge($person, string $academicYear): bool
    {
        if (!$person->dob_year) {
            return false;
        }

        // Lấy năm bắt đầu của năm học (ví dụ: 2024-2025 -> 2024)
        $yearStart = (int) explode('-', $academicYear)[0];
        
        // Tính tuổi đơn giản: năm học - năm sinh
        $age = $yearStart - $person->dob_year;
        
        return $age <= 5;
    }

    /**
     * Kiểm tra xem khối học có phải mầm non không
     */
    private function isPreschoolBlock($education): bool
    {
        if (!$education || !$education->education_block_id) {
            return false;
        }

        $block = EducationBlock::find($education->education_block_id);
        if (!$block) {
            return false;
        }

        $preschoolPatterns = [
            '3-12 tháng', '13-24 tháng', '25-36 tháng',
            '3-4 tuổi', '4-5 tuổi', '5-6 tuổi',
            'mầm non', 'mam non', 'preschool'
        ];
        
        $blockName = strtolower($block->name);
        foreach ($preschoolPatterns as $pattern) {
            if (str_contains($blockName, $pattern)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Tạo PersonMamnon record cho trẻ mầm non
     */
    private function createPersonMamnon($person, array $row, string $academicYear, array $opt): ?PersonMamnon
    {
        try {
            // Xác định trái tuyến dựa trên so sánh mã tỉnh/quận huyện của trường với khu vực khảo sát
            $traiTuyen = $this->determineTraiTuyen($person, $row, $opt);
            
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
     * Tạo SurveyMamnon record liên kết
     */
    private function createSurveyMamnon($personMamnon, $education, $detail, $survey, string $academicYear): ?SurveyMamnon
    {
        try {
            $surveyMamnon = SurveyMamnon::create([
                'person_id' => $personMamnon->person_id,
                'person_mamnon_id' => $personMamnon->id,
                'person_education_id' => $education->id,
                'survey_form_detail_id' => $detail->id,
                'so_phieu' => $survey->so_phieu,
                'academic_year' => $academicYear,
                'editor_id' => Auth::check() ? Auth::id() : 1, // Lấy ID user hiện tại hoặc 1 (system)
                'is_deleted' => false,
            ]);

            return $surveyMamnon;
        } catch (\Throwable $e) {
            Log::error('Error creating SurveyMamnon for person ' . $personMamnon->person_id . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Xác định trái tuyến dựa trên so sánh mã tỉnh/quận huyện của trường với khu vực khảo sát
     */
    private function determineTraiTuyen($person, array $row, array $opt): bool
    {
        try {
            // Lấy thông tin trường học từ PersonEducation
            $education = $person->educations()->where('academic_year', $opt['academic_year'] ?? '')->first();
            if (!$education || !$education->school_id) {
                return false;
            }

            $school = $education->school;
            if (!$school) {
                return false;
            }

            // So sánh mã tỉnh/quận huyện của trường với khu vực khảo sát
            $geo = $opt['geo'] ?? [];
            $scheme = $opt['scheme'] ?? 'new';

            if ($scheme === 'old') {
                // Sử dụng mã cũ
                $schoolProvince = $school->province_code;
                $schoolDistrict = $school->district_code;
                $surveyProvince = $geo['province_id'] ?? null;
                $surveyDistrict = $geo['district_id'] ?? null;
            } else {
                // Sử dụng mã mới
                $schoolProvince = $school->province_code_new;
                $schoolDistrict = $school->district_code_new;
                $surveyProvince = $geo['province_id'] ?? null;
                $surveyDistrict = $geo['district_id'] ?? null;
            }

            // Nếu không có thông tin địa lý để so sánh, mặc định là không trái tuyến
            if (!$schoolProvince || !$surveyProvince) {
                return false;
            }

            // Trái tuyến nếu tỉnh khác nhau, hoặc cùng tỉnh nhưng quận/huyện khác nhau
            return $schoolProvince != $surveyProvince || 
                   ($schoolDistrict && $surveyDistrict && $schoolDistrict != $surveyDistrict);

        } catch (\Throwable $e) {
            Log::error('Error determining trai tuyen for person ' . $person->id . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Chuyển đổi giá trị thành boolean
     */
    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['1', 'true', 'yes', 'có', 'co', 'on']);
        }
        
        return (bool) $value;
    }
     
}
