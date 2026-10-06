<?php

namespace App\Services;

use App\Models\PersonEducation;
use App\Models\GraduationLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service xử lý cập nhật bậc tốt nghiệp tự động
 * 
 * Dựa vào thông tin học tập theo thứ tự ưu tiên:
 * 1. bo_hoc_lop (integer 1-12): Lớp bỏ học
 * 2. hoc_xong_lop (integer 1-12): Lớp học xong
 * 3. education_block_id (ID 7-18): Khối học hiện tại
 *    - ID 7 = lớp 1, ID 8 = lớp 2, ..., ID 18 = lớp 12
 */
class UpdateGraduationLevelService
{
    /**
     * Mapping lớp học sang ID bậc tốt nghiệp
     * 
     * Logic:
     * - Lớp 1-5 (Tiểu học) → TN Mầm non (graduation_level_id = 1)
     * - Lớp 6-9 (THCS) → TN Tiểu học (graduation_level_id = 2)
     * - Lớp 10-12 (THPT) → TN THCS (graduation_level_id = 3)
     */
    protected array $gradeToGraduationMapping = [
        // Tiểu học (đang học/đã học xong) → TN Mầm non (id=1)
        '1'  => 1,
        '2'  => 1,
        '3'  => 1,
        '4'  => 1,
        '5'  => 1,
        
        // THCS (đang học/đã học xong) → TN Tiểu học (id=2)
        '6'  => 2,
        '7'  => 2,
        '8'  => 2,
        '9'  => 2,
        
        // THPT (đang học/đã học xong) → TN THCS (id=3)
        '10' => 3,
        '11' => 3,
        '12' => 3,
    ];

    /**
     * Cập nhật bậc TN hàng loạt theo bộ lọc
     * 
     * @param array $filters ['academic_year', 'geo', 'scheme', ...]
     * @return array ['updated' => int, 'skipped' => int, 'errors' => array]
     */
    public function updateByFilters(array $filters = []): array
    {
        $stats = [
            'updated' => 0,
            'skipped' => 0,
            'errors'  => [],
        ];

        // Query PersonEducation cần cập nhật
        $query = PersonEducation::query()
            ->whereNull('graduation_level_id');  // Chỉ cập nhật những record chưa có bậc TN

        // Kiểm tra nếu bảng có cột is_deleted
        if (\Schema::hasColumn('person_educations', 'is_deleted')) {
            $query->where('is_deleted', false);
        }

        // Áp dụng filter theo academic_year
        if (!empty($filters['academic_year'])) {
            $query->where('academic_year', $filters['academic_year']);
        }

        // Filter theo geo (nếu có)
        if (!empty($filters['geo'])) {
            $this->applyGeoFilter($query, $filters['geo'], $filters['scheme'] ?? 'new');
        }

        // Chunk xử lý để tránh quá tải bộ nhớ
        $query->chunk(500, function ($educations) use (&$stats) {
            foreach ($educations as $education) {
                try {
                    $graduationLevelId = $this->determineGraduationLevelId($education);
                    
                    if ($graduationLevelId) {
                        $education->graduation_level_id = $graduationLevelId;
                        $education->save();
                        
                        $stats['updated']++;
                        
                        Log::info("Cập nhật bậc TN", [
                            'person_education_id' => $education->id,
                            'person_id' => $education->person_id,
                            'graduation_level_id' => $graduationLevelId,
                            'source' => $this->getUpdateSource($education),
                        ]);
                    } else {
                        $stats['skipped']++;
                    }
                } catch (\Throwable $e) {
                    $stats['errors'][] = [
                        'person_education_id' => $education->id,
                        'error' => $e->getMessage(),
                    ];
                    
                    Log::error("Lỗi cập nhật bậc TN", [
                        'person_education_id' => $education->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        return $stats;
    }

    /**
     * Xác định ID bậc tốt nghiệp dựa vào thông tin học tập
     * 
     * @param PersonEducation $education
     * @return int|null ID bậc TN hoặc null
     */
    protected function determineGraduationLevelId(PersonEducation $education): ?int
    {
        $grade = null;

        // Ưu tiên 1: Lớp bỏ học (bo_hoc_lop)
        // Trong DB: cast là integer, giá trị trực tiếp 1-12
        if (!empty($education->bo_hoc_lop)) {
            $grade = (string)$education->bo_hoc_lop;
        }

        // Ưu tiên 2: Lớp học xong (hoc_xong_lop)
        // Trong DB: cast là integer, giá trị trực tiếp 1-12
        if (empty($grade) && !empty($education->hoc_xong_lop)) {
            $grade = (string)$education->hoc_xong_lop;
        }

        // Ưu tiên 3: Khối học hiện tại (education_block_id)
        // ID 7 = lớp 1, ID 8 = lớp 2, ..., ID 18 = lớp 12
        // Công thức: grade = education_block_id - 6
        if (empty($grade) && !empty($education->education_block_id)) {
            $blockId = (int)$education->education_block_id;
            // Chỉ xử lý nếu block_id từ 7 đến 18
            if ($blockId >= 7 && $blockId <= 18) {
                $grade = (string)($blockId - 6);
            }
        }

        // Map grade → graduation_level_id
        if ($grade && isset($this->gradeToGraduationMapping[$grade])) {
            return $this->gradeToGraduationMapping[$grade];
        }

        return null;
    }

    /**
     * Lấy nguồn cập nhật (để log)
     * 
     * @param PersonEducation $education
     * @return string
     */
    protected function getUpdateSource(PersonEducation $education): string
    {
        if (!empty($education->bo_hoc_lop)) {
            return "bo_hoc_lop: {$education->bo_hoc_lop}";
        }
        if (!empty($education->hoc_xong_lop)) {
            return "hoc_xong_lop: {$education->hoc_xong_lop}";
        }
        if (!empty($education->education_block_id)) {
            $blockId = $education->education_block_id;
            $grade = $blockId >= 7 && $blockId <= 18 ? ($blockId - 6) : '?';
            return "education_block_id: {$blockId} (lớp {$grade})";
        }
        return "unknown";
    }

    /**
     * Trích xuất số lớp từ chuỗi
     * Ví dụ: "Lớp 6", "6", "Khối 6" → "6"
     * 
     * @param string $input
     * @return string|null
     */
    protected function extractGradeNumber(string $input): ?string
    {
        // Loại bỏ dấu và chuyển về ASCII
        $normalized = \Illuminate\Support\Str::ascii($input);
        
        // Tìm số 1-12
        if (preg_match('/\b(1[0-2]|[1-9])\b/', $normalized, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Áp dụng filter địa bàn vào query
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $geo ['province_id', 'ward_id', 'village_id']
     * @param string $scheme 'old' | 'new'
     */
    protected function applyGeoFilter($query, array $geo, string $scheme): void
    {
        // Join với person để lọc theo geo
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
     * Preview: Đếm số lượng đối tượng sẽ được cập nhật
     * 
     * @param array $filters
     * @return array ['total' => int, 'by_level' => array]
     */
    public function previewCount(array $filters = []): array
    {
        $query = PersonEducation::query()
            ->whereNull('graduation_level_id');

        // Kiểm tra nếu bảng có cột is_deleted
        if (\Schema::hasColumn('person_educations', 'is_deleted')) {
            $query->where('is_deleted', false);
        }

        if (!empty($filters['academic_year'])) {
            $query->where('academic_year', $filters['academic_year']);
        }

        if (!empty($filters['geo'])) {
            $this->applyGeoFilter($query, $filters['geo'], $filters['scheme'] ?? 'new');
        }

        $total = $query->count();

        // Đếm theo từng bậc (nếu cần)
        $byLevel = [];
        foreach (['TN Mầm non', 'TN Tiểu học', 'TN THCS'] as $levelName) {
            // Tạm thời để 0, có thể implement logic đếm chi tiết hơn nếu cần
            $byLevel[$levelName] = 0;
        }

        return [
            'total' => $total,
            'by_level' => $byLevel,
        ];
    }
}

