<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Services\GeoService;
use App\Services\ReportFormatService;

/**
 * Report: Thống kê trình độ văn hóa đối tượng nữ
 * (Thống kê theo độ tuổi và giới tính nữ)
 * 
 * - Handler hiển thị danh sách: list(Request $req)
 * - Handler xuất Excel:        export(Request $req)
 */
class TDVHDoiTuongNuReport extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    /**
     * Hiển thị danh sách (phân trang)
     */
    public function list(Request $req)
    {
        // Nếu có parameter export=1, gọi export thay vì hiển thị view
        if ($req->filled('export') && $req->input('export') == '1') {
            return $this->export($req);
        }

        // Build query
        ['qb' => $qb, 'context' => $ctx] = $this->tdvhDoiTuongNuQuery($req);

        // Lấy dữ liệu trang hiện tại
        $rows  = $qb->paginate($ctx['perPage'])->withQueryString();
        $items = collect($rows->items())->map(fn($r) => $this->mapPersonRow($r, $ctx['scheme'], $ctx['year']));

        // Render view
        return view('front.reports.tdvh_doi_tuong_nu_list', [
            'rows'             => $rows,
            'items'            => $items,
            'year'             => $ctx['year'],
            'scheme'           => $ctx['scheme'],
            'geo'              => $ctx['geo'],
            'sort_by'          => $ctx['sortBy'],
            'sort_dir'         => $ctx['sortDir'],
            'per_page'         => $ctx['perPage'],
            'report_group_id'  => (int) $req->report_group_id,
            'report_detail_id' => (int) $req->report_detail_id,
            'so_phieu'         => $req->input('so_phieu',''),
            'q'                => $req->input('q',''),
        ]);
    }

    /**
     * Xuất Excel thống kê trình độ văn hóa đối tượng nữ
     */
    public function export(Request $req)
    {
        // Build query
        ['qb' => $qb, 'context' => $ctx] = $this->tdvhDoiTuongNuQuery($req);

        // Lấy toàn bộ dữ liệu
        $coll = $qb->get();
        $rows = $coll->map(fn($r) => (object) $this->mapPersonRow($r, $ctx['scheme'], $ctx['year']));

        // Chọn template theo scheme
        $cfg = $this->templateCfgForScheme($ctx['scheme']);
        $wb  = IOFactory::load($cfg['path']);
        $sh  = $wb->getActiveSheet();

        // Header meta (tên tỉnh/huyện/xã từ geo filter)
        $meta = $this->metaFromGeoFilter($ctx['geo'], $ctx['scheme']);

        // Điền thông tin header
        if (!empty($cfg['header']['province'])) {
            $sh->setCellValue($cfg['header']['province'], $meta['province']);
        }
        if (!empty($cfg['header']['ward'])) {
            $sh->setCellValue($cfg['header']['ward'], $meta['ward']);
        }
        if (!empty($cfg['header']['year'])) {
            $sh->setCellValue($cfg['header']['year'], $ctx['year']);
        }
        
        // Thiết lập font size cho header
        $sh->getStyle('C1:C3')->getFont()->setName('Tahoma')->setSize(8);

        // Thống kê theo độ tuổi và ghi vào các ô tương ứng
        $this->fillAgeStatistics($sh, $rows, $cfg, $ctx['year']);

        // Ghi đè footer từ cột AD đến AL với thông tin xã, ngày, tháng, năm hiện tại
        $wardName = $meta['ward'] ?? 'Xã/Phường';
        $currentDate = date('d');
        $currentMonth = date('m');
        $currentYear = date('Y');
        
        // Tìm vị trí footer (thường ở hàng 77-80)
        $footerRow = 78;
        
        // Merge các ô từ AD đến AL
        $sh->mergeCells('AD'.$footerRow.':AL'.$footerRow);
        $sh->setCellValue('AD'.$footerRow, $wardName . ', ngày ' . $currentDate . ' tháng ' . $currentMonth . ' năm ' . $currentYear);
        $sh->getStyle('AD'.$footerRow.':AL'.$footerRow)->getFont()->setName('Tahoma')->setSize(8)->setItalic(true);
        $sh->getStyle('AD'.$footerRow.':AL'.$footerRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Xuất file
        return response()->streamDownload(function () use ($wb) {
            $writer = IOFactory::createWriter($wb, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Query thống kê trình độ văn hóa đối tượng nữ
     */
    protected function tdvhDoiTuongNuQuery(Request $req): array
    {
        $year     = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        $scheme   = (string) $req->input('scheme', 'new');
        $geo      = (array)  $req->input('geo', []);
        $q        = trim((string) $req->input('q', ''));
        $soPhieu  = trim((string) $req->input('so_phieu', ''));
        $perPage  = (int) $req->input('per_page', 50);
        $sortBy   = (string) $req->input('sort_by', 'full_name');
        $sortDir  = strtolower((string) $req->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $qb = DB::table('survey_form_details as d')
            ->join('survey_forms as f', function($j){
                $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
            })
            ->join('people as p', 'p.id', '=', 'd.person_id')
            
            // Địa chỉ nơi cư trú
            ->leftJoin('geo_units as res_province', 'res_province.id', '=', 
                $scheme === 'old' ? 'f.old_province_id' : 'f.new_province_id')
            ->leftJoin('geo_units as res_ward', 'res_ward.id', '=', 
                $scheme === 'old' ? 'f.old_ward_id' : 'f.new_ward_id')
            
            // Join dân tộc và khuyết tật
            ->leftJoin('ethnicities as eth', 'eth.id', '=', 'p.ethnicity_id')
            ->leftJoin('person_disabilities as pd', 'pd.id', '=', 'd.person_disability_id')
            
            // Join thông tin giáo dục
            ->leftJoin('person_educations as pe', 'pe.id', '=', 'd.person_education_id')
            ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id')
            ->leftJoin('schools as sch', function($j) {
                $j->on('sch.id', '=', 'pe.school_id')
                  ->where(function($w) {
                      $w->whereNull('sch.is_dissolved')
                        ->orWhere('sch.is_dissolved', false)
                        ->orWhere('sch.is_dissolved', 0);
                  });
            })
            ->leftJoin('graduation_levels as gl', 'gl.id', '=', 'pe.graduation_level_id')
            
            ->where('d.is_deleted', false); // Bỏ điều kiện chỉ lấy nữ để tính tổng cả nam và nữ

        // Lọc theo số phiếu
        if ($soPhieu !== '') {
            $qb->where('f.so_phieu', 'like', '%'.$soPhieu.'%');
        }

        // Lọc theo năm học
        if ($year !== 'all') {
            $qb->where('d.academic_year', $year);
        }

        // Lọc địa bàn theo scheme
        $this->applyGeoFilter($qb, $scheme, $geo);

        // Tìm kiếm nhanh
        if ($q !== '') {
            $like = '%'.$q.'%';
            $qb->where(function($w) use ($like) {
                $w->where('p.ten', 'like', $like)
                  ->orWhereRaw("CONCAT(TRIM(COALESCE(p.ho_dem,'')),' ',COALESCE(p.ten,'')) like ?", [$like])
                  ->orWhere('f.so_phieu', 'like', $like);
            });
        }

        // Select các cột cần thiết
        $qb->selectRaw("
            d.id as detail_id,
            f.so_phieu,
            p.is_female,
            CONCAT(TRIM(COALESCE(p.ho_dem,'')),
                CASE WHEN p.ho_dem IS NOT NULL AND p.ten IS NOT NULL THEN ' ' ELSE '' END,
                COALESCE(p.ten,'')) as full_name,
            p.dob_year,
            res_province.name as residence_province,
            res_ward.name as residence_ward,
            eth.name as ethnicity_name,
            CASE WHEN pd.id IS NOT NULL THEN 1 ELSE 0 END as has_disability,
            pe.class_label,
            eb.name as education_block,
            sch.name as school_name,
            pe.graduation_level_id,
            pe.graduation_year,
            pe.hoc_xong_lop,
            pe.bo_hoc_lop
        ");

        // Sắp xếp
        $sortMap = [
            'full_name'   => 'full_name',
            'dob_year'    => 'p.dob_year',
            'so_phieu'    => 'f.so_phieu',
        ];
        $orderCol = $sortMap[$sortBy] ?? 'full_name';
        $qb->orderBy($orderCol, $sortDir);

        return [
            'qb'      => $qb,
            'context' => compact('year','scheme','geo','perPage','sortBy','sortDir') + ['so_phieu' => $soPhieu],
        ];
    }

    /**
     * Map dữ liệu 1 dòng
     */
    protected function mapPersonRow(object $r, string $scheme, string $year): array
    {
        // Tính tuổi dựa trên năm học được chọn
        $age = null;
        if ($r->dob_year) {
            // Lấy năm đầu tiên từ academic_year (2024-2025 => 2024)
            if ($year !== 'all') {
                $academicYear = explode('-', $year);
                $referenceYear = (int)($academicYear[0] ?? date('Y'));
            } else {
                $referenceYear = date('Y');
            }
            $age = $referenceYear - $r->dob_year;
        }

        return [
            'full_name'       => $r->full_name,
            'dob_year'        => $r->dob_year,
            'age'             => $age,
            'is_female'       => (bool)$r->is_female,
            'so_phieu'        => $r->so_phieu,
            'residence_province' => $r->residence_province,
            'residence_ward'  => $r->residence_ward,
            'ethnicity_name'  => $r->ethnicity_name ?? '',
            'has_disability'  => (bool)$r->has_disability,
            'class_label'     => $r->class_label ?? '',
            'education_block' => $r->education_block ?? '',
            'school_name'     => $r->school_name ?? '',
            'graduation_level_id' => $r->graduation_level_id ?? null,
            'graduation_year' => $r->graduation_year ?? null,
            'hoc_xong_lop'    => $r->hoc_xong_lop ?? '',
            'bo_hoc_lop'      => $r->bo_hoc_lop ?? '',
        ];
    }

    /**
     * Lấy meta từ geo filter (tên tỉnh/xã từ địa bàn được chọn)
     */
    protected function metaFromGeoFilter(array $geo, string $scheme): array
    {
        $province = $ward = '';
        
        // Lấy tên tỉnh từ geo filter
        if (!empty($geo['province_id'])) {
            $provinceModel = \App\Models\GeoUnit::find($geo['province_id']);
            $province = $provinceModel ? $provinceModel->name : '';
        }
        
        // Lấy tên phường/xã từ geo filter
        if (!empty($geo['ward_id'])) {
            $wardModel = \App\Models\GeoUnit::find($geo['ward_id']);
            $ward = $wardModel ? $wardModel->name : '';
        }
        
        return compact('province','ward');
    }

    /**
     * Cấu hình template theo scheme
     */
    protected function templateCfgForScheme(string $scheme): array
    {
        return [
            'path'   => resource_path('excel/6.12.TDVHDoiTuongNu.xlsx'),
            'header' => [
                'province' => 'C1',  // Tỉnh/Thành
                'ward'     => 'C2',  // Phường/Xã
                'year'     => 'C3',  // Năm học
            ],
            'age_ranges' => [
                '0-2'   => ['start' => 8, 'end' => 10],    // A8-A10
                '3-5'   => ['start' => 12, 'end' => 14],    // A12-A14
                '6-14'  => ['start' => 16, 'end' => 24],    // A16-A24
                '15-18' => ['start' => 26, 'end' => 29],    // A26-A29
                '19-25' => ['start' => 31, 'end' => 37],    // A31-A37
                '26-35' => ['start' => 39, 'end' => 48],    // A39-A48
                '36-60' => ['start' => 50, 'end' => 74],    // A50-A74
            ],
        ];
    }

    /**
     * Điền thống kê theo độ tuổi vào Excel
     */
    protected function fillAgeStatistics(Worksheet $sh, $rows, array $cfg, string $year): void
    {
        $ageRanges = $cfg['age_ranges'];
        
        // Lấy năm tham chiếu từ năm học được chọn
        if ($year !== 'all') {
            $academicYear = explode('-', $year);
            $referenceYear = (int)($academicYear[0] ?? date('Y'));
        } else {
            $referenceYear = date('Y');
        }
        
        // Nhóm dữ liệu theo tuổi với thống kê chi tiết
        $ageData = [];
        foreach ($rows as $row) {
            if ($row->dob_year && $row->age !== null) {
                $age = $row->age;
                if (!isset($ageData[$age])) {
                    $ageData[$age] = [
                        'age' => $age,
                        'calculated_birth_year' => $referenceYear - $age,
                        'total' => 0,              // C: Tổng cả nam và nữ
                        'female' => 0,             // D: Tổng nữ
                        'female_ethnic' => 0,      // E: Nữ dân tộc (không phải Kinh)
                        'female_disability' => 0,  // F: Nữ khuyết tật
                        'female_studying' => 0,    // G: Nữ đang đi học
                        'female_preschool' => 0,   // H: Nữ mầm non
                        'female_grade_1' => 0,     // I: Nữ lớp 1
                        'female_grade_2' => 0,     // J: Nữ lớp 2
                        'female_grade_3' => 0,     // K: Nữ lớp 3
                        'female_grade_4' => 0,     // L: Nữ lớp 4
                        'female_grade_5' => 0,     // M: Nữ lớp 5
                        'female_grade_6' => 0,     // N: Nữ lớp 6
                        'female_grade_7' => 0,     // O: Nữ lớp 7
                        'female_grade_8' => 0,     // P: Nữ lớp 8
                        'female_grade_9' => 0,     // Q: Nữ lớp 9
                        'female_grade_10' => 0,    // R: Nữ lớp 10
                        'female_grade_11' => 0,    // S: Nữ lớp 11
                        'female_grade_12' => 0,    // T: Nữ lớp 12
                        'female_grad_level_2_studying' => 0,  // U: Nữ tốt nghiệp cấp 2 + đang học
                        'female_grad_level_3_studying' => 0,  // V: Nữ tốt nghiệp cấp 3 + đang học
                        'female_grad_level_4' => 0,           // W: Nữ tốt nghiệp cấp 4
                        'female_not_studying_or_dropout' => 0, // X: Nữ chưa học + bỏ học
                        'female_never_studied' => 0,          // Y: Nữ chưa đi học
                        'female_finished_or_dropout_grade_1' => 0,  // Z: Học xong/bỏ lớp 1
                        'female_finished_or_dropout_grade_2' => 0,  // AA: Học xong/bỏ lớp 2
                        'female_finished_or_dropout_grade_3' => 0,  // AB: Học xong/bỏ lớp 3
                        'female_finished_or_dropout_grade_4' => 0,  // AC: Học xong/bỏ lớp 4
                        'female_finished_or_dropout_grade_5' => 0,  // AD: Học xong/bỏ lớp 5
                        'female_finished_or_dropout_grade_6' => 0,  // AE: Học xong/bỏ lớp 6
                        'female_finished_or_dropout_grade_7' => 0,  // AF: Học xong/bỏ lớp 7
                        'female_finished_or_dropout_grade_8' => 0,  // AG: Học xong/bỏ lớp 8
                        'female_finished_or_dropout_grade_9' => 0,  // AH: Học xong/bỏ lớp 9
                        'female_finished_or_dropout_grade_10' => 0, // AI: Học xong/bỏ lớp 10
                        'female_finished_or_dropout_grade_11' => 0, // AJ: Học xong/bỏ lớp 11
                        'female_finished_or_dropout_grade_12' => 0, // AK: Học xong/bỏ lớp 12
                        'female_grad_2_not_studying' => 0,          // AL: TN tiểu học (cấp 2) + chưa học/bỏ học
                        'female_grad_3_not_studying' => 0,          // AM: TN THCS (cấp 3) + chưa học/bỏ học
                    ];
                }
                
                // Tổng cả nam và nữ
                $ageData[$age]['total']++;
                
                // Thống kê nữ
                if ($row->is_female) {
                    $ageData[$age]['female']++;
                    
                    // Nữ dân tộc (không phải Kinh)
                    if ($row->ethnicity_name && strtolower($row->ethnicity_name) !== 'kinh') {
                        $ageData[$age]['female_ethnic']++;
                    }
                    
                    // Nữ khuyết tật
                    if ($row->has_disability) {
                        $ageData[$age]['female_disability']++;
                    }
                    
                    // Thống kê theo graduation_level_id
                    $graduationLevelId = $row->graduation_level_id;
                    
                    // Kiểm tra có thông tin bỏ học/học xong lớp
                    $hasDropoutInfo = !empty($row->hoc_xong_lop) || !empty($row->bo_hoc_lop);
                    
                    // Logic "đang học" cho cột U, V: có lớp/trường/khối HOẶC (từ 18 tuổi trở lên + có bằng TN TH trở lên + không bỏ học)
                    $isStudyingForGrad = !empty($row->class_label) || !empty($row->school_name) || !empty($row->education_block);
                    if ($row->age >= 18 && $graduationLevelId >= 2 && !$hasDropoutInfo) {
                        $isStudyingForGrad = true;
                    }
                    
                    // U: Tốt nghiệp cấp 2 (graduation_level_id = 2) và đang học
                    if ($graduationLevelId == 2 && $isStudyingForGrad) {
                        $ageData[$age]['female_grad_level_2_studying']++;
                    }
                    
                    // V: Tốt nghiệp cấp 3 (graduation_level_id = 3) và đang học
                    if ($graduationLevelId == 3 && $isStudyingForGrad) {
                        $ageData[$age]['female_grad_level_3_studying']++;
                    }
                    
                    // W: Tốt nghiệp cấp 4 (graduation_level_id = 4) - không cần đang học
                    if ($graduationLevelId == 4) {
                        $ageData[$age]['female_grad_level_4']++;
                    }
                    
                    // Kiểm tra nữ chưa đi học (không có thông tin học tập)
                    $hasEducationInfo = !empty($graduationLevelId) || 
                                       !empty($row->graduation_year) || 
                                       !empty($row->class_label) || 
                                       !empty($row->education_block) || 
                                       !empty($row->school_name);
                    
                    // Z-AK: Thống kê học xong lớp + bỏ học lớp từ 1-12
                    // Trích xuất số lớp từ hoc_xong_lop
                    if (!empty($row->hoc_xong_lop)) {
                        if (preg_match('/\b(\d+)\b/', $row->hoc_xong_lop, $matches)) {
                            $grade = (int)$matches[1];
                            if ($grade >= 1 && $grade <= 12) {
                                $key = 'female_finished_or_dropout_grade_' . $grade;
                                $ageData[$age][$key]++;
                            }
                        }
                    }
                    
                    // Trích xuất số lớp từ bo_hoc_lop
                    if (!empty($row->bo_hoc_lop)) {
                        if (preg_match('/\b(\d+)\b/', $row->bo_hoc_lop, $matches)) {
                            $grade = (int)$matches[1];
                            if ($grade >= 1 && $grade <= 12) {
                                $key = 'female_finished_or_dropout_grade_' . $grade;
                                $ageData[$age][$key]++;
                            }
                        }
                    }
                    
                    // AL-AM: Tốt nghiệp tiểu học/THCS + thuộc nhóm chưa học/bỏ học (X)
                    // Điều kiện: có graduation_level_id = 2 hoặc 3 VÀ thuộc nhóm X (chưa học hoặc có thông tin bỏ học)
                    $isInNotStudyingGroup = !$hasEducationInfo || $hasDropoutInfo;
                    
                    // AL: Tốt nghiệp tiểu học (graduation_level_id = 2) + thuộc nhóm X
                    if ($graduationLevelId == 2 && $isInNotStudyingGroup) {
                        $ageData[$age]['female_grad_2_not_studying']++;
                    }
                    
                    // AM: Tốt nghiệp THCS (graduation_level_id = 3) + thuộc nhóm X
                    if ($graduationLevelId == 3 && $isInNotStudyingGroup) {
                        $ageData[$age]['female_grad_3_not_studying']++;
                    }
                    
                    // Nữ đang đi học (có lớp, trường hoặc khối) HOẶC (trên 18 tuổi + có bằng TN TH trở lên)
                    $hasActiveStudying = !empty($row->class_label) || !empty($row->school_name) || !empty($row->education_block);
                    
                    // Đối với người từ 18 tuổi trở lên: nếu có bằng TN TH trở lên VÀ không có thông tin bỏ học thì vẫn tính là "đang học"
                    $isStudying = $hasActiveStudying;
                    if ($row->age >= 18 && $graduationLevelId >= 2 && !$hasDropoutInfo) {
                        $isStudying = true;
                    }
                    
                    if ($isStudying) {
                        $ageData[$age]['female_studying']++;
                        
                        // Phân loại theo khối/lớp
                        $block = $row->education_block ?? '';
                        $class = $row->class_label ?? '';
                        $age = $row->age;
                        
                        // Từ 0-5 tuổi: tự động là mầm non nếu có thông tin học tập
                        if ($age >= 0 && $age <= 5) {
                            $ageData[$age]['female_preschool']++;
                        }
                        // Mầm non (dựa vào tên khối)
                        else if (stripos($block, 'mầm non') !== false || stripos($block, 'mẫu giáo') !== false || 
                                 stripos($block, 'nhà trẻ') !== false) {
                            $ageData[$age]['female_preschool']++;
                        }
                        // Lớp 1-12 (từ education_block)
                        else if (preg_match('/\b(\d+)\b/', $block, $matches)) {
                            $grade = (int)$matches[1];
                            if ($grade >= 1 && $grade <= 12) {
                                $key = 'female_grade_' . $grade;
                                $ageData[$age][$key]++;
                            }
                        }
                        // Lớp 1-12 (từ class_label)
                        else if (preg_match('/\b(\d+)\b/', $class, $matches)) {
                            $grade = (int)$matches[1];
                            if ($grade >= 1 && $grade <= 12) {
                                $key = 'female_grade_' . $grade;
                                $ageData[$age][$key]++;
                            }
                        }
                    }
                    
                    // Y: Nữ chưa đi học (không có bất kỳ thông tin học tập nào)
                    if (!$hasEducationInfo) {
                        $ageData[$age]['female_never_studied']++;
                    }
                    
                    // X: Nữ KHÔNG đang học (bao gồm tất cả người không được tính vào cột G)
                    // X = Tất cả nữ - Nữ đang học
                    if (!$isStudying) {
                        $ageData[$age]['female_not_studying_or_dropout']++;
                    }
                }
            }
        }
        
        // Sắp xếp theo tuổi (từ 0 đến 60)
        ksort($ageData);
        
        // Điền dữ liệu vào các ô theo nhóm tuổi và tính tổng
        $totalRows = ['11' => 0, '15' => 0, '25' => 0, '30' => 0, '38' => 0, '49' => 0, '75' => 0];
        $grandTotal = [
            'total' => 0, 'female' => 0, 'female_ethnic' => 0, 'female_disability' => 0,
            'female_studying' => 0, 'female_preschool' => 0,
            'female_grade_1' => 0, 'female_grade_2' => 0, 'female_grade_3' => 0,
            'female_grade_4' => 0, 'female_grade_5' => 0, 'female_grade_6' => 0,
            'female_grade_7' => 0, 'female_grade_8' => 0, 'female_grade_9' => 0,
            'female_grade_10' => 0, 'female_grade_11' => 0, 'female_grade_12' => 0,
            'female_grad_level_2_studying' => 0, 'female_grad_level_3_studying' => 0, 'female_grad_level_4' => 0,
            'female_not_studying_or_dropout' => 0, 'female_never_studied' => 0,
            'female_finished_or_dropout_grade_1' => 0, 'female_finished_or_dropout_grade_2' => 0,
            'female_finished_or_dropout_grade_3' => 0, 'female_finished_or_dropout_grade_4' => 0,
            'female_finished_or_dropout_grade_5' => 0, 'female_finished_or_dropout_grade_6' => 0,
            'female_finished_or_dropout_grade_7' => 0, 'female_finished_or_dropout_grade_8' => 0,
            'female_finished_or_dropout_grade_9' => 0, 'female_finished_or_dropout_grade_10' => 0,
            'female_finished_or_dropout_grade_11' => 0, 'female_finished_or_dropout_grade_12' => 0,
            'female_grad_2_not_studying' => 0, 'female_grad_3_not_studying' => 0
        ];
        
        foreach ($ageRanges as $range => $positions) {
            list($minAge, $maxAge) = explode('-', $range);
            $minAge = (int)$minAge;
            $maxAge = (int)$maxAge;
            
            // Tính tổng cho nhóm tuổi này
            $groupTotal = [
                'total' => 0, 'female' => 0, 'female_ethnic' => 0, 'female_disability' => 0,
                'female_studying' => 0, 'female_preschool' => 0,
                'female_grade_1' => 0, 'female_grade_2' => 0, 'female_grade_3' => 0,
                'female_grade_4' => 0, 'female_grade_5' => 0, 'female_grade_6' => 0,
                'female_grade_7' => 0, 'female_grade_8' => 0, 'female_grade_9' => 0,
                'female_grade_10' => 0, 'female_grade_11' => 0, 'female_grade_12' => 0,
                'female_grad_level_2_studying' => 0, 'female_grad_level_3_studying' => 0, 'female_grad_level_4' => 0,
                'female_not_studying_or_dropout' => 0, 'female_never_studied' => 0,
                'female_finished_or_dropout_grade_1' => 0, 'female_finished_or_dropout_grade_2' => 0,
                'female_finished_or_dropout_grade_3' => 0, 'female_finished_or_dropout_grade_4' => 0,
                'female_finished_or_dropout_grade_5' => 0, 'female_finished_or_dropout_grade_6' => 0,
                'female_finished_or_dropout_grade_7' => 0, 'female_finished_or_dropout_grade_8' => 0,
                'female_finished_or_dropout_grade_9' => 0, 'female_finished_or_dropout_grade_10' => 0,
                'female_finished_or_dropout_grade_11' => 0, 'female_finished_or_dropout_grade_12' => 0,
                'female_grad_2_not_studying' => 0, 'female_grad_3_not_studying' => 0
            ];
            
            // Ghi dữ liệu vào các dòng - LẶP QUA TẤT CẢ TUỔI trong range
            $row = $positions['start'];
            for ($currentAge = $minAge; $currentAge <= $maxAge; $currentAge++) {
                // Lấy dữ liệu của tuổi này (nếu có), hoặc tạo dữ liệu rỗng
                if (isset($ageData[$currentAge])) {
                    $data = $ageData[$currentAge];
                } else {
                    // Tạo dữ liệu rỗng cho tuổi không có trong database
                    $data = [
                        'age' => $currentAge,
                        'calculated_birth_year' => $referenceYear - $currentAge,
                        'total' => 0, 'female' => 0, 'female_ethnic' => 0, 'female_disability' => 0,
                        'female_studying' => 0, 'female_preschool' => 0,
                        'female_grade_1' => 0, 'female_grade_2' => 0, 'female_grade_3' => 0,
                        'female_grade_4' => 0, 'female_grade_5' => 0, 'female_grade_6' => 0,
                        'female_grade_7' => 0, 'female_grade_8' => 0, 'female_grade_9' => 0,
                        'female_grade_10' => 0, 'female_grade_11' => 0, 'female_grade_12' => 0,
                        'female_grad_level_2_studying' => 0, 'female_grad_level_3_studying' => 0, 
                        'female_grad_level_4' => 0, 'female_not_studying_or_dropout' => 0, 
                        'female_never_studied' => 0,
                        'female_finished_or_dropout_grade_1' => 0, 'female_finished_or_dropout_grade_2' => 0,
                        'female_finished_or_dropout_grade_3' => 0, 'female_finished_or_dropout_grade_4' => 0,
                        'female_finished_or_dropout_grade_5' => 0, 'female_finished_or_dropout_grade_6' => 0,
                        'female_finished_or_dropout_grade_7' => 0, 'female_finished_or_dropout_grade_8' => 0,
                        'female_finished_or_dropout_grade_9' => 0, 'female_finished_or_dropout_grade_10' => 0,
                        'female_finished_or_dropout_grade_11' => 0, 'female_finished_or_dropout_grade_12' => 0,
                        'female_grad_2_not_studying' => 0, 'female_grad_3_not_studying' => 0
                    ];
                }
                
                // Cộng vào tổng nhóm
                foreach (['total', 'female', 'female_ethnic', 'female_disability', 'female_studying',
                          'female_preschool', 'female_grade_1', 'female_grade_2', 'female_grade_3',
                          'female_grade_4', 'female_grade_5', 'female_grade_6', 'female_grade_7',
                          'female_grade_8', 'female_grade_9', 'female_grade_10', 'female_grade_11',
                          'female_grade_12', 'female_grad_level_2_studying', 'female_grad_level_3_studying',
                          'female_grad_level_4', 'female_not_studying_or_dropout', 'female_never_studied',
                          'female_finished_or_dropout_grade_1', 'female_finished_or_dropout_grade_2',
                          'female_finished_or_dropout_grade_3', 'female_finished_or_dropout_grade_4',
                          'female_finished_or_dropout_grade_5', 'female_finished_or_dropout_grade_6',
                          'female_finished_or_dropout_grade_7', 'female_finished_or_dropout_grade_8',
                          'female_finished_or_dropout_grade_9', 'female_finished_or_dropout_grade_10',
                          'female_finished_or_dropout_grade_11', 'female_finished_or_dropout_grade_12',
                          'female_grad_2_not_studying', 'female_grad_3_not_studying'] as $key) {
                    $groupTotal[$key] += $data[$key];
                }
                
                // Chỉ hiển thị nếu còn chỗ trong template
                if ($row <= $positions['end']) {
                    // Cột A: Năm sinh (tính theo năm học), Cột B: Tuổi
                    $sh->setCellValue('A'.$row, $data['calculated_birth_year']);
                    $sh->setCellValue('B'.$row, $data['age']);
                    
                    // Cột C-F
                    $sh->setCellValue('C'.$row, $data['total'] > 0 ? $data['total'] : '');
                    $sh->setCellValue('D'.$row, $data['female'] > 0 ? $data['female'] : '');
                    $sh->setCellValue('E'.$row, $data['female_ethnic'] > 0 ? $data['female_ethnic'] : '');
                    $sh->setCellValue('F'.$row, $data['female_disability'] > 0 ? $data['female_disability'] : '');
                    
                    // Cột G-T: Nữ đang học theo cấp lớp
                    $sh->setCellValue('G'.$row, $data['female_studying'] > 0 ? $data['female_studying'] : '');
                    $sh->setCellValue('H'.$row, $data['female_preschool'] > 0 ? $data['female_preschool'] : '');
                    $sh->setCellValue('I'.$row, $data['female_grade_1'] > 0 ? $data['female_grade_1'] : '');
                    $sh->setCellValue('J'.$row, $data['female_grade_2'] > 0 ? $data['female_grade_2'] : '');
                    $sh->setCellValue('K'.$row, $data['female_grade_3'] > 0 ? $data['female_grade_3'] : '');
                    $sh->setCellValue('L'.$row, $data['female_grade_4'] > 0 ? $data['female_grade_4'] : '');
                    $sh->setCellValue('M'.$row, $data['female_grade_5'] > 0 ? $data['female_grade_5'] : '');
                    $sh->setCellValue('N'.$row, $data['female_grade_6'] > 0 ? $data['female_grade_6'] : '');
                    $sh->setCellValue('O'.$row, $data['female_grade_7'] > 0 ? $data['female_grade_7'] : '');
                    $sh->setCellValue('P'.$row, $data['female_grade_8'] > 0 ? $data['female_grade_8'] : '');
                    $sh->setCellValue('Q'.$row, $data['female_grade_9'] > 0 ? $data['female_grade_9'] : '');
                    $sh->setCellValue('R'.$row, $data['female_grade_10'] > 0 ? $data['female_grade_10'] : '');
                    $sh->setCellValue('S'.$row, $data['female_grade_11'] > 0 ? $data['female_grade_11'] : '');
                    $sh->setCellValue('T'.$row, $data['female_grade_12'] > 0 ? $data['female_grade_12'] : '');
                    
                    // Cột U-W: Nữ theo graduation_level_id
                    $sh->setCellValue('U'.$row, $data['female_grad_level_2_studying'] > 0 ? $data['female_grad_level_2_studying'] : '');
                    $sh->setCellValue('V'.$row, $data['female_grad_level_3_studying'] > 0 ? $data['female_grad_level_3_studying'] : '');
                    $sh->setCellValue('W'.$row, $data['female_grad_level_4'] > 0 ? $data['female_grad_level_4'] : '');
                    
                    // Cột X-Y: Nữ chưa học và bỏ học
                    $sh->setCellValue('X'.$row, $data['female_not_studying_or_dropout'] > 0 ? $data['female_not_studying_or_dropout'] : '');
                    $sh->setCellValue('Y'.$row, $data['female_never_studied'] > 0 ? $data['female_never_studied'] : '');
                    
                    // Cột Z-AK: Học xong/bỏ lớp 1-12
                    $sh->setCellValue('Z'.$row, $data['female_finished_or_dropout_grade_1'] > 0 ? $data['female_finished_or_dropout_grade_1'] : '');
                    $sh->setCellValue('AA'.$row, $data['female_finished_or_dropout_grade_2'] > 0 ? $data['female_finished_or_dropout_grade_2'] : '');
                    $sh->setCellValue('AB'.$row, $data['female_finished_or_dropout_grade_3'] > 0 ? $data['female_finished_or_dropout_grade_3'] : '');
                    $sh->setCellValue('AC'.$row, $data['female_finished_or_dropout_grade_4'] > 0 ? $data['female_finished_or_dropout_grade_4'] : '');
                    $sh->setCellValue('AD'.$row, $data['female_finished_or_dropout_grade_5'] > 0 ? $data['female_finished_or_dropout_grade_5'] : '');
                    $sh->setCellValue('AE'.$row, $data['female_finished_or_dropout_grade_6'] > 0 ? $data['female_finished_or_dropout_grade_6'] : '');
                    $sh->setCellValue('AF'.$row, $data['female_finished_or_dropout_grade_7'] > 0 ? $data['female_finished_or_dropout_grade_7'] : '');
                    $sh->setCellValue('AG'.$row, $data['female_finished_or_dropout_grade_8'] > 0 ? $data['female_finished_or_dropout_grade_8'] : '');
                    $sh->setCellValue('AH'.$row, $data['female_finished_or_dropout_grade_9'] > 0 ? $data['female_finished_or_dropout_grade_9'] : '');
                    $sh->setCellValue('AI'.$row, $data['female_finished_or_dropout_grade_10'] > 0 ? $data['female_finished_or_dropout_grade_10'] : '');
                    $sh->setCellValue('AJ'.$row, $data['female_finished_or_dropout_grade_11'] > 0 ? $data['female_finished_or_dropout_grade_11'] : '');
                    $sh->setCellValue('AK'.$row, $data['female_finished_or_dropout_grade_12'] > 0 ? $data['female_finished_or_dropout_grade_12'] : '');
                    
                    // Cột AL-AM: TN tiểu học/THCS + chưa học/bỏ học
                    $sh->setCellValue('AL'.$row, $data['female_grad_2_not_studying'] > 0 ? $data['female_grad_2_not_studying'] : '');
                    $sh->setCellValue('AM'.$row, $data['female_grad_3_not_studying'] > 0 ? $data['female_grad_3_not_studying'] : '');
                    
                    
                    // Thiết lập font size và căn giữa
                    $sh->getStyle('A'.$row.':AM'.$row)->getFont()->setName('Tahoma')->setSize(8);
                    $sh->getStyle('A'.$row.':AM'.$row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    
                    $row++;
                }
            }
            
            // Ghi tổng cho nhóm tuổi
            $totalRow = $positions['end'] + 1;
            $this->writeGroupTotal($sh, $totalRow, $groupTotal);
            
            // Lưu vào mảng tổng
            if ($range === '0-2') $totalRows['11'] = $groupTotal;
            if ($range === '3-5') $totalRows['15'] = $groupTotal;
            if ($range === '6-14') $totalRows['25'] = $groupTotal;
            if ($range === '15-18') $totalRows['30'] = $groupTotal;
            if ($range === '19-25') $totalRows['38'] = $groupTotal;
            if ($range === '26-35') $totalRows['49'] = $groupTotal;
            if ($range === '36-60') $totalRows['75'] = $groupTotal;
            
            // Cộng vào tổng lớn
            foreach (['total', 'female', 'female_ethnic', 'female_disability', 'female_studying',
                      'female_preschool', 'female_grade_1', 'female_grade_2', 'female_grade_3',
                      'female_grade_4', 'female_grade_5', 'female_grade_6', 'female_grade_7',
                      'female_grade_8', 'female_grade_9', 'female_grade_10', 'female_grade_11',
                      'female_grade_12', 'female_grad_level_2_studying', 'female_grad_level_3_studying',
                      'female_grad_level_4', 'female_not_studying_or_dropout', 'female_never_studied',
                      'female_finished_or_dropout_grade_1', 'female_finished_or_dropout_grade_2',
                      'female_finished_or_dropout_grade_3', 'female_finished_or_dropout_grade_4',
                      'female_finished_or_dropout_grade_5', 'female_finished_or_dropout_grade_6',
                      'female_finished_or_dropout_grade_7', 'female_finished_or_dropout_grade_8',
                      'female_finished_or_dropout_grade_9', 'female_finished_or_dropout_grade_10',
                      'female_finished_or_dropout_grade_11', 'female_finished_or_dropout_grade_12',
                      'female_grad_2_not_studying', 'female_grad_3_not_studying'] as $key) {
                $grandTotal[$key] += $groupTotal[$key];
            }
        }
        
        // Ghi tổng lớn ở hàng 76
        $this->writeGroupTotal($sh, 76, $grandTotal, true);
    }
    
    /**
     * Ghi tổng cho nhóm tuổi
     */
    protected function writeGroupTotal(Worksheet $sh, int $row, array $total, bool $isGrandTotal = false): void
    {
        $sh->setCellValue('A'.$row, $isGrandTotal ? 'TỔNG CỘNG' : 'Tổng');
        
        // Cột C-F
        $sh->setCellValue('C'.$row, $total['total'] > 0 ? $total['total'] : '');
        $sh->setCellValue('D'.$row, $total['female'] > 0 ? $total['female'] : '');
        $sh->setCellValue('E'.$row, $total['female_ethnic'] > 0 ? $total['female_ethnic'] : '');
        $sh->setCellValue('F'.$row, $total['female_disability'] > 0 ? $total['female_disability'] : '');
        
        // Cột G-T: Nữ đang học theo cấp lớp
        $sh->setCellValue('G'.$row, $total['female_studying'] > 0 ? $total['female_studying'] : '');
        $sh->setCellValue('H'.$row, $total['female_preschool'] > 0 ? $total['female_preschool'] : '');
        $sh->setCellValue('I'.$row, $total['female_grade_1'] > 0 ? $total['female_grade_1'] : '');
        $sh->setCellValue('J'.$row, $total['female_grade_2'] > 0 ? $total['female_grade_2'] : '');
        $sh->setCellValue('K'.$row, $total['female_grade_3'] > 0 ? $total['female_grade_3'] : '');
        $sh->setCellValue('L'.$row, $total['female_grade_4'] > 0 ? $total['female_grade_4'] : '');
        $sh->setCellValue('M'.$row, $total['female_grade_5'] > 0 ? $total['female_grade_5'] : '');
        $sh->setCellValue('N'.$row, $total['female_grade_6'] > 0 ? $total['female_grade_6'] : '');
        $sh->setCellValue('O'.$row, $total['female_grade_7'] > 0 ? $total['female_grade_7'] : '');
        $sh->setCellValue('P'.$row, $total['female_grade_8'] > 0 ? $total['female_grade_8'] : '');
        $sh->setCellValue('Q'.$row, $total['female_grade_9'] > 0 ? $total['female_grade_9'] : '');
        $sh->setCellValue('R'.$row, $total['female_grade_10'] > 0 ? $total['female_grade_10'] : '');
        $sh->setCellValue('S'.$row, $total['female_grade_11'] > 0 ? $total['female_grade_11'] : '');
        $sh->setCellValue('T'.$row, $total['female_grade_12'] > 0 ? $total['female_grade_12'] : '');
        
        // Cột U-W: Nữ theo graduation_level_id
        $sh->setCellValue('U'.$row, $total['female_grad_level_2_studying'] > 0 ? $total['female_grad_level_2_studying'] : '');
        $sh->setCellValue('V'.$row, $total['female_grad_level_3_studying'] > 0 ? $total['female_grad_level_3_studying'] : '');
        $sh->setCellValue('W'.$row, $total['female_grad_level_4'] > 0 ? $total['female_grad_level_4'] : '');
        
        // Cột X-Y: Nữ chưa học và bỏ học
        $sh->setCellValue('X'.$row, $total['female_not_studying_or_dropout'] > 0 ? $total['female_not_studying_or_dropout'] : '');
        $sh->setCellValue('Y'.$row, $total['female_never_studied'] > 0 ? $total['female_never_studied'] : '');
        
        // Cột Z-AK: Học xong/bỏ lớp 1-12
        $sh->setCellValue('Z'.$row, $total['female_finished_or_dropout_grade_1'] > 0 ? $total['female_finished_or_dropout_grade_1'] : '');
        $sh->setCellValue('AA'.$row, $total['female_finished_or_dropout_grade_2'] > 0 ? $total['female_finished_or_dropout_grade_2'] : '');
        $sh->setCellValue('AB'.$row, $total['female_finished_or_dropout_grade_3'] > 0 ? $total['female_finished_or_dropout_grade_3'] : '');
        $sh->setCellValue('AC'.$row, $total['female_finished_or_dropout_grade_4'] > 0 ? $total['female_finished_or_dropout_grade_4'] : '');
        $sh->setCellValue('AD'.$row, $total['female_finished_or_dropout_grade_5'] > 0 ? $total['female_finished_or_dropout_grade_5'] : '');
        $sh->setCellValue('AE'.$row, $total['female_finished_or_dropout_grade_6'] > 0 ? $total['female_finished_or_dropout_grade_6'] : '');
        $sh->setCellValue('AF'.$row, $total['female_finished_or_dropout_grade_7'] > 0 ? $total['female_finished_or_dropout_grade_7'] : '');
        $sh->setCellValue('AG'.$row, $total['female_finished_or_dropout_grade_8'] > 0 ? $total['female_finished_or_dropout_grade_8'] : '');
        $sh->setCellValue('AH'.$row, $total['female_finished_or_dropout_grade_9'] > 0 ? $total['female_finished_or_dropout_grade_9'] : '');
        $sh->setCellValue('AI'.$row, $total['female_finished_or_dropout_grade_10'] > 0 ? $total['female_finished_or_dropout_grade_10'] : '');
        $sh->setCellValue('AJ'.$row, $total['female_finished_or_dropout_grade_11'] > 0 ? $total['female_finished_or_dropout_grade_11'] : '');
        $sh->setCellValue('AK'.$row, $total['female_finished_or_dropout_grade_12'] > 0 ? $total['female_finished_or_dropout_grade_12'] : '');
        
        // Cột AL-AM: TN tiểu học/THCS + chưa học/bỏ học
        $sh->setCellValue('AL'.$row, $total['female_grad_2_not_studying'] > 0 ? $total['female_grad_2_not_studying'] : '');
        $sh->setCellValue('AM'.$row, $total['female_grad_3_not_studying'] > 0 ? $total['female_grad_3_not_studying'] : '');
        
        // Thiết lập font size, in đậm và căn giữa
        $sh->getStyle('A'.$row.':AM'.$row)->getFont()->setName('Tahoma')->setSize(8)->setBold(true);
        $sh->getStyle('A'.$row.':AM'.$row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    }
}
