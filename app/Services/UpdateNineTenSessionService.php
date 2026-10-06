<?php

namespace App\Services;

use App\Models\PersonEducation;
use App\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service cập nhật học 9-10 buổi/tuần
 * 
 * Logic:
 * - Khối 1 (education_block_id = 7)
 * - Trẻ 6 tuổi (tính từ dob_year)
 * - Cập nhật: hoc_9_10_buoi_tuan = 1
 * - Thêm dấu * vào class_label nếu chưa có
 */
class UpdateNineTenSessionService
{
    /**
     * Cập nhật hàng loạt theo bộ lọc
     * 
     * @param array $filters ['academic_year', 'geo', 'scheme']
     * @return array ['updated' => int, 'skipped' => int, 'errors' => array]
     */
    public function updateByFilters(array $filters = []): array
    {
        $stats = [
            'updated' => 0,
            'skipped' => 0,
            'errors'  => [],
        ];

        // Tính năm sinh cho trẻ 6 tuổi
        $academicYear = $filters['academic_year'] ?? \App\Models\SurveyForm::currentAcademicYear();
        $yearStart = (int)explode('-', $academicYear)[0];
        $targetBirthYear = $yearStart - 6; // Trẻ 6 tuổi

        // Query PersonEducation
        $query = PersonEducation::query()
            ->where('education_block_id', 7)  // Khối 1
            ->where(function($q) {
                // Chưa có cờ 9-10 buổi/tuần HOẶC chưa có dấu *
                $q->where('hoc_9_10_buoi_tuan', false)
                  ->orWhere('hoc_9_10_buoi_tuan', null)
                  ->orWhereRaw("class_label NOT LIKE '%*%'");
            });

        // Kiểm tra cột is_deleted
        if (\Schema::hasColumn('person_educations', 'is_deleted')) {
            $query->where('is_deleted', false);
        }

        // Filter theo academic_year
        if (!empty($filters['academic_year'])) {
            $query->where('academic_year', $filters['academic_year']);
        }

        // Filter theo geo (join với person)
        if (!empty($filters['geo'])) {
            $this->applyGeoFilter($query, $filters['geo'], $filters['scheme'] ?? 'new');
        }

        // Eager load person để kiểm tra tuổi
        $query->with('person:id,dob_year');

        // Chunk xử lý
        $query->chunk(500, function ($educations) use (&$stats, $targetBirthYear) {
            foreach ($educations as $education) {
                try {
                    $person = $education->person;
                    
                    // Kiểm tra tuổi 6
                    if (!$person || !$person->dob_year || $person->dob_year != $targetBirthYear) {
                        $stats['skipped']++;
                        continue;
                    }

                    $updated = false;

                    // Cập nhật hoc_9_10_buoi_tuan
                    if (!$education->hoc_9_10_buoi_tuan) {
                        $education->hoc_9_10_buoi_tuan = true;
                        $updated = true;
                    }

                    // Thêm dấu * vào class_label nếu chưa có
                    if (!empty($education->class_label) && !str_contains($education->class_label, '*')) {
                        $education->class_label = $education->class_label . '*';
                        $updated = true;
                    }

                    if ($updated) {
                        $education->save();
                        $stats['updated']++;

                        Log::info("Cập nhật học 9-10 b/t", [
                            'person_education_id' => $education->id,
                            'person_id' => $person->id,
                            'dob_year' => $person->dob_year,
                            'class_label' => $education->class_label,
                        ]);
                    } else {
                        $stats['skipped']++;
                    }

                } catch (\Throwable $e) {
                    $stats['errors'][] = [
                        'person_education_id' => $education->id,
                        'error' => $e->getMessage(),
                    ];

                    Log::error("Lỗi cập nhật học 9-10 b/t", [
                        'person_education_id' => $education->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        return $stats;
    }

    /**
     * Áp dụng filter địa bàn
     */
    protected function applyGeoFilter($query, array $geo, string $scheme): void
    {
        $query->whereHas('person', function ($q) use ($geo, $scheme) {
            if ($scheme === 'old') {
                if (!empty($geo['province_id'])) {
                    $q->where('old_province_id', $geo['province_id']);
                }
                if (!empty($geo['district_id'])) {
                    $q->where('old_district_id', $geo['district_id']);
                }
                if (!empty($geo['ward_id'])) {
                    $q->where('old_ward_id', $geo['ward_id']);
                }
            } else {
                if (!empty($geo['province_id'])) {
                    $q->where('new_province_id', $geo['province_id']);
                }
                if (!empty($geo['ward_id'])) {
                    $q->where('new_ward_id', $geo['ward_id']);
                }
            }

            if (!empty($geo['village_id'])) {
                $q->where('village_id', $geo['village_id']);
            }
        });
    }

    /**
     * Preview số lượng sẽ cập nhật
     */
    public function previewCount(array $filters = []): array
    {
        $academicYear = $filters['academic_year'] ?? \App\Models\SurveyForm::currentAcademicYear();
        $yearStart = (int)explode('-', $academicYear)[0];
        $targetBirthYear = $yearStart - 6;

        $query = PersonEducation::query()
            ->where('education_block_id', 7)
            ->where(function($q) {
                $q->where('hoc_9_10_buoi_tuan', false)
                  ->orWhere('hoc_9_10_buoi_tuan', null)
                  ->orWhereRaw("class_label NOT LIKE '%*%'");
            });

        if (\Schema::hasColumn('person_educations', 'is_deleted')) {
            $query->where('is_deleted', false);
        }

        if (!empty($filters['academic_year'])) {
            $query->where('academic_year', $filters['academic_year']);
        }

        if (!empty($filters['geo'])) {
            $this->applyGeoFilter($query, $filters['geo'], $filters['scheme'] ?? 'new');
        }

        // Đếm theo năm sinh
        $query->whereHas('person', function($q) use ($targetBirthYear) {
            $q->where('dob_year', $targetBirthYear);
        });

        $total = $query->count();

        return [
            'total' => $total,
            'target_birth_year' => $targetBirthYear,
        ];
    }
}

