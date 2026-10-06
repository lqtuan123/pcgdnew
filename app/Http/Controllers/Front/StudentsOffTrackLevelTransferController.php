<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\StudentsOffTrack;
use App\Models\School;
use App\Models\GeoUnit;
use App\Models\EducationBlock;
use App\Models\GraduationLevel;
use App\Models\LevelTransferLock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

use App\Services\AccessGuardService;

class StudentsOffTrackLevelTransferController extends Controller
{
    public function __construct(
        private AccessGuardService $guard
    ) {}

    /**
     * Hiển thị trang thực hiện lên lớp cho học sinh trái tuyến
     */
    public function index(Request $request)
    {
        // Enforce quyền lên lớp học sinh trái tuyến
        $ctx = $this->guard->enforce($request, 'len_lop_hoc_sinh_trai_tuyen', 'view', [
            'force_scheme' => null,
        ]);

        try {
            // Tính năm học hiện tại dựa trên thời gian thực
            $currentYear = date('Y');
            $currentMonth = date('n');
            
            if ($currentMonth >= 9) {
                $currentAcademicYear = $currentYear . '-' . ($currentYear + 1);
                $nextAcademicYear = ($currentYear + 1) . '-' . ($currentYear + 2);
            } else {
                $currentAcademicYear = ($currentYear - 1) . '-' . $currentYear;
                $nextAcademicYear = $currentYear . '-' . ($currentYear + 1);
            }

            // Tạo danh sách năm học từ 2000-2001 đến năm hiện tại +1
            $academicYears = $this->generateAcademicYearsList();

            return view('front.students-off-track.level-transfer', compact('currentAcademicYear', 'nextAcademicYear', 'academicYears'));
            
        } catch (\Exception $e) {
            $currentYear = date('Y');
            $currentMonth = date('n');
            
            if ($currentMonth >= 9) {
                $currentAcademicYear = $currentYear . '-' . ($currentYear + 1);
                $nextAcademicYear = ($currentYear + 1) . '-' . ($currentYear + 2);
            } else {
                $currentAcademicYear = ($currentYear - 1) . '-' . $currentYear;
                $nextAcademicYear = $currentYear . '-' . ($currentYear + 1);
            }
            
            $academicYears = $this->generateAcademicYearsList();
            $provincesOld = collect();
            $provincesNew = collect();
            
            return view('front.students-off-track.level-transfer', compact('currentAcademicYear', 'nextAcademicYear', 'academicYears', 'provincesOld', 'provincesNew'));
        }
    }

    /**
     * Thực hiện lên lớp cho học sinh trái tuyến
     */
    public function execute(Request $request)
    {
        // Enforce quyền thực hiện lên lớp học sinh trái tuyến
        $ctx = $this->guard->enforce($request, 'len_lop_hoc_sinh_trai_tuyen', 'view', [
            'force_scheme' => null,
        ]);
        
        // Nếu non-admin, ép province_id và ward_id từ địa bàn phân quyền
        if (!$ctx['isAdmin']) {
            if (!empty($ctx['geo']['province_id'])) {
                $request->merge(['province_id' => $ctx['geo']['province_id']]);
            }
            if (!empty($ctx['geo']['ward_id'])) {
                $request->merge(['ward_id' => $ctx['geo']['ward_id']]);
            }
        }

        try {
            $request->validate([
                'academic_year_from' => 'required|string',
                'academic_year_to' => 'required|string',
                'province_id' => 'required|integer|exists:geo_units,id',
                'district_id' => 'nullable|integer|exists:geo_units,id',
                'ward_id' => 'nullable|integer|exists:geo_units,id',
                'scheme' => 'required|in:old,new',
                'school_mappings' => 'required|array',
                'school_mappings.*.level' => 'required|in:kindergarten,primary,junior,high',
                'school_mappings.*.school_id' => 'required|integer|min:0',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        }

        try {
            // Kiểm tra khóa theo phạm vi và năm học
            $locked = $this->isLockedForScope(
                $request->input('academic_year_to'),
                $request->input('province_id'),
                $request->input('district_id'),
                $request->input('ward_id'),
                $request->input('school_id')
            );
            if ($locked) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chức năng lên lớp học sinh trái tuyến đã bị khóa cho phạm vi/năm học này.'
                ], 423);
            }

            DB::beginTransaction();

            $result = $this->executeLevelTransfer($request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Thực hiện lên lớp thành công',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Students off track level transfer error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách trường theo cấp học và địa bàn
     */
    public function getSchoolsByLevel(Request $request)
    {
        // Kiểm tra quyền lên lớp học sinh trái tuyến
        $user = Auth::user();
        if (!$user || !$user->hasPermission('len_lop_hoc_sinh_trai_tuyen', 'view')) {
            return response()->json(['error' => 'Bạn không có quyền truy cập chức năng này.'], 403);
        }

        $request->validate([
            'province_id' => 'required|integer|exists:geo_units,id',
            'district_id' => 'nullable|integer|exists:geo_units,id',
            'ward_id' => 'nullable|integer|exists:geo_units,id',
            'scheme' => 'required|in:old,new',
            'level' => 'required|in:kindergarten,primary,junior,high',
        ]);

        $provinceId = $request->province_id;
        $districtId = $request->district_id;
        $wardId = $request->ward_id;
        $scheme = $request->scheme;
        $level = $request->level;

        // Lấy thông tin địa bàn
        $province = GeoUnit::find($provinceId);
        $district = $districtId ? GeoUnit::find($districtId) : null;
        $ward = $wardId ? GeoUnit::find($wardId) : null;

        if (!$province) {
            return response()->json([]);
        }

        // Xây dựng query
        $query = School::active();

        // Mapping level names to educational level IDs
        $levelMappings = [
            'kindergarten' => ['Tiểu học', 'Tiểu học + THCS', 'Tiểu học + THCS + THPT'],
            'primary' => ['THCS', 'Trung học cơ sở', 'Tiểu học + THCS + THPT', 'THCS + THPT', 'Tiểu học + THCS'],
            'junior' => ['THCS + THPT', 'Tiểu học + THCS + THPT', 'THPT', 'Trung học phổ thông'],
            'high' => ['THCS + THPT', 'Tiểu học + THCS + THPT', 'THPT', 'Trung học phổ thông']
        ];

        $possibleNames = $levelMappings[$level] ?? [$level];

        // Lọc theo cấp học
        $query->whereHas('educationalLevel', function($q) use ($possibleNames) {
            $q->whereIn('name', $possibleNames);
        });

        // Lọc theo địa bàn
        if ($ward && $scheme === 'old') {
            $query->where('ward_code', $ward->code);
        } elseif ($district && $scheme === 'old') {
            $query->where('district_code', $district->code);
        } elseif ($province && $scheme === 'old') {
            $query->where('province_code', $province->code);
        } elseif ($ward && $scheme === 'new') {
            $query->where('ward_code_new', $ward->code);
        } elseif ($province && $scheme === 'new') {
            $query->where('province_code_new', $province->code);
        }

        $schools = $query->orderBy('name')->get(['id', 'code', 'name']);

        return response()->json($schools);
    }

    /**
     * Lấy danh sách địa bàn con
     */
    public function getGeoUnits(Request $request)
    {
        // Kiểm tra quyền lên lớp học sinh trái tuyến
        $user = Auth::user();
        if (!$user || !$user->hasPermission('len_lop_hoc_sinh_trai_tuyen', 'view')) {
            return response()->json(['error' => 'Bạn không có quyền truy cập chức năng này.'], 403);
        }

        $request->validate([
            'parent_id' => 'required|integer|exists:geo_units,id',
            'type_level' => 'required|integer|in:2,3,4',
            'scheme' => 'nullable|string|in:old,new'
        ]);

        $scheme = $request->input('scheme', 'old');
        
        $units = GeoUnit::where('parent_id', $request->input('parent_id'))
            ->where('type_level', $request->input('type_level'))
            ->where('scheme', $scheme)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json($units);
    }

    /**
     * Thực hiện logic lên lớp cho học sinh trái tuyến
     */
    private function executeLevelTransfer(array $data): array
    {
        $result = [
            'total_processed' => 0,
            'total_updated' => 0,
            'total_created' => 0,
            'errors' => [],
            'details' => []
        ];

        $academicYearFrom = $data['academic_year_from'];
        $academicYearTo = $data['academic_year_to'];
        $scheme = $data['scheme'];
        $schoolMappings = $data['school_mappings'];

        // Lấy thông tin địa bàn
        $geoInfo = $this->getGeoInfo($data);

        // Xử lý từng cấp học
        foreach ($schoolMappings as $mapping) {
            $level = $mapping['level'];
            $schoolId = $mapping['school_id'];

            $levelResult = $this->processLevelTransfer($academicYearFrom, $academicYearTo, $level, $schoolId, $geoInfo, $scheme);
            
            $result['total_processed'] += $levelResult['processed'];
            $result['total_updated'] += $levelResult['updated'];
            $result['total_created'] += $levelResult['created'];
            $result['errors'] = array_merge($result['errors'], $levelResult['errors']);
            $result['details'][] = $levelResult;
        }

        return $result;
    }

    /**
     * Xử lý lên lớp cho một cấp học cụ thể
     */
    private function processLevelTransfer(string $academicYearFrom, string $academicYearTo, string $level, int $schoolId, array $geoInfo, string $scheme): array
    {
        $result = [
            'level' => $level,
            'school_id' => $schoolId,
            'processed' => 0,
            'updated' => 0,
            'created' => 0,
            'errors' => []
        ];

        // Lấy học sinh trái tuyến trong năm học hiện tại
        $query = StudentsOffTrack::where('academic_year', $academicYearFrom)
            ->where('is_deleted', false);

        // Lọc theo địa bàn dựa vào selected_mã tương ứng
        $this->applyGeoFilter($query, $geoInfo, $scheme);

        // Lọc theo cấp học dựa trên education_block_id
        $blockIds = $this->getBlockIdsForLevel($level);
        if (!empty($blockIds)) {
            $query->whereIn('education_block_id', $blockIds);
        }

        $students = $query->with(['educationBlock', 'school'])->get();

        foreach ($students as $student) {
            try {
                $educationBlock = $student->educationBlock;
                
                if (!$educationBlock) {
                    $result['errors'][] = [
                        'student_id' => $student->id,
                        'error' => 'Không tìm thấy khối học'
                    ];
                    continue;
                }

                // Kiểm tra xem có phải khối cần chuyển cấp không
                $isGraduationLevel = $this->isGraduationLevel($educationBlock->name);
                
                if ($isGraduationLevel) {
                    // Khối tốt nghiệp: chuyển cấp và đổi trường
                    $created = $this->processGraduationLevelTransfer($student, $academicYearTo, $schoolId, $level, $academicYearFrom);
                    if ($created) {
                        $result['created']++;
                    } else {
                        $result['updated']++;
                    }
                } else {
                    // Khối không tốt nghiệp: lên lớp trong cùng trường (chỉ tăng education_block_id)
                    $created = $this->processRegularLevelUp($student, $academicYearTo);
                    if ($created) {
                        $result['created']++;
                    } else {
                        $result['updated']++;
                    }
                }

                $result['processed']++;
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'student_id' => $student->id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $result;
    }

    /**
     * Xử lý lên lớp thông thường (không chuyển cấp)
     * @return bool true nếu tạo mới, false nếu cập nhật
     */
    private function processRegularLevelUp(StudentsOffTrack $student, string $academicYearTo): bool
    {
        // Kiểm tra xem đã có bản ghi cho năm học tiếp theo chưa
        // Sử dụng logic giống import Excel: identity_number (ưu tiên) hoặc ho_dem + ten + dob + ethnicity_id
        $existingStudent = null;
        
        // Ưu tiên kiểm tra bằng identity_number nếu có
        if (!empty($student->identity_number)) {
            $existingStudent = StudentsOffTrack::where('academic_year', $academicYearTo)
                ->where('identity_number', $student->identity_number)
                ->where('is_deleted', false)
                ->first();
        }
        
        // Nếu không tìm thấy hoặc identity_number rỗng, dùng combination khác
        if (!$existingStudent) {
            $existingStudent = StudentsOffTrack::where('academic_year', $academicYearTo)
                ->where('ho_dem', $student->ho_dem)
                ->where('ten', $student->ten)
                ->where('dob_day', $student->dob_day)
                ->where('dob_month', $student->dob_month)
                ->where('dob_year', $student->dob_year)
                ->where('ethnicity_id', $student->ethnicity_id)
                ->where('is_deleted', false)
                ->first();
        }
        
        // Tìm khối học tiếp theo theo thứ tự đúng
        $nextBlockId = $this->getNextEducationBlockId($student->education_block_id);
        
        if ($existingStudent) {
            // Cập nhật bản ghi hiện có
            if ($nextBlockId) {
                $existingStudent->education_block_id = $nextBlockId;
            }
            $existingStudent->school_id = $student->school_id;
            $existingStudent->graduation_level_id = $student->graduation_level_id;
            $existingStudent->class_label = $this->updateClassLabel($student->class_label);
            $existingStudent->save();
            return false; // Cập nhật
        } else {
            // Tạo bản ghi mới cho năm học tiếp theo với tất cả thông tin
            $newStudentData = $student->toArray();
            
            // Cập nhật thông tin cho năm học mới
            $newStudentData['academic_year'] = $academicYearTo;
            $newStudentData['id'] = null; // Để tạo mới
            $newStudentData['created_at'] = null;
            $newStudentData['updated_at'] = null;
            
            // Tăng education_block_id theo thứ tự khối học
            if ($nextBlockId) {
                $newStudentData['education_block_id'] = $nextBlockId;
            }
            
            // Cập nhật tên lớp
            $newStudentData['class_label'] = $this->updateClassLabel($student->class_label);
            
            // Giữ nguyên school_id và graduation_level_id
            $newStudentData['school_id'] = $student->school_id;
            $newStudentData['graduation_level_id'] = $student->graduation_level_id;
            
            // Tạo bản ghi mới
            StudentsOffTrack::create($newStudentData);
            return true; // Tạo mới
        }
    }

    /**
     * Xử lý chuyển cấp cho khối tốt nghiệp
     * @return bool true nếu tạo mới, false nếu cập nhật
     */
    private function processGraduationLevelTransfer(StudentsOffTrack $student, string $academicYearTo, int $newSchoolId, string $level, string $academicYearFrom): bool
    {
        // Kiểm tra xem đã có bản ghi cho năm học tiếp theo chưa
        // Sử dụng logic giống import Excel: identity_number (ưu tiên) hoặc ho_dem + ten + dob + ethnicity_id
        $existingStudent = null;
        
        // Ưu tiên kiểm tra bằng identity_number nếu có
        if (!empty($student->identity_number)) {
            $existingStudent = StudentsOffTrack::where('academic_year', $academicYearTo)
                ->where('identity_number', $student->identity_number)
                ->where('is_deleted', false)
                ->first();
        }
        
        // Nếu không tìm thấy hoặc identity_number rỗng, dùng combination khác
        if (!$existingStudent) {
            $existingStudent = StudentsOffTrack::where('academic_year', $academicYearTo)
                ->where('ho_dem', $student->ho_dem)
                ->where('ten', $student->ten)
                ->where('dob_day', $student->dob_day)
                ->where('dob_month', $student->dob_month)
                ->where('dob_year', $student->dob_year)
                ->where('ethnicity_id', $student->ethnicity_id)
                ->where('is_deleted', false)
                ->first();
        }
        
        if ($existingStudent) {
            // Cập nhật bản ghi hiện có
            if ($newSchoolId > 0) {
                $existingStudent->school_id = $newSchoolId;
            } else {
                // school_id = 0 nghĩa là tốt nghiệp, không cần school_id
                $existingStudent->school_id = null;
            }
            $this->updateGraduationLevelAndBlock($existingStudent, $level, $academicYearFrom, $student);
            $existingStudent->class_label = $this->getNewClassLabel($level);
            $existingStudent->save();
            return false; // Cập nhật
        } else {
            // Tạo bản ghi mới cho năm học tiếp theo với tất cả thông tin
            $newStudentData = $student->toArray();
            
            // Cập nhật thông tin cho năm học mới
            $newStudentData['academic_year'] = $academicYearTo;
            $newStudentData['id'] = null; // Để tạo mới
            $newStudentData['created_at'] = null;
            $newStudentData['updated_at'] = null;
            
            // Đổi trường
            if ($newSchoolId > 0) {
                $newStudentData['school_id'] = $newSchoolId;
            } else {
                // school_id = 0 nghĩa là tốt nghiệp, không cần school_id
                $newStudentData['school_id'] = null;
            }
            
            // Cập nhật bậc tốt nghiệp và khối học
            $this->updateGraduationLevelAndBlockData($newStudentData, $level, $academicYearFrom, $student);
            $newStudentData['class_label'] = $this->getNewClassLabel($level);
            
            // Tạo bản ghi mới
            StudentsOffTrack::create($newStudentData);
            return true; // Tạo mới
        }
    }

    /**
     * Cập nhật bậc tốt nghiệp và khối học cho học sinh chuyển cấp
     */
    private function updateGraduationLevelAndBlock(StudentsOffTrack $newStudent, string $level, string $academicYearFrom, StudentsOffTrack $originalStudent = null): void
    {
        // Lấy khối học của năm học cũ để xác định chuyển cấp nào
        $currentBlock = null;
        if ($originalStudent) {
            $currentBlock = EducationBlock::find($originalStudent->education_block_id);
        } else {
            // Fallback: lấy khối học hiện tại
            $currentBlock = EducationBlock::find($newStudent->education_block_id);
        }
        
        if (!$currentBlock) {
            return;
        }

        // Mapping từ khối học hiện tại sang khối học tiếp theo
        $blockMappings = [
            '5-6 tuổi' => [
                'next_graduation_level' => 1, // MN (bậc tốt nghiệp MN)
                'next_block_name' => '1' // Lớp 1
            ],
            '5' => [
                'next_graduation_level' => 2, // TH (bậc tốt nghiệp TH)
                'next_block_name' => '6' // Lớp 6
            ],
            '9' => [
                'next_graduation_level' => 3, // THCS (bậc tốt nghiệp THCS)
                'next_block_name' => '10' // Lớp 10
            ],
            '12' => [
                'next_graduation_level' => 4, // THPT (bậc tốt nghiệp THPT)
                'next_block_name' => null // Tốt nghiệp, không có lớp tiếp theo
            ]
        ];

        $mapping = $blockMappings[$currentBlock->name] ?? null;
        
        if ($mapping) {
            // Cập nhật graduation_level_id
            $newStudent->graduation_level_id = $mapping['next_graduation_level'];
            
            // Cập nhật năm tốt nghiệp (năm học hiện tại - năm học cũ)
            $newStudent->graduation_year = $academicYearFrom;
            
            // Cập nhật education_block_id cho lớp đầu tiên của cấp học mới
            if ($mapping['next_block_name']) {
                $nextBlock = EducationBlock::where('name', $mapping['next_block_name'])->first();
                if ($nextBlock) {
                    $newStudent->education_block_id = $nextBlock->id;
                }
            }
        }
    }

    /**
     * Cập nhật bậc tốt nghiệp và khối học cho học sinh chuyển cấp (array data)
     */
    private function updateGraduationLevelAndBlockData(array &$newStudentData, string $level, string $academicYearFrom, StudentsOffTrack $originalStudent = null): void
    {
        // Lấy khối học của năm học cũ để xác định chuyển cấp nào
        $currentBlock = null;
        if ($originalStudent) {
            $currentBlock = EducationBlock::find($originalStudent->education_block_id);
        } else {
            // Fallback: lấy khối học hiện tại
            $currentBlock = EducationBlock::find($newStudentData['education_block_id']);
        }
        
        if (!$currentBlock) {
            return;
        }

        // Mapping từ khối học hiện tại sang khối học tiếp theo
        $blockMappings = [
            '5-6 tuổi' => [
                'next_graduation_level' => 1, // MN (bậc tốt nghiệp MN)
                'next_block_name' => '1' // Lớp 1
            ],
            '5' => [
                'next_graduation_level' => 2, // TH (bậc tốt nghiệp TH)
                'next_block_name' => '6' // Lớp 6
            ],
            '9' => [
                'next_graduation_level' => 3, // THCS (bậc tốt nghiệp THCS)
                'next_block_name' => '10' // Lớp 10
            ],
            '12' => [
                'next_graduation_level' => 4, // THPT (bậc tốt nghiệp THPT)
                'next_block_name' => null // Tốt nghiệp, không có lớp tiếp theo
            ]
        ];

        $mapping = $blockMappings[$currentBlock->name] ?? null;
        
        if ($mapping) {
            // Cập nhật graduation_level_id
            $newStudentData['graduation_level_id'] = $mapping['next_graduation_level'];
            
            // Cập nhật năm tốt nghiệp (năm học hiện tại - năm học cũ)
            $newStudentData['graduation_year'] = $academicYearFrom;
            
            // Cập nhật education_block_id cho lớp đầu tiên của cấp học mới
            if ($mapping['next_block_name']) {
                $nextBlock = EducationBlock::where('name', $mapping['next_block_name'])->first();
                if ($nextBlock) {
                    $newStudentData['education_block_id'] = $nextBlock->id;
                }
            }
        }
    }

    /**
     * Kiểm tra xem có phải khối chuyển cấp không (5-6 tuổi, lớp 5, lớp 9, lớp 12)
     */
    private function isGraduationLevel(?string $blockName): bool
    {
        if (!$blockName) return false;
        
        // Có 4 khối chuyển cấp: 5-6 tuổi → lớp 1, lớp 5 → lớp 6, lớp 9 → lớp 10, lớp 12 → tốt nghiệp
        $graduationBlocks = ['5-6 tuổi', '5', '9', '12'];
        return in_array($blockName, $graduationBlocks);
    }

    /**
     * Lấy danh sách block IDs cho một cấp học
     */
    private function getBlockIdsForLevel(string $level): array
    {
        $levelBlockMappings = [
            'kindergarten' => ['3-12 tháng', '13-24 tháng', '25-36 tháng', '3-4 tuổi', '4-5 tuổi', '5-6 tuổi'], // Mầm non
            'primary' => ['1', '2', '3', '4', '5'], // Tiểu học
            'junior' => ['6', '7', '8', '9'], // THCS
            'high' => ['10', '11', '12'] // THPT
        ];

        $blockNames = $levelBlockMappings[$level] ?? [];
        
        if (empty($blockNames)) {
            return [];
        }

        return EducationBlock::whereIn('name', $blockNames)->pluck('id')->toArray();
    }

    /**
     * Tìm khối học tiếp theo theo thứ tự đúng
     */
    private function getNextEducationBlockId(?int $currentBlockId): ?int
    {
        if (!$currentBlockId) {
            return null;
        }

        $currentBlock = EducationBlock::find($currentBlockId);
        if (!$currentBlock) {
            return null;
        }

        // Thứ tự đúng của tất cả khối học
        $allBlocks = [
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

        $currentIndex = array_search($currentBlock->name, $allBlocks);
        
        if ($currentIndex !== false && isset($allBlocks[$currentIndex + 1])) {
            $nextBlockName = $allBlocks[$currentIndex + 1];
            $newBlock = EducationBlock::where('name', $nextBlockName)->first();
            return $newBlock ? $newBlock->id : null;
        }

        return null; // Không có khối tiếp theo (tốt nghiệp)
    }

    /**
     * Lấy thông tin địa bàn từ request
     */
    private function getGeoInfo(array $data): array
    {
        return [
            'province_id' => $data['province_id'],
            'district_id' => $data['district_id'] ?? null,
            'ward_id' => $data['ward_id'] ?? null,
        ];
    }

    /**
     * Áp dụng bộ lọc địa bàn cho query
     */
    private function applyGeoFilter($query, array $geoInfo, string $scheme): void
    {
        $provinceId = $geoInfo['province_id'];
        $districtId = $geoInfo['district_id'];
        $wardId = $geoInfo['ward_id'];

        // Lấy code từ id của GeoUnit
        $provinceCode = GeoUnit::find($provinceId)?->code;
        $districtCode = $districtId ? GeoUnit::find($districtId)?->code : null;
        $wardCode = $wardId ? GeoUnit::find($wardId)?->code : null;

        if ($scheme === 'old') {
            if ($wardCode) {
                $query->where('selected_ward_code_old', $wardCode);
            } elseif ($districtCode) {
                $query->where('selected_district_code_old', $districtCode);
            } elseif ($provinceCode) {
                $query->where('selected_province_code_old', $provinceCode);
            }
        } else {
            if ($wardCode) {
                $query->where('selected_ward_code_new', $wardCode);
            } elseif ($provinceCode) {
                $query->where('selected_province_code_new', $provinceCode);
            }
        }
    }

    /**
     * Cập nhật tên lớp cho học sinh lên lớp thông thường
     */
    private function updateClassLabel(?string $currentClassLabel): ?string
    {
        if (!$currentClassLabel) {
            return null;
        }

        // Kiểm tra xem lớp có bắt đầu bằng số không
        if (preg_match('/^\d/', $currentClassLabel)) {
            // Lớp có số: tăng số lớp lên 1 (ví dụ: 6A1 -> 7A1, 9B2 -> 10B2)
            return preg_replace_callback('/(\d+)([A-Z]\d*)/', function($matches) {
                $classNumber = (int)$matches[1] + 1;
                return $classNumber . $matches[2];
            }, $currentClassLabel);
        } else {
            // Lớp không có số: bỏ qua, không thay đổi
            return $currentClassLabel;
        }
    }

    /**
     * Lấy tên lớp mới cho học sinh chuyển cấp
     */
    private function getNewClassLabel(string $level): ?string
    {
        // Mapping từ cấp học sang tên lớp mặc định
        $levelClassMappings = [
            'kindergarten' => '1', // 5-6 tuổi -> Tiểu học lớp 1
            'primary' => '6', // Lớp 5 -> THCS lớp 6
            'junior' => '10', // Lớp 9 -> THPT lớp 10
            'high' => null // Lớp 12 -> Tốt nghiệp
        ];

        return $levelClassMappings[$level] ?? null;
    }

    /**
     * Kiểm tra xem có bị khóa không theo phạm vi và năm học
     */
    private function isLockedForScope(string $academicYear, ?int $provinceId, ?int $districtId, ?int $wardId, ?int $schoolId = null): bool
    {
        // Kiểm tra khóa trái tuyến (trai_tuyen) cho lên lớp học sinh trái tuyến
        // Ưu tiên khóa theo phạm vi hẹp nhất → rộng hơn (tỉnh, xã, trường)
        if ($schoolId && LevelTransferLock::where([
            'scope_type' => 'school', 
            'scope_id' => $schoolId, 
            'academic_year' => $academicYear,
            'lock_type' => 'trai_tuyen'
        ])->exists()) {
            return true;
        }
        if ($wardId && LevelTransferLock::where([
            'scope_type' => 'ward', 
            'scope_id' => $wardId, 
            'academic_year' => $academicYear,
            'lock_type' => 'trai_tuyen'
        ])->exists()) {
            return true;
        }
        if ($provinceId && LevelTransferLock::where([
            'scope_type' => 'province', 
            'scope_id' => $provinceId, 
            'academic_year' => $academicYear,
            'lock_type' => 'trai_tuyen'
        ])->exists()) {
            return true;
        }
        return false;
    }

    /**
     * Khóa chức năng lên lớp học sinh trái tuyến
     */
    public function lock(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Chưa đăng nhập.'], 401);
        }
        
        $data = $request->validate([
            'academic_year' => 'required|string',
            'scope_type' => 'required|string|in:province,ward,school',
            'scope_id' => 'required|integer',
        ]);

        // Kiểm tra quyền địa giới của user
        if (!$this->hasGeoPermission($user, $data['scope_type'], $data['scope_id'])) {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền khóa ở địa bàn này.'], 403);
        }

        // Kiểm tra xem đã có khóa nào cho phạm vi này chưa (cùng loại khóa trai_tuyen)
        $existingLock = LevelTransferLock::where([
            'scope_type' => $data['scope_type'],
            'scope_id' => $data['scope_id'],
            'academic_year' => $data['academic_year'],
            'lock_type' => 'trai_tuyen',
        ])->first();

        if ($existingLock) {
            return response()->json(['success' => false, 'message' => 'Địa bàn này đã bị khóa bởi người khác.'], 400);
        }

        $lock = LevelTransferLock::create([
            'scope_type' => $data['scope_type'],
            'scope_id' => $data['scope_id'],
            'academic_year' => $data['academic_year'],
            'lock_type' => 'trai_tuyen',
            'locked_by' => $user->id,
            'locked_at' => now(),
        ]);

        return response()->json(['success' => true, 'data' => $lock]);
    }

    /**
     * Mở khóa chức năng lên lớp học sinh trái tuyến
     */
    public function unlock(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Chưa đăng nhập.'], 401);
        }
        
        $data = $request->validate([
            'academic_year' => 'required|string',
            'scope_type' => 'required|string|in:province,ward,school',
            'scope_id' => 'required|integer',
        ]);

        $lock = LevelTransferLock::where([
            'scope_type' => $data['scope_type'],
            'scope_id' => $data['scope_id'],
            'academic_year' => $data['academic_year'],
            'lock_type' => 'trai_tuyen',
        ])->first();

        if (!$lock) {
            return response()->json(['success' => true]);
        }
        
        if ($lock->locked_by !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Chỉ người đã khóa mới mở khóa được.'], 403);
        }

        $lock->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Kiểm tra trạng thái khóa
     */
    public function lockStatus(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Chưa đăng nhập.'], 401);
        }
        
        $data = $request->validate([
            'academic_year' => 'required|string',
            'scope_type' => 'required|string|in:province,ward,school',
            'scope_id' => 'required|integer',
        ]);

        $lock = LevelTransferLock::where([
            'scope_type' => $data['scope_type'],
            'scope_id' => $data['scope_id'],
            'academic_year' => $data['academic_year'],
            'lock_type' => 'trai_tuyen',
        ])->with('lockedByUser')->first();

        if ($lock) {
            return response()->json([
                'locked' => true, 
                'locked_by' => $lock->locked_by,
                'locked_at' => $lock->locked_at,
                'locked_by_user' => [
                    'name' => $lock->lockedByUser->name ?? 'N/A',
                    'email' => $lock->lockedByUser->email ?? 'N/A'
                ]
            ]);
        }

        return response()->json(['locked' => false]);
    }

    /**
     * Kiểm tra quyền địa giới của user
     */
    private function hasGeoPermission($user, $scopeType, $scopeId)
    {
        // Lấy thông tin địa giới của user
        $userGeo = $this->getUserGeoInfo($user);
        
        if (!$userGeo) {
            return false; // User không có thông tin địa giới
        }

        // Kiểm tra quyền theo phạm vi (tỉnh, xã, trường)
        switch ($scopeType) {
            case 'province':
                return $userGeo['province_id'] == $scopeId;
            case 'ward':
                return $userGeo['ward_id'] == $scopeId;
            case 'school':
                // Kiểm tra quyền trường
                return $userGeo['school_id'] == $scopeId;
            default:
                return false;
        }
    }

    /**
     * Lấy thông tin địa giới của user
     */
    private function getUserGeoInfo($user)
    {
        // Sử dụng method getGeoInfo() của User model
        return $user->getGeoInfo();
    }

    /**
     * Tạo danh sách năm học từ 2000-2001 đến năm hiện tại +1
     */
    private function generateAcademicYearsList(): array
    {
        $academicYears = [];
        $currentYear = date('Y');
        
        // Tạo từ năm 2000 đến năm hiện tại +1
        for ($year = 2000; $year <= $currentYear + 1; $year++) {
            $academicYear = $year . '-' . ($year + 1);
            $academicYears[] = $academicYear;
        }
        
        // Sắp xếp theo thứ tự giảm dần (năm mới nhất trước)
        return array_reverse($academicYears);
    }
}
