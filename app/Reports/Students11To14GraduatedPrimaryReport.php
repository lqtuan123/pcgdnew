<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class Students11To14GraduatedPrimaryReport extends BaseReport
{
    /**
     * Hiển thị danh sách học sinh 11-14 tuổi tốt nghiệp tiểu học
     */
    public function list(Request $req)
    {
        // Kiểm tra nếu có export=1 thì chuyển sang export
        if ($req->has('export') && $req->export == '1') {
            return $this->export($req);
        }

        // Build query
        ['qb' => $qb, 'context' => $ctx] = $this->students11To14Query($req);

        // Pagination
        $rows = $qb->paginate($ctx['perPage']);
        $items = $rows->getCollection()->map(fn($r) => $this->mapStudentRow($r, $ctx['scheme']));

        return view('front.reports.students_11_to_14_graduated_primary_list', [
            'items' => $items,
            'rows' => $rows,
            'year' => $ctx['year'],
            'scheme' => $ctx['scheme'],
            'geo' => $ctx['geo'],
            'q' => $ctx['q'],
            'so_phieu' => $ctx['so_phieu'],
            'per_page' => $ctx['perPage'],
            'sort_by' => $ctx['sortBy'],
            'sort_dir' => $ctx['sortDir'],
            'report_group_id' => (int) $req->report_group_id,
            'report_detail_id' => (int) $req->report_detail_id,
        ]);
    }

    /**
     * Xuất Excel danh sách học sinh 11-14 tuổi tốt nghiệp tiểu học
     */
    public function export(Request $req)
    {
        // Build query
        ['qb' => $qb, 'context' => $ctx] = $this->students11To14Query($req);

        // Lấy toàn bộ dữ liệu
        $coll = $qb->get();
        $rows = $coll->map(fn($r) => (object) $this->mapStudentRow($r, $ctx['scheme']));

        // Chọn template
        $cfg = $this->templateCfgForScheme($ctx['scheme']);
        $wb  = IOFactory::load($cfg['path']);
        $sh  = $wb->getActiveSheet();

        // Header meta (tên tỉnh/xã từ geo filter)
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
        
        // Thiết lập font size 8 cho header
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
            $sh->setCellValue($col['graduation_level'].$r, $row->graduation_level_name ?? '');
            $sh->setCellValue($col['graduation_year'].$r, $row->graduation_year ?? '');
            $sh->setCellValue($col['class'].$r, $row->class_label ?? '');
            $sh->setCellValue($col['school'].$r, $row->school_name ?? '');
            $sh->setCellValue($col['note'].$r, $row->note ?? '');
            
            // Thiết lập font size 8 cho dòng dữ liệu
            $sh->getStyle('A'.$r.':'.$lastCol.$r)->getFont()->setName('Tahoma')->setSize(8);
            
            $r++; $stt++;
        }

        // Viền + merge cho cột tên
        $count       = max(1, $rows->count());
        $lastDataRow = $startRow + $count - 1;
        
        // Merge và kẻ viền cột tên (B:C:D)
        $this->fixNameMergeAndBorders($sh, $startRow, $lastDataRow, 'B', 'D', $lastCol);

        // Ghi đè lên dòng ngày tháng năm có sẵn trong template
        $wardName = $meta['ward'] ?? '';
        $signatureRow = $lastDataRow + 2; // Dòng chữ ký
        $dateRow = $signatureRow - 1; // Dòng ngày tháng năm (1 dòng trước chữ ký)
        
        // Ghi đè lên dòng ngày tháng năm có sẵn trong template
        $sh->setCellValue('N'.$dateRow, $wardName . ', ngày ... tháng ... năm ...');

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
     * Query học sinh 11-14 tuổi tốt nghiệp tiểu học hoặc đang học THCS
     */
    protected function students11To14Query(Request $req): array
    {
        $year     = (string) $req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        $scheme   = (string) $req->input('scheme', 'new');
        $geo      = (array)  $req->input('geo', []);
        $q        = trim((string) $req->input('q', ''));
        $soPhieu  = trim((string) $req->input('so_phieu', ''));
        $perPage  = (int) $req->input('per_page', 50);
        $sortBy   = (string) $req->input('sort_by', 'full_name');
        $sortDir  = strtolower((string) $req->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        // Tính độ tuổi dựa vào năm học (VD: 2024-2025 => trẻ sinh 2010,2011,2012,2013)
        $yearForAge = (int) explode('-', $year)[0];
        $ageMin = $yearForAge - 14; // năm sinh của trẻ 14 tuổi (VD: 2024 - 14 = 2010)
        $ageMax = $yearForAge - 11; // năm sinh của trẻ 11 tuổi (VD: 2024 - 11 = 2013)

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
            ->leftJoin('graduation_levels as gl', 'gl.id', '=', 'pe.graduation_level_id')
            ->leftJoin('geo_units as res_province', function($j) use ($scheme) {
                $j->on('res_province.id', '=', $scheme === 'old' ? 'f.old_province_id' : 'f.new_province_id');
            })
            ->leftJoin('geo_units as res_ward', function($j) use ($scheme) {
                $j->on('res_ward.id', '=', $scheme === 'old' ? 'f.old_ward_id' : 'f.new_ward_id');
            })
            ->leftJoin('geo_units as res_village', 'res_village.id', '=', 'f.village_id')
            ->where('d.is_deleted', false)
            ->whereBetween('p.dob_year', [$ageMin, $ageMax]) // 11-14 tuổi
            ->where(function($w) {
                // Điều kiện: Tốt nghiệp tiểu học HOẶC đang học THCS (lớp > 5)
                $w->where(function($subW) {
                    // Tốt nghiệp tiểu học
                    $subW->where('gl.name', 'TH')
                         ->whereNotNull('pe.graduation_year');
                })->orWhere(function($subW) {
                    // Đang học THCS (lớp > 5)
                    $subW->whereNotNull('pe.education_block_id')
                         ->whereRaw('CAST(eb.name AS UNSIGNED) > 5')
                         ->whereNotNull('pe.school_id');
                });
            });

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

        // Tìm kiếm nhanh
        if ($q !== '') {
            $like = '%'.$q.'%';
            $qb->where(function($w) use ($like) {
                $w->where('p.ten', 'like', $like)
                  ->orWhereRaw("CONCAT(TRIM(COALESCE(p.ho_dem,'')),' ',COALESCE(p.ten,'')) like ?", [$like])
                  ->orWhere('s.name', 'like', $like)
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
            res_ward.name as residence_ward,
            res_village.name as village,
            eb.name as block_name,
            pe.class_label,
            s.name as school_name,
            gl.name as graduation_level_name,
            pe.graduation_year,
            pe.note
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
            'context' => compact('year','scheme','geo','perPage','sortBy','sortDir','q','soPhieu') + ['so_phieu' => $soPhieu],
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
            'full_name'             => $r->full_name,
            'dob'                   => $dob,
            'is_female'              => (bool)$r->is_female,
            'ethnicity'              => $r->ethnicity,
            'religion'               => $r->religion,
            'so_phieu'              => $r->so_phieu,
            'head_name'              => $r->head_name,
            'village'                => $r->village,
            'block_name'             => $r->block_name,
            'class_label'            => $r->class_label,
            'school_name'            => $r->school_name,
            'graduation_level_name'  => $r->graduation_level_name,
            'graduation_year'        => $r->graduation_year,
            'note'                   => $r->note,
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
            'path'   => resource_path('excel/6.9.DS.11-14Tuoi.TNTH.xlsx'),
            'header' => [
                'province' => 'D1',  // Tỉnh/Thành
                'ward'     => 'D2',  // Phường/Xã
                'year'     => 'D3',  // Năm học
            ],
            'table'  => [
                'startRow' => 10,     // Dòng dữ liệu đầu tiên (hàng 10)
                'lastCol'  => 'P',   // Cột cuối cùng
            ],
            'cols' => [
                'stt'             => 'A',   // TT
                'full_name'       => 'B',   // Họ và tên (B:C:D sẽ merge)
                'dob'             => 'E',   // Ngày sinh
                'female'          => 'F',   // Nữ
                'ethnicity'       => 'G',   // Dân tộc
                'religion'        => 'H',   // Tôn giáo
                'so_phieu'        => 'I',   // Số phiếu
                'head_name'       => 'J',   // Họ và tên chủ hộ
                'village'         => 'K',   // Thôn/Xóm
                'graduation_level' => 'L',  // Bậc tốt nghiệp
                'graduation_year' => 'M',   // Năm tốt nghiệp
                'class'           => 'N',   // Lớp
                'school'          => 'O',   // Trường
                'note'            => 'P',   // Ghi chú
            ],
        ];
    }
}
