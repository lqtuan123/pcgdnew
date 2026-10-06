<?php

namespace App\Services;

use App\Models\SurveyForm;
use App\Models\SurveyFormDetail;
use App\Models\Person;
use App\Models\PersonEducation;
use App\Services\GeoService;
use App\Models\Household;
use App\Models\HouseholdMembership;
use App\Models\DisabilityCategory;
 
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
 
 
use PhpOffice\PhpSpreadsheet\Style\Alignment; // 👈 thêm dòng này
class SurveyFormExportService
{
    /** 
     * Tạo 1 workbook nhiều sheet từ danh sách ID phiếu (hoặc số phiếu nếu bạn muốn).
     * $ids: mảng ID SurveyForm.
     */
    protected array $disCatNames = [];

    

    protected function getDisCatNames(): array
    {
        if (!$this->disCatNames) {
            // 1 query cho toàn bộ lượt export
            $this->disCatNames = DisabilityCategory::pluck('name','id')->all();
        }
        return $this->disCatNames;
    }
    public function makeSpreadsheet(int $id): Spreadsheet
    {
        $form = SurveyForm::with([
            'household',
            'headPerson',
            'details.person.latestEducation',
        ])->findOrFail($id);
        // dd($form);
        $template = config('survey_export.template');
        $spreadsheet = IOFactory::load($template);
        $sheet = $spreadsheet->getActiveSheet();

        $this->fillHeader($sheet, $form);
        $this->fillMembers($sheet, $form);

        return $spreadsheet;
    }

     
 
public function makeWorkbookForMany(array $ids): Spreadsheet
{
    $templatePath = config('survey_export.template');
    if (!$templatePath || !is_file($templatePath)) {
        throw new \RuntimeException('Không tìm thấy template: '.($templatePath ?: '(null)'));
    }

    // 1) Load workbook TEMPLATE 1 lần và dùng CHÍNH workbook này làm kết quả
    /** @var Spreadsheet $wb */
    $wb   = IOFactory::load($templatePath);
    $base = $wb->getActiveSheet();   // sheet gốc trong template (KHÔNG đụng tên)

    // 2) Lấy dữ liệu
    $forms = SurveyForm::query()
        ->with([
            'household','headPerson',
            'oldWard','newWard','oldDistrict','oldProvince','newProvince','village',
            'details' => function ($qq) {
                $qq->notDeleted()->with([
                    'person.ethnicity','person.religion',
                    'person.oldWard','person.newWard','person.oldDistrict','person.oldProvince','person.newProvince','person.village',
                    'education.level','education.finishedGrade','education.vocationalLevel','education.school','education.block',
                    'literacy',
                    'person.disabilities',
                    'person.specialCircumstanceLinks.specialCircumstance',
                    'contact','residence',
                ]);
            },
        ])
        ->whereIn('id', $ids)
        ->get();

    // $forms = SurveyForm::query()
    // ->with([
    //     'household',
    //     'headPerson',
    //     'details.person.latestEducation', // giờ mới hợp lệ
    //     // và các with khác nếu cần
    // ])
    // ->whereIn('id', $ids)
    // ->get();

    if ($forms->isEmpty()) {
        return $wb; // trả template nguyên trạng
    }

    // 3) Mỗi phiếu = 1 bản sao sheet gốc NGAY TRONG workbook này
    foreach ($forms as $form) {
        // copy() an toàn hơn clone ở nhiều phiên bản
        $sheet = method_exists($base, 'copy') ? $base->copy() : clone $base;

        // Đặt tên duy nhất TRƯỚC khi add
        $title = $this->uniqueTitle($wb, $this->safeTitle($form->so_phieu ?: ('Phieu_'.$form->id)));
        $sheet->setTitle($title);

        // Thêm trực tiếp vào CÙNG workbook (không dùng addExternalSheet)
        $wb->addSheet($sheet);

        // Điền dữ liệu lên sheet vừa thêm
        $this->fillHeader($sheet, $form);
        $this->fillMembers($sheet, $form);
    }

    // 4) XÓA sheet gốc bằng chính object (không cần rename)
    $baseIdx = $wb->getIndex($base);          // tìm index hiện tại của sheet gốc
    if ($baseIdx >= 0 && $baseIdx < $wb->getSheetCount()) {
        $wb->removeSheetByIndex($baseIdx);
    }

    // 5) Set active sheet an toàn
    if ($wb->getSheetCount() > 0) {
        $wb->setActiveSheetIndex(0);
    }

    // Tối ưu nếu có công thức
    $wb->getCalculationEngine()->setCalculationCacheEnabled(true);

    return $wb;
}

private function uniqueTitle(Spreadsheet $wb, string $base): string
{
    $title = $base; $n = 1;
    while ($wb->getSheetByName($title) !== null) {
        $n++;
        $title = $this->safeTitle($base.' ('.$n.')');
    }
    return $title;
}

  

/** Cắt/ký tự cấm cho tên sheet, tối đa 31 ký tự */
private function safeTitle(string $raw): string
{
    $t = preg_replace('/[\\/:*?\\[\\]]/', '-', trim($raw));
    if ($t === '') $t = 'Sheet';
    return function_exists('mb_substr') ? mb_substr($t, 0, 31) : substr($t, 0, 31);
}

    /** An toàn tiêu đề sheet (<=31 ký tự, không chứa: : \ / ? * [ ]) */
 

    /* ===== Điền phần Header ===== */
    protected function fillHeader(Worksheet $sheet, SurveyForm $form): void
    {
        $map = config('survey_export.cells');
        $set = fn(string $addr, $val) => $sheet->setCellValue($addr, $val ?? '');

        
        // ... nếu còn 50+ trường khác, thêm $set(...) tại đây theo key trong config.
        //PHường xã
            $new_ward  = $map['new_ward'] ?? 'A1';
            $new_wardRange = $map['household_owner_range'] ?? $ownerCell;
            
            $set( $map['new_ward'], "Phường/Xã (1): ". $form->newWard?->name ?? '');
            $set( $map['village'], "KP/Thôn/Ấp (2): ". $form->village?->name ?? '');
            $set( $map['addressline'], "Địa chỉ (3): ". $form->headPerson?->addressline ?? '');
            $set( $map['so_phieu'], "Số phiếu (5): ". $form->so_phieu ?? '');
            $set( $map['dien_cu_tru'], "Diện cư trú (6): ". $form->headPerson?->type?->name ?? '');
            $set( $map['dienthoai'], "Điện thoại (7): ". $form->headPerson?->phone ?? '');
        // Họ và tên chủ hộ – I3:Z3
            $ownerCell  = $map['household_owner'] ?? 'I3';
            $ownerRange = $map['household_owner_range'] ?? $ownerCell;

            $set($ownerCell, "Họ và tên chủ hộ (4): ". $form->headPerson?->full_name ?? '');

            // Bảo đảm vùng đã merge (nếu template chưa merge sẵn)
            $this->ensureMerged($sheet, $ownerRange);

            // Canh giữa ngang + dọc
            $sheet->getStyle($ownerRange)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
    }
    protected function ensureMerged(Worksheet $sheet, string $range): void
    {
        $range = strtoupper($range);
        if (!str_contains($range, ':')) return; // chỉ 1 ô -> không merge

        foreach ($sheet->getMergeCells() as $merged) {
            if (strtoupper($merged) === $range) return; // đã merge rồi
        }
        $sheet->mergeCells($range); // merge nếu chưa merge
    }
    /* ===== Điền bảng thành viên + chèn dòng khi vượt quá ===== */
   protected function fillMembers(Worksheet $sheet, SurveyForm $form): void
{
    $cfg   = config('survey_export.blocks');
    $top   = (int) $cfg['top_row'];        // 9
    $H     = (int) $cfg['rows_per_block']; // 5
    $limit = (int) $cfg['max_blocks'];     // 6

    $F = $cfg['fields'];    // cột bên trái (5 hàng)
    $C = $cfg['columns'];   // cột ngang bên phải
    $YL= $cfg['year_label']; // cột năm học (I)

    $templateTop = $top; // dùng để clone style khi chèn block

    // Tính 3 năm theo công thức của bạn
    [$years, $matchYear] = $this->yearsForForm($form); // ['2021-2022','2023-2024','2025-2026'], '2023-2024'
    $yearOffsets = array_values($YL['rows'] ?? [0,1,2,3,4]); // vị trí hàng cho hiển thị nhãn năm (dư 2 hàng cũng OK)

    $i = 1; // STT
    foreach ($form->details as $d) {
        // Vượt số block mặc định -> chèn block mới
        if ($i > $limit) {
            $this->insertClonedBlock($sheet, $templateTop, $H, $top);
            $limit++;
        }

        $p = $d->person;

        // helper lấy ô theo định nghĩa + offset hàng trong block
        $cellAt = function(array $def, int $topRow, int $extraRowOffset = 0): string {
            $col = $def['col'] ?? 'A';
            $r0  = (int) ($def['row'] ?? 0);
            return $col . ($topRow + $r0 + $extraRowOffset);
        };
        $set = fn(string $addr, $val) => $sheet->setCellValue($addr, $val ?? '');
        $role = collect($p?->householdMemberships ?? [])
            ->firstWhere('household_id', $d->surveyForm?->household_id)
            ->role ?? null;
        // ===== BÊN TRÁI (5 hàng)
         if (isset($F['dob_label']))  $set($cellAt($F['dob_label'], $top), ($F['dob_label']['pre_text'] ?? ''));
         if (isset($F['female_label']))  $set($cellAt($F['female_label'], $top), ($F['female_label']['pre_text'] ?? ''));
        if (isset($F['stt']))          $set($cellAt($F['stt'], $top), $i);
        if (isset($F['rel_to_head']))  $set($cellAt($F['rel_to_head'], $top), ($F['rel_to_head']['pre_text'] ?? '').($role ?? ''));
        if (isset($F['ten']))          $set($cellAt($F['ten'], $top), ($F['ten']['pre_text'] ?? '').(($p?->ho_dem.' '.$p?->ten) ?? $p?->full_name ?? ''));
        if (isset($F['dob']))          $set($cellAt($F['dob'], $top), ($F['dob']['pre_text'] ?? '').($p?->dob_text ?? ''));
        if (isset($F['female']))       $set($cellAt($F['female'], $top), ($p?->is_female ? '☑' : '☐'));
        if (isset($F['ethnicity']))    $set($cellAt($F['ethnicity'], $top), ($F['ethnicity']['pre_text'] ?? '').($p?->ethnicity?->name ?? ''));
        if (isset($F['religion']))     $set($cellAt($F['religion'], $top), ($F['religion']['pre_text'] ?? '').($p?->religion?->name ?? ''));
        if (isset($F['contact_name'])) $set($cellAt($F['contact_name'], $top), ($F['contact_name']['pre_text'] ?? '').( $p?->contact_name ?? ''));


       $cellFemale = $cellAt($F['female'],$top);
            // set font + căn giữa cho đúng
            $sheet->getStyle($cellFemale)->getFont()->setName('Tahoma')->setSize(11);
            $sheet->getStyle($cellFemale)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        
        // ===== CỘT NĂM HỌC (I)
        // In 3 năm vào 3 hàng đầu tiên (theo yearOffsets). Hàng còn lại để trống.
        foreach ($years as $idx => $y) {
            if (!isset($yearOffsets[$idx])) break;
            $addr = ($YL['col'] ?? 'G') . ($top + (int)$yearOffsets[$idx]);

            $set($addr, $this->formatYearLabel($y));
        }
        // Xoá nhãn ở các hàng còn lại (nếu muốn sạch ô)
        for ($k = count($years); $k < count($yearOffsets); $k++) {
            $addr = ($YL['col'] ?? 'G') . ($top + (int)$yearOffsets[$k]);
            $set($addr, '');
        }

        // Hàng tương ứng với academic_year của phiếu
        $offset = 0;
        foreach ($years as $idx => $y) {
            if ($y === $matchYear) { $offset = (int)$yearOffsets[$idx]; break; }
        }

        // ===== CỘT NGANG (điền tại hàng khớp academic_year)
        $edu = $d->education; // dữ liệu học tập “đính” vào detail của năm đang khảo sát
       
        $putCol = function(string $key, $value) use ($sheet, $C, $cellAt, $top, $offset) {
            if (!isset($C[$key])) return;
            $addr = $cellAt($C[$key], $top, $offset);
            //  dd($offset,$addr,$value);
            $sheet->setCellValue($addr, $value ?? '');
        };
        // dd($edu,$edu?->vocational_grad_year);
        // Lưu ý: 'lop_dang_hoc' trùng cột I với nhãn năm -> sẽ GHI ĐÈ ở hàng khớp năm
        // dd($d->person);
        $putCol('lop_dang_hoc', $this->eduClassLabel($d));
        $putCol('truong_huyen', $this->schoolDistrictLabel($d));

        $putCol('graduation_level_name',  $edu?->graduationLevel?->name);
        $putCol('is_bo_tuc',              $edu?->is_bo_tuc ? '✓' : '');
        $putCol('graduation_year',        $edu?->graduation_year);
        $putCol('vocational_level_name',  $edu?->vocationalLevel?->name);
        $putCol('vocational_grad_year',   $edu?->vocational_grad_year);

        $putCol('hoc_xong_lop',           $edu?->hoc_xong_lop);
        $putCol('hoc_xong_nam',           $edu?->hoc_xong_nam);
        $putCol('bo_hoc_lop',             $edu?->bo_hoc_lop);
        $putCol('bo_hoc_nam',             $edu?->bo_hoc_nam);

        $putCol('lit_dang_hoc_lop',       $d->literacy?->dang_hoc_lop);
        $putCol('lit_hoan_thanh_lop',     $d->literacy?->hoan_thanh_lop);
        $putCol('lit_tai_mu_muc',         $d->literacy?->tai_mu_chu_muc);

        $putCol('khuyet_tat',             $this->disabilityLabel($d));
        $putCol('sc_name',                $d->specialCircumstance?->specialCircumstance?->name);
        $putCol('note',                   $d->person->note ?? '');

        // Sang block kế tiếp (5 hàng)
        $top += $H;
        $i++;
    }
}
/**
 * Chèn thêm 1 block (gồm $height hàng) trước vị trí $insertTop
 * và sao chép toàn bộ style/merge/validation từ block mẫu
 * bắt đầu ở $srcTop.
 */
protected function insertClonedBlock(Worksheet $sheet, int $srcTop, int $height, int $insertTop): void
{
    // 1) Chèn số hàng đúng bằng chiều cao block
    $sheet->insertNewRowBefore($insertTop, $height);

    // 2) Sao chép style + data validation + chiều cao từng hàng
    for ($off = 0; $off < $height; $off++) {
        $this->replicateRowFormat(
            $sheet,
            $srcTop   + $off,
            $insertTop + $off
        );
    }

    // 3) Sao chép các merge NẰM TRỌN trong block mẫu sang block mới
    $srcBottom = $srcTop + $height - 1;
    $rowOffset = $insertTop - $srcTop;

    foreach ($sheet->getMergeCells() as $range) {
        [$c1, $c2] = explode(':', $range);
        [$col1, $row1] = Coordinate::coordinateFromString($c1);
        [$col2, $row2] = Coordinate::coordinateFromString($c2);

        if ($row1 >= $srcTop && $row2 <= $srcBottom) {
            // tịnh tiến xuống vị trí block mới
            $dst1 = $col1 . ($row1 + $rowOffset);
            $dst2 = $col2 . ($row2 + $rowOffset);
            $dstRange = "{$dst1}:{$dst2}";

            // tránh merge trùng
            $exists = false;
            foreach ($sheet->getMergeCells() as $m) {
                if (strtoupper($m) === strtoupper($dstRange)) { $exists = true; break; }
            }
            if (!$exists) {
                $sheet->mergeCells($dstRange);
            }
        }
    }
}

/**
 * Sao chép style, data validation, chiều cao hàng từ $srcRow sang $dstRow.
 * (Dùng cho từng hàng trong block.)
 */

protected function yearsForForm(SurveyForm $form): array
{
    // base theo yêu cầu của bạn
    $base = \App\Models\SurveyForm::currentAcademicYear(); // ví dụ "2023-2024"
    [$y1, $y2] = array_map('intval', explode('-', $base)); // 2023, 2024

    $years = [($y1-2).'-'.($y1-1),($y1-1).'-'.$y1, $base, $y2.'-'.($y2+1),($y2+1).'-'.($y2+2),];
    // năm để so khớp hàng điền cột ngang:
    $match = $form->academic_year ?: $base;

    return [$years, $match];
}

protected function formatYearLabel(string $year, bool $short = true): string
{
    return $year;
    if (!$short) return $year;           // trả nguyên "2023-2024"
    if (preg_match('/^(\d{4})-(\d{4})$/', $year, $m)) {
        return substr($m[1], 2, 2).' - '.substr($m[2], 2, 2);
    }
    return $year;
}
    /** Tạo chuỗi đa dòng cho khối B…I */
    protected function buildHoTenBlock(?\App\Models\Person $p, \App\Models\SurveyFormDetail $d, SurveyForm $form): string
    {
        $role = $p?->roleInHousehold((int)$form->household_id) ?: '';
        $dob  = $p?->dob_text ?: '';
        $nu   = $p?->is_female ? '☑' : '☐';
        $dt   = $p?->ethnicity?->name ?: '';
        $tg   = $p?->religion?->name ?: '';
        $gh   = $d->contact->name ?? $p?->contact_name ?? '';

        $lines = array_filter([
            $p?->full_name ?? '',
            ($role ? "Chủ hộ/Quan hệ: {$role}" : ''),
            ($dob  ? "Ngày sinh: {$dob}" : ''),
            "Nữ: {$nu}    DT: {$dt}    TG: {$tg}",
            ($gh ? "Cha, mẹ, Người giám hộ: {$gh}" : ''),
        ]);

        return implode("\n", $lines);
    }

    /** Nhãn lớp/khối đang học (ô K) */
    protected function eduClassLabel(\App\Models\SurveyFormDetail $d): string
    {
        if ($e = $d->education) {
            return $e->class_label_with_marker ?? $e->class_label ?? '';
        }
        if ($lit = $d->literacy) {
            // fallback literacy
            return $lit->dang_hoc_lop ? "Đang học lớp {$lit->dang_hoc_lop}" : '';
        }
        return '';
    }

    /** Trường – Huyện (ô N) */
    protected function schoolDistrictLabel(\App\Models\SurveyFormDetail $d): string
    {
        $school  = $d->education?->school?->name ?? '';
        // dd($d->education?->school,$d->education?->school?->wardNew,$d->education?->school?->wardOld);
        $district= $d->education?->school?->wardNew?->name ?? $d->education?->school?->wardOld?->name ??''; // nếu có quan hệ
        if ($school && $district) return "{$school} – {$district}";
        return $school ?: $district;
    }

    /** Trả về mảng năm tốt nghiệp theo bậc: tieu_hoc, thcs, thpt, tc_nghe */
    protected function graduationYears(\App\Models\SurveyFormDetail $d): array
    {
        $out = [];
        $e = $d->education;
        if (!$e) return $out;

        $year = $e->graduation_year;
        $lvl  = $e->graduationLevel?->name ? mb_strtolower($e->graduationLevel->name) : '';

        // Bạn chỉnh map theo tên/bộ mã thực tế của bảng GraduationLevel
        if ($year && $lvl) {
            if (str_contains($lvl, 'tiểu học'))      $out['tieu_hoc'] = $year;
            elseif (str_contains($lvl, 'thcs'))      $out['thcs']     = $year;
            elseif (str_contains($lvl, 'thpt'))      $out['thpt']     = $year;
            elseif (str_contains($lvl, 'trung cấp') || str_contains($lvl, 'nghề')) $out['tc_nghe'] = $year;
        }

        return $out;
    }

    /** Nhãn biến động đơn giản (có thể tuỳ biến theo dữ liệu của bạn) */
    protected function movementLabel(?\App\Models\Person $p): string
    {
        // Ví dụ placeholder: nếu có new_province khác old_province → "Chuyển đến"
        if (!$p) return '';
        if ($p->new_province_id && $p->new_province_id !== $p->old_province_id) return 'Chuyển đến';
        return '';
    }


    protected function sexLabel(?bool $isFemale): string
    {
        return $isFemale === null ? '' : ($isFemale ? 'Nữ' : 'Nam');
    }

    protected function eduLabel(SurveyFormDetail $d): string
    {
        if ($edu = $d->education) {
            $class = $edu->class_label_with_marker ?? $edu->class_label ?? null;
            $block = $edu->block?->name ?? null;
            if ($class && $block) return "{$block} - {$class}";
            if ($class) return $class;
            if ($edu->finishedGrade?->name) return 'Hoàn thành: '.$edu->finishedGrade->name;
        }
        if ($lit = $d->literacy) {
            $dang = $lit->dang_hoc_lop ? "Đang học lớp {$lit->dang_hoc_lop}" : null;
            $xong = $lit->hoan_thanh_lop ? "Hoàn thành lớp {$lit->hoan_thanh_lop}" : null;
            return trim(implode(' - ', array_filter([$dang, $xong])));
        }
        return '';
    }


    protected function disabilityLabel(\App\Models\SurveyFormDetail $d): string
    {
        // Lấy mảng ID từ accessor trên SurveyFormDetail
        $ids = array_map('intval', $d->disability_ids ?? []);
        if (empty($ids)) return '';

        $nameMap = $this->getDisCatNames();

        // Giữ thứ tự theo ID trong dữ liệu, loại trùng
        $names = [];
        foreach ($ids as $id) {
            if (isset($nameMap[$id])) {
                $names[$id] = $nameMap[$id];
            }
        }

        // Chỉ trả về danh sách tên, bỏ Giấy CN / Khả năng học
        return implode(', ', array_values($names));
    }

     
    /** Sao chép style + merge + validation từ 1 hàng mẫu sang hàng mới vừa chèn */
    protected function replicateRowFormat(Worksheet $sheet, int $srcRow, int $dstRow): void
    {
        $highestCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        // 1) Copy style từng ô + data validation
        for ($colIndex = 1; $colIndex <= $highestCol; $colIndex++) {
            $col = Coordinate::stringFromColumnIndex($colIndex);
            $src = $col.$srcRow;
            $dst = $col.$dstRow;

            // style
            $sheet->duplicateStyle($sheet->getStyle($src), $dst);

            // data validation (nếu có)
            $dv = $sheet->getCell($src)->getDataValidation();
            if ($dv && $dv->getType() !== '') {
                $sheet->getCell($dst)->setDataValidation(clone $dv);
            }
        }

        // 2) Copy chiều cao hàng
        $sheet->getRowDimension($dstRow)->setRowHeight(
            $sheet->getRowDimension($srcRow)->getRowHeight()
        );

        // 3) Copy các merge theo hàng (chỉ merge 1 hàng như trong template)
        foreach ($sheet->getMergeCells() as $range) {
            [$c1, $c2] = explode(':', $range);
            [$col1, $row1] = Coordinate::coordinateFromString($c1);
            [$col2, $row2] = Coordinate::coordinateFromString($c2);
            if ((int)$row1 === $srcRow && (int)$row2 === $srcRow) {
                $sheet->mergeCells("{$col1}{$dstRow}:{$col2}{$dstRow}");
            }
        }
    }
 
}
