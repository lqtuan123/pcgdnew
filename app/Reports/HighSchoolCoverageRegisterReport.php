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

class HighSchoolCoverageRegisterReport extends BaseReport
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

        // THPT thường 21→15 tuổi
        foreach (range(21, 15, -1) as $age) {
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

            // xác định cột "tên lớp đang học" trùng năm học đang chọn
            $classIdx = 2; // mặc định cột giữa nếu không khớp
            foreach ($cfg['classCols'] as $i => $col) {
                $label = trim((string)$sh->getCell($col.$cfg['classYearRow'])->getValue());
                if ($label === $academic) { $classIdx = $i; break; }
            }

            // vùng dữ liệu
            $startRow = $cfg['startRow'];   // 10
            $lastCol  = $cfg['lastCol'];    // U
            $n        = count($rows);
            if ($n > 1) $sh->insertNewRowBefore($startRow + 1, $n - 1);

            // build mảng đổ 1 phát
            $data = [];
            $stt  = 1;
            foreach ($rows as $it) {
                $dob = $this->fmtDob($it['dob_day'] ?? null, $it['dob_month'] ?? null, $it['dob_year'] ?? null);

                // ba cột tên lớp theo 3 năm học — chỉ 1 cột có dữ liệu
                $classVals = [null, null, null];
                $classVals[$classIdx] = $it['class_label'] ?? null;

                $data[] = [
                    $stt,                               // A  - STT
                    $it['full_name'] ?? null, null,          // B  - Họ và tên
                    $dob ?: null,                       // C  - Ngày, tháng, năm sinh
                    (!empty($it['is_female']) ? 'x' : null), // D  - Nữ
                    $it['ethnicity'] ?? null,           // E  - Dân tộc
                    $it['contact_name'] ?? null,        // F  - Họ tên cha/mẹ (hoặc NGĐĐ)
                    $it['address'] ?? null,             // G  - Thôn xóm
                    $classVals[0],                      // H?I  - Tên lớp (năm học thứ 1)
                    $classVals[1],                      // J    - Tên lớp (năm học thứ 2)
                    $classVals[2],                      // K    - Tên lớp (năm học thứ 3)
                    $it['school_name'] ?? null,         // L  - Tên Trường
                    $this->toAcademicSpan($it['thcs_grad_year'] ?? null), // M - TN THCS
                    $this->toAcademicSpan($it['thpt_grad_year'] ?? null), // N - TN THPT
                    $it['finished_grade'] ?? null,      // O - Đã học xong: Lớp
                    $this->toAcademicSpan($it['finished_year'] ?? null),  // P - Đã học xong: Năm
                    $it['quit_grade'] ?? null,          // Q - Bỏ học lớp
                    $this->toAcademicSpan($it['quit_year'] ?? null),      // R - Bỏ học năm
                    $it['so_phieu'] ?? null,            // S - Số phiếu điều tra
                    $it['special_text'] ?? null,        // T - Hoàn cảnh đặc biệt (kèm Khuyết tật)
                    $it['note'] ?? null,                // U - Ghi chú
                ];
                $stt++;
            }

            if ($n > 0) {
                $sh->fromArray($data, null, "A{$startRow}");

                $endRow = $startRow + $n - 1;
                $src    = "A{$startRow}:{$lastCol}{$startRow}";
                $dst    = "A{$startRow}:{$lastCol}{$endRow}";
                $sh->duplicateStyle($sh->getStyle($src), $dst);
                // Merge B:C cho từng hàng dữ liệu
                for ($r = $startRow; $r <= $endRow; $r++) {
                    // Nếu có helper safeMerge thì dùng: $this->safeMerge($sh, "B{$r}:C{$r}");
                    $sh->mergeCells("B{$r}:C{$r}");
                }

                // căn lề
                $sh->getStyle($dst)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sh->getStyle("A{$startRow}:A{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("C{$startRow}:C{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("D{$startRow}:D{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sh->getStyle("H{$startRow}:K{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT); // tên lớp dạng text
                $sh->getStyle("L{$startRow}:R{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                foreach (["B{$startRow}:B{$endRow}","F{$startRow}:F{$endRow}","G{$startRow}:G{$endRow}","L{$startRow}:L{$endRow}",
                          "T{$startRow}:T{$endRow}","U{$startRow}:U{$endRow}"] as $rng) {
                    $sh->getStyle($rng)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }
                foreach (["G{$startRow}:G{$endRow}","T{$startRow}:T{$endRow}","U{$startRow}:U{$endRow}"] as $rng) {
                    $sh->getStyle($rng)->getAlignment()->setWrapText(true);
                }

                $this->drawThinBorders($sh, $startRow, $endRow, 'A', $lastCol);
            }

            // footer ngày tháng (tuỳ template)
            // $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
            // $footerRow = max($startRow + $n + 2, $cfg['footerRow']);
            // $sh->setCellValue($cfg['footerCellPrefix'].$footerRow, $this->formatService->end_date($wardName));
             $wardName = $this->geoService->geoNameFromId($geo['ward_id'] ?? null) ?? '';
            // $sh->mergeCells('N'.($stt + 12).':R'.($stt + 12));
            $sh->setCellValue('N'.($stt + 12), $this->formatService->end_date($wardName));
        }

        return response()->streamDownload(function () use ($wb) {
            $writer = IOFactory::createWriter($wb, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Lấy 1 cohort theo NĂM SINH cho THPT. */
    protected function queryCohortByBirthYear(string $academic, string $scheme, array $geo, int $birthYear): array
    {
        $peLatest = DB::table('person_educations as pe1')
            ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) AS rn'))
            ->where('pe1.academic_year', $academic);

        $pdLatest = DB::table('person_disabilities as t')
            ->select('t.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY t.person_id ORDER BY t.id DESC) AS rn'))
            ->where('t.is_deleted', false);

        $scLink = DB::table('person_special_circumstances as psc')
            ->join('special_circumstances as sc', 'sc.id', '=', 'psc.sc_id')
            ->select(
                'psc.person_id',
                DB::raw('GROUP_CONCAT(sc.name SEPARATOR ", ") AS sc_names')
            )
            ->groupBy('psc.person_id');

        $qb = DB::table('survey_form_details as d')
            ->join('survey_forms as f', fn($j) => $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false))
            ->join('people as p', 'p.id', '=', 'd.person_id')
            ->leftJoinSub($peLatest, 'pe', fn($j) => $j->on('pe.person_id','=','p.id')->where('pe.rn',1))
            ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id')
            ->leftJoin('schools as s', function($j) {
                $j->on('s.id', '=', 'pe.school_id')
                  ->where(function($w) {
                      $w->whereNull('s.is_dissolved')
                        ->orWhere('s.is_dissolved', false)
                        ->orWhere('s.is_dissolved', 0);
                  });
            })
            ->leftJoin('graduation_levels as gl', 'gl.id', '=', 'pe.graduation_level_id')
            ->leftJoin('ethnicities as et', 'et.id', '=', 'p.ethnicity_id')
            ->leftJoinSub($pdLatest, 'pd', fn($j) => $j->on('pd.person_id','=','p.id')->where('pd.rn',1))
            ->leftJoinSub($scLink, 'sc', fn($j) => $j->on('sc.person_id','=','p.id'))
            ->where('d.is_deleted', false)
            ->where('d.academic_year', $academic)
            ->where('p.dob_year', $birthYear);

        $this->applyGeoFilter($qb, $scheme, $geo);

        $gradeNum       = "CAST(eb.name AS UNSIGNED)";
        $isDisabled     = "JSON_LENGTH(pd.disability_ids) > 0";
        $notDisabled    = "(pd.disability_ids IS NULL OR JSON_LENGTH(pd.disability_ids)=0)";
        $isStudyingHS   = "(pe.education_block_id IS NOT NULL AND {$gradeNum} BETWEEN 10 AND 12 AND {$notDisabled})";
        $isGradTHCS     = "(gl.id IS NOT NULL AND UPPER(TRIM(gl.name))='THCS')";
        $isGradTHPT     = "(gl.id IS NOT NULL AND UPPER(TRIM(gl.name))='THPT')";

        $finishedGrade  = "NULLIF(pe.hoc_xong_lop,'')";
        $finishedYear   = "NULLIF(pe.hoc_xong_nam,'')";
        $quitGrade      = "NULLIF(pe.bo_hoc_lop,'')";
        $quitYear       = "NULLIF(pe.bo_hoc_nam,'')";

        $rows = $qb->selectRaw("
                p.id,
                p.ho_dem, p.ten, p.dob_day, p.dob_month, p.dob_year, p.is_female,
                p.contact_name, p.addressline, p.note as p_note,
                COALESCE(et.name,'') as ethnicity,
                f.so_phieu,
                s.name as school_name,

                CASE WHEN {$isStudyingHS} THEN {$gradeNum} ELSE NULL END AS grade_now,
                pe.class_label as class_label,

                CASE WHEN {$isGradTHCS} THEN pe.graduation_year END AS thcs_grad_year,
                CASE WHEN {$isGradTHPT} THEN pe.graduation_year END AS thpt_grad_year,

                CAST({$finishedGrade} AS UNSIGNED) AS finished_grade,
                CAST({$finishedYear}  AS UNSIGNED) AS finished_year,
                CAST({$quitGrade}     AS UNSIGNED) AS quit_grade,
                CAST({$quitYear}      AS UNSIGNED) AS quit_year,

                ({$isDisabled}) AS is_disabled,
                COALESCE(sc.sc_names,'') AS sc_names
            ")
            ->orderBy('p.ten')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $special = [];
            if ($r->sc_names)   $special[] = $r->sc_names;
            if ($r->is_disabled) $special[] = 'Khuyết tật';

            $out[] = [
                'person_id'      => (int)$r->id,
                'full_name'      => trim(($r->ho_dem ? $r->ho_dem.' ' : '').($r->ten ?? '')),
                'dob_day'        => (int)($r->dob_day ?? 0),
                'dob_month'      => (int)($r->dob_month ?? 0),
                'dob_year'       => (int)($r->dob_year ?? 0),
                'is_female'      => (bool)$r->is_female,
                'ethnicity'      => $r->ethnicity ?: null,
                'contact_name'   => $r->contact_name ?: null,
                'address'        => $r->addressline ?: null,

                'grade_now'      => $r->grade_now ? (int)$r->grade_now : null,
                'class_label'    => $r->class_label ?: null,
                'school_name'    => $r->school_name ?: null,

                'thcs_grad_year' => $r->thcs_grad_year ? (int)$r->thcs_grad_year : null,
                'thpt_grad_year' => $r->thpt_grad_year ? (int)$r->thpt_grad_year : null,

                'finished_grade' => $r->finished_grade ? (int)$r->finished_grade : null,
                'finished_year'  => $r->finished_year ? (int)$r->finished_year : null,
                'quit_grade'     => $r->quit_grade ? (int)$r->quit_grade : null,
                'quit_year'      => $r->quit_year ? (int)$r->quit_year : null,

                'special_text'   => $special ? implode(', ', $special) : null,
                'so_phieu'       => $r->so_phieu ?: null,
                'note'           => $r->p_note ?: null,
            ];
        }
        return $out;
    }

    /* ===== Template & helpers ===== */

    protected function templateCfg(): array
    {
        return [
            // Đổi path này cho đúng file template THPT của bạn
            'path'          => resource_path('excel/4.6.THPT.SoPhoCap.TatCaNam.xlsx'),

            'header'        => ['province' => 'C1', 'ward' => 'C2', 'birthYear' => 'C3'],
            'startRow'      => 12,
            'lastCol'       => 'U',

            // Nhãn năm học 3 cột "Tên lớp đang học"
            'classYearRow'  => 7,            // hàng hiển thị "2022-2023 | 2023-2024 | 2024-2025"
            'classCols'     => ['H','I','J'],// chỉnh lại đúng 3 cột trong template của bạn

            // Footer ngày tháng (đặt 1 cột cố định, dòng tính theo dữ liệu)
            'footerCellPrefix' => 'N',
            'footerRow'        => 22,
        ];
    }

    protected function toAcademicSpan($year): ?string
    {
        if ($year === null || $year === '') return null;
        $s = is_string($year) ? trim($year) : (string)$year;
        if (preg_match('/^\d{4}\s*-\s*\d{4}$/', $s)) return $s;
        $y = (int)$s;
        if ($y >= 1900 && $y <= 2500) return sprintf('%d-%d', $y, $y + 1);
        return $s;
    }

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
