<?php

namespace App\Services\Catalogs;

use App\Models\Ethnicity;
use Illuminate\Support\Str;

class EthnicityService
{
    protected function norm(?string $s): string
    {
        return Str::lower(Str::ascii(trim($s ?? '')));
    }

    public function resolveIdByName(?string $raw): ?int
    {
        $n = $this->norm($raw);
        if ($n === '' || in_array($n, ['khong', 'none', 'null', 'na'])) return null;

        // Ưu tiên code khớp y nguyên
        if (preg_match('/^[A-Z0-9\-_]+$/i', (string)$raw)) {
            $found = Ethnicity::where('code', trim($raw))->first();
            if ($found) return $found->id;
        }

        // So theo slug hoặc tên không dấu
        $found = Ethnicity::query()
            ->whereRaw('LOWER(slug) = ?', [$n])
            ->orWhereRaw('LOWER(name) = ?', [$n])
             ->orWhereRaw('LOWER(name) = ?', strtolower($raw) )
            ->first();
        // dd([$n],$found);
        if ($found) return $found->id;

        // Không có thì tạo
        $created = Ethnicity::create([
            'name' => trim($raw),
            // slug tự sinh ở model
        ]);

        return $created->id;
    }
}
