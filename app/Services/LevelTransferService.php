<?php

namespace App\Services;

use App\Models\PersonEducation;
use App\Models\PersonMamnon;
use App\Models\SurveyFormDetail;
use App\Models\SurveyForm;
use App\Models\School;
use App\Models\GeoUnit;
use App\Models\EducationBlock;
use App\Models\GraduationLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LevelTransferService
{
    /**
     * Thực hiện lên lớp và lên cấp cho tất cả các cấp
     */
    public function executeLevelTransfer(array $data): array
    {
        // Tăng thời gian thực thi và bộ nhớ cho quá trình lên lớp
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '1024M');
        
        $result = [
            'total_processed' => 0,
            'total_updated' => 0,
            'total_created' => 0,
            'errors' => [],
            'details' => []
        ];

        DB::beginTransaction();
        try {
            // BƯỚC 1: Tạo bản ghi mới cho TẤT CẢ SurveyFormDetail và PersonEducation của năm học mới
            $copyResult = $this->copyAllRecordsToNewAcademicYear($data);
            $result['total_created'] += $copyResult['total_created'];
            $result['errors'] = array_merge($result['errors'], $copyResult['errors']);
            
            // BƯỚC 2: Thực hiện logic lên lớp, lên cấp và tốt nghiệp
            if (isset($data['selected_schools']) && !empty($data['selected_schools'])) {
                // Xử lý theo trường cụ thể được chọn
                $transferResult = $this->processSelectedSchoolsTransfer($data);
                $result['total_processed'] += $transferResult['processed'];
                $result['total_updated'] += $transferResult['updated'];
                $result['total_created'] += $transferResult['created'];
                $result['details'][] = $transferResult;
            } else {
                // Fallback: lấy danh sách trường trong xã/phường đã chọn
                $schoolIds = $this->getSchoolsInWard($data);
                $transferResult = $this->processAllLevelsTransfer($data, $schoolIds);
                $result['total_processed'] += $transferResult['processed'];
                $result['total_updated'] += $transferResult['updated'];
                $result['total_created'] += $transferResult['created'];
                $result['details'][] = $transferResult;
            }
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $result['errors'][] = [
                'error' => $e->getMessage()
            ];
        }

        return $result;
    }

    /**
     * Copy tất cả bản ghi SurveyFormDetail và PersonEducation sang năm học mới
     */
    private function copyAllRecordsToNewAcademicYear(array $data): array
    {
        $academicYearFrom = $data['academic_year_from'];
        $academicYearTo = $data['academic_year_to'];
        
        $result = [
            'total_created' => 0,
            'errors' => []
        ];

        try {
            // Lấy SurveyFormDetail của năm cũ, lọc theo địa bàn
            $query = SurveyFormDetail::where('academic_year', $academicYearFrom)
                ->where('is_deleted', false)
                ->with(['education', 'person']);
            
            // Lọc theo địa bàn đã chọn
            if (!empty($data['ward_id'])) {
                // Lọc theo xã
                $query->whereHas('person', function ($q) use ($data) {
                    $q->where('new_ward_id', $data['ward_id'])
                      ->orWhere('old_ward_id', $data['ward_id']);
                });
                
                // Nếu có chọn thôn thì lọc thêm theo thôn
                if (!empty($data['village_id'])) {
                    $query->whereHas('person', function ($q) use ($data) {
                        $q->where('village_id', $data['village_id']);
                    });
                }
            } elseif (!empty($data['province_id'])) {
                // Lọc theo tỉnh nếu không có xã
                $query->whereHas('person', function ($q) use ($data) {
                    $q->where('new_province_id', $data['province_id'])
                      ->orWhere('old_province_id', $data['province_id']);
                });
            }
            
            $surveyFormDetails = $query->get();

            foreach ($surveyFormDetails as $detail) {
                try {
                    // Tạo SurveyFormDetail mới cho năm học mới
                    $newDetail = SurveyFormDetail::firstOrCreate([
                        'survey_form_id' => $detail->survey_form_id,
                        'person_id' => $detail->person_id,
                        'academic_year' => $academicYearTo
                    ], [
                        'residence_id' => $detail->residence_id,
                        'person_education_id' => null, // Sẽ được cập nhật sau
                        'literacy_id' => $detail->literacy_id,
                        'person_disability_id' => $detail->person_disability_id,
                        'person_special_circumstance_id' => $detail->person_special_circumstance_id,
                        'person_contact_id' => $detail->person_contact_id,
                        'person_mamnon_id' => $detail->person_mamnon_id,
                        'note' => $detail->note,
                        'is_deleted' => false
                    ]);

                    // Debug: Kiểm tra có education không
                    if (!$detail->education) {
                        $result['errors'][] = [
                            'person_id' => $detail->person_id,
                            'survey_form_id' => $detail->survey_form_id,
                            'error' => 'SurveyFormDetail không có PersonEducation (person_education_id: ' . $detail->person_education_id . ')'
                        ];
                        continue;
                    }

                    // Nếu có PersonEducation, tạo bản ghi mới
                    $newEducation = PersonEducation::firstOrCreate([
                        'person_id' => $detail->person_id,
                        'academic_year' => $academicYearTo
                    ], [
                        'class_label' => $detail->education->class_label,
                        'hoc_hai_buoi' => $detail->education->hoc_hai_buoi,
                        'hoc_9_10_buoi_tuan' => $detail->education->hoc_9_10_buoi_tuan,
                        'is_repeat_grade' => false, // Reset cho năm học mới
                        'education_block_id' => $detail->education->education_block_id,
                        'studying_district_id' => $detail->education->studying_district_id,
                        'school_id' => $detail->education->school_id,
                        'school_name_custom' => $detail->education->school_name_custom,
                        'studying_district_custom' => $detail->education->studying_district_custom,
                        'graduation_level_id' => $detail->education->graduation_level_id,
                        'is_bo_tuc' => $detail->education->is_bo_tuc,
                        'graduation_year' => $detail->education->graduation_year,
                        'trai_tuyen' => $detail->education->trai_tuyen,
                        'vocational_level_id' => $detail->education->vocational_level_id,
                        'vocational_grad_year' => $detail->education->vocational_grad_year,
                        'graduation_certificate_no' => $detail->education->graduation_certificate_no,
                        'hoc_xong_lop' => $detail->education->hoc_xong_lop,
                        'hoc_xong_nam' => $detail->education->hoc_xong_nam,
                        'bo_hoc_lop' => $detail->education->bo_hoc_lop,
                        'bo_hoc_nam' => $detail->education->bo_hoc_nam,
                        'note' => ($detail->education->note ? $detail->education->note . '; ' : '') . 'auto_copy_' . now()->format('Y-m-d_H-i-s')
                    ]);

                    // Debug: Kiểm tra PersonEducation có được tạo mới không
                    $wasRecentlyCreated = $newEducation->wasRecentlyCreated;
                    if ($wasRecentlyCreated) {
                        $result['total_created']++;
                    } else {
                        $result['errors'][] = [
                            'person_id' => $detail->person_id,
                            'survey_form_id' => $detail->survey_form_id,
                            'error' => 'PersonEducation đã tồn tại cho person_id: ' . $detail->person_id . ', academic_year: ' . $academicYearTo
                        ];
                    }

                    // Cập nhật person_education_id trong SurveyFormDetail mới
                    $newDetail->update(['person_education_id' => $newEducation->id]);

                } catch (\Exception $e) {
                    $result['errors'][] = [
                        'person_id' => $detail->person_id,
                        'survey_form_id' => $detail->survey_form_id,
                        'error' => $e->getMessage()
                    ];
                }
            }

        } catch (\Exception $e) {
            $result['errors'][] = [
                'error' => $e->getMessage()
            ];
        }

        return $result;
    }

    /**
     * Lấy danh sách trường trong xã/phường đã chọn
     */
    private function getSchoolsInWard(array $data): array
    {
        $query = School::active();
        
        // Lọc theo địa bàn đã chọn sử dụng các trường địa lý cũ và mới
        if (!empty($data['ward_id'])) {
            $query->where(function($q) use ($data) {
                $q->where('old_ward_id', $data['ward_id'])
                  ->orWhere('new_ward_id', $data['ward_id'])
                  ->orWhere('village_id', $data['ward_id']);
            });
        } elseif (!empty($data['district_id'])) {
            $query->where(function($q) use ($data) {
                $q->where('old_district_id', $data['district_id']);
            });
        } elseif (!empty($data['province_id'])) {
            $query->where(function($q) use ($data) {
                $q->where('old_province_id', $data['province_id'])
                  ->orWhere('new_province_id', $data['province_id']);
            });
        }
        
        $schoolIds = $query->pluck('id')->toArray();
        
        
        return $schoolIds;
    }

    /**
     * Xử lý lên lớp cho các trường được chọn
     */
    private function processSelectedSchoolsTransfer(array $data): array
    {
        $academicYearFrom = $data['academic_year_from'];
        $academicYearTo = $data['academic_year_to'];
        $keepClassName = $data['keep_class_name'] ?? false;
        $keepTransferObjects = $data['keep_transfer_objects'] ?? false;
        $selectedSchools = $data['selected_schools'];

        $result = [
            'level' => 'selected_schools',
            'processed' => 0,
            'updated' => 0,
            'created' => 0,
            'errors' => []
        ];

        foreach ($selectedSchools as $schoolInfo) {
            $schoolId = $schoolInfo['school_id'];
            $level = $schoolInfo['level'];
            
            // Tạo transfer data cho method xử lý cấp học
            $transferData = [
                'select_specific_school' => true,
                'old_school_ids' => [$schoolId],
                'new_school_id' => null
            ];
            
            // Xử lý theo cấp học
            $levelResult = [];
            switch ($level) {
                case 'preschool':
                    $levelResult = $this->processPreschoolSchool($academicYearFrom, $academicYearTo, $transferData, $keepClassName, $keepTransferObjects);
                    break;
                case 'primary':
                    $levelResult = $this->processPrimarySchool($academicYearFrom, $academicYearTo, $transferData, $keepClassName, $keepTransferObjects);
                    break;
                case 'junior':
                    $levelResult = $this->processJuniorSchool($academicYearFrom, $academicYearTo, $transferData, $keepClassName, $keepTransferObjects);
                    break;
                case 'high':
                    $levelResult = $this->processHighSchool($academicYearFrom, $academicYearTo, $transferData, $keepClassName, $keepTransferObjects);
                    break;
            }
            
            // Cộng dồn kết quả
            $result['processed'] += $levelResult['processed'];
            $result['updated'] += $levelResult['updated'];
            $result['created'] += $levelResult['created'];
            $result['errors'] = array_merge($result['errors'], $levelResult['errors']);
        }

        return $result;
    }

    /**
     * Xử lý lên lớp cho tất cả các cấp trong xã/phường/thôn
     */
    private function processAllLevelsTransfer(array $data, array $schoolIds): array
    {
        $academicYearFrom = $data['academic_year_from'];
        $academicYearTo = $data['academic_year_to'];
        $keepClassName = $data['keep_class_name'] ?? false;
        $keepTransferObjects = $data['keep_transfer_objects'] ?? false;

        $result = [
            'level' => 'all_levels',
            'processed' => 0,
            'updated' => 0,
            'created' => 0,
            'errors' => []
        ];

        // Lấy tất cả học sinh trong các trường đã chọn từ năm học MỚI (đã được copy)
        // CHỈ lấy học sinh bình thường (không phải học sinh trái tuyến)
        $query = SurveyFormDetail::whereNotNull('person_education_id')
            ->where('academic_year', $academicYearTo) // Lấy từ năm học mới
            ->whereHas('education', function ($q) use ($schoolIds) {
                $q->whereIn('school_id', $schoolIds)
                  ->where('trai_tuyen', false); // Chỉ lấy học sinh bình thường
            });

        // Lọc theo thôn/xóm nếu có
        if (!empty($data['village_id'])) {
            $query->whereHas('person', function ($q) use ($data) {
                $q->where('village_id', $data['village_id']);
            });
        }

        // Kiểm tra "giữ đối tượng chuyển đi"
        if (!$keepTransferObjects) {
            $query->whereNull('person_special_circumstance_id');
        }

        $students = $query->with(['education', 'education.block', 'person'])->get();
        
        foreach ($students as $student) {
            try {
                $education = $student->education;
                $block = $education->block;
                
                // Kiểm tra xem có phải khối cần chuyển cấp không
                $isGraduationLevel = $this->isGraduationLevel($block ? $block->name : null);
                
                if ($isGraduationLevel) {
                    // XỬ LÝ LÊN CẤP/TỐT NGHIỆP
                    $levelType = $this->getLevelTypeFromBlock($block);
                    
                    if ($levelType === 'high') {
                        // Xử lý đặc biệt cho lớp 12 - tốt nghiệp
                        $this->processGraduation($education, $academicYearTo, $academicYearFrom);
                        $result['processed']++;
                        $result['updated']++; // Cập nhật bản ghi đã có
                    } else {
                        // Xử lý lên cấp bình thường
                        $nextSchoolId = $this->getNextSchoolIdFromMapping($levelType, $data['next_school_mapping'] ?? []);
                        
                        if ($nextSchoolId) {
                            $this->processLevelUp($education, $nextSchoolId, $academicYearTo, $levelType, $academicYearFrom);
                            $result['processed']++;
                            $result['updated']++; // Cập nhật bản ghi đã có
                        }
                    }
                } else {
                    // XỬ LÝ LÊN LỚP: Tăng khối trong cùng cấp
                    $this->processLevelTransfer($education, $academicYearTo, $keepClassName);
                    $result['processed']++;
                    $result['updated']++; // Cập nhật bản ghi đã có
                }
                
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'person_id' => $student->person_id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $result;
    }

    /**
     * Kiểm tra xem có phải khối tốt nghiệp không (5-6 tuổi, lớp 5, lớp 9, lớp 12)
     */
    private function isGraduationLevel(?string $blockName): bool
    {
        if (!$blockName) {
            return false;
        }
        
        $graduationLevels = ['5-6 tuổi', '5', '9', '12'];
        return in_array($blockName, $graduationLevels);
    }

    /**
     * Xử lý lên lớp cho trường mầm non
     */
    private function processPreschoolSchool(string $academicYearFrom, string $academicYearTo, array $transfer, bool $keepClassName, bool $keepTransferObjects): array
    {
        $result = [
            'level' => 'preschool_school',
            'processed' => 0,
            'updated' => 0,
            'created' => 0,
            'errors' => []
        ];

        // Lấy danh sách học sinh mầm non
        $query = SurveyFormDetail::whereNotNull('person_education_id')
            ->whereHas('surveyForm', function ($q) use ($academicYearFrom) {
                $q->where('academic_year', $academicYearFrom);
            })
            ->whereHas('education', function ($q) {
                $q->whereHas('school', function ($sq) {
                    $sq->whereHas('educationalLevel', function($el) {
                        $el->where('name', 'Mầm non');
                    });
                });
            });

        // Kiểm tra "giữ đối tượng chuyển đi"
        if (!$keepTransferObjects) {
            $query->whereNull('person_special_circumstance_id');
        }

        // Nếu chọn trường cụ thể
        if ($transfer['select_specific_school'] && !empty($transfer['old_school_ids'])) {
            $query->whereHas('education', function ($q) use ($transfer) {
                $q->whereIn('school_id', $transfer['old_school_ids']);
            });
        }

        $preschoolStudents = $query->with(['education', 'education.block'])->get();

        foreach ($preschoolStudents as $student) {
            try {
                $education = $student->education;
                $block = $education->block;
                
                // Kiểm tra xem có phải khối cần chuyển cấp không
                $isGraduationLevel = $this->isGraduationLevel($block ? $block->name : null);
                
                if ($isGraduationLevel) {
                    // Không lên lớp cho các khối tốt nghiệp
                    continue;
                }
                
                // Nếu đã có thì cập nhật, chưa có thì tạo mới
                $existingEducation = PersonEducation::where('person_id', $education->person_id)
                    ->where('academic_year', $academicYearTo)
                    ->first();
                
                if ($existingEducation) {
                    $newEducation = $this->updatePersonEducationForLevelUp($existingEducation, $education, $academicYearTo, $keepClassName);
                    $result['updated']++;
                } else {
                    $newEducation = $this->createPersonEducationForLevelUp($education, $academicYearTo, $keepClassName);
                    $result['created']++;
                }
                
                // Tạo hoặc cập nhật SurveyFormDetail cho năm học mới
                $this->createOrUpdateSurveyFormDetailForNewYear(
                    $student,
                    $academicYearTo,
                    $newEducation,
                    null
                );
                
                $result['processed']++;
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'person_id' => $student->person_id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $result;
    }

    /**
     * Xử lý tiểu học (lên lớp trong cùng cấp)
     */
    private function processPrimarySchool(string $academicYearFrom, string $academicYearTo, array $transfer, bool $keepClassName, bool $keepTransferObjects): array
    {
        $result = [
            'level' => 'primary_school',
            'processed' => 0,
            'updated' => 0,
            'created' => 0,
            'errors' => []
        ];

        // Lấy danh sách học sinh tiểu học (chỉ học sinh bình thường)
        $query = SurveyFormDetail::whereNotNull('person_education_id')
            ->whereHas('surveyForm', function ($q) use ($academicYearFrom) {
                $q->where('academic_year', $academicYearFrom);
            })
            ->whereHas('education', function ($q) {
                $q->where('trai_tuyen', false) // Chỉ lấy học sinh bình thường
                  ->whereHas('school', function ($sq) {
                    $sq->whereHas('educationalLevel', function($el) {
                        $el->whereIn('name', ['Tiểu học + THCS', 'Tiểu học + THCS + THPT', 'Tiểu học']);
                    });
                });
            });

        // Kiểm tra "giữ đối tượng chuyển đi"
        if (!$keepTransferObjects) {
            $query->whereNull('person_special_circumstance_id');
        }

        // Nếu chọn trường cụ thể
        if ($transfer['select_specific_school'] && !empty($transfer['old_school_ids'])) {
            $query->whereHas('education', function ($q) use ($transfer) {
                $q->whereIn('school_id', $transfer['old_school_ids']);
            });
        }

        $primaryStudents = $query->with(['education', 'education.block'])->get();

        foreach ($primaryStudents as $student) {
            try {
                $education = $student->education;
                $block = $education->block;
                
                // Kiểm tra xem có phải khối cần chuyển cấp không
                $isGraduationLevel = $this->isGraduationLevel($block ? $block->name : null);
                
                if ($isGraduationLevel) {
                    // Không lên lớp cho các khối tốt nghiệp
                    continue;
                }
                
                // Nếu đã có thì cập nhật, chưa có thì tạo mới
                $existingEducation = PersonEducation::where('person_id', $education->person_id)
                    ->where('academic_year', $academicYearTo)
                    ->first();
                
                if ($existingEducation) {
                    $newEducation = $this->updatePersonEducationForLevelUp($existingEducation, $education, $academicYearTo, $keepClassName);
                    $result['updated']++;
                } else {
                    $newEducation = $this->createPersonEducationForLevelUp($education, $academicYearTo, $keepClassName);
                    $result['created']++;
                }
                
                // Tạo hoặc cập nhật SurveyFormDetail cho năm học mới
                $this->createOrUpdateSurveyFormDetailForNewYear(
                    $student,
                    $academicYearTo,
                    $newEducation,
                    null
                );
                
                $result['processed']++;
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'person_id' => $student->person_id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $result;
    }

    /**
     * Xử lý trung học cơ sở (lên lớp trong cùng cấp)
     */
    private function processJuniorSchool(string $academicYearFrom, string $academicYearTo, array $transfer, bool $keepClassName, bool $keepTransferObjects): array
    {
        $result = [
            'level' => 'junior_school',
            'processed' => 0,
            'updated' => 0,
            'created' => 0,
            'errors' => []
        ];

        // Lấy danh sách học sinh trung học cơ sở
        $query = SurveyFormDetail::whereNotNull('person_education_id')
            ->whereHas('surveyForm', function ($q) use ($academicYearFrom) {
                $q->where('academic_year', $academicYearFrom);
            })
            ->whereHas('education', function ($q) {
                $q->whereHas('school', function ($sq) {
                    $sq->whereHas('educationalLevel', function($el) {
                        $el->whereIn('name', ['THCS', 'Trung học cơ sở', 'Tiểu học + THCS + THPT', 'THCS + THPT', 'Tiểu học + THCS']);
                    });
                });
            });

        // Kiểm tra "giữ đối tượng chuyển đi"
        if (!$keepTransferObjects) {
            $query->whereNull('person_special_circumstance_id');
        }

        // Nếu chọn trường cụ thể
        if ($transfer['select_specific_school'] && !empty($transfer['old_school_ids'])) {
            $query->whereHas('education', function ($q) use ($transfer) {
                $q->whereIn('school_id', $transfer['old_school_ids']);
            });
        }

        $juniorStudents = $query->with(['education', 'education.block'])->get();

        foreach ($juniorStudents as $student) {
            try {
                $education = $student->education;
                $block = $education->block;
                
                // Kiểm tra xem có phải khối cần chuyển cấp không
                $isGraduationLevel = $this->isGraduationLevel($block ? $block->name : null);
                
                if ($isGraduationLevel) {
                    // Không lên lớp cho các khối tốt nghiệp
                    continue;
                }
                
                // Nếu đã có thì cập nhật, chưa có thì tạo mới
                $existingEducation = PersonEducation::where('person_id', $education->person_id)
                    ->where('academic_year', $academicYearTo)
                    ->first();
                
                if ($existingEducation) {
                    $newEducation = $this->updatePersonEducationForLevelUp($existingEducation, $education, $academicYearTo, $keepClassName);
                    $result['updated']++;
                } else {
                    $newEducation = $this->createPersonEducationForLevelUp($education, $academicYearTo, $keepClassName);
                    $result['created']++;
                }
                
                // Tạo hoặc cập nhật SurveyFormDetail cho năm học mới
                $this->createOrUpdateSurveyFormDetailForNewYear(
                    $student,
                    $academicYearTo,
                    $newEducation,
                    null
                );
                
                $result['processed']++;
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'person_id' => $student->person_id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $result;
    }

    /**
     * Xử lý trung học phổ thông (lên lớp trong cùng cấp)
     */
    private function processHighSchool(string $academicYearFrom, string $academicYearTo, array $transfer, bool $keepClassName, bool $keepTransferObjects): array
    {
        $result = [
            'level' => 'high_school',
            'processed' => 0,
            'updated' => 0,
            'created' => 0,
            'errors' => []
        ];

        // Lấy danh sách học sinh trung học phổ thông
        $query = SurveyFormDetail::whereNotNull('person_education_id')
            ->whereHas('surveyForm', function ($q) use ($academicYearFrom) {
                $q->where('academic_year', $academicYearFrom);
            })
            ->whereHas('education', function ($q) {
                $q->whereHas('school', function ($sq) {
                    $sq->whereHas('educationalLevel', function($el) {
                        $el->whereIn('name', ['THCS + THPT', 'Tiểu học + THCS + THPT', 'THPT', 'Trung học phổ thông']);
                    });
                });
            });

        // Kiểm tra "giữ đối tượng chuyển đi"
        if (!$keepTransferObjects) {
            $query->whereNull('person_special_circumstance_id');
        }

        // Nếu chọn trường cụ thể
        if ($transfer['select_specific_school'] && !empty($transfer['old_school_ids'])) {
            $query->whereHas('education', function ($q) use ($transfer) {
                $q->whereIn('school_id', $transfer['old_school_ids']);
            });
        }

        $highStudents = $query->with(['education', 'education.block'])->get();

        foreach ($highStudents as $student) {
            try {
                $education = $student->education;
                $block = $education->block;
                
                // Kiểm tra xem có phải khối cần chuyển cấp không
                $isGraduationLevel = $this->isGraduationLevel($block ? $block->name : null);
                
                if ($isGraduationLevel) {
                    // Không lên lớp cho các khối tốt nghiệp
                    continue;
                }
                
                // Nếu đã có bản ghi năm học mới thì bỏ qua (idempotent)
                if ($this->hasEducationInYear($education->person_id, $academicYearTo)) {
                    $result['processed']++;
                    continue;
                }

                // Tạo PersonEducation mới cho lên lớp
                    $newEducation = $this->createPersonEducationForLevelUp($education, $academicYearTo, $keepClassName);
                    $result['created']++;
                
                // Tạo hoặc cập nhật SurveyFormDetail cho năm học mới
                $this->createOrUpdateSurveyFormDetailForNewYear(
                    $student,
                    $academicYearTo,
                    $newEducation,
                    null
                );
                
                $result['processed']++;
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'person_id' => $student->person_id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $result;
    }


    /**
     * Xử lý mầm non lên tiểu học
     */
    private function processPreschoolToPrimary(string $academicYearFrom, string $academicYearTo, array $transfer, bool $keepClassName, bool $keepTransferObjects): array
    {
        $result = [
            'level' => 'preschool_to_primary',
            'processed' => 0,
            'updated' => 0,
            'created' => 0,
            'errors' => []
        ];

        // Lấy danh sách học sinh mầm non có hoàn thành chương trình GDMN (chỉ học sinh bình thường)
        $query = SurveyFormDetail::whereNotNull('person_mamnon_id')
            ->where('academic_year', $academicYearFrom)
            ->whereHas('personMamnon', function ($q) {
                $q->where('hoan_thanh_chuong_trinh_gdmn', true); // Chỉ lấy HS đã hoàn thành GDMN
            })
            ->whereHas('education', function ($q) {
                $q->where('trai_tuyen', false) // Chỉ lấy học sinh bình thường
                  ->whereHas('school', function ($sq) {
                    $sq->where('educational_level_id', 1); // Mầm non
                });
            });

        // Kiểm tra "giữ đối tượng chuyển đi"
        if (!$keepTransferObjects) {
            $query->whereNull('person_special_circumstance_id'); // Không lấy HS có hoàn cảnh đặc biệt
        }

        // Nếu chọn trường cụ thể
        if ($transfer['select_specific_school'] && !empty($transfer['old_school_ids'])) {
            $query->whereHas('education', function ($q) use ($transfer) {
                $q->whereIn('school_id', $transfer['old_school_ids']);
            });
        }

        $preschoolStudents = $query->with(['education', 'personMamnon'])->get();

        foreach ($preschoolStudents as $student) {
            try {
                $education = $student->education;
                $personMamnon = $student->personMamnon;
                
                // Kiểm tra đã lên lớp chưa
                $existingEducation = PersonEducation::where('person_id', $education->person_id)
                    ->where('academic_year', $academicYearTo)
                    ->first();
                
                $existingPersonMamnon = PersonMamnon::where('person_id', $personMamnon->person_id)
                    ->whereHas('surveyFormDetails', function($q) use ($academicYearTo) {
                        $q->whereHas('surveyForm', function($sq) use ($academicYearTo) {
                            $sq->where('academic_year', $academicYearTo);
                        });
                    })
                    ->first();
                
                // Kiểm tra xem có phải dữ liệu từ lần lên lớp trước không
                if ($existingEducation && $this->isFromPreviousTransfer($existingEducation, $academicYearFrom)) {
                    // Bỏ qua - đã xử lý rồi từ lần lên lớp trước
                    $result['processed']++;
                    continue;
                }
                
                // Tạo hoặc cập nhật PersonEducation
                if ($existingEducation) {
                    $newEducation = $this->updatePersonEducation($existingEducation, $education, $academicYearTo, $transfer['new_school_id'] ?? null, $keepClassName, 'primary');
                    $result['updated']++;
                } else {
                    $newEducation = $this->createNewPersonEducation($education, $academicYearTo, $transfer['new_school_id'] ?? null, $keepClassName, 'primary');
                    $result['created']++;
                }
                
                // Kiểm tra có phải chuyển cấp không (5-6 tuổi -> lớp 1)
                $isLevelTransfer = $this->isLevelTransfer($education->education_block_id, 'preschool_to_primary');
                
                if ($isLevelTransfer) {
                    // Chuyển cấp: không tạo PersonMamnon nữa vì đã hết là HS mầm non
                    $newPersonMamnon = null;
                } else {
                    // Không chuyển cấp: tạo hoặc cập nhật PersonMamnon
                    if ($existingPersonMamnon) {
                        $newPersonMamnon = $this->updatePersonMamnon($existingPersonMamnon, $personMamnon, $academicYearTo);
                    } else {
                        $newPersonMamnon = $this->createNewPersonMamnon($personMamnon, $academicYearTo);
                    }
                }
                
                // Cập nhật SurveyFormDetail với các ID mới
                $this->updateSurveyFormDetailForNewYear(
                    $student,
                    $academicYearTo,
                    $newEducation,
                    $newPersonMamnon
                );
                
                $result['processed']++;
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'person_id' => $student->person_id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $result;
    }

    /**
     * Xử lý tiểu học lên trung học cơ sở
     */
    private function processPrimaryToJunior(string $academicYearFrom, string $academicYearTo, array $transfer, bool $keepClassName, bool $keepTransferObjects): array
    {
        $result = [
            'level' => 'primary_to_junior',
            'processed' => 0,
            'updated' => 0,
            'created' => 0,
            'errors' => []
        ];

        // Lấy danh sách học sinh tiểu học từ lớp 1-5 (chỉ học sinh bình thường)
        $query = SurveyFormDetail::whereNotNull('person_education_id')
            ->where('academic_year', $academicYearFrom)
            ->whereHas('education', function ($q) {
                $q->where('trai_tuyen', false) // Chỉ lấy học sinh bình thường
                  ->whereHas('school', function ($sq) {
                    $sq->where('educational_level_id', 2); // Tiểu học
                })
                ->whereHas('block', function ($bq) {
                    $bq->whereIn('name', ['1', '2', '3', '4', '5']); // Lớp 1-5
                });
            });

        // Kiểm tra "giữ đối tượng chuyển đi"
        if (!$keepTransferObjects) {
            $query->whereNull('person_special_circumstance_id'); // Không lấy HS có hoàn cảnh đặc biệt
        }

        // Nếu chọn trường cụ thể
        if ($transfer['select_specific_school'] && !empty($transfer['old_school_ids'])) {
            $query->whereHas('education', function ($q) use ($transfer) {
                $q->whereIn('school_id', $transfer['old_school_ids']);
            });
        }

        $primaryStudents = $query->with(['education'])->get();

        foreach ($primaryStudents as $student) {
            try {
                $education = $student->education;
                
                // Kiểm tra đã lên lớp chưa
                $existingEducation = PersonEducation::where('person_id', $education->person_id)
                    ->where('academic_year', $academicYearTo)
                    ->first();
                
                // Kiểm tra xem có phải dữ liệu từ lần lên lớp trước không
                if ($existingEducation && $this->isFromPreviousTransfer($existingEducation, $academicYearFrom)) {
                    // Bỏ qua - đã xử lý rồi từ lần lên lớp trước
                    $result['processed']++;
                    continue;
                }
                
                // Tạo hoặc cập nhật PersonEducation
                if ($existingEducation) {
                    $newEducation = $this->updatePersonEducation($existingEducation, $education, $academicYearTo, $transfer['new_school_id'] ?? null, $keepClassName, 'junior');
                    $result['updated']++;
                } else {
                    $newEducation = $this->createNewPersonEducation($education, $academicYearTo, $transfer['new_school_id'] ?? null, $keepClassName, 'junior');
                    $result['created']++;
                }
                
                // Cập nhật SurveyFormDetail với ID mới
                $this->updateSurveyFormDetailForNewYear(
                    $student,
                    $academicYearTo,
                    $newEducation,
                    null // Không có PersonMamnon cho cấp này
                );
                
                $result['processed']++;
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'person_id' => $student->person_id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $result;
    }

    /**
     * Xử lý trung học cơ sở lên trung học phổ thông
     */
    private function processJuniorToHigh(string $academicYearFrom, string $academicYearTo, array $transfer, bool $keepClassName, bool $keepTransferObjects): array
    {
        $result = [
            'level' => 'junior_to_high',
            'processed' => 0,
            'updated' => 0,
            'created' => 0,
            'errors' => []
        ];

        // Lấy danh sách học sinh trung học cơ sở từ lớp 6-9
        $query = SurveyFormDetail::whereNotNull('person_education_id')
            ->whereHas('surveyForm', function ($q) use ($academicYearFrom) {
                $q->where('academic_year', $academicYearFrom);
            })
            ->whereHas('education', function ($q) {
                $q->whereHas('school', function ($sq) {
                    $sq->where('educational_level_id', 3); // Trung học cơ sở
                })
                ->whereHas('block', function ($bq) {
                    $bq->whereIn('name', ['6', '7', '8', '9']); // Lớp 6-9
                });
            });

        // Kiểm tra "giữ đối tượng chuyển đi"
        if (!$keepTransferObjects) {
            $query->whereNull('person_special_circumstance_id'); // Không lấy HS có hoàn cảnh đặc biệt
        }

        // Nếu chọn trường cụ thể
        if ($transfer['select_specific_school'] && !empty($transfer['old_school_ids'])) {
            $query->whereHas('education', function ($q) use ($transfer) {
                $q->whereIn('school_id', $transfer['old_school_ids']);
            });
        }

        $juniorStudents = $query->with(['education'])->get();

        foreach ($juniorStudents as $student) {
            try {
                $education = $student->education;
                
                // Kiểm tra đã lên lớp chưa
                $existingEducation = PersonEducation::where('person_id', $education->person_id)
                    ->where('academic_year', $academicYearTo)
                    ->first();
                
                // Kiểm tra xem có phải dữ liệu từ lần lên lớp trước không
                if ($existingEducation && $this->isFromPreviousTransfer($existingEducation, $academicYearFrom)) {
                    // Bỏ qua - đã xử lý rồi từ lần lên lớp trước
                    $result['processed']++;
                    continue;
                }
                
                // Tạo hoặc cập nhật PersonEducation
                if ($existingEducation) {
                    $newEducation = $this->updatePersonEducation($existingEducation, $education, $academicYearTo, $transfer['new_school_id'] ?? null, $keepClassName, 'high');
                    $result['updated']++;
                } else {
                    $newEducation = $this->createNewPersonEducation($education, $academicYearTo, $transfer['new_school_id'] ?? null, $keepClassName, 'high');
                    $result['created']++;
                }
                
                // Cập nhật SurveyFormDetail với ID mới
                $this->updateSurveyFormDetailForNewYear(
                    $student,
                    $academicYearTo,
                    $newEducation,
                    null // Không có PersonMamnon cho cấp này
                );
                
                $result['processed']++;
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'person_id' => $student->person_id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $result;
    }

    /**
     * Tạo PersonEducation mới cho lên lớp (tăng education_block_id)
     */
    private function createPersonEducationForLevelUp(PersonEducation $oldEducation, string $academicYearTo, bool $keepClassName): ?PersonEducation
    {
        // Tìm khối học tiếp theo (tăng index) - không có levelUpType vì đây là lên lớp bình thường
        $newBlockId = $this->getNextEducationBlockForLevelUp($oldEducation->education_block_id, null);
        
        if (!$newBlockId) {
            return null; // Không tìm thấy khối tiếp theo
        }
        
        $newEducationData = [
            'person_id' => $oldEducation->person_id,
            'academic_year' => $academicYearTo,
            'education_block_id' => $newBlockId,
            'education_grade_id' => $oldEducation->education_grade_id,
            'class_label' => $keepClassName ? $this->incrementClassName($oldEducation->class_label) : null,
            'hoc_hai_buoi' => $oldEducation->hoc_hai_buoi,
            'hoc_9_10_buoi_tuan' => $oldEducation->hoc_9_10_buoi_tuan,
            'is_repeat_grade' => false, // Reset cho năm học mới
            'studying_district_id' => $oldEducation->studying_district_id,
            'school_id' => $oldEducation->school_id, // Giữ nguyên trường
            'school_name_custom' => $oldEducation->school_name_custom,
            'studying_district_custom' => $oldEducation->studying_district_custom,
            'graduation_level_id' => $oldEducation->graduation_level_id,
            'is_bo_tuc' => $oldEducation->is_bo_tuc,
            'graduation_year' => $oldEducation->graduation_year,
            'vocational_level_id' => $oldEducation->vocational_level_id,
            'vocational_grad_year' => $oldEducation->vocational_grad_year,
            'graduation_certificate_no' => $oldEducation->graduation_certificate_no,
            'hoc_xong_lop' => $oldEducation->hoc_xong_lop,
            'hoc_xong_nam' => $oldEducation->hoc_xong_nam,
            'bo_hoc_lop' => $oldEducation->bo_hoc_lop,
            'bo_hoc_nam' => $oldEducation->bo_hoc_nam,
            'note' => ($oldEducation->note ? $oldEducation->note . '; ' : '') . 'auto_transfer_' . now()->format('Y-m-d_H-i-s'),
        ];

        $newEducation = PersonEducation::create($newEducationData);
        
        // Tạo SurveyFormDetail mới cho năm học tiếp theo
        $this->createOrUpdateSurveyFormDetailForNewYear(
            SurveyFormDetail::where('person_id', $oldEducation->person_id)
                ->where('academic_year', $oldEducation->academic_year)
                ->first(),
            $academicYearTo,
            $newEducation,
            null
        );
        
        return $newEducation;
    }

    /**
     * Cập nhật PersonEducation cho lên lớp (tăng education_block_id)
     */
    private function updatePersonEducationForLevelUp(PersonEducation $existingEducation, PersonEducation $oldEducation, string $academicYearTo, bool $keepClassName): PersonEducation
    {
        // Tìm khối học tiếp theo (tăng index) - không có levelUpType vì đây là lên lớp bình thường
        $newBlockId = $this->getNextEducationBlockForLevelUp($oldEducation->education_block_id, null);
        
        if ($newBlockId) {
            $existingEducation->update([
                'education_block_id' => $newBlockId,
                'class_label' => $keepClassName ? $this->incrementClassName($oldEducation->class_label) : null,
                'is_repeat_grade' => false, // Reset cho năm học mới
                'note' => ($existingEducation->note ? $existingEducation->note . '; ' : '') . 'auto_level_up_' . now()->format('Y-m-d_H-i-s')
            ]);
        }

        // SurveyFormDetail đã được tạo sẵn trong bước copy, không cần tạo mới

        return $existingEducation;
    }

    /**
     * Tìm khối học tiếp theo cho lên lớp (tăng index) hoặc lên cấp
     */
    private function getNextEducationBlockForLevelUp(?int $currentBlockId, ?string $levelUpType = null): ?int
    {
        if (!$currentBlockId) {
            return null;
        }

        $currentBlock = EducationBlock::find($currentBlockId);
        if (!$currentBlock) {
            return null;
        }

        // Nếu có levelUpType, kiểm tra xem có phải chuyển cấp không
        if ($levelUpType) {
            $levelTransferMapping = [
                'preschool' => ['5-6 tuổi' => '1'], // Mầm non 5-6 tuổi → Tiểu học lớp 1
                'primary' => ['5' => '6'],          // Tiểu học lớp 5 → THCS lớp 6
                'junior' => ['9' => '10'],          // THCS lớp 9 → THPT lớp 10
            ];

            $transferBlocks = $levelTransferMapping[$levelUpType] ?? [];
            if (isset($transferBlocks[$currentBlock->name])) {
                $nextBlockName = $transferBlocks[$currentBlock->name];
                $newBlock = EducationBlock::where('name', $nextBlockName)->first();
                return $newBlock ? $newBlock->id : null;
            }
        }

        // Nếu không chuyển cấp, tăng theo thứ tự
        $allBlocks = $this->getAllEducationBlocks();
        $currentIndex = array_search($currentBlock->name, $allBlocks);
        
        if ($currentIndex !== false && isset($allBlocks[$currentIndex + 1])) {
            $nextBlockName = $allBlocks[$currentIndex + 1];
            $newBlock = EducationBlock::where('name', $nextBlockName)->first();
            return $newBlock ? $newBlock->id : null;
        }

        return null;
    }

    /**
     * Tạo PersonEducation mới cho năm học tiếp theo
     */
    private function createNewPersonEducation(PersonEducation $oldEducation, string $academicYearTo, ?int $newSchoolId, bool $keepClassName, string $targetLevel): ?PersonEducation
    {
        // Tìm khối học mới dựa trên cấp học đích
        $newBlockId = $this->getNextEducationBlock($oldEducation->education_block_id, $targetLevel);
        
        // Kiểm tra xem có phải chuyển cấp không để quyết định trường học
        $isLevelTransfer = $this->isLevelTransfer($oldEducation->education_block_id, $targetLevel);
        $finalSchoolId = $isLevelTransfer ? $newSchoolId : $oldEducation->school_id;
        
        $newEducationData = [
            'person_id' => $oldEducation->person_id,
            'academic_year' => $academicYearTo,
            'education_block_id' => $newBlockId,
            'education_grade_id' => $oldEducation->education_grade_id,
            'class_label' => $keepClassName ? $this->incrementClassName($oldEducation->class_label) : null,
            'hoc_hai_buoi' => $oldEducation->hoc_hai_buoi,
            'hoc_9_10_buoi_tuan' => $oldEducation->hoc_9_10_buoi_tuan,
            'is_repeat_grade' => false, // Reset cho năm học mới
            'studying_district_id' => $oldEducation->studying_district_id,
            'school_id' => $finalSchoolId ?? $oldEducation->school_id,
            'school_name_custom' => $oldEducation->school_name_custom,
            'studying_district_custom' => $oldEducation->studying_district_custom,
            'graduation_level_id' => $oldEducation->graduation_level_id,
            'is_bo_tuc' => $oldEducation->is_bo_tuc,
            'graduation_year' => $oldEducation->graduation_year,
            'vocational_level_id' => $oldEducation->vocational_level_id,
            'vocational_grad_year' => $oldEducation->vocational_grad_year,
            'graduation_certificate_no' => $oldEducation->graduation_certificate_no,
            'hoc_xong_lop' => $oldEducation->hoc_xong_lop,
            'hoc_xong_nam' => $oldEducation->hoc_xong_nam,
            'bo_hoc_lop' => $oldEducation->bo_hoc_lop,
            'bo_hoc_nam' => $oldEducation->bo_hoc_nam,
            'note' => ($oldEducation->note ? $oldEducation->note . '; ' : '') . 'auto_transfer_' . now()->format('Y-m-d_H-i-s'),
        ];

        return PersonEducation::create($newEducationData);
    }

    /**
     * Tăng tên lớp (6A1 -> 7A1, 3T1 -> Chồi -> Lá)
     */
    private function incrementClassName(?string $className): ?string
    {
        if (!$className) {
            return null;
        }

        // Xử lý đặc biệt cho khối mầm non (không phân biệt hoa thường)
        
        // Pattern: Mầm + đuôi -> Chồi + đuôi (giữ nguyên đuôi kể cả số và dấu *)
        if (preg_match('/^[Mm]ầm(.*)$/u', $className, $matches)) {
            // Mầm1 -> Chồi1, mầm1 -> Chồi1, Mầm* -> Chồi*, mầm -> Chồi
            $suffix = $matches[1]; // Lấy phần sau "Mầm" hoặc "mầm"
            return 'Chồi' . $suffix;
        }
        
        // Pattern: 3T + đuôi -> Chồi + đuôi (giữ nguyên đuôi kể cả số và dấu *)
        if (preg_match('/^3[Tt](.*)$/u', $className, $matches)) {
            // 3T1 -> Chồi1, 3t1 -> Chồi1, 3T* -> Chồi*, 3T -> Chồi
            $suffix = $matches[1]; // Lấy phần sau "3T" hoặc "3t"
            return 'Chồi' . $suffix;
        }
        
        // Pattern: Chồi + đuôi -> Lá + đuôi (giữ nguyên đuôi kể cả số và dấu *)
        if (preg_match('/^[Cc]hồi(.*)$/u', $className, $matches)) {
            // Chồi1 -> Lá1, chồi1 -> Lá1, Chồi* -> Lá*, chồi* -> Lá*
            $suffix = $matches[1]; // Lấy phần sau "Chồi" hoặc "chồi"
            return 'Lá' . $suffix;
        }
        
        // Pattern: Lá + bất kỳ gì -> 1 (lên cấp sang lớp 1, bỏ hết phần đằng sau)
        if (preg_match('/^[Ll]á(.*)$/u', $className, $matches)) {
            // Lá1 -> 1, lá1 -> 1, Lá2 -> 1, lá2 -> 1, Lá* -> 1*, lá* -> 1*
            $suffix = $matches[1]; // Lấy phần sau "Lá" hoặc "lá"
            
            // Kiểm tra nếu có dấu * thì giữ lại, các ký tự số bỏ đi
            if (strpos($suffix, '*') !== false) {
                return '1*'; // Giữ dấu *
            }
            
            return '1'; // Bỏ hết số và ký tự khác
        }

        // Xử lý cho các lớp phổ thông
        // Chỉ tăng số đầu tiên, giữ nguyên phần còn lại
        // Ví dụ: 7A1 -> 8A1, 10B2 -> 11B2, 7a1 -> 8a1
        if (preg_match('/^(\d+)(.*)$/u', $className, $matches)) {
            $number = (int)$matches[1]; // Số đầu tiên
            $suffix = $matches[2];       // Phần còn lại (bao gồm cả chữ và số)
            return ($number + 1) . $suffix;
        }

        // Nếu không khớp pattern nào thì trả về null
        return null;
    }

    /**
     * Lấy danh sách tất cả các khối học theo thứ tự
     */
    private function getAllEducationBlocks(): array
    {
        return [
            '3-12 tháng',
            '13-24 tháng', 
            '25-36 tháng',
            '3-4 tuổi',
            '4-5 tuổi',
            '5-6 tuổi',
            '1',
            '2',
            '3',
            '4',
            '5',
            '6',
            '7',
            '8',
            '9',
            '10',
            '11',
            '12',
            'TDN1',
            'TDN2',
            'TDN3',
            'TDN3.5',
            'TDN4',
            'THCN1',
            'THCN2',
            'THCN3',
            'THCN3.5',
            'THCN4',
            'SV1',
            'SV2',
            'SV3',
            'SV4',
            'SV5',
            'SV6',
            'SV7'
        ];
    }

    /**
     * Tìm khối học tiếp theo dựa trên cấp học đích
     */
    private function getNextEducationBlock(?int $currentBlockId, string $targetLevel): ?int
    {
        if (!$currentBlockId) {
            return null;
        }

        $currentBlock = EducationBlock::find($currentBlockId);
        if (!$currentBlock) {
            return null;
        }

        // Mapping khối học theo thứ tự (tăng dần)
        $blockMapping = [
            'preschool_to_primary' => [
                '5-6 tuổi' => '1', // Chuyển cấp: Mầm non 5-6 tuổi -> Tiểu học lớp 1
            ],
            'primary_to_junior' => [
                '5' => '6', // Chuyển cấp: Tiểu học lớp 5 -> THCS lớp 6
            ],
            'junior_to_high' => [
                '9' => '10', // Chuyển cấp: THCS lớp 9 -> THPT lớp 10
            ]
        ];

        // Kiểm tra xem có phải chuyển cấp không
        $targetBlockName = $blockMapping[$targetLevel][$currentBlock->name] ?? null;
        
        if ($targetBlockName) {
            // Chuyển cấp: tìm khối học mới
            $newBlock = EducationBlock::where('name', $targetBlockName)->first();
            return $newBlock ? $newBlock->id : null;
        } else {
            // Không chuyển cấp: tăng theo thứ tự
            $allBlocks = $this->getAllEducationBlocks();
            $currentIndex = array_search($currentBlock->name, $allBlocks);
            
            if ($currentIndex !== false && isset($allBlocks[$currentIndex + 1])) {
                $nextBlockName = $allBlocks[$currentIndex + 1];
                $newBlock = EducationBlock::where('name', $nextBlockName)->first();
                return $newBlock ? $newBlock->id : null;
            }
        }

        return null;
    }

    /**
     * Kiểm tra xem có phải chuyển cấp không
     */
    private function isLevelTransfer(?int $currentBlockId, string $targetLevel): bool
    {
        if (!$currentBlockId) {
            return false;
        }

        $currentBlock = EducationBlock::find($currentBlockId);
        if (!$currentBlock) {
            return false;
        }

        // Các trường hợp chuyển cấp - CHỈ 5-6 tuổi mới chuyển cấp lên lớp 1
        $levelTransferMapping = [
            'preschool_to_primary' => ['5-6 tuổi'], // Chỉ 5-6 tuổi mới chuyển cấp
            'primary_to_junior' => ['5'],
            'junior_to_high' => ['9']
        ];

        $transferBlocks = $levelTransferMapping[$targetLevel] ?? [];
        return in_array($currentBlock->name, $transferBlocks);
    }

    /**
     * Kiểm tra xem dữ liệu đã tồn tại có phải từ lần lên lớp trước không
     */
    private function isFromPreviousTransfer(PersonEducation $existingEducation, string $academicYearFrom): bool
    {
        // Kiểm tra xem có phải dữ liệu được tạo từ lần lên lớp trước không
        // bằng cách kiểm tra thời gian tạo và các dấu hiệu khác
        
        // Nếu dữ liệu được tạo trong vòng 1 giờ trước khi chạy lên lớp
        // và có các đặc điểm của dữ liệu được tạo tự động
        $oneHourAgo = now()->subHour();
        
        if ($existingEducation->created_at && $existingEducation->created_at->gte($oneHourAgo)) {
            return true;
        }
        
        // Kiểm tra thêm: nếu có note hoặc các dấu hiệu đặc biệt
        if ($existingEducation->note && str_contains($existingEducation->note, 'auto_transfer')) {
            return true;
        }
        
        return false;
    }

    /**
     * Tạo PersonMamnon mới cho năm học tiếp theo
     */
    private function createNewPersonMamnon(PersonMamnon $oldPersonMamnon, string $academicYearTo): ?PersonMamnon
    {
        $newPersonMamnonData = [
            'person_id' => $oldPersonMamnon->person_id,
            'hoc_2_buoi' => $oldPersonMamnon->hoc_2_buoi,
            'hoc_ban_tru' => $oldPersonMamnon->hoc_ban_tru,
            'hoc_du_ngay_theo_quy_dinh' => $oldPersonMamnon->hoc_du_ngay_theo_quy_dinh,
            'chuyen_can' => $oldPersonMamnon->chuyen_can,
            'theo_doi_bd_can_nang' => $oldPersonMamnon->theo_doi_bd_can_nang,
            'sdd_nhe_can' => $oldPersonMamnon->sdd_nhe_can,
            'theo_doi_bd_chieu_cao' => $oldPersonMamnon->theo_doi_bd_chieu_cao,
            'sdd_thap_coi' => $oldPersonMamnon->sdd_thap_coi,
            'dan_toc_chuan_bi_tv' => $oldPersonMamnon->dan_toc_chuan_bi_tv,
            'hoan_thanh_chuong_trinh_gdmn' => false, // Reset cho năm học mới
            'trai_tuyen' => $oldPersonMamnon->trai_tuyen,
        ];

        return PersonMamnon::create($newPersonMamnonData);
    }

    /**
     * Cập nhật PersonEducation đã tồn tại
     */
    private function updatePersonEducation(PersonEducation $existingEducation, PersonEducation $oldEducation, string $academicYearTo, ?int $newSchoolId, bool $keepClassName, string $targetLevel): PersonEducation
    {
        // Tìm khối học mới dựa trên cấp học đích
        $newBlockId = $this->getNextEducationBlock($oldEducation->education_block_id, $targetLevel);
        
        // Kiểm tra xem có phải chuyển cấp không để quyết định trường học
        $isLevelTransfer = $this->isLevelTransfer($oldEducation->education_block_id, $targetLevel);
        $finalSchoolId = $isLevelTransfer ? $newSchoolId : $oldEducation->school_id;
        
        $existingEducation->update([
            'education_block_id' => $newBlockId,
            'class_label' => $keepClassName ? $this->incrementClassName($oldEducation->class_label) : null,
            'school_id' => $finalSchoolId ?? $oldEducation->school_id,
            'is_repeat_grade' => false, // Reset cho năm học mới
        ]);

        return $existingEducation;
    }

    /**
     * Cập nhật PersonMamnon đã tồn tại
     */
    private function updatePersonMamnon(PersonMamnon $existingPersonMamnon, PersonMamnon $oldPersonMamnon, string $academicYearTo): PersonMamnon
    {
        $existingPersonMamnon->update([
            'hoc_2_buoi' => $oldPersonMamnon->hoc_2_buoi,
            'hoc_ban_tru' => $oldPersonMamnon->hoc_ban_tru,
            'hoc_du_ngay_theo_quy_dinh' => $oldPersonMamnon->hoc_du_ngay_theo_quy_dinh,
            'chuyen_can' => $oldPersonMamnon->chuyen_can,
            'theo_doi_bd_can_nang' => $oldPersonMamnon->theo_doi_bd_can_nang,
            'sdd_nhe_can' => $oldPersonMamnon->sdd_nhe_can,
            'theo_doi_bd_chieu_cao' => $oldPersonMamnon->theo_doi_bd_chieu_cao,
            'sdd_thap_coi' => $oldPersonMamnon->sdd_thap_coi,
            'dan_toc_chuan_bi_tv' => $oldPersonMamnon->dan_toc_chuan_bi_tv,
            'hoan_thanh_chuong_trinh_gdmn' => false, // Reset cho năm học mới
            'trai_tuyen' => $oldPersonMamnon->trai_tuyen,
        ]);

        return $existingPersonMamnon;
    }

    /**
     * Tạo hoặc cập nhật SurveyFormDetail cho năm học mới
     */
    private function createOrUpdateSurveyFormDetailForNewYear(?SurveyFormDetail $oldDetail, string $academicYearTo, PersonEducation $newEducation, ?PersonMamnon $newPersonMamnon): void
    {
        // Lấy so_phieu từ bản ghi cũ (số phiếu của hộ gia đình)
        $soPhieu = null;
        if ($oldDetail && $oldDetail->surveyForm) {
            $soPhieu = $oldDetail->surveyForm->so_phieu;
        }
        
        if (!$soPhieu) {
            // Nếu không có so_phieu cũ, tạo mới (trường hợp hiếm)
            $soPhieu = 'AUTO-' . $academicYearTo . '-' . time() . '-' . rand(1000, 9999);
        }
        
        // Tìm hoặc tạo SurveyForm duy nhất với so_phieu này (không phụ thuộc academic_year)
        $surveyForm = SurveyForm::firstOrCreate(
            ['so_phieu' => $soPhieu],
            [
                'academic_year' => $academicYearTo, // Năm học tiếp theo
                'household_id' => $oldDetail?->surveyForm?->household_id ?? null,
                'head_person_id' => $oldDetail?->surveyForm?->head_person_id ?? null,
                'is_deleted' => false
            ]
        );
        
        // Kiểm tra và tạo PersonMamnon nếu cần thiết
        $finalPersonMamnon = $newPersonMamnon;
        if (!$finalPersonMamnon && $this->shouldCreatePersonMamnonForNewYear($newEducation, $academicYearTo)) {
            $finalPersonMamnon = $this->createPersonMamnonForNewYear($newEducation, $academicYearTo);
        }
        
        // Kiểm tra xem đã có SurveyFormDetail cho năm học mới chưa
        $existingDetail = SurveyFormDetail::where('person_id', $newEducation->person_id)
            ->where('academic_year', $academicYearTo)
            ->first();
        
        if ($existingDetail) {
            // Cập nhật nếu đã tồn tại
            $existingDetail->update([
                'person_education_id' => $newEducation->id,
                'person_mamnon_id' => $finalPersonMamnon?->id,
            ]);
        } else {
            // Tạo mới nếu chưa tồn tại
            SurveyFormDetail::create([
                'survey_form_id' => $surveyForm->id,
                'person_id' => $newEducation->person_id,
                'residence_id' => $oldDetail?->residence_id,
                'person_education_id' => $newEducation->id,
                'literacy_id' => $oldDetail?->literacy_id,
                'person_disability_id' => $oldDetail?->person_disability_id,
                'person_special_circumstance_id' => $oldDetail?->person_special_circumstance_id,
                'person_contact_id' => $oldDetail?->person_contact_id,
                'person_mamnon_id' => $finalPersonMamnon?->id,
                'note' => $oldDetail?->note,
                'is_deleted' => false,
                'academic_year' => $academicYearTo,
            ]);
        }
    }

    /**
     * Cập nhật SurveyFormDetail với các ID mới
     */
    private function updateSurveyFormDetailForNewYear(SurveyFormDetail $oldDetail, string $academicYearTo, PersonEducation $newEducation, ?PersonMamnon $newPersonMamnon): void
    {
        // Lấy so_phieu từ bản ghi cũ (số phiếu của hộ gia đình)
        $soPhieu = $oldDetail->surveyForm ? $oldDetail->surveyForm->so_phieu : 'AUTO-' . $academicYearTo . '-' . time() . '-' . rand(1000, 9999);
        
        // Tìm hoặc tạo SurveyForm duy nhất với so_phieu này (không phụ thuộc academic_year)
        $surveyForm = SurveyForm::firstOrCreate(
            ['so_phieu' => $soPhieu],
            [
                'academic_year' => $academicYearTo, // Năm học hiện tại
                'household_id' => $oldDetail->surveyForm->household_id ?? null,
                'head_person_id' => $oldDetail->surveyForm->head_person_id ?? null,
                'is_deleted' => false
            ]
        );
        
        // Kiểm tra và tạo PersonMamnon nếu cần thiết
        $finalPersonMamnon = $newPersonMamnon;
        if (!$finalPersonMamnon && $this->shouldCreatePersonMamnonForNewYear($newEducation, $academicYearTo)) {
            $finalPersonMamnon = $this->createPersonMamnonForNewYear($newEducation, $academicYearTo);
        }
        
        // Tạo SurveyFormDetail mới cho năm học tiếp theo
        SurveyFormDetail::create([
            'survey_form_id' => $surveyForm->id,
            'person_id' => $oldDetail->person_id,
            'residence_id' => $oldDetail->residence_id,
            'person_education_id' => $newEducation->id,
            'literacy_id' => $oldDetail->literacy_id,
            'person_disability_id' => $oldDetail->person_disability_id,
            'person_special_circumstance_id' => $oldDetail->person_special_circumstance_id,
            'person_contact_id' => $oldDetail->person_contact_id,
            'person_mamnon_id' => $finalPersonMamnon?->id,
            'note' => $oldDetail->note,
            'is_deleted' => false,
            'academic_year' => $academicYearTo,
        ]);
    }

    /**
     * Xử lý lên cấp cho các khối tốt nghiệp trong quá trình lên lớp
     */
    private function processLevelUpForGraduationStudents(array $data): array
    {
        $result = [
            'level' => 'graduation_level_up',
            'total_processed' => 0,
            'total_updated' => 0,
            'total_created' => 0,
            'errors' => []
        ];

        $academicYearFrom = $data['academic_year_from'];
        $academicYearTo = $data['academic_year_to'];
        $nextSchoolMapping = $data['next_school_mapping'] ?? [];

        // Mapping khối tốt nghiệp
        $graduationBlocks = [
            'preschool' => '5-6 tuổi',
            'primary' => '5',
            'junior' => '9',
            'high' => '12'
        ];

        foreach ($graduationBlocks as $levelType => $blockName) {
            try {
                // Lấy trường cấp học tiếp theo từ mapping
                $nextSchoolId = $this->getNextSchoolIdFromMapping($levelType, $nextSchoolMapping);
                
                if (!$nextSchoolId) {
                    continue; // Bỏ qua nếu không có trường cấp tiếp theo
                }

                // Lấy danh sách học sinh tốt nghiệp
                $students = $this->getGraduationStudentsForLevelUp($blockName, $data);
                
                foreach ($students as $student) {
                    try {
                        $education = $student->education;
                        
                        if ($levelType === 'high') {
                            // Xử lý đặc biệt cho lớp 12 - tốt nghiệp
                            $this->processGraduation($education, $academicYearTo, $academicYearFrom);
                            $result['total_processed']++;
                            $result['total_created']++;
                        } else {
                            // Xử lý lên cấp bình thường
                            $this->processLevelUp($education, $nextSchoolId, $academicYearTo, $levelType, $academicYearFrom);
                            $result['total_processed']++;
                            $result['total_created']++;
                        }
                        
                    } catch (\Exception $e) {
                        $result['errors'][] = [
                            'person_id' => $student->person_id,
                            'level_type' => $levelType,
                            'error' => $e->getMessage()
                        ];
                    }
                }
                
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'level_type' => $levelType,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $result;
    }

    /**
     * Lấy ID trường cấp học tiếp theo từ mapping
     */
    private function getNextSchoolIdFromMapping(string $levelType, array $mapping): ?int
    {
        foreach ($mapping as $item) {
            if ($item['level'] === $levelType) {
                return $item['school_id'];
            }
        }
        return null;
    }

    /**
     * Lấy loại cấp học từ khối học
     */
    private function getLevelTypeFromBlock($block): string
    {
        if (!$block) {
            return 'unknown';
        }

        $blockName = $block->name;
        
        // Mapping khối học -> cấp học
        if (in_array($blockName, ['3-4 tuổi', '4-5 tuổi', '5-6 tuổi'])) {
            return 'preschool';
        } elseif (in_array($blockName, ['1', '2', '3', '4', '5'])) {
            return 'primary';
        } elseif (in_array($blockName, ['6', '7', '8', '9'])) {
            return 'junior';
        } elseif (in_array($blockName, ['10', '11', '12'])) {
            return 'high';
        }
        
        return 'unknown';
    }

    /**
     * Xử lý lên lớp trong cùng cấp
     */
    private function processLevelTransfer(PersonEducation $education, string $academicYearTo, bool $keepClassName): void
    {
        // Bản ghi đã được tạo sẵn trong bước copy, chỉ cần cập nhật
        $this->updatePersonEducationForLevelUp($education, $education, $academicYearTo, $keepClassName);
    }

    /**
     * Lấy danh sách học sinh tốt nghiệp cho lên cấp
     */
    private function getGraduationStudentsForLevelUp(string $blockName, array $data): \Illuminate\Database\Eloquent\Collection
    {
        $query = SurveyFormDetail::whereNotNull('person_education_id')
            ->where('academic_year', $data['academic_year_from'])
            ->whereHas('education.block', function ($q) use ($blockName) {
                $q->where('name', $blockName);
            })
            ->with(['person', 'education.block', 'education', 'education.school']);

        // Lọc theo địa bàn nếu có
        if (isset($data['province_id'])) {
            $query->whereHas('education.school', function ($q) use ($data) {
                if (isset($data['ward_id'])) {
                    $q->where('ward_code', GeoUnit::find($data['ward_id'])->code ?? '')
                      ->orWhere('ward_code_new', GeoUnit::find($data['ward_id'])->code ?? '');
                } elseif (isset($data['district_id'])) {
                    $q->where('district_code', GeoUnit::find($data['district_id'])->code ?? '')
                      ->orWhere('district_code_new', GeoUnit::find($data['district_id'])->code ?? '');
                } else {
                    $q->where('province_code', GeoUnit::find($data['province_id'])->code ?? '')
                      ->orWhere('province_code_new', GeoUnit::find($data['province_id'])->code ?? '');
                }
            });
        }

        return $query->get();
    }

    /**
     * Thực hiện lên cấp cho các khối tốt nghiệp (method cũ - giữ lại để tương thích)
     */
    public function executeLevelUp(array $data): array
    {
        $result = [
            'total_processed' => 0,
            'total_updated' => 0,
            'total_created' => 0,
            'errors' => [],
            'details' => []
        ];

        $levelUpType = $data['level_up_type'];
        $currentSchoolId = $data['current_school_id'];
        $nextSchoolId = $data['next_school_id'] ?? null;
        $selectAllCurrent = $data['select_all_current'] ?? false;
        $geoInfo = $data['geo_info'] ?? null;
        $academicYearFrom = $data['academic_year_from'];
        $academicYearTo = $data['academic_year_to'];

        // Mapping khối tốt nghiệp
        $graduationBlocks = [
            'preschool' => '5-6 tuổi',
            'primary' => '5',
            'junior' => '9',
            'high' => '12'
        ];

        $blockName = $graduationBlocks[$levelUpType] ?? null;
        if (!$blockName) {
            throw new \Exception('Loại lên cấp không hợp lệ');
        }

        // Lấy danh sách học sinh ở khối tốt nghiệp
        $students = $this->getGraduationStudents($currentSchoolId, $blockName, $selectAllCurrent, $geoInfo, $levelUpType);

        foreach ($students as $student) {
            try {
                $education = $student->education;
                
                if ($levelUpType === 'high') {
                    // Xử lý đặc biệt cho lớp 12 - tốt nghiệp (tạo bản ghi mới)
                    $this->processGraduation($education, $academicYearTo, $academicYearFrom);
                    $result['total_processed']++;
                    $result['total_created']++;
                } else {
                    // Xử lý lên cấp bình thường
                    $this->processLevelUp($education, $nextSchoolId, $academicYearTo, $levelUpType, $academicYearFrom);
                    $result['total_processed']++;
                    $result['total_created']++;
                }
                
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'person_id' => $student->person_id,
                    'school_id' => $currentSchoolId,
                    'level_up_type' => $levelUpType,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $result;
    }

    /**
     * Lấy danh sách học sinh tốt nghiệp
     */
    private function getGraduationStudents($currentSchoolId, $blockName, $selectAllCurrent, $geoInfo, $levelUpType)
    {
        $query = SurveyFormDetail::whereNotNull('person_education_id')
            ->whereHas('education.block', function ($q) use ($blockName) {
                $q->where('name', $blockName);
            })
            ->whereHas('education', function ($q) {
                $q->where('trai_tuyen', false); // Chỉ lấy học sinh bình thường
            })
            ->with(['person', 'education.block', 'education', 'education.school']);

        if ($selectAllCurrent && $currentSchoolId === 'all') {
            // Lấy học sinh từ tất cả trường trong cấp học
            $schoolIds = $this->getSchoolsByLevelAndGeo($levelUpType, $geoInfo);
            $query->whereHas('education', function ($q) use ($schoolIds) {
                $q->whereIn('school_id', $schoolIds);
            });
        } else {
            // Lấy học sinh từ trường cụ thể
            $query->whereHas('education', function ($q) use ($currentSchoolId) {
                $q->where('school_id', $currentSchoolId);
            });
        }

        return $query->get();
    }


    /**
     * Lấy danh sách trường theo cấp học và địa bàn
     */
    private function getSchoolsByLevelAndGeo($levelType, $geoInfo)
    {
        $levelMappings = [
            'preschool' => ['Mầm non'],
            'primary' => ['Tiểu học + THCS', 'Tiểu học + THCS + THPT', 'Tiểu học'],
            'junior' => ['THCS', 'Trung học cơ sở', 'Tiểu học + THCS + THPT', 'THCS + THPT', 'Tiểu học + THCS'],
            'high' => ['THCS + THPT', 'Tiểu học + THCS + THPT', 'THPT', 'Trung học phổ thông']
        ];

        $possibleNames = $levelMappings[$levelType] ?? [];
        
        $query = School::active()
            ->whereHas('educationalLevel', function($q) use ($possibleNames) {
                $q->whereIn('name', $possibleNames);
            });

        if ($geoInfo) {
            if ($geoInfo['scheme'] === 'old') {
                if (!empty($geoInfo['ward_id'])) {
                    $query->where(function($q) use ($geoInfo) {
                        $q->where('old_ward_id', $geoInfo['ward_id'])
                          ->orWhere('new_ward_id', $geoInfo['ward_id'])
                          ->orWhere('village_id', $geoInfo['ward_id']);
                    });
                } elseif (!empty($geoInfo['district_id'])) {
                    $query->where('old_district_id', $geoInfo['district_id']);
                } elseif (!empty($geoInfo['province_id'])) {
                    $query->where(function($q) use ($geoInfo) {
                        $q->where('old_province_id', $geoInfo['province_id'])
                          ->orWhere('new_province_id', $geoInfo['province_id']);
                    });
                }
            } else {
                if (!empty($geoInfo['ward_id'])) {
                    $query->where('new_ward_id', $geoInfo['ward_id']);
                } elseif (!empty($geoInfo['province_id'])) {
                    $query->where('new_province_id', $geoInfo['province_id']);
                }
            }
        }

        return $query->pluck('id')->toArray();
    }


    /**
     * Xử lý lên cấp bình thường (không phải lớp 12)
     */
    private function processLevelUp(PersonEducation $education, int $nextSchoolId, string $academicYearTo, string $levelUpType, string $academicYearFrom): void
    {
        // Tìm education_block_id tiếp theo dựa trên cấp học đích
        $nextBlockId = $this->getNextEducationBlockForLevelUp($education->education_block_id, $levelUpType);
        if (!$nextBlockId) {
            throw new \Exception('Không tìm thấy khối học tiếp theo');
        }

        // Xử lý graduation_level_id và graduation_year theo logic mới
        $nextGraduationLevelId = $this->getNextGraduationLevelId($education->graduation_level_id);
        $graduationYear = ($nextGraduationLevelId !== null) ? $academicYearFrom : null;

        // Bản ghi đã được tạo sẵn trong bước copy, chỉ cần cập nhật
        $education->update([
            'education_block_id' => $nextBlockId,
            'class_label' => $this->generateNewClassLabel($education->class_label),
            'hoc_hai_buoi' => $education->hoc_hai_buoi,
            'hoc_9_10_buoi_tuan' => $education->hoc_9_10_buoi_tuan,
            'is_repeat_grade' => false,
            'studying_district_id' => $education->studying_district_id,
            'school_id' => $nextSchoolId,
            'graduation_level_id' => $nextGraduationLevelId,
            'is_bo_tuc' => false,
            'graduation_year' => $graduationYear,
            'trai_tuyen' => $education->trai_tuyen,
            'vocational_level_id' => $education->vocational_level_id,
            'vocational_grad_year' => $education->vocational_grad_year,
            'graduation_certificate_no' => $education->graduation_certificate_no,
            'hoc_xong_lop' => $education->hoc_xong_lop,
            'hoc_xong_nam' => $education->hoc_xong_nam,
            'bo_hoc_lop' => $education->bo_hoc_lop,
            'bo_hoc_nam' => $education->bo_hoc_nam,
            'note' => ($education->note ? $education->note . '; ' : '') . 'auto_level_up_' . now()->format('Y-m-d_H-i-s')
        ]);

        // SurveyFormDetail đã được tạo sẵn trong bước copy, không cần tạo mới
    }

    /**
     * Xử lý tốt nghiệp cho lớp 12
     */
    private function processGraduation(PersonEducation $education, string $academicYearTo, string $academicYearFrom): void
    {
        // Lưu academic_year_from làm năm tốt nghiệp (năm người dùng chọn trong selectbox)
        $graduationYear = $academicYearFrom;
        // Xử lý graduation_level_id: tăng lên 1 (từ 3 lên 4)
        $nextGraduationLevelId = $this->getNextGraduationLevelId($education->graduation_level_id);
        
        // Bản ghi đã được tạo sẵn trong bước copy, chỉ cần cập nhật
        $education->update([
            'education_block_id' => null,
            'class_label' => null,
            'hoc_hai_buoi' => $education->hoc_hai_buoi,
            'hoc_9_10_buoi_tuan' => $education->hoc_9_10_buoi_tuan,
            'is_repeat_grade' => false,
            'studying_district_id' => $education->studying_district_id,
            'school_id' => null,
            'graduation_level_id' => $nextGraduationLevelId,
            'is_bo_tuc' => false,
            'graduation_year' => $graduationYear,
            'trai_tuyen' => $education->trai_tuyen,
            'vocational_level_id' => $education->vocational_level_id,
            'vocational_grad_year' => $education->vocational_grad_year,
            'graduation_certificate_no' => $education->graduation_certificate_no,
            'hoc_xong_lop' => $education->hoc_xong_lop,
            'hoc_xong_nam' => $education->hoc_xong_nam,
            'bo_hoc_lop' => $education->bo_hoc_lop,
            'bo_hoc_nam' => $education->bo_hoc_nam,
            'note' => ($education->note ? $education->note . '; ' : '') . 'auto_graduation_' . now()->format('Y-m-d_H-i-s')
        ]);

        // SurveyFormDetail đã được tạo sẵn trong bước copy, không cần tạo mới
    }

    /**
     * Tìm graduation_level_id tiếp theo theo logic mới
     */
    private function getNextGraduationLevelId(?int $currentId): ?int
    {
        if ($currentId === null) {
            // Nếu null thì chuyển thành id = 1
            return 1;
        }
        
        if ($currentId >= 1 && $currentId <= 3) {
            // Nếu là 1, 2, 3 thì cộng 1 lên
            return $currentId + 1;
        }
        
        if ($currentId == 4) {
            // Nếu là 4 thì chuyển thành null
            return null;
        }
        
        // Các trường hợp khác giữ nguyên
        return $currentId;
    }

    /**
     * Tạo class_label mới cho khối tiếp theo
     */
    private function generateNewClassLabel(?string $currentClassLabel): ?string
    {
        if (!$currentClassLabel) {
            return null;
        }

        // Sử dụng hàm incrementClassName đã được cập nhật
        return $this->incrementClassName($currentClassLabel);
    }

    /**
     * Kiểm tra xem có nên tạo PersonMamnon cho năm học mới không
     */
    private function shouldCreatePersonMamnonForNewYear(PersonEducation $education, string $academicYear): bool
    {
        $person = $education->person;
        
        // Kiểm tra tuổi mầm non (1-5 tuổi)
        $isPreschoolAge = $this->isPreschoolAgeForNewYear($person, $academicYear);
        
        // Kiểm tra khối học mầm non
        $isPreschoolBlock = $this->isPreschoolBlockForNewYear($education);
        
        return $isPreschoolAge || $isPreschoolBlock;
    }

    /**
     * Kiểm tra xem người có phải tuổi mầm non (1-5 tuổi) trong năm học mới không
     */
    private function isPreschoolAgeForNewYear($person, string $academicYear): bool
    {
        if (!$person->dob_year || !$person->dob_month || !$person->dob_day) {
            return false;
        }

        // Lấy năm bắt đầu của năm học (ví dụ: 2024-2025 -> 2024)
        $yearStart = (int) explode('-', $academicYear)[0];
        
        // Tính tuổi vào đầu năm học
        // Tính tuổi đơn giản: năm học - năm sinh
        $age = $yearStart - $person->dob_year;
        
        return $age <= 5;
    }

    /**
     * Kiểm tra xem khối học có phải mầm non không
     */
    private function isPreschoolBlockForNewYear(PersonEducation $education): bool
    {
        if (!$education || !$education->education_block_id) {
            return false;
        }

        $block = \App\Models\EducationBlock::find($education->education_block_id);
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
     * Tạo PersonMamnon mới cho năm học mới
     */
    private function createPersonMamnonForNewYear(PersonEducation $education, string $academicYear): ?\App\Models\PersonMamnon
    {
        try {
            $person = $education->person;
            
            // Tạo PersonMamnon với dữ liệu mặc định
            $personMamnon = \App\Models\PersonMamnon::create([
                'person_id' => $person->id,
                'hoc_2_buoi' => false,
                'hoc_ban_tru' => false,
                'hoc_du_ngay_theo_quy_dinh' => false,
                'chuyen_can' => false,
                'theo_doi_bd_can_nang' => false,
                'sdd_nhe_can' => false,
                'theo_doi_bd_chieu_cao' => false,
                'sdd_thap_coi' => false,
                'dan_toc_chuan_bi_tv' => false,
                'hoan_thanh_chuong_trinh_gdmn' => false,
                'trai_tuyen' => false,
            ]);

            return $personMamnon;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Kiểm tra đã có PersonEducation của năm học mới chưa (idempotent)
     */
    private function hasEducationInYear(int $personId, string $academicYear): bool
    {
        return PersonEducation::where('person_id', $personId)
            ->where('academic_year', $academicYear)
            ->exists();
    }

}
