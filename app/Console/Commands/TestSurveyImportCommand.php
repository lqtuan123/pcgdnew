<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SurveyForm;
use App\Models\SurveyFormDetail;
use App\Models\Person;
use App\Models\PersonEducation;
use App\Models\PersonMamnon;
use App\Models\SurveyMamnon;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\EducationBlock;
use App\Models\School;
use App\Models\Ethnicity;
use App\Models\Religion;
use App\Models\ResidenceType;
use App\Models\ResidenceStatus;
use App\Models\GeoUnit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TestSurveyImportCommand extends Command
{
    protected $signature = 'test:survey-import {academic_year?}';
    protected $description = 'Tạo dữ liệu phiếu khảo sát mẫu để test tự động tạo học sinh mầm non';

    public function handle()
    {
        $academicYear = $this->argument('academic_year') ?? \App\Models\SurveyForm::currentAcademicYear();
        
        $this->info("Đang tạo dữ liệu phiếu khảo sát mẫu cho năm học: {$academicYear}");
        
        try {
            DB::beginTransaction();
            
            // Lấy dữ liệu chuẩn từ database
            $ethnicities = Ethnicity::all();
            $religions = Religion::all();
            $residenceTypes = ResidenceType::all();
            $residenceStatuses = ResidenceStatus::all();
            $educationBlocks = EducationBlock::all();
            $schools = School::all();
            $wards = GeoUnit::where('type_level', 3)->get();
            
            if ($ethnicities->isEmpty() || $religions->isEmpty() || $residenceTypes->isEmpty() || 
                $residenceStatuses->isEmpty() || $educationBlocks->isEmpty() || $schools->isEmpty() || $wards->isEmpty()) {
                $this->error("Thiếu dữ liệu chuẩn trong database. Vui lòng chạy seeders trước.");
                return;
            }
            
            $createdCount = 0;
            
            // Tạo 10 phiếu khảo sát, mỗi phiếu có 5 người
            for ($i = 1; $i <= 10; $i++) {
                $ward = $wards->random();
                
                // Tạo hộ gia đình
                $household = Household::create([
                    'name' => "Hộ gia đình Test {$i}",
                    'old_province_id' => $ward->old_province_id,
                    'old_district_id' => $ward->old_district_id,
                    'old_ward_id' => $ward->id,
                    'new_province_id' => $ward->new_province_id,
                    'new_district_id' => $ward->new_district_id,
                    'new_ward_id' => $ward->id,
                    'village_id' => $ward->village_id ?? null,
                ]);
                
                // Tạo phiếu điều tra
                $surveyForm = SurveyForm::create([
                    'so_phieu' => 'TEST' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'household_id' => $household->id,
                    'academic_year' => $academicYear,
                    'old_province_id' => $ward->old_province_id,
                    'old_district_id' => $ward->old_district_id,
                    'old_ward_id' => $ward->id,
                    'new_province_id' => $ward->new_province_id,
                    'new_district_id' => $ward->new_district_id,
                    'new_ward_id' => $ward->id,
                    'village_id' => $ward->village_id ?? null,
                    'surveyed_at' => now(),
                    'editor_id' => 1,
                ]);
                
                // Tạo 5 người cho mỗi phiếu
                for ($j = 1; $j <= 5; $j++) {
                    $personNumber = ($i - 1) * 5 + $j;
                    
                    // Tạo người với thông tin khác nhau
                    $person = $this->createPerson($personNumber, $ward, $ethnicities, $religions, $residenceTypes, $residenceStatuses);
                    
                    // Thêm vào hộ gia đình
                    HouseholdMember::create([
                        'household_id' => $household->id,
                        'person_id' => $person->id,
                        'role' => $j === 1 ? 'Chủ hộ' : 'Thành viên',
                    ]);
                    
                    // Tạo chi tiết phiếu điều tra
                    $surveyFormDetail = SurveyFormDetail::create([
                        'survey_form_id' => $surveyForm->id,
                        'person_id' => $person->id,
                        'is_deleted' => false,
                    ]);
                    
                    // Tạo thông tin học tập (một số sẽ có khối mầm non)
                    $personEducation = $this->createPersonEducation($person, $academicYear, $educationBlocks, $schools);
                    
                    // Cập nhật survey_form_detail với person_education_id
                    $surveyFormDetail->update(['person_education_id' => $personEducation->id]);
                    
                    $createdCount++;
                    
                    // Kiểm tra xem có tự động tạo dữ liệu mầm non không
                    $age = $this->calculateAgeInAcademicYear($person, $academicYear);
                    $blockName = $personEducation->block->name ?? '';
                    $isPreschoolAge = $age >= 1 && $age <= 5;
                    $isPreschoolBlock = $this->isPreschoolBlockName(strtolower($blockName));
                    
                    if ($isPreschoolAge || $isPreschoolBlock) {
                        $this->line("✓ Tự động tạo dữ liệu mầm non cho: {$person->full_name} (Tuổi: {$age}, Khối: {$blockName})");
                        
                        // Tạo PersonMamnon
                        $personMamnon = PersonMamnon::updateOrCreate(
                            ['person_id' => $person->id],
                            [
                                'hoc_ban_tru' => rand(0, 1) == 1,
                                'hoc_du_ngay_theo_quy_dinh' => rand(0, 1) == 1,
                                'chuyen_can' => rand(0, 1) == 1,
                                'theo_doi_bd_can_nang' => rand(0, 1) == 1,
                                'sdd_nhe_can' => rand(0, 1) == 1,
                                'theo_doi_bd_chieu_cao' => rand(0, 1) == 1,
                                'sdd_thap_coi' => rand(0, 1) == 1,
                                'dan_toc_chuan_bi_tv' => rand(0, 1) == 1,
                                'hoan_thanh_chuong_trinh_gdmn' => rand(0, 1) == 1,
                            ]
                        );
                        
                        // Tạo SurveyMamnon
                        SurveyMamnon::create([
                            'person_id' => $person->id,
                            'person_mamnon_id' => $personMamnon->id,
                            'person_education_id' => $personEducation->id,
                            'survey_form_detail_id' => $surveyFormDetail->id,
                            'so_phieu' => $surveyForm->so_phieu,
                            'academic_year' => $academicYear,
                            'editor_id' => 1,
                            'is_deleted' => false,
                        ]);
                    } else {
                        $this->line("→ Không tạo dữ liệu mầm non cho: {$person->full_name} (Tuổi: {$age}, Khối: {$blockName})");
                    }
                }
            }
            
            DB::commit();
            
            $this->info("\n✅ Hoàn thành!");
            $this->info("- Đã tạo: {$createdCount} người");
            $this->info("- Đã tạo: 10 phiếu khảo sát");
            $this->info("- Đã tạo: 10 hộ gia đình");
            $this->info("- Tự động tạo dữ liệu mầm non cho người phù hợp");
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Lỗi: " . $e->getMessage());
        }
    }
    
    private function createPerson($number, $ward, $ethnicities, $religions, $residenceTypes, $residenceStatuses)
    {
        $hoDem = ["Nguyễn Văn", "Trần Thị", "Lê Văn", "Phạm Thị", "Hoàng Văn", "Phan Thị", "Vũ Văn", "Đặng Thị", "Bùi Văn", "Đinh Thị"];
        $ten = ["An", "Bình", "Cường", "Dung", "Em", "Phúc", "Giang", "Hoa", "Ivan", "Khoa", "Linh", "Minh", "Nga", "Oanh", "Phong"];
        
        // Tạo ngày sinh khác nhau để có người nhỏ tuổi
        $currentYear = (int) explode('-', \App\Models\SurveyForm::currentAcademicYear())[0];
        $birthYear = $currentYear - rand(1, 10); // Tuổi từ 1-10
        $birthMonth = rand(1, 12);
        $birthDay = rand(1, 28);
        
        return Person::create([
            'ho_dem' => $hoDem[array_rand($hoDem)],
            'ten' => $ten[array_rand($ten)],
            'dob_day' => $birthDay,
            'dob_month' => $birthMonth,
            'dob_year' => $birthYear,
            'is_female' => rand(0, 1) == 1,
            'ethnicity_id' => $ethnicities->random()->id,
            'religion_id' => $religions->random()->id,
            'identity_number' => 'TEST' . str_pad($number, 9, '0', STR_PAD_LEFT),
            'phone' => '0' . rand(100000000, 999999999),
            'addressline' => "Địa chỉ test {$number}",
            'old_province_id' => $ward->old_province_id,
            'old_district_id' => $ward->old_district_id,
            'old_ward_id' => $ward->id,
            'new_province_id' => $ward->new_province_id,
            'new_district_id' => $ward->new_district_id,
            'new_ward_id' => $ward->id,
            'village_id' => $ward->village_id ?? null,
            'residence_type_id' => $residenceTypes->random()->id,
            'residence_status_id' => $residenceStatuses->random()->id,
            'academic_year' => \App\Models\SurveyForm::currentAcademicYear(),
        ]);
    }
    
    private function createPersonEducation($person, $academicYear, $educationBlocks, $schools)
    {
        // Một số người sẽ có khối mầm non
        $preschoolBlocks = $educationBlocks->filter(function($block) {
            return in_array($block->name, [
                '3-12 tháng', '13-24 tháng', '25-36 tháng',
                '3-4 tuổi', '4-5 tuổi', '5-6 tuổi'
            ]);
        });
        
        $regularBlocks = $educationBlocks->filter(function($block) {
            return !in_array($block->name, [
                '3-12 tháng', '13-24 tháng', '25-36 tháng',
                '3-4 tuổi', '4-5 tuổi', '5-6 tuổi'
            ]);
        });
        
        // 30% cơ hội có khối mầm non
        $usePreschoolBlock = rand(1, 100) <= 30 && $preschoolBlocks->isNotEmpty();
        
        $selectedBlock = $usePreschoolBlock ? $preschoolBlocks->random() : $regularBlocks->random();
        
        return PersonEducation::create([
            'person_id' => $person->id,
            'education_block_id' => $selectedBlock->id,
            'class_label' => $usePreschoolBlock ? 'Mầm non' : 'Lớp ' . rand(1, 12),
            'school_id' => $schools->random()->id,
            'academic_year' => $academicYear,
        ]);
    }
    
    private function calculateAgeInAcademicYear($person, string $academicYear): float
    {
        if (!$person->dob_year || !$person->dob_month || !$person->dob_day) {
            return 0;
        }

        $startYear = (int) explode('-', $academicYear)[0];
        $birthDate = Carbon::createFromDate($person->dob_year, $person->dob_month, $person->dob_day);
        $academicStart = Carbon::createFromDate($startYear, 9, 1);
        
        return $birthDate->diffInYears($academicStart, true);
    }
    
    private function isPreschoolBlockName(string $blockName): bool
    {
        $preschoolPatterns = [
            '3-12 tháng', '13-24 tháng', '25-36 tháng',
            '3-4 tuổi', '4-5 tuổi', '5-6 tuổi',
            'mầm non', 'mam non', 'preschool'
        ];
        
        foreach ($preschoolPatterns as $pattern) {
            if (str_contains($blockName, $pattern)) {
                return true;
            }
        }
        
        return false;
    }
}