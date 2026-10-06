<?php
// app/Services/People/PersonDisabilityService.php

namespace App\Services\People;

use App\Models\DisabilityCategory;
use App\Models\Person;
use App\Models\PersonDisability;

class PersonDisabilityService
{
    /** Map từ key trong file excel → code & tên danh mục */
    private const MAP = [
        'dis_VAN_DONG'   => ['code' => 'VAN_DONG',  'name' => 'Khuyết tật vận động'],
        'dis_NGHE_NOI'   => ['code' => 'NGHE_NOI',  'name' => 'Khuyết tật nghe, nói'],
        'dis_NHIN'       => ['code' => 'NHIN',      'name' => 'Khuyết tật tật nhìn'],
        'dis_THAN_KINH'  => ['code' => 'THAN_KINH', 'name' => 'Khuyết tật thần kinh, tâm thần'],
        'dis_TRI_TUE'    => ['code' => 'TRI_TUE',   'name' => 'Khuyết tật trí tuệ'],
        'dis_HOC_TAP'    => ['code' => 'HOC_TAP',   'name' => 'Khuyết tật học tập'],
        'dis_TU_KY'      => ['code' => 'TU_KY',     'name' => 'Tự kỷ'],
        'dis_KHAC'       => ['code' => 'KHAC',      'name' => 'Khuyết tật khác'],
    ];

    /** cache code→id để hạn chế query */
    private array $catIdCache = [];

    private function toBool($v): bool
    {
        $s = mb_strtolower(trim((string)($v ?? '')));
        if ($s === '') return false;
        if (str_contains($s, '✓') || str_contains($s, '✔')) return true;

        $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
        $s = preg_replace('/\p{Mn}/u', '', $s); // bỏ dấu: "có"->"co"
        return in_array($s, ['1','x','true','y','yes','co'], true);
    }

    /** Lấy ID theo code, tự tạo nếu thiếu */
    private function categoryId(string $code, string $name): int
    {
        if (isset($this->catIdCache[$code])) return $this->catIdCache[$code];

        $cat = DisabilityCategory::firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'short_name' => $code, 'sort_order' => 0, 'is_active' => true]
        );
        return $this->catIdCache[$code] = $cat->id;
    }

    /** Rút trích từ 1 dòng excel → [ids[], hasCert, canLearn] */
    public function extractFromRow(array $row): array
    {
        $ids = [];
        foreach (self::MAP as $key => $meta) {
            if ($this->toBool($row[$key] ?? false)) {
                $ids[] = $this->categoryId($meta['code'], $meta['name']);
            }
        }
        sort($ids);

        $hasCert = $this->toBool($row['dis_has_certificate'] ?? false);
        $canLearn= $this->toBool($row['dis_can_learn'] ?? false);

        return [$ids, $hasCert, $canLearn];
    }

    /**
     * Upsert bản ghi khuyết tật cho 1 người.
     * Idempotent: không lưu nếu không có thay đổi.
     */
    public function upsertForPerson(Person $person, array $row): ?PersonDisability
    {
        [$ids, $hasCert, $canLearn] = $this->extractFromRow($row);

        // Không có gì cả → nếu chưa có record thì thôi; nếu có mà rỗng thì không làm gì thêm
        if (empty($ids) && $hasCert === false && $canLearn === false) {
            return PersonDisability::where('person_id', $person->id)->first();
        }

        $rec = PersonDisability::firstOrNew(['person_id' => $person->id]);

        // so sánh để tránh save thừa
        $same =
            $rec->has_certificate === $hasCert &&
            $rec->can_learn      === $canLearn  &&
            is_array($rec->disability_ids ?? []) &&
            (array_values(array_unique($rec->disability_ids)) === $ids);

        if ($same) return $rec;

        $rec->disability_ids = $ids;
        $rec->has_certificate = $hasCert;
        $rec->can_learn = $canLearn;
        $rec->is_deleted = false;
        $rec->save();

        return $rec;
    }
}
