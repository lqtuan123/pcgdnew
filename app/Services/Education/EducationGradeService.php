<?php
// app/Services/Education/EducationGradeService.php

namespace App\Services\Education;

use App\Models\EducationGrade;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EducationGradeService
{
    /** @var Collection<int, array{id:int,code:string,name:string,norm:string}> */
    protected Collection $grades;

    /** map synonym -> code theo seeder */
    protected array $synonyms = [
        // MN3
        'mn3'        => 'MN3',
        'mam 3'      => 'MN3',
        'mam 3 tuoi' => 'MN3',
        '3-4 tuoi'   => 'MN3',
        '3 tuoi'     => 'MN3',
        'mam'        => 'MN3', // mơ hồ => coi là MN3

        // MN4 (Chồi / 4-5 tuổi)
        'mn4'        => 'MN4',
        'choi'       => 'MN4',
        '4-5 tuoi'   => 'MN4',
        '4 tuoi'     => 'MN4',

        // MN5 (Lá / 5-6 tuổi)
        'mn5'        => 'MN5',
        'la'         => 'MN5',
        '5-6 tuoi'   => 'MN5',
        '5 tuoi'     => 'MN5',
    ];

    public function __construct()
    {
        // cache toàn bộ grade để match nhanh
        $this->grades = EducationGrade::query()
            ->select('id', 'code', 'name')
            ->get()
            ->map(fn ($g) => [
                'id'   => (int) $g->id,
                'code' => (string) $g->code,                 // ví dụ: MN3, 1..12
                'name' => (string) $g->name,                 // ví dụ: Lớp 1, Mầm 5 tuổi
                'norm' => $this->norm($g->code.' '.$g->name) // cho tìm kiếm gần đúng
            ]);
    }

    /** Chuẩn hoá: hạ chữ + bỏ dấu + gọn khoảng trắng */
    protected function norm(string $s): string
    {
        $s = Str::lower($s);
        $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
        $s = preg_replace('/\p{Mn}+/u', '', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }

    /** Trả về ID grade hoặc null nếu không khớp */
    public function resolveId($hocXongLop, ?string $gradeNameHint = null): ?int
    {
        // 1) Nếu truyền thẳng ID hợp lệ
        if (filled($hocXongLop) && ctype_digit((string)$hocXongLop)) {
            $id = (int) $hocXongLop;
            if ($this->grades->firstWhere('id', $id)) return $id;
        }

        // 2) Ưu tiên code theo seeder: '1'..'12' hoặc 'MN3|MN4|MN5'
        $text = trim((string)($hocXongLop ?? ''));
        $hint = trim((string)($gradeNameHint ?? ''));
        $candidate = $text !== '' ? $text : $hint;

        if ($candidate === '') return null;

        $norm = $this->norm($candidate);

        // 2a) số lớp 1..12 -> code = số
        if (preg_match('/\b(1[0-2]|[1-9])\b/u', $norm, $m)) {
            $code = (string) ((int)$m[1]); // '1'..'12'
            $g = $this->grades->first(fn($x) => $x['code'] === $code);
            if ($g) return $g['id'];
        }

        // 2b) code MN3|MN4|MN5 xuất hiện trực tiếp
        if (preg_match('/\bmn[345]\b/u', $norm, $m)) {
            $code = Str::upper($m[0]); // 'MN3'...
            $g = $this->grades->first(fn($x) => $x['code'] === $code);
            if ($g) return $g['id'];
        }

        // 3) Ánh xạ từ synonyms (Mầm/Chồi/Lá, 4-5 tuổi, 5-6 tuổi...)
        foreach ($this->synonyms as $key => $code) {
            if (str_contains($norm, $key)) {
                $g = $this->grades->first(fn($x) => $x['code'] === $code);
                if ($g) return $g['id'];
            }
        }

        // 4) Thử match theo tên/chuẩn hoá
        $g = $this->grades->first(fn($x) => $x['norm'] === $norm)
          ?? $this->grades->first(fn($x) => str_contains($x['norm'], $norm));

        return $g['id'] ?? null;
    }
}
