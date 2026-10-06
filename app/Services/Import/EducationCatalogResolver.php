<?php

namespace App\Services\Import;

use App\Models\EducationBlock;
use App\Models\EducationGrade;
use App\Models\GraduationLevel;
use App\Models\VocationalLevel;
use App\Models\School;
use Illuminate\Support\Str;

class EducationCatalogResolver
{
    protected array $blocksByKey = [];
    protected array $gradesByCode = [];
    protected array $gradesByName = [];
    protected array $gradLevelsByName = [];
    protected array $vocLevelsByName = [];

    public function __construct()
    {
        $norm = fn(?string $s) => $s ? Str::of($s)->lower()->ascii()->squish()->value() : null;

        foreach (EducationBlock::all(['id','name']) as $b) {
            $this->blocksByKey[$norm($b->name)] = $b->id; // block hiện dùng name
        }
        $this->gradesByCode = EducationGrade::pluck('id','code')->all();
        $this->gradesByName = EducationGrade::pluck('id','name')->all();

        foreach (GraduationLevel::all(['id','name']) as $g) {
            $this->gradLevelsByName[$norm($g->name)] = $g->id;
        }
        foreach (VocationalLevel::all(['id','name']) as $v) {
            $this->vocLevelsByName[$norm($v->name)] = $v->id;
        }
    }

    protected function norm(?string $s): ?string
    {
        return $s ? Str::of($s)->lower()->ascii()->squish()->value() : null;
    }

    public function blockId(?string $codeOrName): ?int
    {
        $k = $this->norm($codeOrName);
        
        return $k && isset($this->blocksByKey[$k]) ? $this->blocksByKey[$k] : null;
    }

    public function gradeId(?string $grade): ?int
    {
        if (!$grade) return null;
        if (isset($this->gradesByName[$grade])) return $this->gradesByName[$grade];
        if (isset($this->gradesByCode[$grade])) return $this->gradesByCode[$grade];

        // "Lớp 10" -> "10"
        if (preg_match('/\d+/', $grade, $m)) {
            $n = $m[0];
            if (isset($this->gradesByCode[$n])) return $this->gradesByCode[$n];
            if (isset($this->gradesByName[$n])) return $this->gradesByName[$n];
        }
        return null;
    }

    public function graduationLevelId(?string $name): ?int
    {
        $k = $this->norm($name);
        return $k && isset($this->gradLevelsByName[$k]) ? $this->gradLevelsByName[$k] : null;
    }

    public function vocationalLevelId(?string $name): ?int
    {
        $k = $this->norm($name);
        return $k && isset($this->vocLevelsByName[$k]) ? $this->vocLevelsByName[$k] : null;
    }

    public function resolveSchool(?string $code, ?string $name): ?School
    {
        if ($code) {
            $s = School::active()->where('code',$code)->with('districtUnit')->first();
            if ($s) return $s;
        }
        if ($name) {
            return School::active()->where('name',$name)->with('districtUnit')->first();
        }
        return null;
    }
    // app/Services/People/PersonService.php (thêm vào class)

     function normalize(string $s): string
    {
        // hạ chữ, bỏ dấu tiếng Việt, gom khoảng trắng
        $s = mb_strtolower($s, 'UTF-8');
        $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
        $s = preg_replace('/\p{Mn}+/u', '', $s); // bỏ dấu
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }

    /**
     * Parse education_grade_name để lấy tên lớp sạch + cờ.
     * - '*' => hai_buoi OR 9-10 buoi/tuan (đặt cả 2 = true)
     * - '!' => repeat
     * Đồng thời dò thêm từ khóa '2 buoi', '9-10 buoi/tuan' nếu người nhập gõ rõ ra.
     */
     function parseEducationGradeName(?string $raw): array
    {
        $raw = (string)($raw ?? '');

        // bắt cờ qua ký hiệu
        $hasStar     = preg_match('/[\*\✱✳️⭐]/u', $raw) === 1;
        $hasBang     = str_contains($raw, '!');
        $cleanGrade  = trim(preg_replace('/[\*\✱✳️⭐!]/u', '', $raw)); // bỏ * và !

        // dò từ khoá (không dấu)
        $n = $this->normalize($raw);

        $twoShift = $hasStar
            || str_contains($n, '2 buoi')
            || str_contains($n, 'hai buoi')
            || preg_match('/\b2-?buoi\b/u', $n);

        $nineTen = $hasStar
            || str_contains($n, '9-10 buoi')
            || str_contains($n, '9 10 buoi')
            || preg_match('/9[\-\– ]?10/u', $n);

        $repeat = $hasBang
            || str_contains($n, 'luu ban')
            || str_contains($n, 'nhac lop');

        return [
            'grade_clean'             => $cleanGrade,   // tên lớp đã bỏ dấu hiệu
            'hoc_hai_buoi'            => (bool)$twoShift,
            'hoc_9_10_buoi_tuan'      => (bool)$nineTen,
            'is_repeat_grade'         => (bool)$repeat,
            'has_star_marker'         => $hasStar,      // nếu cần debug thêm
            'has_repeat_marker'       => $hasBang,
        ];
    }

}
