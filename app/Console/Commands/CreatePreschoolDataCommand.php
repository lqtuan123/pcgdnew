<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PersonEducation;
use App\Models\PersonMamnon;
use App\Models\SurveyMamnon;
use App\Models\SurveyFormDetail;
use Carbon\Carbon;

class CreatePreschoolDataCommand extends Command
{
    protected $signature = 'preschool:create-data {academic_year?}';
    protected $description = 'Tạo dữ liệu mầm non cho học sinh đã có trong hệ thống';

    public function handle()
    {
        $academicYear = $this->argument('academic_year') ?? \App\Models\SurveyForm::currentAcademicYear();
        
        $this->info("Đang tạo dữ liệu mầm non cho năm học: {$academicYear}");
        
        // Lấy tất cả PersonEducation trong năm học
        $allEducations = PersonEducation::with(['person', 'block'])
            ->where('academic_year', $academicYear)
            ->get();

        $this->info("Tìm thấy {$allEducations->count()} học sinh trong năm học");

        $created = 0;
        $skipped = 0;

        foreach ($allEducations as $education) {
            $person = $education->person;
            $age = $this->calculateAgeInAcademicYear($person, $academicYear);
            
            $this->line("Học sinh: {$person->full_name}, Khối: {$education->block->name}, Tuổi: {$age}");
            
            // Kiểm tra tuổi từ 1-5 tuổi HOẶC có khối mầm non
            $isPreschoolAge = $age >= 1 && $age <= 5;
            $isPreschoolBlock = $this->isPreschoolBlockName(strtolower(trim($education->block->name)));
            
            if ($isPreschoolAge || $isPreschoolBlock) {
                // Kiểm tra đã có dữ liệu mầm non chưa
                $existingSurveyMamnon = SurveyMamnon::where('person_id', $person->id)
                    ->where('academic_year', $academicYear)
                    ->first();
                
                if (!$existingSurveyMamnon) {
                    // Tìm SurveyFormDetail
                    $surveyFormDetail = SurveyFormDetail::where('person_id', $person->id)
                        ->whereHas('surveyForm', function($q) use ($academicYear) {
                            $q->where('academic_year', $academicYear);
                        })
                        ->first();
                    
                    if ($surveyFormDetail) {
                        // Tạo PersonMamnon
                        $personMamnon = PersonMamnon::updateOrCreate(
                            ['person_id' => $person->id],
                            [
                                'hoc_ban_tru' => false,
                                'hoc_du_ngay_theo_quy_dinh' => false,
                                'chuyen_can' => false,
                                'theo_doi_bd_can_nang' => false,
                                'sdd_nhe_can' => false,
                                'theo_doi_bd_chieu_cao' => false,
                                'sdd_thap_coi' => false,
                                'dan_toc_chuan_bi_tv' => false,
                                'hoan_thanh_chuong_trinh_gdmn' => false,
                            ]
                        );

                        // Tạo SurveyMamnon
                        SurveyMamnon::create([
                            'person_id' => $person->id,
                            'person_mamnon_id' => $personMamnon->id,
                            'person_education_id' => $education->id,
                            'survey_form_detail_id' => $surveyFormDetail->id,
                            'so_phieu' => $surveyFormDetail->surveyForm->so_phieu,
                            'academic_year' => $academicYear,
                            'editor_id' => 1, // System
                        ]);
                        
                        $created++;
                        $this->info("✓ Đã tạo dữ liệu mầm non cho: {$person->full_name}");
                    } else {
                        $this->warn("⚠ Không tìm thấy SurveyFormDetail cho: {$person->full_name}");
                    }
                } else {
                    $skipped++;
                    $this->line("→ Đã có dữ liệu mầm non: {$person->full_name}");
                }
            } else {
                $this->line("→ Tuổi không phù hợp ({$age}): {$person->full_name}");
            }
        }

        $this->info("\nKết quả:");
        $this->info("- Đã tạo: {$created} học sinh");
        $this->info("- Đã có sẵn: {$skipped} học sinh");
        $this->info("- Tổng kiểm tra: {$allEducations->count()} học sinh");
    }

    private function calculateAgeInAcademicYear($person, string $academicYear): float
    {
        if (!$person->dob_year || !$person->dob_month || !$person->dob_day) {
            return 0;
        }

        // Lấy năm bắt đầu của năm học (VD: "2023-2024" → 2023)
        $startYear = (int) explode('-', $academicYear)[0];
        
        // Tính tuổi vào tháng 9 (tháng bắt đầu năm học)
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
