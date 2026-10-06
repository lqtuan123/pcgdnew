<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Calculation\Calculation;
use App\Services\GeoService;
use App\Services\ReportFormatService;

class SecondaryCoverageRegisterReport extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    public function export(Request $req)
    {
        $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$y1]     = array_map('intval', explode('-', $academic));
        $scheme   = (string)$req->input('scheme', 'old');
        $geo      = (array)$req->input('geo', []);

        // tăng tốc
        if (method_exists(Calculation::getInstance(), 'setCalculationEnabled')) {
            Calculation::getInstance()->setCalculationEnabled(false);
        }

        $cfg = $this->templateCfg();
        $wb  = IOFactory::load($cfg['path']);

        // 9 trang: 18 → 10 tuổi (năm sinh tương ứng)
        foreach (range(18, 10, -1) as $age) {
            $sheetName = sprintf('%d tuổi', $age);
            $sh = $wb->getSheetByName($sheetName);
            if (!$sh) {
                $base = $wb->getSheet(0);
                $sh   = clone $base;
                $sh->setTitle($sheetName);
                $wb->addSheet($sh);
            }

            $birthYear = $y1 - $age;
            $rows = $this->queryCohortByBirthYear($academic, $scheme, $geo, $birthYear);

            // header
            $sh->setCellValue($cfg['header']['province'],  $this->geoService->geoNameFromId($geo['province_id'] ?? null));
            $sh->setCellValue($cfg['header']['ward'],      $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
            $sh->setCellValue($cfg['header']['birthYear'], $birthYear);

            // vùng dữ liệu
            $startRow = $cfg['startRow'];   // 12
            $lastCol  = $cfg['lastCol'];    // W
            $n        = count($rows);
            if ($n > 1) {
                $sh->insertNewRowBefore($startRow + 1, $n - 1);
            }

            // dựng mảng 2D và đổ 1 lần
            $data = [];
            $stt  = 1;
            foreach ($rows as $it) {
                $grade = $it['grade_now'] ?? null;
                $dob   = $this->fmtDob($it['dob_day'] ?? null, $it['dob_month'] ?? null, $it['dob_year'] ?? null);
                $thYear   = $this->toAcademicSpan($it['th_grad_year']  ?? null);   // P
                $thcsYear = $this->toAcademicSpan($it['thcs_grad_year'] ?? null);  // Q
                $data[] = [
                    $stt,                               // A  - STT
                    $it['full_name'] ?? null,           // B  - Họ và tên
                    null,                          // C,D (merge tiêu đề)
                    $dob ?: null,                       // E  - Ngày sinh
                    (!empty($it['is_female']) ? 'x' : null), // F  - Nữ
                    $it['ethnicity'] ?? null,           // G  - Dân tộc
                    $it['contact_name'] ?? null,        // H  - Họ tên cha/mẹ/NGĐĐ
                    $it['address'] ?? null,             // I  - Thôn xóm / địa chỉ
                    ($grade === 5 ? 'x' : null),        // J  - Lớp 5
                    ($grade === 6 ? 'x' : null),        // K  - Lớp 6
                    ($grade === 7 ? 'x' : null),        // L  - Lớp 7
                    ($grade === 8 ? 'x' : null),        // M  - Lớp 8
                    ($grade === 9 ? 'x' : null),        // N  - Lớp 9
                    $it['school_name'] ?? null,         // O  - Trường
                    $thYear ?? null,        // P  - Năm HTCT Tiểu học
                    $thcsYear?? null,      // Q  - Năm tốt nghiệp THCS
                    $it['finished_grade'] ?? null,      // R  - Đã học xong (lớp)
                    $it['finished_year'] ?? null,       // S  - Đã học xong (năm)
                    $it['quit_grade'] ?? null,          // T  - Bỏ học lớp
                    $it['quit_year'] ?? null,           // U  - Bỏ học năm
                    $it['special_text'] ?? null,        // V  - Hoàn cảnh đặc biệt + Khuyết tật
                    $it['so_phieu'] ?? null,            // W  - Số phiếu điều tra
                    $it['note'] ?? null,                 // X  - Ghi chú (nếu template có cột X)
                ];
                $stt++;
            }

            if ($n > 0) {
                $firstCell = "A{$startRow}";
                $sh->fromArray($data, null, $firstCell);

                $endRow = $startRow + $n - 1;
                $src    = "A{$startRow}:{$lastCol}{$startRow}";
                $dst    = "A{$startRow}:{$lastCol}{$endRow}";
                $sh->duplicateStyle($sh->getStyle($src), $dst);

                // căn lề theo khối
                $sh->getStyle($dst)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                foreach (["A{$startRow}:A{$endRow}", "E{$startRow}:N{$endRow}", "P{$startRow}:U{$endRow}"] as $rng) {
                    $sh->getStyle($rng)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }
                foreach (["B{$startRow}:B{$endRow}", "H{$startRow}:H{$endRow}", "I{$startRow}:I{$endRow}", "O{$startRow}:O{$endRow}", "V{$startRow}:V{$endRow}", "W{$startRow}:W{$endRow}"] as $rng) {
                    $sh->getStyle($rng)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }
                foreach (["I{$startRow}:I{$endRow}", "V{$startRow}:V{$endRow}", "W{$startRow}:W{$endRow}"] as $rng) {
                    $sh->getStyle($rng)->getAlignment()->setWrapText(true);
                }

                $this->drawThinBorders($sh, $startRow, $endRow, 'A', $lastCol);
            }

            // thống kê dưới trang (offset theo số dòng đã ghi)
            $stats = $this->buildStats($rows);
            $this->writeStats($sh, $cfg['stats'], $stats, $stt - 4);

            // dòng ngày tháng cuối trang
            $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
            $sh->mergeCells('O'.($stt + 22).':R'.($stt + 22));
            $sh->setCellValue('O'.($stt + 22), $this->formatService->end_date($wardName));
        }

        return response()->streamDownload(function () use ($wb) {
            $writer = IOFactory::createWriter($wb, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Lấy 1 cohort theo NĂM SINH cho THCS.
     * - Lớp đang học: 5..9
     * - Năm HTCT TH (P), Năm tốt nghiệp THCS (Q)
     * - Đã học xong: R (lớp), S (năm)
     * - Bỏ học: T (lớp), U (năm)
     * - Hoàn cảnh đặc biệt + Khuyết tật: V
     * - Số phiếu: W; Ghi chú: note của person
     */
    protected function queryCohortByBirthYear(string $academic, string $scheme, array $geo, int $birthYear): array
    {
        $peLatest = DB::table('person_educations as pe1')
            ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) AS rn'))
            ->where('pe1.academic_year', $academic);

        $pdLatest = DB::table('person_disabilities as t')
            ->select('t.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY t.person_id ORDER BY t.id DESC) AS rn'))
            ->where('t.is_deleted', false);

        // tổng hợp hoàn cảnh đặc biệt
        $scLink = DB::table('person_special_circumstances as psc')
            ->join('special_circumstances as sc', 'sc.id', '=', 'psc.sc_id')
            ->select(
                'psc.person_id',
                DB::raw('GROUP_CONCAT(sc.name SEPARATOR ", ") AS sc_names'),
                DB::raw('MAX(CASE WHEN sc.name IN ("Chuyển đến","MOVED_IN")  THEN 1 ELSE 0 END) AS moved_in'),
                DB::raw('MAX(CASE WHEN sc.name IN ("Chuyển đi","MOVED_OUT") THEN 1 ELSE 0 END) AS moved_out'),
                DB::raw('MAX(CASE WHEN sc.name IN ("Chết","DECEASED")      THEN 1 ELSE 0 END) AS deceased')
            )
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

        $this->applyGeoFilter($qb, $scheme, $geo);

        // cờ tính
        $gradeNum        = "CAST(eb.name AS UNSIGNED)";
        $isDisabled      = "JSON_LENGTH(pd.disability_ids) > 0";
        $notDisabled     = "(pd.disability_ids IS NULL OR JSON_LENGTH(pd.disability_ids)=0)";
        $isStudyingTHCS  = "(pe.education_block_id IS NOT NULL AND {$gradeNum} BETWEEN 5 AND 9 AND {$notDisabled})";
        $isGradTH        = "(gl.id IS NOT NULL AND UPPER(TRIM(gl.name))='TH')";
        $isGradTHCS      = "(gl.id IS NOT NULL AND UPPER(TRIM(gl.name))='THCS')";

        // các cột “đã học xong” & “bỏ học”
        $finishedGrade = "NULLIF(pe.hoc_xong_lop,'')";
        $finishedYear  = "NULLIF(pe.hoc_xong_nam,'')";
        $quitGrade     = "NULLIF(pe.bo_hoc_lop,'')";
        $quitYear      = "NULLIF(pe.bo_hoc_nam,'')";

        $rows = $qb->selectRaw("
                p.id,
                p.ho_dem, p.ten, p.dob_day, p.dob_month, p.dob_year, p.is_female,
                p.contact_name, p.addressline, p.note as p_note,
                COALESCE(et.name,'') as ethnicity,
                f.so_phieu,
                COALESCE(pe.school_name_custom, s.name) as school_name,

                CASE WHEN {$isStudyingTHCS} THEN {$gradeNum} ELSE NULL END AS grade_now,

                CASE WHEN {$isGradTH}   THEN pe.graduation_year END AS th_grad_year,
                CASE WHEN {$isGradTHCS} THEN pe.graduation_year END AS thcs_grad_year,

                CAST({$finishedGrade} AS UNSIGNED) AS finished_grade,
                CAST({$finishedYear}  AS UNSIGNED) AS finished_year,

                CAST({$quitGrade} AS UNSIGNED)     AS quit_grade,
                CAST({$quitYear}  AS UNSIGNED)     AS quit_year,

                ({$isDisabled}) AS is_disabled,
                COALESCE(sc.sc_names,'') AS sc_names,
                COALESCE(sc.moved_in,0)  AS moved_in,
                COALESCE(sc.moved_out,0) AS moved_out,
                COALESCE(sc.deceased,0)  AS deceased
            ")
            ->orderBy('p.ten')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $special = [];
            if ($r->sc_names)   $special[] = $r->sc_names;
            if ($r->is_disabled) $special[] = 'Khuyết tật';

            $out[] = [
                'person_id'     => (int)$r->id,
                'full_name'     => trim(($r->ho_dem ? $r->ho_dem.' ' : '').($r->ten ?? '')),
                'dob_day'       => (int)($r->dob_day ?? 0),
                'dob_month'     => (int)($r->dob_month ?? 0),
                'dob_year'      => (int)($r->dob_year ?? 0),
                'is_female'     => (bool)$r->is_female,
                'ethnicity'     => $r->ethnicity ?: null,
                'contact_name'  => $r->contact_name ?: null,
                'address'       => $r->addressline ?: null,

                'grade_now'     => $r->grade_now ? (int)$r->grade_now : null,
                'school_name'   => $r->school_name ?: null,

                'th_grad_year'  => $r->th_grad_year ? (int)$r->th_grad_year : null,
                'thcs_grad_year'=> $r->thcs_grad_year ? (int)$r->thcs_grad_year : null,

                'finished_grade'=> $r->finished_grade ? (int)$r->finished_grade : null,
                'finished_year' => $r->finished_year ? (int)$r->finished_year : null,

                'quit_grade'    => $r->quit_grade ? (int)$r->quit_grade : null,
                'quit_year'     => $r->quit_year ? (int)$r->quit_year : null,

                'special_text'  => $special ? implode(', ', $special) : null,
                'so_phieu'      => $r->so_phieu ?: null,
                'note'          => $r->p_note ?: null,

                'is_disabled'   => (bool)$r->is_disabled,
                'moved_in'      => (int)$r->moved_in,
                'moved_out'     => (int)$r->moved_out,
                'deceased'      => (int)$r->deceased,
            ];
        }
        return $out;
    }

    /** Thống kê cho THCS (học 5..9, tốt nghiệp THCS). */
    protected function buildStats(array $rows): array
    {
        $s = [
            'total'=>0,'moved_in'=>0,'moved_out'=>0,'deceased'=>0,'disabled'=>0,
            'ppc_total'=>0, 'studying'=>0,'quit'=>0,'graduated'=>0,
            'female'=>0,'ethnic'=>0,
            'studying_l5'=>0,'studying_l6'=>0,'studying_l7'=>0,'studying_l8'=>0,'studying_l9'=>0,
            'quit_l5'=>0,'quit_l6'=>0,'quit_l7'=>0,'quit_l8'=>0,'quit_l9'=>0,
        ];

        foreach ($rows as $r) {
            $s['total']++;
            if ($r['is_female']) $s['female']++;
            if (($r['ethnicity'] ?? '') !== '' && mb_strtolower($r['ethnicity']) !== 'kinh') $s['ethnic']++;

            $s['moved_in']  += $r['moved_in'];
            $s['moved_out'] += $r['moved_out'];
            $s['deceased']  += $r['deceased'];
            if ($r['is_disabled']) $s['disabled']++;

            if ($r['grade_now']) {
                $s['studying']++;
                $k = 'studying_l'.$r['grade_now'];
                if (isset($s[$k])) $s[$k]++;
            } elseif ($r['quit_grade']) {
                $s['quit']++;
                $k = 'quit_l'.$r['quit_grade'];
                if (isset($s[$k])) $s[$k]++;
            } elseif ($r['thcs_grad_year']) {
                $s['graduated']++;
            }
        }

        $s['ppc_total'] = $s['total'] - $s['disabled'];
        return $s;
    }
// Hiển thị "2021-2022" từ 2021; nếu đã là "2021-2022" thì giữ nguyên
protected function toAcademicSpan($year): ?string
{
    if ($year === null || $year === '') return null;
    $s = is_string($year) ? trim($year) : (string)$year;

    // đã đúng định dạng -> trả về luôn
    if (preg_match('/^\d{4}\s*-\s*\d{4}$/', $s)) return $s;

    // số năm đơn lẻ -> chuyển thành YYYY-YYYY+1
    $y = (int)$s;
    if ($y >= 1900 && $y <= 2500) {
        return sprintf('%d-%d', $y, $y + 1);
    }
    return $s; // fallback
}
    /** Ghi thống kê xuống phần dưới (mapping theo layout bạn gửi cho THCS). */
    protected function writeStats(Worksheet $sh, array $map, array $s, int $usedRows): void
    {
        // offset theo số dòng dữ liệu đã điền (giống cách bạn làm bên TiH)
        $o = $usedRows;

        // Tổng số trẻ sinh năm …
        $sh->setCellValue('E'.($o+13), $s['total']);

        // + chia ra
        $sh->setCellValue('E'.($o+15), $s['moved_in']);
        $sh->setCellValue('E'.($o+16), $s['moved_out']);
        $sh->setCellValue('H'.($o+15), $s['deceased']);
        $sh->setCellValue('H'.($o+16), $s['disabled']);

        // PPC
        $sh->setCellValue('H'.($o+18), $s['ppc_total']);

        // Đang học / Bỏ học tổng quát
        $sh->setCellValue('M'.($o+18), $s['studying']);
        $sh->setCellValue('Q'.($o+18), $s['quit']);

        // Đã tốt nghiệp THCS
        $sh->setCellValue('H'.($o+22), $s['graduated']);

        // Nữ/Dân tộc
        $sh->setCellValue('H'.($o+24), $s['female'].' / '.$s['ethnic']);

        // Đang học theo lớp 5..9
        $sh->setCellValue('M'.($o+20), $s['studying_l5']);
        $sh->setCellValue('M'.($o+21), $s['studying_l6']);
        $sh->setCellValue('M'.($o+22), $s['studying_l7']);
        $sh->setCellValue('M'.($o+23), $s['studying_l8']);
        $sh->setCellValue('M'.($o+24), $s['studying_l9']);

        // Bỏ học theo lớp 5..9
        $sh->setCellValue('Q'.($o+20), $s['quit_l5']);
        $sh->setCellValue('Q'.($o+21), $s['quit_l6']);
        $sh->setCellValue('Q'.($o+22), $s['quit_l7']);
        $sh->setCellValue('Q'.($o+23), $s['quit_l8']);
        $sh->setCellValue('Q'.($o+24), $s['quit_l9']);
    }

    protected function templateCfg(): array
    {
        return [
            // template THCS (9 sheet: “18 tuổi”…“10 tuổi”)
            'path' => resource_path('excel/3.10.THCS.SoPhoCap.TatCaNam.xlsx'),

            'header'  => ['province' => 'C1', 'ward' => 'C2', 'birthYear' => 'C3'],
            'startRow'=> 10,
            'lastCol' => 'W',

            // Giữ key 'stats' cho tương thích (không dùng trực tiếp vì mình ghi theo offset)
            'stats'   => [],
        ];
    }

    /* ===== tiện ích dùng lại ===== */

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
