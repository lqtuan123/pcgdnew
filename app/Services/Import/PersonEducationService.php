<?php

namespace App\Services\Import;

use App\Models\PersonEducation;
use App\Models\School;
use App\Models\GeoUnit;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
class PersonEducationService
{
    public function __construct(protected PersonEducationMapper $mapper) {}

    /**
     * Upsert theo (person_id, academic_year)
     */
    public function upsertForPerson(int $personId, array $row, array $geo, string $scheme, string $academicYear): PersonEducation
    {
        //   Log::info('1 upsertForPerson',$geo);
        $attrs = $this->mapper->map($row, $geo, $scheme, $academicYear);
         $schoolName = trim((string)($row['school_name'] ?? ''));
        $schoolCode = trim((string)($row['school_code'] ?? ''));
         $hint = $schoolName ?: $schoolCode;
        //    Log::info('2 upsertForPerson',$geo);
        // Nếu mapper không xác định được trường -> ném lỗi để Orchestrator bắt và đẩy vào row_errors
        if (!array_key_exists('school_id', $attrs) || empty($attrs['school_id']) && !empty($row['school_name'] ?? null)) {
            // Gợi ý thêm bối cảnh cho thông báo (tùy chọn)
             $msg = 'Không tìm thấy trường';
            if ($hint !== '') {
                $msg .= ": {$hint}";
            }
//  Log::info('3 upsertForPerson');
//  Log::info($msg);
            throw ValidationException::withMessages([
                'school_id' => [$msg],
            ]);
        }
       
        // Chỉ tự động gán địa bàn theo trường đối với trường trong tỉnh (không có custom)
        if (empty($attrs['studying_district_custom']) && empty($attrs['school_name_custom'])) {
            $ward_code =  School::where('name',$schoolName)->where('code',$schoolCode)->first()?->ward_code_new;
            if($ward_code)
            {
                $ward = GeoUnit::where('code',$ward_code)->first();
                if ($ward) {
                    $attrs['studying_district_id'] = $ward->id;
                }
            }
        }
            //   Log::info('5 upsertForPerson',$geo);
        return PersonEducation::updateOrCreate(
            ['person_id' => $personId, 'academic_year' => $academicYear],
            array_merge($attrs, ['person_id' => $personId])
        );
    }
}
