<?php

namespace App\Services\Catalogs;

use App\Models\Religion;
use Illuminate\Support\Str;

class ReligionService
{
    protected function norm(?string $s): string
    {
        return Str::lower(Str::ascii(trim($s ?? '')));
    }

    public function resolveIdByName(?string $raw): ?int
    {
        $n = $this->norm($raw);
        if ($n === '' || in_array($n, ['khong', 'none', 'null', 'na'])) return null;

        if (preg_match('/^[A-Z0-9\-_]+$/i', (string)$raw)) {
            $found = Religion::where('code', trim($raw))->first();
            if ($found) return $found->id;
        }

        $found = Religion::query()
            ->whereRaw('LOWER(slug) = ?', [$n])
            ->orWhereRaw('LOWER(name) = ?', [$n])
             ->orWhereRaw('LOWER(name) = ?', strtolower($raw) )
            ->first();

        if ($found) return $found->id;

        $created = Religion::create([
            'name' => trim($raw),
            // slug tự sinh ở model
        ]);

        return $created->id;
    }
}
