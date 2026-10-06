<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Services\GeoService;
use App\Services\ReportFormatService;
use PhpOffice\PhpSpreadsheet\Settings;
// use PhpOffice\PhpSpreadsheet\Collection\Cells\MemoryGZIP;

use PhpOffice\PhpSpreadsheet\Calculation\Calculation;
 

class PrimaryCoverageRegisterReport extends BaseReport
{
     public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    public function export(Request $req)
    {
        // ===== 1) Đầu vào chung
        $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1]     = array_map('intval', explode('-', $academic));
        $scheme   = (string)$req->input('scheme', 'old');
        $geo      = (array)$req->input('geo', []);

        // Bật cache & tắt tính công thức để tăng tốc ghi
        // Settings::setCacheStorageMethod(Settings::CACHE_TO_MEMORY_GZIP);
        if (method_exists(Calculation::getInstance(), 'setCalculationEnabled')) {
                Calculation::getInstance()->setCalculationEnabled(false);
            }
        // Settings::setCache(new MemoryGZIP());
        // Calculation::getInstance()->setCalculationEnabled(false);

        // ===== 2) Nạp template
        $cfg = $this->templateCfg();
        $wb  = IOFactory::load($cfg['path']);

        // ===== 3) Lặp 9 trang: 14 -> 6 tuổi
        foreach (range(14, 6, -1) as $age) {
            $sheetName = sprintf('%d tuổi', $age);

            // nếu sheet chưa có trong template, clone từ sheet 0 cho giữ style
            $sh = $wb->getSheetByName($sheetName);
            if (!$sh) {
                $base = $wb->getSheet(0);
                $sh   = clone $base;
                $sh->setTitle($sheetName);
                $wb->addSheet($sh);
            }

            // 3.1 Năm sinh của trang này
            $birthYear = $y1 - $age;

            // 3.2 Lấy dữ liệu của cohort (một mảng các bản ghi)
            // Kỳ vọng mỗi item có: full_name, dob_day, dob_month, dob_year, is_female,
            // ethnicity, contact_name, address, grade_now (1..5|null), school_name,
            // th_grad_year, quit_grade, quit_year, so_phieu, note
            $rows = $this->queryCohortByBirthYear($academic, $scheme, $geo, $birthYear);

            // 3.3 Ghi header
            $sh->setCellValue($cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
            $sh->setCellValue($cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
            $sh->setCellValue($cfg['header']['birthYear'], $birthYear);
           
            // 3.4 Chuẩn bị vùng dữ liệu: chèn đủ số dòng một lần
            $startRow = $cfg['startRow'];   // ví dụ 11
            $lastCol  = 'T';
            $count    = count($rows);
            $need     = max(0, $count - 1);
            if ($need > 0) {
                $sh->insertNewRowBefore($startRow + 1, $need);
            }
            $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
            // 3.5 Map dữ liệu -> mảng 2D và đổ một phát bằng fromArray
            $data = [];
            $stt  = 1;
            foreach ($rows as $it) {
                $grade = isset($it['grade_now']) ? (int)$it['grade_now'] : null;
                $dob   = $this->fmtDob($it['dob_day'] ?? null, $it['dob_month'] ?? null, $it['dob_year'] ?? null);

                $data[] = [
                    $stt,                                  // A - STT
                    $it['full_name'] ?? null,              // B - Họ và tên
                    null,                                  // C - (trống - header gộp)
                    null,                                  // D - (trống - header gộp)
                    $dob ?: null,                          // E - Ngày tháng năm sinh
                    (!empty($it['is_female']) ? 'x' : null), // F - Nữ
                    $it['ethnicity'] ?? null,              // G - Dân tộc
                    $it['contact_name'] ?? null,           // H - Họ tên cha/mẹ/NGĐĐ
                    $it['address'] ?? null,                // I - Chỗ ở hiện nay
                    ($grade === 1 ? 'x' : null),           // J - Lớp 1
                    ($grade === 2 ? 'x' : null),           // K - Lớp 2
                    ($grade === 3 ? 'x' : null),           // L - Lớp 3
                    ($grade === 4 ? 'x' : null),           // M - Lớp 4
                    ($grade === 5 ? 'x' : null),           // N - Lớp 5
                    $it['school_name'] ?? null,            // O - Tên trường
                    $it['th_grad_year'] ?? null,           // P - Năm HTCT TH
                    $it['quit_grade'] ?? null,             // Q - Bỏ học lớp
                    $it['quit_year'] ?? null,              // R - Năm bỏ học
                    $it['so_phieu'] ?? null,               // S - Số phiếu điều tra
                    $it['note'] ?? null,                   // T - Ghi chú
                ];
                $stt++;
            }

            // Ghi dữ liệu
            if ($count > 0) {
                $firstCell = "A{$startRow}";
                $sh->fromArray($data, null, $firstCell);

                // Clone style dòng mẫu cho toàn dải mới chèn (nhanh hơn so với copy từng dòng)
                $endRow = $startRow + $count - 1;
                $src    = "A{$startRow}:{$lastCol}{$startRow}";
                $dst    = "A{$startRow}:{$lastCol}{$endRow}";
                $sh->duplicateStyle($sh->getStyle($src), $dst);

                // Căn lề theo khối (1 lần)
                $sh->getStyle($dst)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                // Center: STT, ngày sinh, cột lớp 1..5, P..R
                foreach (["A{$startRow}:A{$endRow}", "E{$startRow}:N{$endRow}", "P{$startRow}:R{$endRow}"] as $rng) {
                    $sh->getStyle($rng)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                // Left: tên, địa chỉ, ghi chú, tên trường
                foreach (["B{$startRow}:B{$endRow}", "H{$startRow}:H{$endRow}", "I{$startRow}:I{$endRow}", "O{$startRow}:O{$endRow}", "T{$startRow}:T{$endRow}"] as $rng) {
                    $sh->getStyle($rng)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }
                // Wrap cho địa chỉ & ghi chú
                foreach (["I{$startRow}:I{$endRow}", "T{$startRow}:T{$endRow}"] as $rng) {
                    $sh->getStyle($rng)->getAlignment()->setWrapText(true);
                }

                // Viền bảng dữ liệu
                $this->drawThinBorders($sh, $startRow, $endRow, 'A', $lastCol);
            }

            // 3.6 Tính & ghi thống kê phần dưới (ít ô -> cứ setCellValue trực tiếp)
            $stats = $this->buildStats($rows);
            $this->writeStats($sh, $cfg['stats'], $stats,$stt-2);
             $sh->mergeCells("O".($stt+24).":R".($stt+24));
            $this->setCellValue($sh,'O'.($stt+24), $this->formatService->end_date($wardName)); // ô tùy template
            

        }

        // ===== 4) Xuất file
        return response()->streamDownload(function () use ($wb) {
            $writer = IOFactory::createWriter($wb, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /* ===========================
     *   DỮ LIỆU DÙNG CHUNG
     * =========================== */

    /**
     * Lấy cohort theo NĂM SINH (1 sheet).
     * Trả về mảng mỗi học sinh gồm đủ thông tin để đổ bảng & tính thống kê.
     */
    protected function queryCohortByBirthYear(string $academic, string $scheme, array $geo, int $birthYear): array
    {
        // — subquery education mới nhất năm học
        $peLatest = DB::table('person_educations as pe1')
            ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) AS rn'))
            ->where('pe1.academic_year', $academic);

        // — subquery disability mới nhất
        $pdLatest = DB::table('person_disabilities as t')
            ->select('t.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY t.person_id ORDER BY t.id DESC) AS rn'))
            ->where('t.is_deleted', false);

        // — (tuỳ hệ thống) hoàn cảnh đặc biệt: map theo code
        //   Nếu DB của bạn dùng bảng khác, chỉ cần sửa JOIN & CASE dưới.
        $scLink = DB::table('person_special_circumstances as psc')
            ->select('psc.person_id', DB::raw('GROUP_CONCAT(sc.name SEPARATOR ", ") AS sc_names'),
                     DB::raw('MAX(CASE WHEN sc.name IN ("Chuyển đi","MOVED_IN")  THEN 1 ELSE 0 END) AS moved_in'),
                     DB::raw('MAX(CASE WHEN sc.name IN ("Chuyển đến","MOVED_OUT") THEN 1 ELSE 0 END) AS moved_out'),
                     DB::raw('MAX(CASE WHEN sc.name IN ("Chết","DECEASED")      THEN 1 ELSE 0 END) AS deceased'))
            ->join('special_circumstances as sc', 'sc.id', '=', 'psc.sc_id')
            ->groupBy('psc.person_id');

        $qb = DB::table('survey_form_details as d')
            ->join('survey_forms as f', fn($j) => $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false))
            ->join('people as p', 'p.id', '=', 'd.person_id')
            ->leftJoinSub($peLatest, 'pe', fn($j) => $j->on('pe.person_id','=','p.id')->where('pe.rn',1))
            ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id')
            ->leftJoin('schools as s', 's.id', '=', 'pe.school_id')
            ->leftJoin('graduation_levels as gl', 'gl.id', '=', 'pe.graduation_level_id')
            ->leftJoin('ethnicities as et', 'et.id', '=', 'p.ethnicity_id')
            ->leftJoinSub($pdLatest, 'pd', fn($j) => $j->on('pd.person_id','=','p.id')->where('pd.rn',1))
            ->leftJoinSub($scLink, 'sc', fn($j) => $j->on('sc.person_id','=','p.id'))
            ->where('d.is_deleted', false)
            ->where('d.academic_year', $academic)
            ->where('p.dob_year', $birthYear);

        // Lọc địa bàn cư trú
        $this->applyGeoFilter($qb, $scheme, $geo);

        // Cờ trạng thái
        $isDisabled   = "JSON_LENGTH(pd.disability_ids) > 0";
        $isNotDisabled   = "pd.disability_ids is NULL or JSON_LENGTH(pd.disability_ids) = 0";
        $gradeNum     = "CAST(eb.name AS UNSIGNED)";
        $isTHGrade    = "({$gradeNum} BETWEEN 1 AND 5)";
        $isStudyingTH = "(pe.education_block_id IS NOT NULL AND {$isTHGrade} and {$isNotDisabled})";
        $isGradTH     = "(gl.id IS NOT NULL AND UPPER(TRIM(gl.name))='TH')";
        $quitGrade    = "NULLIF(pe.bo_hoc_lop, '')";
        $quitYear     = "NULLIF(pe.bo_hoc_nam, '')"; // SỬA tên cột nếu khác

        // Lấy dữ liệu một lần đủ cho mọi phần
        $rows = $qb->selectRaw("
                p.id,
                p.ho_dem, p.ten, p.dob_day, p.dob_month, p.dob_year, p.is_female,
                p.contact_name, p.addressline,
                COALESCE(et.name,'') as ethnicity,
                f.so_phieu,
                COALESCE(pe.school_name_custom, s.name) as school_name,
                CASE WHEN {$isStudyingTH} THEN {$gradeNum} ELSE NULL END AS grade_now,
                CASE WHEN {$isGradTH} THEN COALESCE( pe.graduation_year) END AS th_grad_year,  -- SỬA nếu DB bạn khác tên
                CASE WHEN {$quitGrade} IS NOT NULL AND {$gradeNum} NOT BETWEEN 1 AND 5
                     THEN CAST({$quitGrade} AS UNSIGNED)
                     ELSE CAST({$quitGrade} AS UNSIGNED)
                END AS quit_grade,
                CAST({$quitYear} AS UNSIGNED) AS quit_year,
                ({$isDisabled}) AS is_disabled,
                COALESCE(sc.sc_names,'') AS sc_names,
                COALESCE(sc.moved_in,0)  AS moved_in,
                COALESCE(sc.moved_out,0) AS moved_out,
                COALESCE(sc.deceased,0)  AS deceased
            ")
            ->orderBy('p.ten')
            ->get();

        // Map về mảng thuần
        $out = [];
        foreach ($rows as $r) {
            $noteParts = [];
            if ($r->sc_names)  $noteParts[] = $r->sc_names;
            if ($r->is_disabled) $noteParts[] = 'Khuyết tật';

            $out[] = [
                'person_id'   => (int)$r->id,
                'full_name'   => trim(($r->ho_dem ? $r->ho_dem.' ' : '').($r->ten ?? '')),
                'dob_day'     => (int)($r->dob_day ?? 0),
                'dob_month'   => (int)($r->dob_month ?? 0),
                'dob_year'    => (int)($r->dob_year ?? 0),
                'is_female'   => (bool)$r->is_female,
                'ethnicity'   => $r->ethnicity ?: null,
                'contact_name'=> $r->contact_name ?: null,
                'address'     => $r->addressline ?: null,
                'grade_now'   => $r->grade_now ? (int)$r->grade_now : null,
                'school_name' => $r->school_name ?: null,
                'th_grad_year'=> $r->th_grad_year ? (int)$r->th_grad_year : null,
                'quit_grade'  => $r->quit_grade ? (int)$r->quit_grade : null,
                'quit_year'   => $r->quit_year ? (int)$r->quit_year : null,
                'so_phieu'    => $r->so_phieu ?: null,
                'is_disabled' => (bool)$r->is_disabled,
                'moved_in'    => (int)$r->moved_in,
                'moved_out'   => (int)$r->moved_out,
                'deceased'    => (int)$r->deceased,
                'note'        => $noteParts ? implode(', ', $noteParts) : null,
            ];
        }
        return $out;
    }

    /**
     * Thống kê phần dưới trang (từ list 1 cohort).
     */
    protected function buildStats(array $rows): array
    {
        $stats = [
            'total'        => 0,
            'moved_in'     => 0,
            'moved_out'    => 0,
            'deceased'     => 0,
            'disabled'     => 0,
            'ppc_total'    => 0, // = total - disabled

            'studying'     => 0,
            'quit'         => 0,
            'graduated'    => 0,

            'female'       => 0,
            'ethnic'       => 0, // != Kinh

            'studying_l1'  => 0,
            'studying_l2'  => 0,
            'studying_l3'  => 0,
            'studying_l4'  => 0,
            'studying_l5'  => 0,

            'quit_l1'      => 0,
            'quit_l2'      => 0,
            'quit_l3'      => 0,
            'quit_l4'      => 0,
            'quit_l5'      => 0,
        ];

        foreach ($rows as $r) {
            $stats['total']++;

            if ($r['is_female']) $stats['female']++;
            if (($r['ethnicity'] ?? '') !== '' && mb_strtolower($r['ethnicity']) !== 'kinh') $stats['ethnic']++;

            $stats['moved_in']  += $r['moved_in'];
            $stats['moved_out'] += $r['moved_out'];
            $stats['deceased']  += $r['deceased'];
            if ($r['is_disabled']) $stats['disabled']++;

            if ($r['grade_now']) {
                $stats['studying']++;
                $key = 'studying_l'.$r['grade_now'];
                if (isset($stats[$key])) $stats[$key]++;
            } elseif ($r['quit_grade']) {
                $stats['quit']++;
                $key = 'quit_l'.$r['quit_grade'];
                if (isset($stats[$key])) $stats[$key]++;
            } elseif ($r['th_grad_year']) {
                $stats['graduated']++;
            }
           
        }

        $stats['ppc_total'] = $stats['total'] - $stats['disabled'];
        $stats['noclass'] =  $stats['ppc_total'] -  $stats['graduated'] -  $stats['studying'] - $stats['quit'];
        return $stats;
    }

    /**
     * Ghi thống kê xuống các ô cố định phía dưới.
     * Map các ô trong template của bạn ở đây.
     */
    protected function writeStats(Worksheet $sh, array $map, array $s, $stt): void
    {
        // Tổng số trẻ sinh năm ...
        $this->setCellValue($sh, 'F'.($stt+13), $s['total']);

        // Chia ra
        $this->setCellValue($sh, 'F'.($stt+15),  $s['moved_in']);
        $this->setCellValue($sh, 'F'.($stt+16), $s['moved_out']);
        $this->setCellValue($sh, 'J'.($stt+15),  $s['deceased']);
        $this->setCellValue($sh, 'J'.($stt+16),  $s['disabled']);

        // PPC
        $this->setCellValue($sh,  'J'.($stt+18), $s['ppc_total']);

        // Đang học/Bỏ học/Đã tốt nghiệp
        $this->setCellValue($sh,  'O'.($stt+18),  $s['studying']);
        $this->setCellValue($sh,  'J'.($stt+22),  $s['studying']);
        $this->setCellValue($sh, 'S'.($stt+19),       $s['quit']);
        $this->setCellValue($sh, 'J'.($stt+21),       $s['quit']);
        $this->setCellValue($sh, 'J'.($stt+23),  $s['graduated']);
         $this->setCellValue($sh, 'J'.($stt+20),  $s['noclass']); 
        // Nữ, Dân tộc
        $this->setCellValue($sh, 'J'.($stt+24), $s['female'].'/'.$s['ethnic']);
       
        // Đang học theo lớp
        foreach ([1,2,3,4,5] as $k=> $g) {
            $this->setCellValue($sh,  'O'.($stt+20 + $k), $s['studying_l'.$g]);
        }
        // Bỏ học theo lớp
        foreach ([1,2,3,4,5] as $k=> $g) {
            $this->setCellValue($sh, 'S'.($stt+20 + $k), $s['quit_l'.$g]);
        }
    }

    /* ===========================
     *   TEMPLATE & TIỆN ÍCH
     * =========================== */

    protected function templateCfg(): array
    {
        return [
            // template có sẵn 9 sheet: "14 tuổi" ..."6 tuổi"
            'path' => resource_path('excel/2.10.TiH.SoPhoCap.TatCaNam.xlsx'),

            'header' => [
                'province'  => 'D1',
                'ward'      => 'D2',
                'birthYear' => 'D3',
            ],
            'dateTop'  => 'I5',     // ô ngày phía trên (bạn thay nếu khác)
            'startRow' => 12,       // dòng đầu dữ liệu
            'lastCol'  => 'T',

            // toạ độ các ô thống kê ở phần dưới (theo template trắng bạn gửi)
            'stats' => [
                'total'          => 'F13',  // "* Tổng số trẻ sinh năm ... có:"
                'moved_in'       => 'F15',
                'moved_out'      => 'F16',
                'deceased'       => 'K15',
                'disabled'       => 'K16',
                'ppc_total'      => 'I18',

                'studying_total' => 'O18',
                'quit_total'     => 'S18',
                'graduated_total'=> 'I21',

                'female'         => 'I22',
                'ethnic'         => 'I23',

                'studying_l1'    => 'O19',
                'studying_l2'    => 'O20',
                'studying_l3'    => 'O21',
                'studying_l4'    => 'O22',
                'studying_l5'    => 'O23',

                'quit_l1'        => 'S19',
                'quit_l2'        => 'S20',
                'quit_l3'        => 'S21',
                'quit_l4'        => 'S22',
                'quit_l5'        => 'S23',
            ],
        ];
    }

    /** Ghi cell + căn lề/ wrap nếu cần (bản mở rộng của setCellValue bạn đang dùng). */
    // protected function setCellValue(Worksheet $sh, string $addr, $value, array $opt = []): void
    // {
    //     parent::setCellValue($sh, $addr, $value, $opt);

    //     if (!empty($opt['align'])) {
    //         $align = $sh->getStyle($addr)->getAlignment();
    //         if ($opt['align'] === 'center') $align->setHorizontal(Alignment::HORIZONTAL_CENTER);
    //         if ($opt['align'] === 'left')   $align->setHorizontal(Alignment::HORIZONTAL_LEFT);
    //     }
    //     if (!empty($opt['wrap'])) {
    //         $sh->getStyle($addr)->getAlignment()->setWrapText(true);
    //     }
    // }

    protected function fmtDob(?int $d, ?int $m, ?int $y): ?string
    {
        if (!$d && !$m && !$y) return null;
        $dd = $d ? str_pad($d, 2, '0', STR_PAD_LEFT) : '??';
        $mm = $m ? str_pad($m, 2, '0', STR_PAD_LEFT) : '??';
        $yy = $y ?: '????';
        return "{$dd}/{$mm}/{$yy}";
    }

    protected function drawThinBorders(Worksheet $sh, int $rowFrom, int $rowTo, string $colFrom, string $colTo): void
    {
        $range = sprintf('%s%d:%s%d', $colFrom, $rowFrom, $colTo, $rowTo);
        $sh->getStyle($range)->getBorders()->getAllBorders()
           ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    }
}
