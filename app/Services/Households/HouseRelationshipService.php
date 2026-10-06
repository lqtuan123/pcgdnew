<?php
// app/Services/Households/HouseRelationshipService.php

namespace App\Services\Households;

use App\Models\HouseRelationship;
use Illuminate\Support\Str;

class HouseRelationshipService
{
    protected array $cache = [];

    public function canonicalize(?string $raw): string
    {
        $raw = (string) $raw;
        $s   = Str::lower(Str::ascii(trim($raw)));
        if ($s === '') return 'Thành viên';

        $has = fn ($regex) => preg_match($regex.'u', $s);

        if ($has('/\b(anh|chi|em|ruot)\b/')) return 'Anh/Chị/Em';
        if ($has('/\bcon\b/'))               return 'Con';
        if ($has('/\b(cha|bo)\b/'))          return 'Cha';
        if ($has('/\bme\b/'))                return 'Mẹ';
        if ($has('/\b(ong|ba)\b/'))          return 'Ông/Bà';

        // ⚠️ thay str_contains bằng Str::contains (có thể nhận mảng)
        if (Str::contains($s, ['chu ho','chuho','chu-ho']))  return 'Chủ hộ';
        if (Str::contains($s, ['vo','chong','vo/chong','vo chong','vo-chong'])) return 'Vợ/Chồng';
        if (Str::contains($s, ['cha me','cha_me','cha hoac me','phu huynh']))   return 'Cha/Mẹ';

        return ucfirst(trim($raw));
    }

   
    public function getOrCreate(string $raw): HouseRelationship
    {
        $name = $this->canonicalize($raw);
        return $this->cache[$name] ??= HouseRelationship::firstOrCreate(['name' => $name]);
    }
}
