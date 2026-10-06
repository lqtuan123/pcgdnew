<?php

namespace App\Services\Import;
use App\Services\Education\EducationGradeService;
use App\Services\Schools\SchoolService;
use App\Services\Schools\StudyAreaResolver;
class PersonEducationMapper
{
    public function __construct(protected EducationCatalogResolver $catalog, protected EducationGradeService $gradeService,protected SchoolService $schoolService, protected StudyAreaResolver $studyAreaResolver) {}

    protected function toBool(mixed $v): bool
    {
        if (is_bool($v)) return $v;
        $s = is_string($v) ? mb_strtolower(trim($v)) : $v;
        if ($s === '' || $s === null) return false;
        if (in_array($s, ['1','x','yes','y','true','t','co','có'], true)) return true;
        if (in_array($s, ['0','no','n','false','k','ko','khong','không'], true)) return false;
        if (is_numeric($s)) return ((int)$s) > 0;
        return false;
    }
    protected function toInt(mixed $v): ?int
    {
        return is_numeric($v) ? (int)$v : null;
    }

    /**
     * Map 1 dòng rows -> attributes cho PersonEducation::fillable
     */
    public function map(array $row,array $geo, String $scheme, string $academicYear): array
    {
        // $school = $this->catalog->resolveSchool($row['school_code'] ?? null, $row['school_name'] ?? null);
        $gradeRaw = $row['education_grade_name'] ?? '';
        
        $hocXongId = $this->gradeService->resolveId(
            $row['hoc_xong_lop'] ?? null           // có thể là "4", "Lớp 4", "5-6 tuổi", "Lá", ...
        );
        // khi map dữ liệu giáo dục:
        $studyingId = $this->studyAreaResolver->resolve(
            $scheme,
            $geo,
            $row['studying_district'] ?? null   // chuỗi tên tự do
        );
      
        $r = $this->schoolService->resolve(
                $scheme,
                $geo,
                $row['school_name'] ?? null,
                $row['school_code'] ?? null
                 
            );
        $schoolId = $r['id'];

        // Kiểm tra xem có phải trường ngoài tỉnh không
        $rawSchoolCode = strtoupper(trim((string)($row['school_code'] ?? '')));
        $resolvedCode  = strtoupper(trim((string)($r['code'] ?? '')));
        $isOutside = str_starts_with($rawSchoolCode, 'NGOAI_TINH') || str_starts_with($resolvedCode, 'NGOAI_TINH');

        $schoolNameCustom = null;
        $studyingDistrictCustom = null;

        if ($isOutside) {
            $rawSchoolName = trim((string)($row['school_name'] ?? ''));
            $rawStudyingDistrict = trim((string)($row['studying_district'] ?? ''));

            // Trường ngoài tỉnh: lấy đúng tên trường và phường/xã do người dùng tự nhập từ Excel
            $schoolNameCustom = $rawSchoolName !== '' ? $rawSchoolName : ($r['name'] ?? null);
            $studyingDistrictCustom = $rawStudyingDistrict !== '' ? $rawStudyingDistrict : null;
            // Không gán vào địa bàn trong tỉnh
            $studyingId = null;
        }

        $g = $this->catalog->parseEducationGradeName($gradeRaw);
       
        // echo '<br>'. $this->catalog->blockId($row['education_block_code'] ?? null);
        return [
            'education_block_id'         => $this->catalog->blockId($row['education_block_code'] ?? null),
            'education_grade_id'         => null,
            'class_label'                => $row['education_grade_name'] ?? null,

            'hoc_hai_buoi'               => $this->toBool($g['hoc_hai_buoi'] ?? false),
            'hoc_9_10_buoi_tuan'         => $this->toBool($g['hoc_9_10_buoi_tuan'] ?? false),
            'is_repeat_grade'            => $this->toBool($g['is_repeat_grade'] ?? false),

            'studying_district_id'       => $studyingId,
            'school_id'                  => $schoolId,
            'school_name_custom'         => $schoolNameCustom,
            'studying_district_custom'   => $studyingDistrictCustom,
            

            'graduation_level_id'        => $this->catalog->graduationLevelId($row['graduation_level_name'] ?? null),
            'is_bo_tuc'                  => $this->toBool($row['is_bo_tuc'] ?? false),
            'graduation_year'            => $row['graduation_year'] ?? null,

            'vocational_level_id'        => $this->catalog->vocationalLevelId($row['vocational_level_name'] ?? null),
            'vocational_grad_year'       => $row['vocational_grad_year'] ?? null,
            'graduation_certificate_no'  => $row['vocational_grad_cert_no'] ?? null,

            'hoc_xong_lop'               =>  $hocXongId ?? null,
            'hoc_xong_nam'               => $row['hoc_xong_nam'] ?? null,
            'bo_hoc_lop'                 => $this->toInt($row['bo_hoc_lop'] ?? null),
            'bo_hoc_nam'                 => $row['bo_hoc_nam'] ?? null,

            'academic_year'              => $academicYear,
            'note'                       => null,
        ];
    }
}
