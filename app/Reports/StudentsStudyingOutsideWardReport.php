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
 * Report: Danh sách học sinh ở địa phương đi học nơi khác
 * (Học sinh cư trú tại phường/xã này nhưng học trường ở phường/xã khác)
 * 
 * - Handler hiển thị danh sách: list(Request $req)
 * - Handler xuất Excel:        export(Request $req)
 */
class StudentsStudyingOutsideWardReport extends BaseReport
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
        ['qb' => $qb, 'context' => $ctx] = $this->studentsOutsideWardQuery($req);

        // Lấy dữ liệu trang hiện tại
        $rows  = $qb->paginate($ctx['perPage'])->withQueryString();
        $items = collect($rows->items())->map(fn($r) => $this->mapStudentRow($r, $ctx['scheme']));

        // Render view
        return view('front.reports.students_studying_outside_ward_list', [
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
     * Xuất Excel danh sách học sinh đi học ngoài địa bàn
     */
    public function export(Request $req)
    {
        // Build query
        ['qb' => $qb, 'context' => $ctx] = $this->studentsOutsideWardQuery($req);

        // Lấy toàn bộ dữ liệu
        $coll = $qb->get();
        $rows = $coll->map(fn($r) => (object) $this->mapStudentRow($r, $ctx['scheme']));

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
        
        // Thiết lập font size cho header
        $sh->getStyle('D1:D3')->getFont()->setName('Tahoma')->setSize(8);

        // Khu vực bảng dữ liệu
        $startRow = $cfg['table']['startRow'];
        $lastCol  = $cfg['table']['lastCol'];
        $col      = $cfg['cols'];

        // Chèn thêm dòng nếu cần (template có sẵn 1 dòng mẫu)
        $need = max(0, $rows->count() - 1);
        if ($need > 0) {
            $sh->insertNewRowBefore($startRow + 1, $need);
            for ($i = 1; $i <= $need; $i++) {
                $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $lastCol);
            }
        }

        // Ghi dữ liệu
        $r = $startRow; $stt = 1;
        foreach ($rows as $row) {
            $sh->setCellValue($col['stt'].$r, $stt);
            $sh->setCellValue($col['full_name'].$r, $row->full_name ?? '');
            $sh->setCellValueExplicit($col['dob'].$r, $row->dob ?? '', DataType::TYPE_STRING);
            $sh->setCellValue($col['female'].$r, !empty($row->is_female) ? 'X' : '');
            $sh->setCellValue($col['ethnicity'].$r, $row->ethnicity ?? '');
            $sh->setCellValue($col['religion'].$r, $row->religion ?? '');
            $sh->setCellValue($col['so_phieu'].$r, $row->so_phieu ?? '');
            $sh->setCellValue($col['head_name'].$r, $row->head_name ?? '');
            $sh->setCellValue($col['village'].$r, $row->village ?? '');
            $sh->setCellValue($col['block'].$r, $row->block_name ?? '');
            $sh->setCellValue($col['class'].$r, $row->class_label ?? '');
            $sh->setCellValue($col['school'].$r, $row->school_name ?? '');
            
            // Địa chỉ trường - theo yêu cầu mới
            $sh->setCellValue($col['school_province'].$r, $row->school_province ?? '');  // Cột O - Tỉnh/Thành
            // Cột P để trống (không ghi gì)
            $sh->setCellValue($col['school_ward'].$r, $row->school_ward ?? '');          // Cột Q - Phường/Xã
            
            $sh->setCellValue($col['special'].$r, $row->sc_name ?? '');
            
            $r++; $stt++;
        }

        // Viền + merge cho cột tên
        $count       = max(1, $rows->count());
        $lastDataRow = $startRow + $count - 1;
        
        // Thiết lập font size cho data rows
        for ($r = $startRow; $r <= $lastDataRow; $r++) {
            $sh->getStyle('A'.$r.':'.$lastCol.$r)->getFont()->setName('Tahoma')->setSize(8);
        }
        
        // Merge và kẻ viền cột tên (B:C:D)
        $this->fixNameMergeAndBorders($sh, $startRow, $lastDataRow, 'B', 'D', $lastCol);

        // Ghi đè lên dòng ngày tháng năm có sẵn trong template
        $wardName = $meta['ward'] ?? '';
        $signatureRow = $lastDataRow + 2; // Dòng chữ ký
        $dateRow = $signatureRow - 1; // Dòng ngày tháng năm (1 dòng trước chữ ký)
        
        // Ghi đè lên dòng ngày tháng năm có sẵn trong template
        $sh->setCellValue('P'.$dateRow, $wardName . ', ngày ... tháng ... năm ...');

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
     * Query học sinh CƯ TRÚ trong địa bàn nhưng HỌC TRƯỜNG NGOÀI địa bàn
     * (theo địa chính MỚI: so sánh new_ward_id)
     */
    protected function studentsOutsideWardQuery(Request $req): array
    {
        $year     = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        $scheme   = (string) $req->input('scheme', 'new'); // Mặc định dùng địa chính MỚI
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
            ->leftJoin('people as hp', 'hp.id', '=', 'f.head_person_id')
            ->leftJoin('person_educations as pe', 'pe.id', '=', 'd.person_education_id')
            ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id')
            ->leftJoin('schools as s', 's.id', '=', 'pe.school_id')
            ->leftJoin('ethnicities as eth', 'eth.id', '=', 'p.ethnicity_id')
            ->leftJoin('religions as rel', 'rel.id', '=', 'p.religion_id')
            
            // Địa chỉ nơi cư trú (từ survey_form)
            ->leftJoin('geo_units as res_province', 'res_province.id', '=', 
                $scheme === 'old' ? 'f.old_province_id' : 'f.new_province_id')
            ->leftJoin('geo_units as res_district', 'res_district.id', '=', 'f.old_district_id')
            ->leftJoin('geo_units as res_ward', 'res_ward.id', '=', 
                $scheme === 'old' ? 'f.old_ward_id' : 'f.new_ward_id')
            ->leftJoin('geo_units as res_village', 'res_village.id', '=', 'f.village_id')
            
            // Địa chỉ trường học
            ->leftJoin('geo_units as sch_province', 'sch_province.id', '=', 
                $scheme === 'old' ? 's.old_province_id' : 's.new_province_id')
            ->leftJoin('geo_units as sch_district', 'sch_district.id', '=', 's.old_district_id')
            ->leftJoin('geo_units as sch_ward', 'sch_ward.id', '=', 
                $scheme === 'old' ? 's.old_ward_id' : 's.new_ward_id')
            
            ->leftJoin('person_special_circumstances as psc', 'psc.id', '=', 'd.person_special_circumstance_id')
            ->leftJoin('special_circumstances as sc', 'sc.id', '=', 'psc.sc_id')
            
            ->where('d.is_deleted', false)
            ->whereNotNull('pe.school_id'); // Phải có thông tin trường học

        // Lọc theo số phiếu
        if ($soPhieu !== '') {
            $qb->where('f.so_phieu', 'like', '%'.$soPhieu.'%');
        }

        // Lọc theo năm học
        if ($year !== 'all') {
            $qb->where('d.academic_year', $year)
               ->where(function($w) use ($year) {
                   $w->whereNull('pe.academic_year')
                     ->orWhere('pe.academic_year', $year);
               });
        }

        // Lọc địa bàn CƯ TRÚ theo scheme
        $this->applyGeoFilter($qb, $scheme, $geo);

        // ⭐ ĐIỀU KIỆN QUAN TRỌNG: Trường NGOÀI địa bàn cư trú
        // So sánh ward_id của nơi cư trú và trường học
        if ($scheme === 'old') {
            if (!empty($geo['ward_id'])) {
                // Có ward_id: so sánh cấp phường/xã hoặc trường ngoài tỉnh
                $qb->whereRaw('(s.code LIKE "NGOAI_TINH%" OR (s.old_ward_id IS NOT NULL AND f.old_ward_id IS NOT NULL AND s.old_ward_id <> f.old_ward_id))');
            } elseif (!empty($geo['district_id'])) {
                // Chỉ có district: so sánh cấp quận/huyện hoặc trường ngoài tỉnh
                $qb->whereRaw('(s.code LIKE "NGOAI_TINH%" OR (s.old_district_id IS NOT NULL AND f.old_district_id IS NOT NULL AND s.old_district_id <> f.old_district_id))');
            } elseif (!empty($geo['province_id'])) {
                // Chỉ có province: so sánh cấp tỉnh hoặc trường ngoài tỉnh
                $qb->whereRaw('(s.code LIKE "NGOAI_TINH%" OR (s.old_province_id IS NOT NULL AND f.old_province_id IS NOT NULL AND s.old_province_id <> f.old_province_id))');
            }
        } else {
            // Scheme NEW
            if (!empty($geo['ward_id'])) {
                // So sánh ward_id (địa chính mới) hoặc trường ngoài tỉnh
                $qb->whereRaw('(s.code LIKE "NGOAI_TINH%" OR (s.new_ward_id IS NOT NULL AND f.new_ward_id IS NOT NULL AND s.new_ward_id <> f.new_ward_id))');
            } elseif (!empty($geo['province_id'])) {
                // So sánh province hoặc trường ngoài tỉnh
                $qb->whereRaw('(s.code LIKE "NGOAI_TINH%" OR (s.new_province_id IS NOT NULL AND f.new_province_id IS NOT NULL AND s.new_province_id <> f.new_province_id))');
            }
        }

        // Tìm kiếm nhanh
        if ($q !== '') {
            $like = '%'.$q.'%';
            $qb->where(function($w) use ($like) {
                $w->where('p.ten', 'like', $like)
                  ->orWhereRaw("CONCAT(TRIM(COALESCE(p.ho_dem,'')),' ',COALESCE(p.ten,'')) like ?", [$like])
                  ->orWhere('s.name', 'like', $like)
                  ->orWhere('pe.school_name_custom', 'like', $like)
                  ->orWhere('f.so_phieu', 'like', $like)
                  ->orWhere('eth.name', 'like', $like)
                  ->orWhere('pe.class_label', 'like', $like);
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
            p.dob_day, p.dob_month, p.dob_year,
            eth.name as ethnicity,
            rel.name as religion,
            CONCAT(TRIM(COALESCE(hp.ho_dem,'')),
                CASE WHEN hp.ho_dem IS NOT NULL AND hp.ten IS NOT NULL THEN ' ' ELSE '' END,
                COALESCE(hp.ten,'')) as head_name,
            res_province.name as residence_province,
            res_district.name as residence_district,
            res_ward.name as residence_ward,
            res_village.name as village,
            eb.name as block_name,
            pe.class_label,
            COALESCE(pe.school_name_custom, s.name) as school_name,
            sch_province.name as school_province,
            sch_district.name as school_district,
            COALESCE(pe.studying_district_custom, sch_ward.name) as school_ward,
            sc.name as sc_name
        ");

        // Sắp xếp
        $sortMap = [
            'full_name'   => 'full_name',
            'dob'         => DB::raw('COALESCE(p.dob_year,0), COALESCE(p.dob_month,0), COALESCE(p.dob_day,0)'),
            'ethnicity'   => 'eth.name',
            'school_name' => 's.name',
            'so_phieu'    => 'f.so_phieu',
        ];
        $orderCol = $sortMap[$sortBy] ?? 'full_name';
        $qb->orderBy($orderCol, $sortDir);

        return [
            'qb'      => $qb,
            'context' => compact('year','scheme','geo','perPage','sortBy','sortDir'),
        ];
    }

    /**
     * Map dữ liệu 1 dòng
     */
    protected function mapStudentRow(object $r, string $scheme): array
    {
        $dob = null;
        if ($r->dob_day || $r->dob_month || $r->dob_year) {
            $d = $r->dob_day   ? str_pad($r->dob_day, 2, '0', STR_PAD_LEFT) : '??';
            $m = $r->dob_month ? str_pad($r->dob_month, 2, '0', STR_PAD_LEFT) : '??';
            $y = $r->dob_year ?: '????';
            $dob = "{$d}/{$m}/{$y}";
        }

        return [
            'full_name'       => $r->full_name,
            'dob'             => $dob,
            'is_female'       => (bool)$r->is_female,
            'ethnicity'       => $r->ethnicity,
            'religion'        => $r->religion,
            'so_phieu'        => $r->so_phieu,
            'head_name'       => $r->head_name,
            'village'         => $r->village,
            'block_name'      => $r->block_name,
            'class_label'     => $r->class_label,
            'school_name'     => $r->school_name,
            'school_province' => $r->school_province,
            'school_district' => $r->school_district,
            'school_ward'     => $r->school_ward,
            'sc_name'         => $r->sc_name,
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
        // Template chung cho cả old và new
        return [
            'path'   => resource_path('excel/6.8.DS.HocSinhHocNgoaiDiaBan.xlsx'),
            'header' => [
                'province' => 'D1',  // Tỉnh/Thành (nơi cư trú)
                'ward'     => 'D3',  // Phường/Xã (nơi cư trú)
            ],
            'table'  => [
                'startRow' => 9,     // Dòng dữ liệu đầu tiên (hàng 9)
                'lastCol'  => 'S',   // Cột cuối cùng (có 19 cột A-S)
            ],
            'cols' => [
                'stt'             => 'A',   // 1. TT
                'full_name'       => 'B',   // 2. Họ và tên (B:C:D sẽ merge)
                'dob'             => 'E',   // 3. Ngày sinh
                'female'          => 'F',   // 4. Nữ
                'ethnicity'       => 'G',   // 5. Dân tộc
                'religion'        => 'H',   // 6. Tôn giáo
                'so_phieu'        => 'I',   // 7. Số phiếu
                'head_name'       => 'J',   // 8. Họ và tên chủ hộ
                'village'         => 'K',   // 9. Thôn/Xóm
                'block'           => 'L',   // 10. Khối
                'class'           => 'M',   // 11. Lớp
                'school'          => 'N',   // 12. Trường
                'school_province' => 'O',   // 13. Tỉnh/Thành (trường)
                'school_district' => 'P',   // 14. Để trống
                'school_ward'     => 'Q',   // 15. Phường/Xã (trường)
                'special'         => 'R',   // 16. Hoàn cảnh đặc biệt
                'note'            => 'S',   // 17. Ghi chú
            ],
        ];
    }
}

