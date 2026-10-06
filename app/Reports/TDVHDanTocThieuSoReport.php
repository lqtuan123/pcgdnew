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
 * Report: Thống kê trình độ văn hóa dân tộc thiểu số
 * (Thống kê theo độ tuổi và dân tộc thiểu số)
 * 
 * - Handler hiển thị danh sách: list(Request $req)
 * - Handler xuất Excel:        export(Request $req)
 */
class TDVHDanTocThieuSoReport extends BaseReport
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
        ['qb' => $qb, 'context' => $ctx] = $this->tdvhDanTocThieuSoQuery($req);

        // Lấy dữ liệu trang hiện tại
        $rows  = $qb->paginate($ctx['perPage'])->withQueryString();
        $items = collect($rows->items())->map(fn($r) => $this->mapPersonRow($r, $ctx['scheme'], $ctx['year']));

        // Render view
        return view('front.reports.tdvh_dan_toc_thieu_so_list', [
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
     * Xuất Excel thống kê trình độ văn hóa dân tộc thiểu số
     */
    public function export(Request $req)
    {
        // Build query
        ['qb' => $qb, 'context' => $ctx] = $this->tdvhDanTocThieuSoQuery($req);

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

        // Ghi đè footer từ cột BA đến BQ với thông tin xã, ngày, tháng, năm hiện tại
        $wardName = $meta['ward'] ?? 'Xã/Phường';
        $currentDate = date('d');
        $currentMonth = date('m');
        $currentYear = date('Y');
        
        // Tìm vị trí footer (thường ở hàng 77-80)
        $footerRow = 78;
        
        // Merge các ô từ BA đến BQ
        $sh->mergeCells('BA'.$footerRow.':BQ'.$footerRow);
        $sh->setCellValue('BA'.$footerRow, $wardName . ', ngày ' . $currentDate . ' tháng ' . $currentMonth . ' năm ' . $currentYear);
        $sh->getStyle('BA'.$footerRow.':BQ'.$footerRow)->getFont()->setName('Tahoma')->setSize(8)->setItalic(true);
        $sh->getStyle('BA'.$footerRow.':BQ'.$footerRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

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
     * Query thống kê trình độ văn hóa dân tộc thiểu số
     */
    protected function tdvhDanTocThieuSoQuery(Request $req): array
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
            
            // Join dân tộc, khuyết tật và thông tin giáo dục
            ->leftJoin('ethnicities as eth', 'eth.id', '=', 'p.ethnicity_id')
            ->leftJoin('person_disabilities as pd', function($j) {
                $j->on('pd.person_id', '=', 'p.id')
                  ->where('pd.is_deleted', false);
            })
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
            
            ->where('d.is_deleted', false);

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
            d.academic_year,
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
            pe.education_block_id,
            sch.name as school_name,
            pe.graduation_level_id,
            pe.hoc_xong_lop,
            pe.hoc_xong_nam,
            pe.bo_hoc_lop,
            pe.bo_hoc_nam
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
            'detail_id'       => $r->detail_id,
            'academic_year'   => $r->academic_year ?? '',
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
            'education_block_id' => $r->education_block_id ?? null,
            'school_name'     => $r->school_name ?? '',
            'graduation_level_id' => $r->graduation_level_id ?? null,
            'hoc_xong_lop'    => $r->hoc_xong_lop ?? null,
            'hoc_xong_nam'    => $r->hoc_xong_nam ?? null,
            'bo_hoc_lop'      => $r->bo_hoc_lop ?? null,
            'bo_hoc_nam'      => $r->bo_hoc_nam ?? null,
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
            'path'   => resource_path('excel/6.11.TDVHDanTocThieuSo.xlsx'),
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
        
        // De-duplicate theo person_id và năm học (mỗi người chỉ tính 1 lần trong cùng năm học)
        $uniquePersons = [];
        foreach ($rows as $row) {
            // Tạo unique key dựa trên person_id (từ detail_id) và năm học
            $personKey = $row->detail_id . '_' . ($row->academic_year ?? 'all');
            if (!isset($uniquePersons[$personKey])) {
                $uniquePersons[$personKey] = $row;
            }
        }
        
        // Nhóm dữ liệu theo tuổi với thống kê chi tiết
        $ageData = [];
        foreach ($uniquePersons as $row) {
            if ($row->dob_year && $row->age !== null) {
                $age = $row->age;
                if (!isset($ageData[$age])) {
                    $ageData[$age] = [
                        'age' => $age,
                        'calculated_birth_year' => $referenceYear - $age,
                        'total' => 0,              // A: Tổng cả nam và nữ
                        'ethnic' => 0,            // B: Tổng dân tộc thiểu số
                        'total_all' => 0,         // C: Tổng số người cả nam và nữ theo độ tuổi
                        'female' => 0,            // D: Tổng số nữ chung
                        'ethnic_all' => 0,        // E: Tổng số dân tộc cả nam và nữ
                        'ethnic_must_study' => 0, // F: Tổng số dân tộc phải phổ cập
                        'ethnic_female' => 0,     // G: Tổng số nữ dân tộc
                        'ethnic_disability' => 0, // H: Tổng số dân tộc bị khuyết tật
                        'ethnic_studying' => 0,   // I: Tổng số dân tộc đang đi học
                        // J-AI: Thống kê theo lớp (mỗi cặp 2 cột: tổng và nữ)
                        'ethnic_preschool' => 0,      // J: Dân tộc mầm non
                        'ethnic_preschool_female' => 0, // K: Dân tộc nữ mầm non
                        'ethnic_grade_1' => 0,        // L: Dân tộc lớp 1
                        'ethnic_grade_1_female' => 0,  // M: Dân tộc nữ lớp 1
                        'ethnic_grade_2' => 0,        // N: Dân tộc lớp 2
                        'ethnic_grade_2_female' => 0,  // O: Dân tộc nữ lớp 2
                        'ethnic_grade_3' => 0,        // P: Dân tộc lớp 3
                        'ethnic_grade_3_female' => 0,  // Q: Dân tộc nữ lớp 3
                        'ethnic_grade_4' => 0,        // R: Dân tộc lớp 4
                        'ethnic_grade_4_female' => 0,  // S: Dân tộc nữ lớp 4
                        'ethnic_grade_5' => 0,        // T: Dân tộc lớp 5
                        'ethnic_grade_5_female' => 0,  // U: Dân tộc nữ lớp 5
                        'ethnic_grade_6' => 0,        // V: Dân tộc lớp 6
                        'ethnic_grade_6_female' => 0,  // W: Dân tộc nữ lớp 6
                        'ethnic_grade_7' => 0,        // X: Dân tộc lớp 7
                        'ethnic_grade_7_female' => 0,  // Y: Dân tộc nữ lớp 7
                        'ethnic_grade_8' => 0,        // Z: Dân tộc lớp 8
                        'ethnic_grade_8_female' => 0,  // AA: Dân tộc nữ lớp 8
                        'ethnic_grade_9' => 0,        // AB: Dân tộc lớp 9
                        'ethnic_grade_9_female' => 0,  // AC: Dân tộc nữ lớp 9
                        'ethnic_grade_10' => 0,       // AD: Dân tộc lớp 10
                        'ethnic_grade_10_female' => 0, // AE: Dân tộc nữ lớp 10
                        'ethnic_grade_11' => 0,       // AF: Dân tộc lớp 11
                        'ethnic_grade_11_female' => 0, // AG: Dân tộc nữ lớp 11
                        'ethnic_grade_12' => 0,       // AH: Dân tộc lớp 12
                        'ethnic_grade_12_female' => 0, // AI: Dân tộc nữ lớp 12
                        // AJ-AO: Thống kê tốt nghiệp (mỗi cặp 2 cột: tổng và nữ)
                        'ethnic_grad_primary' => 0,        // AJ: Dân tộc tốt nghiệp tiểu học
                        'ethnic_grad_primary_female' => 0, // AK: Dân tộc nữ tốt nghiệp tiểu học
                        'ethnic_grad_secondary' => 0,      // AL: Dân tộc tốt nghiệp THCS
                        'ethnic_grad_secondary_female' => 0, // AM: Dân tộc nữ tốt nghiệp THCS
                        'ethnic_grad_highschool' => 0,     // AN: Dân tộc tốt nghiệp THPT
                        'ethnic_grad_highschool_female' => 0, // AO: Dân tộc nữ tốt nghiệp THPT
                        // AP-AQ: Thống kê chưa đi học và bỏ học
                        'ethnic_not_studying_dropped' => 0, // AP: Dân tộc chưa đi học + bỏ học
                        'ethnic_never_studied' => 0,        // AQ: Dân tộc chưa từng đi học
                        // AR-BO: Thống kê học xong lớp + bỏ học lớp (mỗi lớp 2 cột)
                        'ethnic_completed_dropped_1' => 0,        // AR: Lớp 1 (học xong + bỏ học)
                        'ethnic_completed_dropped_1_female' => 0, // AS: Lớp 1 nữ
                        'ethnic_completed_dropped_2' => 0,        // AT: Lớp 2
                        'ethnic_completed_dropped_2_female' => 0, // AU: Lớp 2 nữ
                        'ethnic_completed_dropped_3' => 0,        // AV: Lớp 3
                        'ethnic_completed_dropped_3_female' => 0, // AW: Lớp 3 nữ
                        'ethnic_completed_dropped_4' => 0,        // AX: Lớp 4
                        'ethnic_completed_dropped_4_female' => 0, // AY: Lớp 4 nữ
                        'ethnic_completed_dropped_5' => 0,        // AZ: Lớp 5
                        'ethnic_completed_dropped_5_female' => 0, // BA: Lớp 5 nữ
                        'ethnic_completed_dropped_6' => 0,        // BB: Lớp 6
                        'ethnic_completed_dropped_6_female' => 0, // BC: Lớp 6 nữ
                        'ethnic_completed_dropped_7' => 0,        // BD: Lớp 7
                        'ethnic_completed_dropped_7_female' => 0, // BE: Lớp 7 nữ
                        'ethnic_completed_dropped_8' => 0,        // BF: Lớp 8
                        'ethnic_completed_dropped_8_female' => 0, // BG: Lớp 8 nữ
                        'ethnic_completed_dropped_9' => 0,        // BH: Lớp 9
                        'ethnic_completed_dropped_9_female' => 0, // BI: Lớp 9 nữ
                        'ethnic_completed_dropped_10' => 0,       // BJ: Lớp 10
                        'ethnic_completed_dropped_10_female' => 0,// BK: Lớp 10 nữ
                        'ethnic_completed_dropped_11' => 0,       // BL: Lớp 11
                        'ethnic_completed_dropped_11_female' => 0,// BM: Lớp 11 nữ
                        'ethnic_completed_dropped_12' => 0,       // BN: Lớp 12
                        'ethnic_completed_dropped_12_female' => 0,// BO: Lớp 12 nữ
                        // BP-BS: Tốt nghiệp + có học xong/bỏ học lớp
                        'ethnic_grad_primary_with_completed' => 0,        // BP: TN TH + có học xong/bỏ học
                        'ethnic_grad_primary_with_completed_female' => 0, // BQ: TN TH + có học xong/bỏ học - nữ
                        'ethnic_grad_secondary_with_completed' => 0,      // BR: TN THCS + có học xong/bỏ học
                        'ethnic_grad_secondary_with_completed_female' => 0,// BS: TN THCS + có học xong/bỏ học - nữ
                    ];
                }
                
                // Tổng cả nam và nữ
                $ageData[$age]['total']++;
                
                // Cột C: Tổng số người cả nam và nữ theo độ tuổi (tất cả mọi người)
                $ageData[$age]['total_all']++;
                
                // Cột D: Tổng số nữ chung
                if ($row->is_female) {
                    $ageData[$age]['female']++;
                }
                
                // Kiểm tra xem có phải dân tộc thiểu số không
                $isEthnicMinority = $row->ethnicity_name && strtolower($row->ethnicity_name) !== 'kinh';
                
                // Kiểm tra xem có bị khuyết tật không
                $hasDisability = $row->has_disability ?? false;
                
                // Cột E: Tổng số dân tộc thiểu số cả nam và nữ (trừ dân tộc Kinh)
                if ($isEthnicMinority) {
                    $ageData[$age]['ethnic_all']++;
                }
                
                // Cột H: Tổng số dân tộc bị khuyết tật (dân tộc + bị khuyết tật)
                if ($isEthnicMinority && $hasDisability) {
                    $ageData[$age]['ethnic_disability']++;
                }
                
                // Cột F: Tổng số dân tộc phải phổ cập (dân tộc - dân tộc khuyết tật)
                // Tính sau khi đã tính xong ethnic_all và ethnic_disability
                
                // Cột G: Tổng số nữ dân tộc thiểu số (giới tính nữ, trừ dân tộc Kinh)
                if ($isEthnicMinority && $row->is_female) {
                    $ageData[$age]['ethnic_female']++;
                }
                
                // Kiểm tra trước các thông tin chung (để tái sử dụng)
                $hasEducationInfo = !empty($row->class_label) || !empty($row->school_name) || !empty($row->education_block);
                $hasGraduationLevel = !empty($row->graduation_level_id) && $row->graduation_level_id >= 2;
                $hocXongLop = $row->hoc_xong_lop ?? null;
                $hocXongNam = $row->hoc_xong_nam ?? null;
                $boHocLop = $row->bo_hoc_lop ?? null;
                $boHocNam = $row->bo_hoc_nam ?? null;
                $hasCompletedOrDropped = !empty($hocXongLop) || !empty($hocXongNam) || 
                                        !empty($boHocLop) || !empty($boHocNam);
                
                // Cột I: Tổng số dân tộc thiểu số đang đi học
                if ($isEthnicMinority) {
                    // Điều kiện đang đi học:
                    // 1. Có thông tin giáo dục (lớp, khối, trường)
                    // 2. HOẶC >= 18 tuổi + có bằng TN TH trở lên (graduation_level_id >= 2) NHƯNG KHÔNG có hoc_xong_lop/bo_hoc_lop
                    $isStudying = $hasEducationInfo || ($row->age >= 18 && $hasGraduationLevel && !$hasCompletedOrDropped);
                    
                    if ($isStudying) {
                        $ageData[$age]['ethnic_studying']++;
                        
                        // Thống kê theo lớp (J-AI)
                        $blockId = $row->education_block_id ?? null;
                        $block = $row->education_block ?? '';
                        $class = $row->class_label ?? '';
                        $age = $row->age ?? 0;
                        
                        // Trích xuất số lớp từ education_block hoặc class_label
                        $gradeNumber = null;
                        
                        // Ưu tiên 1: Kiểm tra education_block_id = 1,2,3,4,5,6 => Mầm non
                        if (!empty($blockId) && in_array($blockId, [1, 2, 3, 4, 5, 6])) {
                            $gradeNumber = 0; // Mầm non
                        }
                        // Ưu tiên 2: Nếu <= 5 tuổi VÀ có thông tin đi học => Mầm non
                        else if ($age <= 5 && ($hasEducationInfo)) {
                            $gradeNumber = 0; // Mầm non
                        }
                        // Ưu tiên 3: Kiểm tra từ khóa mầm non
                        else if (stripos($block, 'mầm non') !== false || 
                            stripos($block, 'mẫu giáo') !== false || 
                            stripos($block, 'nhà trẻ') !== false ||
                            stripos($class, 'mầm') !== false ||
                            stripos($class, 'chồi') !== false ||
                            stripos($class, 'nụ') !== false ||
                            stripos($class, 'lá') !== false) {
                            $gradeNumber = 0; // Mầm non
                        }
                        // Trích xuất số từ education_block
                        else if (preg_match('/\b(\d+)\b/', $block, $matches)) {
                            $gradeNumber = (int)$matches[1];
                        }
                        // Trích xuất số từ class_label
                        else if (preg_match('/\b(\d+)\b/', $class, $matches)) {
                            $gradeNumber = (int)$matches[1];
                        }
                        
                        // Cập nhật thống kê theo lớp
                        if ($gradeNumber !== null) {
                            if ($gradeNumber == 0) {
                                // Mầm non
                                $ageData[$age]['ethnic_preschool']++;
                                if ($row->is_female) {
                                    $ageData[$age]['ethnic_preschool_female']++;
                                }
                            } else if ($gradeNumber >= 1 && $gradeNumber <= 12) {
                                // Lớp 1-12
                                $key = 'ethnic_grade_' . $gradeNumber;
                                $keyFemale = 'ethnic_grade_' . $gradeNumber . '_female';
                                $ageData[$age][$key]++;
                                if ($row->is_female) {
                                    $ageData[$age][$keyFemale]++;
                                }
                            }
                        }
                    }
                }
                
                // Thống kê dân tộc thiểu số (không phải Kinh) - giữ lại cho cột B
                if ($isEthnicMinority) {
                    $ageData[$age]['ethnic']++;
                    
                    // AJ-AO: Thống kê tốt nghiệp (loại trừ có hoc_xong_lop hoặc bo_hoc_lop)
                    // Chỉ đếm nếu KHÔNG có hoc_xong_lop/nam và KHÔNG có bo_hoc_lop/nam
                    if (!$hasCompletedOrDropped && !empty($row->graduation_level_id)) {
                        $gradLevel = (int)$row->graduation_level_id;
                        
                        if ($gradLevel == 2) { // Tốt nghiệp tiểu học
                            $ageData[$age]['ethnic_grad_primary']++;
                            if ($row->is_female) {
                                $ageData[$age]['ethnic_grad_primary_female']++;
                            }
                        } elseif ($gradLevel == 3) { // Tốt nghiệp THCS
                            $ageData[$age]['ethnic_grad_secondary']++;
                            if ($row->is_female) {
                                $ageData[$age]['ethnic_grad_secondary_female']++;
                            }
                        } elseif ($gradLevel == 4) { // Tốt nghiệp THPT
                            $ageData[$age]['ethnic_grad_highschool']++;
                            if ($row->is_female) {
                                $ageData[$age]['ethnic_grad_highschool_female']++;
                            }
                        }
                    }
                    
                    // BP-BS: Thống kê tốt nghiệp + có học xong/bỏ học lớp
                    // Chỉ đếm nếu CÓ hoc_xong_lop/nam hoặc bo_hoc_lop/nam VÀ có bậc tốt nghiệp
                    if ($hasCompletedOrDropped && !empty($row->graduation_level_id)) {
                        $gradLevel = (int)$row->graduation_level_id;
                        
                        if ($gradLevel == 2) { // Tốt nghiệp tiểu học
                            $ageData[$age]['ethnic_grad_primary_with_completed']++;
                            if ($row->is_female) {
                                $ageData[$age]['ethnic_grad_primary_with_completed_female']++;
                            }
                        } elseif ($gradLevel == 3) { // Tốt nghiệp THCS
                            $ageData[$age]['ethnic_grad_secondary_with_completed']++;
                            if ($row->is_female) {
                                $ageData[$age]['ethnic_grad_secondary_with_completed_female']++;
                            }
                        }
                    }
                    
                    // AP-AQ: Thống kê chưa đi học và bỏ học
                    // AQ: Chưa từng đi học (không có lớp, trường, khối, bậc tốt nghiệp, học xong lớp, bỏ học lớp)
                    $hasNoEducationAtAll = !$hasEducationInfo && !$hasGraduationLevel && !$hasCompletedOrDropped;
                    if ($hasNoEducationAtAll) {
                        $ageData[$age]['ethnic_never_studied']++;
                    }
                    
                    // AP: Tổng số người chưa đi học (AQ) + Tổng số người có thông tin bỏ học/học xong lớp
                    if ($hasNoEducationAtAll || $hasCompletedOrDropped) {
                        $ageData[$age]['ethnic_not_studying_dropped']++;
                    }
                    
                    // AR-BO: Thống kê học xong lớp + bỏ học lớp theo từng lớp (1-12)
                    if ($hasCompletedOrDropped) {
                        // Xác định lớp từ hoc_xong_lop hoặc bo_hoc_lop
                        $gradeLevel = null;
                        
                        if (!empty($hocXongLop)) {
                            $gradeLevel = (int)$hocXongLop;
                        } elseif (!empty($boHocLop)) {
                            $gradeLevel = (int)$boHocLop;
                        }
                        
                        // Chỉ đếm từ lớp 1 đến lớp 12
                        if ($gradeLevel >= 1 && $gradeLevel <= 12) {
                            $key = 'ethnic_completed_dropped_' . $gradeLevel;
                            $keyFemale = 'ethnic_completed_dropped_' . $gradeLevel . '_female';
                            
                            $ageData[$age][$key]++;
                            if ($row->is_female) {
                                $ageData[$age][$keyFemale]++;
                            }
                        }
                    }
                }
            }
        }
        
        // Tính cột F: Tổng số dân tộc phải phổ cập = Tổng dân tộc - Dân tộc khuyết tật
        foreach ($ageData as $age => &$data) {
            $data['ethnic_must_study'] = $data['ethnic_all'] - $data['ethnic_disability'];
        }
        unset($data); // Giải phóng reference
        
        // Sắp xếp theo tuổi (từ 0 đến 60)
        ksort($ageData);
        
        // Điền dữ liệu vào các ô theo nhóm tuổi và tính tổng
        $totalRows = ['11' => 0, '15' => 0, '25' => 0, '30' => 0, '38' => 0, '49' => 0, '75' => 0];
        $grandTotal = [
            'total' => 0, 'ethnic' => 0, 'total_all' => 0, 'female' => 0, 'ethnic_all' => 0, 'ethnic_must_study' => 0, 'ethnic_female' => 0, 'ethnic_disability' => 0, 'ethnic_studying' => 0,
            'ethnic_preschool' => 0, 'ethnic_preschool_female' => 0,
            'ethnic_grade_1' => 0, 'ethnic_grade_1_female' => 0,
            'ethnic_grade_2' => 0, 'ethnic_grade_2_female' => 0,
            'ethnic_grade_3' => 0, 'ethnic_grade_3_female' => 0,
            'ethnic_grade_4' => 0, 'ethnic_grade_4_female' => 0,
            'ethnic_grade_5' => 0, 'ethnic_grade_5_female' => 0,
            'ethnic_grade_6' => 0, 'ethnic_grade_6_female' => 0,
            'ethnic_grade_7' => 0, 'ethnic_grade_7_female' => 0,
            'ethnic_grade_8' => 0, 'ethnic_grade_8_female' => 0,
            'ethnic_grade_9' => 0, 'ethnic_grade_9_female' => 0,
            'ethnic_grade_10' => 0, 'ethnic_grade_10_female' => 0,
            'ethnic_grade_11' => 0, 'ethnic_grade_11_female' => 0,
            'ethnic_grade_12' => 0, 'ethnic_grade_12_female' => 0,
            'ethnic_grad_primary' => 0, 'ethnic_grad_primary_female' => 0,
            'ethnic_grad_secondary' => 0, 'ethnic_grad_secondary_female' => 0,
            'ethnic_grad_highschool' => 0, 'ethnic_grad_highschool_female' => 0,
            'ethnic_not_studying_dropped' => 0, 'ethnic_never_studied' => 0,
            'ethnic_completed_dropped_1' => 0, 'ethnic_completed_dropped_1_female' => 0,
            'ethnic_completed_dropped_2' => 0, 'ethnic_completed_dropped_2_female' => 0,
            'ethnic_completed_dropped_3' => 0, 'ethnic_completed_dropped_3_female' => 0,
            'ethnic_completed_dropped_4' => 0, 'ethnic_completed_dropped_4_female' => 0,
            'ethnic_completed_dropped_5' => 0, 'ethnic_completed_dropped_5_female' => 0,
            'ethnic_completed_dropped_6' => 0, 'ethnic_completed_dropped_6_female' => 0,
            'ethnic_completed_dropped_7' => 0, 'ethnic_completed_dropped_7_female' => 0,
            'ethnic_completed_dropped_8' => 0, 'ethnic_completed_dropped_8_female' => 0,
            'ethnic_completed_dropped_9' => 0, 'ethnic_completed_dropped_9_female' => 0,
            'ethnic_completed_dropped_10' => 0, 'ethnic_completed_dropped_10_female' => 0,
            'ethnic_completed_dropped_11' => 0, 'ethnic_completed_dropped_11_female' => 0,
            'ethnic_completed_dropped_12' => 0, 'ethnic_completed_dropped_12_female' => 0,
            'ethnic_grad_primary_with_completed' => 0, 'ethnic_grad_primary_with_completed_female' => 0,
            'ethnic_grad_secondary_with_completed' => 0, 'ethnic_grad_secondary_with_completed_female' => 0,
        ];
        
        foreach ($ageRanges as $range => $positions) {
            list($minAge, $maxAge) = explode('-', $range);
            $minAge = (int)$minAge;
            $maxAge = (int)$maxAge;
            
            // Tính tổng cho nhóm tuổi này
            $groupTotal = [
                'total' => 0, 'ethnic' => 0, 'total_all' => 0, 'female' => 0, 'ethnic_all' => 0, 'ethnic_must_study' => 0, 'ethnic_female' => 0, 'ethnic_disability' => 0, 'ethnic_studying' => 0,
                'ethnic_preschool' => 0, 'ethnic_preschool_female' => 0,
                'ethnic_grade_1' => 0, 'ethnic_grade_1_female' => 0,
                'ethnic_grade_2' => 0, 'ethnic_grade_2_female' => 0,
                'ethnic_grade_3' => 0, 'ethnic_grade_3_female' => 0,
                'ethnic_grade_4' => 0, 'ethnic_grade_4_female' => 0,
                'ethnic_grade_5' => 0, 'ethnic_grade_5_female' => 0,
                'ethnic_grade_6' => 0, 'ethnic_grade_6_female' => 0,
                'ethnic_grade_7' => 0, 'ethnic_grade_7_female' => 0,
                'ethnic_grade_8' => 0, 'ethnic_grade_8_female' => 0,
                'ethnic_grade_9' => 0, 'ethnic_grade_9_female' => 0,
                'ethnic_grade_10' => 0, 'ethnic_grade_10_female' => 0,
                'ethnic_grade_11' => 0, 'ethnic_grade_11_female' => 0,
                'ethnic_grade_12' => 0, 'ethnic_grade_12_female' => 0,
                'ethnic_grad_primary' => 0, 'ethnic_grad_primary_female' => 0,
                'ethnic_grad_secondary' => 0, 'ethnic_grad_secondary_female' => 0,
                'ethnic_grad_highschool' => 0, 'ethnic_grad_highschool_female' => 0,
                'ethnic_not_studying_dropped' => 0, 'ethnic_never_studied' => 0,
                'ethnic_completed_dropped_1' => 0, 'ethnic_completed_dropped_1_female' => 0,
                'ethnic_completed_dropped_2' => 0, 'ethnic_completed_dropped_2_female' => 0,
                'ethnic_completed_dropped_3' => 0, 'ethnic_completed_dropped_3_female' => 0,
                'ethnic_completed_dropped_4' => 0, 'ethnic_completed_dropped_4_female' => 0,
                'ethnic_completed_dropped_5' => 0, 'ethnic_completed_dropped_5_female' => 0,
                'ethnic_completed_dropped_6' => 0, 'ethnic_completed_dropped_6_female' => 0,
                'ethnic_completed_dropped_7' => 0, 'ethnic_completed_dropped_7_female' => 0,
                'ethnic_completed_dropped_8' => 0, 'ethnic_completed_dropped_8_female' => 0,
                'ethnic_completed_dropped_9' => 0, 'ethnic_completed_dropped_9_female' => 0,
                'ethnic_completed_dropped_10' => 0, 'ethnic_completed_dropped_10_female' => 0,
                'ethnic_completed_dropped_11' => 0, 'ethnic_completed_dropped_11_female' => 0,
                'ethnic_completed_dropped_12' => 0, 'ethnic_completed_dropped_12_female' => 0,
                'ethnic_grad_primary_with_completed' => 0, 'ethnic_grad_primary_with_completed_female' => 0,
                'ethnic_grad_secondary_with_completed' => 0, 'ethnic_grad_secondary_with_completed_female' => 0,
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
                        'total' => 0, 'ethnic' => 0, 'total_all' => 0, 'female' => 0, 
                        'ethnic_all' => 0, 'ethnic_must_study' => 0, 'ethnic_female' => 0, 
                        'ethnic_disability' => 0, 'ethnic_studying' => 0,
                        'ethnic_preschool' => 0, 'ethnic_preschool_female' => 0,
                        'ethnic_grade_1' => 0, 'ethnic_grade_1_female' => 0,
                        'ethnic_grade_2' => 0, 'ethnic_grade_2_female' => 0,
                        'ethnic_grade_3' => 0, 'ethnic_grade_3_female' => 0,
                        'ethnic_grade_4' => 0, 'ethnic_grade_4_female' => 0,
                        'ethnic_grade_5' => 0, 'ethnic_grade_5_female' => 0,
                        'ethnic_grade_6' => 0, 'ethnic_grade_6_female' => 0,
                        'ethnic_grade_7' => 0, 'ethnic_grade_7_female' => 0,
                        'ethnic_grade_8' => 0, 'ethnic_grade_8_female' => 0,
                        'ethnic_grade_9' => 0, 'ethnic_grade_9_female' => 0,
                        'ethnic_grade_10' => 0, 'ethnic_grade_10_female' => 0,
                        'ethnic_grade_11' => 0, 'ethnic_grade_11_female' => 0,
                        'ethnic_grade_12' => 0, 'ethnic_grade_12_female' => 0,
                        'ethnic_grad_primary' => 0, 'ethnic_grad_primary_female' => 0,
                        'ethnic_grad_secondary' => 0, 'ethnic_grad_secondary_female' => 0,
                        'ethnic_grad_highschool' => 0, 'ethnic_grad_highschool_female' => 0,
                        'ethnic_not_studying_dropped' => 0, 'ethnic_never_studied' => 0,
                        'ethnic_completed_dropped_1' => 0, 'ethnic_completed_dropped_1_female' => 0,
                        'ethnic_completed_dropped_2' => 0, 'ethnic_completed_dropped_2_female' => 0,
                        'ethnic_completed_dropped_3' => 0, 'ethnic_completed_dropped_3_female' => 0,
                        'ethnic_completed_dropped_4' => 0, 'ethnic_completed_dropped_4_female' => 0,
                        'ethnic_completed_dropped_5' => 0, 'ethnic_completed_dropped_5_female' => 0,
                        'ethnic_completed_dropped_6' => 0, 'ethnic_completed_dropped_6_female' => 0,
                        'ethnic_completed_dropped_7' => 0, 'ethnic_completed_dropped_7_female' => 0,
                        'ethnic_completed_dropped_8' => 0, 'ethnic_completed_dropped_8_female' => 0,
                        'ethnic_completed_dropped_9' => 0, 'ethnic_completed_dropped_9_female' => 0,
                        'ethnic_completed_dropped_10' => 0, 'ethnic_completed_dropped_10_female' => 0,
                        'ethnic_completed_dropped_11' => 0, 'ethnic_completed_dropped_11_female' => 0,
                        'ethnic_completed_dropped_12' => 0, 'ethnic_completed_dropped_12_female' => 0,
                        'ethnic_grad_primary_with_completed' => 0, 'ethnic_grad_primary_with_completed_female' => 0,
                        'ethnic_grad_secondary_with_completed' => 0, 'ethnic_grad_secondary_with_completed_female' => 0,
                    ];
                }
                
                // Cộng vào tổng nhóm
                $allKeys = ['total', 'ethnic', 'total_all', 'female', 'ethnic_all', 'ethnic_must_study', 'ethnic_female', 'ethnic_disability', 'ethnic_studying',
                    'ethnic_preschool', 'ethnic_preschool_female',
                    'ethnic_grade_1', 'ethnic_grade_1_female', 'ethnic_grade_2', 'ethnic_grade_2_female',
                    'ethnic_grade_3', 'ethnic_grade_3_female', 'ethnic_grade_4', 'ethnic_grade_4_female',
                    'ethnic_grade_5', 'ethnic_grade_5_female', 'ethnic_grade_6', 'ethnic_grade_6_female',
                    'ethnic_grade_7', 'ethnic_grade_7_female', 'ethnic_grade_8', 'ethnic_grade_8_female',
                    'ethnic_grade_9', 'ethnic_grade_9_female', 'ethnic_grade_10', 'ethnic_grade_10_female',
                    'ethnic_grade_11', 'ethnic_grade_11_female', 'ethnic_grade_12', 'ethnic_grade_12_female',
                    'ethnic_grad_primary', 'ethnic_grad_primary_female',
                    'ethnic_grad_secondary', 'ethnic_grad_secondary_female',
                    'ethnic_grad_highschool', 'ethnic_grad_highschool_female',
                    'ethnic_not_studying_dropped', 'ethnic_never_studied',
                    'ethnic_completed_dropped_1', 'ethnic_completed_dropped_1_female',
                    'ethnic_completed_dropped_2', 'ethnic_completed_dropped_2_female',
                    'ethnic_completed_dropped_3', 'ethnic_completed_dropped_3_female',
                    'ethnic_completed_dropped_4', 'ethnic_completed_dropped_4_female',
                    'ethnic_completed_dropped_5', 'ethnic_completed_dropped_5_female',
                    'ethnic_completed_dropped_6', 'ethnic_completed_dropped_6_female',
                    'ethnic_completed_dropped_7', 'ethnic_completed_dropped_7_female',
                    'ethnic_completed_dropped_8', 'ethnic_completed_dropped_8_female',
                    'ethnic_completed_dropped_9', 'ethnic_completed_dropped_9_female',
                    'ethnic_completed_dropped_10', 'ethnic_completed_dropped_10_female',
                    'ethnic_completed_dropped_11', 'ethnic_completed_dropped_11_female',
                    'ethnic_completed_dropped_12', 'ethnic_completed_dropped_12_female',
                    'ethnic_grad_primary_with_completed', 'ethnic_grad_primary_with_completed_female',
                    'ethnic_grad_secondary_with_completed', 'ethnic_grad_secondary_with_completed_female'];
                foreach ($allKeys as $key) {
                    $groupTotal[$key] += $data[$key];
                }
                
                // Chỉ hiển thị nếu còn chỗ trong template
                if ($row <= $positions['end']) {
                    // Cột A: Năm sinh (tính theo năm học), Cột B: Tuổi
                    $sh->setCellValue('A'.$row, $data['calculated_birth_year']);
                    $sh->setCellValue('B'.$row, $data['age']);
                    
                    // Cột C: Tổng số người cả nam và nữ theo độ tuổi
                    $sh->setCellValue('C'.$row, $data['total_all'] > 0 ? $data['total_all'] : '');
                    
                    // Cột D: Tổng số nữ chung
                    $sh->setCellValue('D'.$row, $data['female'] > 0 ? $data['female'] : '');
                    
                    // Cột E: Tổng số dân tộc thiểu số cả nam và nữ (trừ dân tộc Kinh)
                    $sh->setCellValue('E'.$row, $data['ethnic_all'] > 0 ? $data['ethnic_all'] : '');
                    
                    // Cột F: Tổng số dân tộc phải phổ cập (dân tộc - dân tộc khuyết tật)
                    $sh->setCellValue('F'.$row, $data['ethnic_must_study'] > 0 ? $data['ethnic_must_study'] : '');
                    
                    // Cột G: Tổng số nữ dân tộc thiểu số (trừ dân tộc Kinh)
                    $sh->setCellValue('G'.$row, $data['ethnic_female'] > 0 ? $data['ethnic_female'] : '');
                    
                    // Cột H: Tổng số dân tộc bị khuyết tật
                    $sh->setCellValue('H'.$row, $data['ethnic_disability'] > 0 ? $data['ethnic_disability'] : '');
                    
                    // Cột I: Tổng số dân tộc thiểu số đang đi học
                    $sh->setCellValue('I'.$row, $data['ethnic_studying'] > 0 ? $data['ethnic_studying'] : '');
                    
                    // Cột J-AI: Thống kê theo lớp
                    // J-K: Mầm non
                    $sh->setCellValue('J'.$row, $data['ethnic_preschool'] > 0 ? $data['ethnic_preschool'] : '');
                    $sh->setCellValue('K'.$row, $data['ethnic_preschool_female'] > 0 ? $data['ethnic_preschool_female'] : '');
                    // L-M: Lớp 1
                    $sh->setCellValue('L'.$row, $data['ethnic_grade_1'] > 0 ? $data['ethnic_grade_1'] : '');
                    $sh->setCellValue('M'.$row, $data['ethnic_grade_1_female'] > 0 ? $data['ethnic_grade_1_female'] : '');
                    // N-O: Lớp 2
                    $sh->setCellValue('N'.$row, $data['ethnic_grade_2'] > 0 ? $data['ethnic_grade_2'] : '');
                    $sh->setCellValue('O'.$row, $data['ethnic_grade_2_female'] > 0 ? $data['ethnic_grade_2_female'] : '');
                    // P-Q: Lớp 3
                    $sh->setCellValue('P'.$row, $data['ethnic_grade_3'] > 0 ? $data['ethnic_grade_3'] : '');
                    $sh->setCellValue('Q'.$row, $data['ethnic_grade_3_female'] > 0 ? $data['ethnic_grade_3_female'] : '');
                    // R-S: Lớp 4
                    $sh->setCellValue('R'.$row, $data['ethnic_grade_4'] > 0 ? $data['ethnic_grade_4'] : '');
                    $sh->setCellValue('S'.$row, $data['ethnic_grade_4_female'] > 0 ? $data['ethnic_grade_4_female'] : '');
                    // T-U: Lớp 5
                    $sh->setCellValue('T'.$row, $data['ethnic_grade_5'] > 0 ? $data['ethnic_grade_5'] : '');
                    $sh->setCellValue('U'.$row, $data['ethnic_grade_5_female'] > 0 ? $data['ethnic_grade_5_female'] : '');
                    // V-W: Lớp 6
                    $sh->setCellValue('V'.$row, $data['ethnic_grade_6'] > 0 ? $data['ethnic_grade_6'] : '');
                    $sh->setCellValue('W'.$row, $data['ethnic_grade_6_female'] > 0 ? $data['ethnic_grade_6_female'] : '');
                    // X-Y: Lớp 7
                    $sh->setCellValue('X'.$row, $data['ethnic_grade_7'] > 0 ? $data['ethnic_grade_7'] : '');
                    $sh->setCellValue('Y'.$row, $data['ethnic_grade_7_female'] > 0 ? $data['ethnic_grade_7_female'] : '');
                    // Z-AA: Lớp 8
                    $sh->setCellValue('Z'.$row, $data['ethnic_grade_8'] > 0 ? $data['ethnic_grade_8'] : '');
                    $sh->setCellValue('AA'.$row, $data['ethnic_grade_8_female'] > 0 ? $data['ethnic_grade_8_female'] : '');
                    // AB-AC: Lớp 9
                    $sh->setCellValue('AB'.$row, $data['ethnic_grade_9'] > 0 ? $data['ethnic_grade_9'] : '');
                    $sh->setCellValue('AC'.$row, $data['ethnic_grade_9_female'] > 0 ? $data['ethnic_grade_9_female'] : '');
                    // AD-AE: Lớp 10
                    $sh->setCellValue('AD'.$row, $data['ethnic_grade_10'] > 0 ? $data['ethnic_grade_10'] : '');
                    $sh->setCellValue('AE'.$row, $data['ethnic_grade_10_female'] > 0 ? $data['ethnic_grade_10_female'] : '');
                    // AF-AG: Lớp 11
                    $sh->setCellValue('AF'.$row, $data['ethnic_grade_11'] > 0 ? $data['ethnic_grade_11'] : '');
                    $sh->setCellValue('AG'.$row, $data['ethnic_grade_11_female'] > 0 ? $data['ethnic_grade_11_female'] : '');
                    // AH-AI: Lớp 12
                    $sh->setCellValue('AH'.$row, $data['ethnic_grade_12'] > 0 ? $data['ethnic_grade_12'] : '');
                    $sh->setCellValue('AI'.$row, $data['ethnic_grade_12_female'] > 0 ? $data['ethnic_grade_12_female'] : '');
                    
                    // AJ-AO: Thống kê tốt nghiệp
                    // AJ-AK: Tốt nghiệp tiểu học
                    $sh->setCellValue('AJ'.$row, $data['ethnic_grad_primary'] > 0 ? $data['ethnic_grad_primary'] : '');
                    $sh->setCellValue('AK'.$row, $data['ethnic_grad_primary_female'] > 0 ? $data['ethnic_grad_primary_female'] : '');
                    // AL-AM: Tốt nghiệp THCS
                    $sh->setCellValue('AL'.$row, $data['ethnic_grad_secondary'] > 0 ? $data['ethnic_grad_secondary'] : '');
                    $sh->setCellValue('AM'.$row, $data['ethnic_grad_secondary_female'] > 0 ? $data['ethnic_grad_secondary_female'] : '');
                    // AN-AO: Tốt nghiệp THPT
                    $sh->setCellValue('AN'.$row, $data['ethnic_grad_highschool'] > 0 ? $data['ethnic_grad_highschool'] : '');
                    $sh->setCellValue('AO'.$row, $data['ethnic_grad_highschool_female'] > 0 ? $data['ethnic_grad_highschool_female'] : '');
                    
                    // AP-AQ: Thống kê chưa đi học và bỏ học
                    // AP: Chưa đi học + Bỏ học
                    $sh->setCellValue('AP'.$row, $data['ethnic_not_studying_dropped'] > 0 ? $data['ethnic_not_studying_dropped'] : '');
                    // AQ: Chưa từng đi học
                    $sh->setCellValue('AQ'.$row, $data['ethnic_never_studied'] > 0 ? $data['ethnic_never_studied'] : '');
                    
                    // AR-BO: Thống kê học xong lớp + bỏ học lớp theo từng lớp
                    // AR-AS: Lớp 1
                    $sh->setCellValue('AR'.$row, $data['ethnic_completed_dropped_1'] > 0 ? $data['ethnic_completed_dropped_1'] : '');
                    $sh->setCellValue('AS'.$row, $data['ethnic_completed_dropped_1_female'] > 0 ? $data['ethnic_completed_dropped_1_female'] : '');
                    // AT-AU: Lớp 2
                    $sh->setCellValue('AT'.$row, $data['ethnic_completed_dropped_2'] > 0 ? $data['ethnic_completed_dropped_2'] : '');
                    $sh->setCellValue('AU'.$row, $data['ethnic_completed_dropped_2_female'] > 0 ? $data['ethnic_completed_dropped_2_female'] : '');
                    // AV-AW: Lớp 3
                    $sh->setCellValue('AV'.$row, $data['ethnic_completed_dropped_3'] > 0 ? $data['ethnic_completed_dropped_3'] : '');
                    $sh->setCellValue('AW'.$row, $data['ethnic_completed_dropped_3_female'] > 0 ? $data['ethnic_completed_dropped_3_female'] : '');
                    // AX-AY: Lớp 4
                    $sh->setCellValue('AX'.$row, $data['ethnic_completed_dropped_4'] > 0 ? $data['ethnic_completed_dropped_4'] : '');
                    $sh->setCellValue('AY'.$row, $data['ethnic_completed_dropped_4_female'] > 0 ? $data['ethnic_completed_dropped_4_female'] : '');
                    // AZ-BA: Lớp 5
                    $sh->setCellValue('AZ'.$row, $data['ethnic_completed_dropped_5'] > 0 ? $data['ethnic_completed_dropped_5'] : '');
                    $sh->setCellValue('BA'.$row, $data['ethnic_completed_dropped_5_female'] > 0 ? $data['ethnic_completed_dropped_5_female'] : '');
                    // BB-BC: Lớp 6
                    $sh->setCellValue('BB'.$row, $data['ethnic_completed_dropped_6'] > 0 ? $data['ethnic_completed_dropped_6'] : '');
                    $sh->setCellValue('BC'.$row, $data['ethnic_completed_dropped_6_female'] > 0 ? $data['ethnic_completed_dropped_6_female'] : '');
                    // BD-BE: Lớp 7
                    $sh->setCellValue('BD'.$row, $data['ethnic_completed_dropped_7'] > 0 ? $data['ethnic_completed_dropped_7'] : '');
                    $sh->setCellValue('BE'.$row, $data['ethnic_completed_dropped_7_female'] > 0 ? $data['ethnic_completed_dropped_7_female'] : '');
                    // BF-BG: Lớp 8
                    $sh->setCellValue('BF'.$row, $data['ethnic_completed_dropped_8'] > 0 ? $data['ethnic_completed_dropped_8'] : '');
                    $sh->setCellValue('BG'.$row, $data['ethnic_completed_dropped_8_female'] > 0 ? $data['ethnic_completed_dropped_8_female'] : '');
                    // BH-BI: Lớp 9
                    $sh->setCellValue('BH'.$row, $data['ethnic_completed_dropped_9'] > 0 ? $data['ethnic_completed_dropped_9'] : '');
                    $sh->setCellValue('BI'.$row, $data['ethnic_completed_dropped_9_female'] > 0 ? $data['ethnic_completed_dropped_9_female'] : '');
                    // BJ-BK: Lớp 10
                    $sh->setCellValue('BJ'.$row, $data['ethnic_completed_dropped_10'] > 0 ? $data['ethnic_completed_dropped_10'] : '');
                    $sh->setCellValue('BK'.$row, $data['ethnic_completed_dropped_10_female'] > 0 ? $data['ethnic_completed_dropped_10_female'] : '');
                    // BL-BM: Lớp 11
                    $sh->setCellValue('BL'.$row, $data['ethnic_completed_dropped_11'] > 0 ? $data['ethnic_completed_dropped_11'] : '');
                    $sh->setCellValue('BM'.$row, $data['ethnic_completed_dropped_11_female'] > 0 ? $data['ethnic_completed_dropped_11_female'] : '');
                    // BN-BO: Lớp 12
                    $sh->setCellValue('BN'.$row, $data['ethnic_completed_dropped_12'] > 0 ? $data['ethnic_completed_dropped_12'] : '');
                    $sh->setCellValue('BO'.$row, $data['ethnic_completed_dropped_12_female'] > 0 ? $data['ethnic_completed_dropped_12_female'] : '');
                    
                    // BP-BS: Tốt nghiệp + có học xong/bỏ học lớp
                    // BP-BQ: TN TH + có học xong/bỏ học
                    $sh->setCellValue('BP'.$row, $data['ethnic_grad_primary_with_completed'] > 0 ? $data['ethnic_grad_primary_with_completed'] : '');
                    $sh->setCellValue('BQ'.$row, $data['ethnic_grad_primary_with_completed_female'] > 0 ? $data['ethnic_grad_primary_with_completed_female'] : '');
                    // BR-BS: TN THCS + có học xong/bỏ học
                    $sh->setCellValue('BR'.$row, $data['ethnic_grad_secondary_with_completed'] > 0 ? $data['ethnic_grad_secondary_with_completed'] : '');
                    $sh->setCellValue('BS'.$row, $data['ethnic_grad_secondary_with_completed_female'] > 0 ? $data['ethnic_grad_secondary_with_completed_female'] : '');
                    
                    // Thiết lập font size và căn giữa
                    $sh->getStyle('A'.$row.':BS'.$row)->getFont()->setName('Tahoma')->setSize(8);
                    $sh->getStyle('A'.$row.':BS'.$row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    
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
            $allKeys = ['total', 'ethnic', 'total_all', 'female', 'ethnic_all', 'ethnic_must_study', 'ethnic_female', 'ethnic_disability', 'ethnic_studying',
                'ethnic_preschool', 'ethnic_preschool_female',
                'ethnic_grade_1', 'ethnic_grade_1_female', 'ethnic_grade_2', 'ethnic_grade_2_female',
                'ethnic_grade_3', 'ethnic_grade_3_female', 'ethnic_grade_4', 'ethnic_grade_4_female',
                'ethnic_grade_5', 'ethnic_grade_5_female', 'ethnic_grade_6', 'ethnic_grade_6_female',
                'ethnic_grade_7', 'ethnic_grade_7_female', 'ethnic_grade_8', 'ethnic_grade_8_female',
                'ethnic_grade_9', 'ethnic_grade_9_female', 'ethnic_grade_10', 'ethnic_grade_10_female',
                'ethnic_grade_11', 'ethnic_grade_11_female', 'ethnic_grade_12', 'ethnic_grade_12_female',
                'ethnic_grad_primary', 'ethnic_grad_primary_female',
                'ethnic_grad_secondary', 'ethnic_grad_secondary_female',
                'ethnic_grad_highschool', 'ethnic_grad_highschool_female',
                'ethnic_not_studying_dropped', 'ethnic_never_studied',
                'ethnic_completed_dropped_1', 'ethnic_completed_dropped_1_female',
                'ethnic_completed_dropped_2', 'ethnic_completed_dropped_2_female',
                'ethnic_completed_dropped_3', 'ethnic_completed_dropped_3_female',
                'ethnic_completed_dropped_4', 'ethnic_completed_dropped_4_female',
                'ethnic_completed_dropped_5', 'ethnic_completed_dropped_5_female',
                'ethnic_completed_dropped_6', 'ethnic_completed_dropped_6_female',
                'ethnic_completed_dropped_7', 'ethnic_completed_dropped_7_female',
                'ethnic_completed_dropped_8', 'ethnic_completed_dropped_8_female',
                'ethnic_completed_dropped_9', 'ethnic_completed_dropped_9_female',
                'ethnic_completed_dropped_10', 'ethnic_completed_dropped_10_female',
                'ethnic_completed_dropped_11', 'ethnic_completed_dropped_11_female',
                'ethnic_completed_dropped_12', 'ethnic_completed_dropped_12_female',
                'ethnic_grad_primary_with_completed', 'ethnic_grad_primary_with_completed_female',
                'ethnic_grad_secondary_with_completed', 'ethnic_grad_secondary_with_completed_female'];
            foreach ($allKeys as $key) {
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
        
        // Cột C: Tổng số người cả nam và nữ theo độ tuổi
        $sh->setCellValue('C'.$row, $total['total_all'] > 0 ? $total['total_all'] : '');
        
        // Cột D: Tổng số nữ chung
        $sh->setCellValue('D'.$row, $total['female'] > 0 ? $total['female'] : '');
        
        // Cột E: Tổng số dân tộc thiểu số cả nam và nữ (trừ dân tộc Kinh)
        $sh->setCellValue('E'.$row, $total['ethnic_all'] > 0 ? $total['ethnic_all'] : '');
        
        // Cột F: Tổng số dân tộc phải phổ cập (dân tộc - dân tộc khuyết tật)
        $sh->setCellValue('F'.$row, $total['ethnic_must_study'] > 0 ? $total['ethnic_must_study'] : '');
        
        // Cột G: Tổng số nữ dân tộc thiểu số (trừ dân tộc Kinh)
        $sh->setCellValue('G'.$row, $total['ethnic_female'] > 0 ? $total['ethnic_female'] : '');
        
        // Cột H: Tổng số dân tộc bị khuyết tật
        $sh->setCellValue('H'.$row, $total['ethnic_disability'] > 0 ? $total['ethnic_disability'] : '');
        
        // Cột I: Tổng số dân tộc thiểu số đang đi học
        $sh->setCellValue('I'.$row, $total['ethnic_studying'] > 0 ? $total['ethnic_studying'] : '');
        
        // Cột J-AI: Thống kê theo lớp
        $sh->setCellValue('J'.$row, $total['ethnic_preschool'] > 0 ? $total['ethnic_preschool'] : '');
        $sh->setCellValue('K'.$row, $total['ethnic_preschool_female'] > 0 ? $total['ethnic_preschool_female'] : '');
        $sh->setCellValue('L'.$row, $total['ethnic_grade_1'] > 0 ? $total['ethnic_grade_1'] : '');
        $sh->setCellValue('M'.$row, $total['ethnic_grade_1_female'] > 0 ? $total['ethnic_grade_1_female'] : '');
        $sh->setCellValue('N'.$row, $total['ethnic_grade_2'] > 0 ? $total['ethnic_grade_2'] : '');
        $sh->setCellValue('O'.$row, $total['ethnic_grade_2_female'] > 0 ? $total['ethnic_grade_2_female'] : '');
        $sh->setCellValue('P'.$row, $total['ethnic_grade_3'] > 0 ? $total['ethnic_grade_3'] : '');
        $sh->setCellValue('Q'.$row, $total['ethnic_grade_3_female'] > 0 ? $total['ethnic_grade_3_female'] : '');
        $sh->setCellValue('R'.$row, $total['ethnic_grade_4'] > 0 ? $total['ethnic_grade_4'] : '');
        $sh->setCellValue('S'.$row, $total['ethnic_grade_4_female'] > 0 ? $total['ethnic_grade_4_female'] : '');
        $sh->setCellValue('T'.$row, $total['ethnic_grade_5'] > 0 ? $total['ethnic_grade_5'] : '');
        $sh->setCellValue('U'.$row, $total['ethnic_grade_5_female'] > 0 ? $total['ethnic_grade_5_female'] : '');
        $sh->setCellValue('V'.$row, $total['ethnic_grade_6'] > 0 ? $total['ethnic_grade_6'] : '');
        $sh->setCellValue('W'.$row, $total['ethnic_grade_6_female'] > 0 ? $total['ethnic_grade_6_female'] : '');
        $sh->setCellValue('X'.$row, $total['ethnic_grade_7'] > 0 ? $total['ethnic_grade_7'] : '');
        $sh->setCellValue('Y'.$row, $total['ethnic_grade_7_female'] > 0 ? $total['ethnic_grade_7_female'] : '');
        $sh->setCellValue('Z'.$row, $total['ethnic_grade_8'] > 0 ? $total['ethnic_grade_8'] : '');
        $sh->setCellValue('AA'.$row, $total['ethnic_grade_8_female'] > 0 ? $total['ethnic_grade_8_female'] : '');
        $sh->setCellValue('AB'.$row, $total['ethnic_grade_9'] > 0 ? $total['ethnic_grade_9'] : '');
        $sh->setCellValue('AC'.$row, $total['ethnic_grade_9_female'] > 0 ? $total['ethnic_grade_9_female'] : '');
        $sh->setCellValue('AD'.$row, $total['ethnic_grade_10'] > 0 ? $total['ethnic_grade_10'] : '');
        $sh->setCellValue('AE'.$row, $total['ethnic_grade_10_female'] > 0 ? $total['ethnic_grade_10_female'] : '');
        $sh->setCellValue('AF'.$row, $total['ethnic_grade_11'] > 0 ? $total['ethnic_grade_11'] : '');
        $sh->setCellValue('AG'.$row, $total['ethnic_grade_11_female'] > 0 ? $total['ethnic_grade_11_female'] : '');
        $sh->setCellValue('AH'.$row, $total['ethnic_grade_12'] > 0 ? $total['ethnic_grade_12'] : '');
        $sh->setCellValue('AI'.$row, $total['ethnic_grade_12_female'] > 0 ? $total['ethnic_grade_12_female'] : '');
        
        // AJ-AO: Thống kê tốt nghiệp
        $sh->setCellValue('AJ'.$row, $total['ethnic_grad_primary'] > 0 ? $total['ethnic_grad_primary'] : '');
        $sh->setCellValue('AK'.$row, $total['ethnic_grad_primary_female'] > 0 ? $total['ethnic_grad_primary_female'] : '');
        $sh->setCellValue('AL'.$row, $total['ethnic_grad_secondary'] > 0 ? $total['ethnic_grad_secondary'] : '');
        $sh->setCellValue('AM'.$row, $total['ethnic_grad_secondary_female'] > 0 ? $total['ethnic_grad_secondary_female'] : '');
        $sh->setCellValue('AN'.$row, $total['ethnic_grad_highschool'] > 0 ? $total['ethnic_grad_highschool'] : '');
        $sh->setCellValue('AO'.$row, $total['ethnic_grad_highschool_female'] > 0 ? $total['ethnic_grad_highschool_female'] : '');
        
        // AP-AQ: Thống kê chưa đi học và bỏ học
        $sh->setCellValue('AP'.$row, $total['ethnic_not_studying_dropped'] > 0 ? $total['ethnic_not_studying_dropped'] : '');
        $sh->setCellValue('AQ'.$row, $total['ethnic_never_studied'] > 0 ? $total['ethnic_never_studied'] : '');
        
        // AR-BO: Thống kê học xong lớp + bỏ học lớp
        $sh->setCellValue('AR'.$row, $total['ethnic_completed_dropped_1'] > 0 ? $total['ethnic_completed_dropped_1'] : '');
        $sh->setCellValue('AS'.$row, $total['ethnic_completed_dropped_1_female'] > 0 ? $total['ethnic_completed_dropped_1_female'] : '');
        $sh->setCellValue('AT'.$row, $total['ethnic_completed_dropped_2'] > 0 ? $total['ethnic_completed_dropped_2'] : '');
        $sh->setCellValue('AU'.$row, $total['ethnic_completed_dropped_2_female'] > 0 ? $total['ethnic_completed_dropped_2_female'] : '');
        $sh->setCellValue('AV'.$row, $total['ethnic_completed_dropped_3'] > 0 ? $total['ethnic_completed_dropped_3'] : '');
        $sh->setCellValue('AW'.$row, $total['ethnic_completed_dropped_3_female'] > 0 ? $total['ethnic_completed_dropped_3_female'] : '');
        $sh->setCellValue('AX'.$row, $total['ethnic_completed_dropped_4'] > 0 ? $total['ethnic_completed_dropped_4'] : '');
        $sh->setCellValue('AY'.$row, $total['ethnic_completed_dropped_4_female'] > 0 ? $total['ethnic_completed_dropped_4_female'] : '');
        $sh->setCellValue('AZ'.$row, $total['ethnic_completed_dropped_5'] > 0 ? $total['ethnic_completed_dropped_5'] : '');
        $sh->setCellValue('BA'.$row, $total['ethnic_completed_dropped_5_female'] > 0 ? $total['ethnic_completed_dropped_5_female'] : '');
        $sh->setCellValue('BB'.$row, $total['ethnic_completed_dropped_6'] > 0 ? $total['ethnic_completed_dropped_6'] : '');
        $sh->setCellValue('BC'.$row, $total['ethnic_completed_dropped_6_female'] > 0 ? $total['ethnic_completed_dropped_6_female'] : '');
        $sh->setCellValue('BD'.$row, $total['ethnic_completed_dropped_7'] > 0 ? $total['ethnic_completed_dropped_7'] : '');
        $sh->setCellValue('BE'.$row, $total['ethnic_completed_dropped_7_female'] > 0 ? $total['ethnic_completed_dropped_7_female'] : '');
        $sh->setCellValue('BF'.$row, $total['ethnic_completed_dropped_8'] > 0 ? $total['ethnic_completed_dropped_8'] : '');
        $sh->setCellValue('BG'.$row, $total['ethnic_completed_dropped_8_female'] > 0 ? $total['ethnic_completed_dropped_8_female'] : '');
        $sh->setCellValue('BH'.$row, $total['ethnic_completed_dropped_9'] > 0 ? $total['ethnic_completed_dropped_9'] : '');
        $sh->setCellValue('BI'.$row, $total['ethnic_completed_dropped_9_female'] > 0 ? $total['ethnic_completed_dropped_9_female'] : '');
        $sh->setCellValue('BJ'.$row, $total['ethnic_completed_dropped_10'] > 0 ? $total['ethnic_completed_dropped_10'] : '');
        $sh->setCellValue('BK'.$row, $total['ethnic_completed_dropped_10_female'] > 0 ? $total['ethnic_completed_dropped_10_female'] : '');
        $sh->setCellValue('BL'.$row, $total['ethnic_completed_dropped_11'] > 0 ? $total['ethnic_completed_dropped_11'] : '');
        $sh->setCellValue('BM'.$row, $total['ethnic_completed_dropped_11_female'] > 0 ? $total['ethnic_completed_dropped_11_female'] : '');
        $sh->setCellValue('BN'.$row, $total['ethnic_completed_dropped_12'] > 0 ? $total['ethnic_completed_dropped_12'] : '');
        $sh->setCellValue('BO'.$row, $total['ethnic_completed_dropped_12_female'] > 0 ? $total['ethnic_completed_dropped_12_female'] : '');
        
        // BP-BS: Tốt nghiệp + có học xong/bỏ học lớp
        $sh->setCellValue('BP'.$row, $total['ethnic_grad_primary_with_completed'] > 0 ? $total['ethnic_grad_primary_with_completed'] : '');
        $sh->setCellValue('BQ'.$row, $total['ethnic_grad_primary_with_completed_female'] > 0 ? $total['ethnic_grad_primary_with_completed_female'] : '');
        $sh->setCellValue('BR'.$row, $total['ethnic_grad_secondary_with_completed'] > 0 ? $total['ethnic_grad_secondary_with_completed'] : '');
        $sh->setCellValue('BS'.$row, $total['ethnic_grad_secondary_with_completed_female'] > 0 ? $total['ethnic_grad_secondary_with_completed_female'] : '');
        
        // Thiết lập font size, in đậm và căn giữa
        $sh->getStyle('A'.$row.':BS'.$row)->getFont()->setName('Tahoma')->setSize(8)->setBold(true);
        $sh->getStyle('A'.$row.':BS'.$row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    }
}
