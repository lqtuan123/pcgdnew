<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Models\School;

/**
 * Report: Danh sách học sinh theo phường/xã (đang học tại trường).
 *
 * - Handler hiển thị danh sách: list(Request $req)
 * - Handler xuất Excel:        exportExcel(Request $req)
 *
 * Gọi từ ReportEntryController thông qua config/reports.php.
 */
class StudentsByWardReport extends BaseReport
{
   
    /**
     * Hiển thị danh sách (phân trang) theo tham số tìm kiếm.
     * Route trong config có thể trỏ '2:7','2:16','2:28' => StudentsByWardReport@list
     */
    public function list(Request $req)
    {
        // Level được ReportEntryController gán thêm (_level) theo report_group_id
        $level = (string) $req->input('_level', 'primary');

        // Build query chung
        ['qb' => $qb, 'context' => $ctx] = $this->studentsByWardQuery($req, $level);

        // Lấy dữ liệu trang hiện tại
        $rows  = $qb->paginate($ctx['perPage'])->withQueryString();
        $items = collect($rows->items())->map(fn($r) => $this->mapStudentRow($r, $ctx['scheme']));

        // Render view có sẵn của bạn
        return view('front.reports.students_by_ward_list', [
            'rows'             => $rows,
            'items'            => $items,
            'year'             => $ctx['year'],
            'scheme'           => $ctx['scheme'],
            'geo'              => $ctx['geo'],
            'level'            => $ctx['level'],
            'sort_by'          => $ctx['sortBy'],
            'sort_dir'         => $ctx['sortDir'],
            'per_page'         => $ctx['perPage'],
            'education.school_id' => $ctx['schoolId'],
            'report_group_id'  => (int) $req->report_group_id,
            'report_detail_id' => (int) $req->report_detail_id,
            'so_phieu'         => $req->input('so_phieu',''),
            'q'                => $req->input('q',''),
        ]);
    }

    /**
     * Xuất Excel danh sách HS theo phường/xã (không phân trang).
     * Có thể map route riêng tới method này nếu bạn cần nút "Xuất Excel" trong view.
     */
    public function exportExcel(Request $req)
    {
        $level = (string) $req->input('_level', 'primary');

        // Build query chung
        ['qb' => $qb, 'context' => $ctx] = $this->studentsByWardQuery($req, $level);

        // Lấy toàn bộ dữ liệu để đổ Excel
        $coll  = $qb->get();
        $rows  = $coll->map(fn($r) => (object) $this->mapStudentRow($r, $ctx['scheme']));

        // Chọn template theo scheme (old/new)
        $cfg = $this->templateCfgForScheme($ctx['scheme']);
        $wb  = IOFactory::load($cfg['path']);
        $sh  = $wb->getActiveSheet();

        // Header meta (tên tỉnh/huyện/xã từ kết quả)
        $meta = $this->metaFromCollection($coll, $ctx['scheme']);

        // Province
        if (!empty($cfg['header']['province'])) {
            $sh->setCellValue($cfg['header']['province'], $meta['province']);
        }
        // District (chỉ file cũ có)
        if (!empty($cfg['header']['district'])) {
            $sh->setCellValue($cfg['header']['district'], $meta['district'] ?? '');
        }
        // Ward
        if (!empty($cfg['header']['ward'])) {
            $sh->setCellValue($cfg['header']['ward'], $meta['ward'] ?? '');
        }
        // School (nếu lọc theo trường)
        $schoolName = '';
        if (!empty($ctx['schoolId'])) {
            $school = School::find($ctx['schoolId']);
            $schoolName = $school?->name ?? '';
        }
        if (!empty($cfg['header']['school'])) {
            $sh->setCellValue($cfg['header']['school'], $schoolName);
        }
        // Tiêu đề lớn (nếu template có sẵn ô)
        if (!empty($cfg['header']['title'])) {
            $sh->setCellValue($cfg['header']['title'], 'DANH SÁCH HỌC SINH PHƯỜNG/XÃ: ĐANG HỌC TẠI TRƯỜNG');
        }

        // Khu vực bảng
        $startRow  = $cfg['table']['startRow'];
        $footerTop = $cfg['table']['footerTop']; // dòng đầu của vùng footer cố định
        $lastCol   = $cfg['table']['lastCol'];
        $col       = $cfg['cols'];

        // Chèn thêm dòng nếu cần (template có sẵn 1 dòng mẫu)
        $need = max(0, $rows->count() - 1);
        if ($need > 0) {
            $sh->insertNewRowBefore($footerTop, $need);
            for ($i = 1; $i <= $need; $i++) {
                // helper trong trait ExcelSheetHelpers
                $this->replicateRowFormatMerged($sh, $startRow, $startRow + $i, $lastCol);
            }
        }

        // Ghi dữ liệu
        $r = $startRow; $stt = 1;
        foreach ($rows as $row) {
            $sh->setCellValue($col['stt'].$r, $stt);
            $sh->setCellValue($col['full_name'].$r,   $row->full_name);                      // B..D (đã merge trong template)
            $sh->setCellValueExplicit($col['dob'].$r, $row->dob ?? '', DataType::TYPE_STRING);
            $sh->setCellValue($col['female'].$r,      !empty($row->is_female) ? 'X' : '');
            $sh->setCellValue($col['ethnicity'].$r,   $row->ethnicity);
            $sh->setCellValue($col['religion'].$r,    $row->religion);
            $sh->setCellValue($col['so_phieu'].$r,    $row->so_phieu);
            $sh->setCellValue($col['head_name'].$r,   $row->head_name);
            $sh->setCellValue($col['province'].$r,    $row->province);

            if (!empty($col['district'])) {
                $sh->setCellValue($col['district'].$r, $row->district);
            }

            $sh->setCellValue($col['ward'].$r,        $row->ward);
            $sh->setCellValue($col['village'].$r,     $row->village);
            $sh->setCellValue($col['class'].$r,       $row->class_label);
            $sh->setCellValue($col['school'].$r,      $row->school);
            $sh->setCellValue($col['special'].$r,     $row->sc_name);
            $r++; $stt++;
        }

        // Viền + merge lại dải tên + dòng ngày tháng năm
        $count       = max(1, $rows->count());
        $lastDataRow = $startRow + $count - 1;

        // helper trong trait ExcelSheetHelpers
        $this->fixNameMergeAndBorders($sh, $startRow, $lastDataRow, 'B', 'D', $lastCol);

        // Dòng ngày tháng năm (tuân theo config/report_formats.php)
        $dateRow = $lastDataRow + 2; // cách 1 dòng
        $this->writeDateLine($sh, $ctx['scheme'], $meta['ward'] ?? '', $dateRow, $lastCol);

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
     * Xây query chung: lọc theo year/scheme/geo/q/school… và trả context.
     * Trả về:
     *  - qb: Builder
     *  - context: mảng tham chiếu để render/export
     */
    protected function studentsByWardQuery(Request $req, string $level, ?int $schoolId = null): array
    {
        $year     = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        $scheme   = (string) $req->input('scheme', 'old');
        $geo      = (array)  $req->input('geo', []);
        $q        = trim((string) $req->input('q', ''));
        $soPhieu  = trim((string) $req->input('so_phieu', ''));
        $perPage  = (int) $req->input('per_page', 50);
        $sortBy   = (string) $req->input('sort_by', 'school_name');
        $sortDir  = strtolower((string) $req->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        // Ưu tiên tham số truyền vào; nếu không có thì lấy từ request
        $schoolId = $schoolId ?? ($req->filled('education.school_id') ? (int) $req->input('education.school_id') : null);

        $blockNames = $this->blocksForLevel($level);

        $classNumExpr = "CASE
            WHEN pe.class_label REGEXP '^[0-9]+$' THEN CAST(pe.class_label AS UNSIGNED)
            WHEN pe.class_label REGEXP '[0-9]+'   THEN CAST(REGEXP_SUBSTR(pe.class_label,'[0-9]+') AS UNSIGNED)
            ELSE NULL END";

        $qb = DB::table('survey_form_details as d')
            ->join('survey_forms as f', function($j){
                $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
            })
            ->join('people as p', 'p.id', '=', 'd.person_id')
            ->leftJoin('people as hp', 'hp.id', '=', 'f.head_person_id')
            ->leftJoin('person_educations as pe', 'pe.id', '=', 'd.person_education_id')
            ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id')
            ->leftJoin('schools as s', function($j) {
                $j->on('s.id', '=', 'pe.school_id')
                  ->where(function($w) {
                      $w->whereNull('s.is_dissolved')
                        ->orWhere('s.is_dissolved', false)
                        ->orWhere('s.is_dissolved', 0);
                  });
            })
            ->leftJoin('ethnicities as eth', 'eth.id', '=', 'p.ethnicity_id')
            ->leftJoin('religions as rel', 'rel.id', '=', 'p.religion_id')
            ->leftJoin('geo_units as op', 'op.id', '=', 'f.old_province_id')
            ->leftJoin('geo_units as od', 'od.id', '=', 'f.old_district_id')
            ->leftJoin('geo_units as ow', 'ow.id', '=', 'f.old_ward_id')
            ->leftJoin('geo_units as np', 'np.id', '=', 'f.new_province_id')
            ->leftJoin('geo_units as nw', 'nw.id', '=', 'f.new_ward_id')
            ->leftJoin('geo_units as vg', 'vg.id', '=', 'f.village_id')
            ->leftJoin('person_special_circumstances as psc', 'psc.id', '=', 'd.person_special_circumstance_id')
            ->leftJoin('special_circumstances as sc', 'sc.id', '=', 'psc.sc_id')
            ->where('d.is_deleted', false)
            ->whereNotNull('pe.school_id'); // đang học tại trường

        if ($soPhieu !== '') {
            $qb->where('f.so_phieu', 'like', '%'.$soPhieu.'%');
        }

        if ($year !== 'all') {
            $qb->where('d.academic_year', $year)
               ->where(function($w) use ($year) {
                   $w->whereNull('pe.academic_year')
                     ->orWhere('pe.academic_year', $year);
               });
        }

        // Lọc geo “nơi cư trú” theo scheme
        $this->applyGeoFilter($qb, $scheme, $geo);

        // Lọc theo trường (nếu có)
        if ($schoolId) {
            $qb->where('pe.school_id', $schoolId);
        }

        // Tìm kiếm nhanh
        if ($q !== '') {
            $like = '%'.$q.'%';
            $qb->where(function($w) use ($like) {
                $w->where('p.ten', 'like', $like)
                  ->orWhereRaw("CONCAT(TRIM(COALESCE(p.ho_dem,'')),' ',COALESCE(p.ten,'')) like ?", [$like])
                  ->orWhere('s.name', 'like', $like)
                  ->orWhere('f.so_phieu', 'like', $like)
                  ->orWhere('eth.name', 'like', $like)
                  ->orWhere('rel.name', 'like', $like)
                  ->orWhere('hp.ten', 'like', $like)
                  ->orWhereRaw("CONCAT(TRIM(COALESCE(hp.ho_dem,'')),' ',COALESCE(hp.ten,'')) like ?", [$like])
                  ->orWhere('pe.class_label', 'like', $like);
            });
        }

        // Chọn cột xuất/hiển thị
        $qb->selectRaw("
            d.id as detail_id, f.so_phieu, p.is_female,
            CONCAT(TRIM(COALESCE(p.ho_dem,'')),
                CASE WHEN p.ho_dem IS NOT NULL AND p.ten IS NOT NULL THEN ' ' ELSE '' END,
                COALESCE(p.ten,'')) as full_name,
            p.dob_day, p.dob_month, p.dob_year,
            eth.name as ethnicity, rel.name as religion,
            CONCAT(TRIM(COALESCE(hp.ho_dem,'')),
                CASE WHEN hp.ho_dem IS NOT NULL AND hp.ten IS NOT NULL THEN ' ' ELSE '' END,
                COALESCE(hp.ten,'')) as head_name,
            op.name as old_province, od.name as old_district, ow.name as old_ward,
            np.name as new_province, nw.name as new_ward, vg.name as village,
            s.id as school_id,
            pe.class_label, s.name as school_name, s.code as school_code,
            sc.name as sc_name,
            {$classNumExpr} as class_no
        ");

        // Lọc theo cấp học (nếu có cấu hình block name)
        if (!empty($blockNames)) {
            $qb->whereIn('eb.name', $blockNames);
        }

        // Sắp xếp
        $sortMap = [
            'full_name'   => 'full_name',
            'dob'         => DB::raw('COALESCE(p.dob_year,0), COALESCE(p.dob_month,0), COALESCE(p.dob_day,0)'),
            'ethnicity'   => 'eth.name',
            'religion'    => 'rel.name',
            'so_phieu'    => 'f.so_phieu',
            'head_name'   => 'head_name',
            'province'    => $scheme === 'old' ? 'op.name' : 'np.name',
            'district'    => $scheme === 'old' ? 'od.name' : DB::raw('NULL'),
            'ward'        => $scheme === 'old' ? 'ow.name' : 'nw.name',
            'village'     => 'vg.name',
            'class_label' => DB::raw('class_no IS NULL, class_no, pe.class_label'),
            'school_name' => 's.name',
            'sc_name'     => 'sc.name',
        ];
        $orderCol = $sortMap[$sortBy] ?? 's.name';
        $qb->orderBy($orderCol, $sortDir);

        return [
            'qb'      => $qb,
            'context' => compact('year','scheme','geo','perPage','sortBy','sortDir','level','schoolId'),
        ];
    }

    /**
     * Chuẩn hoá 1 dòng dữ liệu hiển thị.
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
            'full_name'   => $r->full_name,
            'dob'         => $dob,
            'ethnicity'   => $r->ethnicity,
            'religion'    => $r->religion,
            'so_phieu'    => $r->so_phieu,
            'head_name'   => $r->head_name,
            'province'    => $scheme === 'old' ? $r->old_province : $r->new_province,
            'district'    => $scheme === 'old' ? $r->old_district : null,
            'ward'        => $scheme === 'old' ? $r->old_ward : $r->new_ward,
            'village'     => $r->village,
            'class_label' => $r->class_label ?: ($r->class_no ? (string)$r->class_no : null),
            'school'      => $r->school_name,
            'sc_name'     => $r->sc_name,
            'is_female'   => (bool)$r->is_female,
        ];
    }

    /**
     * Đọc tên tỉnh/huyện/xã từ kết quả (ưu tiên row đầu tiên).
     */
    protected function metaFromCollection(\Illuminate\Support\Collection $rows, string $scheme): array
    {
        $province = $ward = $district = '';
        if ($rows->count()) {
            $r0 = $rows->first();
            $province = $scheme==='old' ? ($r0->old_province ?? '') : ($r0->new_province ?? '');
            $district = $scheme==='old' ? ($r0->old_district ?? '') : '';
            $ward     = $scheme==='old' ? ($r0->old_ward ?? '')     : ($r0->new_ward ?? '');
        }
        return compact('province','district','ward');
    }

    /**
     * Cấu hình template theo scheme (old/new).
     * Đảm bảo 2 file tồn tại:
     *   resources/excel/DSHSCacPhuongXa_old.xlsx
     *   resources/excel/DSHSCacPhuongXa_new.xlsx
     */
    protected function templateCfgForScheme(string $scheme): array
    {
        if ($scheme === 'old') {
            return [
                'path'   => resource_path('excel/DSHSCacPhuongXa_old.xlsx'),
                'header' => [
                    'province' => 'D1',
                    'district' => 'D2',
                    'ward'     => 'D3',
                    'school'   => 'D4',
                    'title'    => 'H2',
                ],
                'table'  => [
                    'startRow'  => 8,
                    'footerTop' => 10,
                    'lastCol'   => 'Q',
                ],
                'cols' => [
                    'stt'        => 'A',
                    'full_name'  => 'B',
                    'dob'        => 'E',
                    'female'     => 'F',
                    'ethnicity'  => 'G',
                    'religion'   => 'H',
                    'so_phieu'   => 'I',
                    'head_name'  => 'J',
                    'province'   => 'K',
                    'district'   => 'L',
                    'ward'       => 'M',
                    'village'    => 'N',
                    'class'      => 'O',
                    'school'     => 'P',
                    'special'    => 'Q',
                ],
            ];
        }

        // NEW scheme
        return [
            'path'   => resource_path('excel/DSHSCacPhuongXa_new.xlsx'),
            'header' => [
                'province' => 'D1',
                'district' => null,
                'ward'     => 'D2',
                'school'   => 'D3',
                'title'    => 'H2',
            ],
            'table'  => [
                'startRow'  => 7,
                'footerTop' => 10,
                'lastCol'   => 'P',
            ],
            'cols' => [
                'stt'        => 'A',
                'full_name'  => 'B',
                'dob'        => 'E',
                'female'     => 'F',
                'ethnicity'  => 'G',
                'religion'   => 'H',
                'so_phieu'   => 'I',
                'head_name'  => 'J',
                'province'   => 'K',
                'ward'       => 'L',
                'village'    => 'M',
                'class'      => 'N',
                'school'     => 'O',
                'special'    => 'P',
            ],
        ];
    }

    /**
     * Ánh xạ block.name theo cấp học để tái sử dụng.
     */
    protected function blocksForLevel(string $level): array
    {
        return match ($level) {
            'mamnon'  => ['3-12 tháng','13-24 tháng','25-36 tháng','3-4 tuổi','4-5 tuổi','5-6 tuổi'],
            'primary' => ['1','2','3','4','5'],       // Tiểu học
            'lower'   => ['6','7','8','9'],           // THCS
            'upper'   => ['10','11','12'],            // THPT
            default   => [],
        };
    }
}
