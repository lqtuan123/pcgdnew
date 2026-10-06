<?php

namespace App\Services\Catalogs;

use App\Models\PriorityCategory;
use Illuminate\Support\Str;

class PriorityCategoryService
{
    protected function norm(?string $s): string
    {
        return Str::lower(Str::ascii(trim($s ?? '')));
    }

    public function resolveIdByName(?string $raw): ?int
    {
        $n = $this->norm($raw);
        if ($n === '' || in_array($n, ['khong', 'none', 'null', 'na'])) return null;

        $found = PriorityCategory::whereRaw('LOWER(name) = ?', [$n])
         ->orWhereRaw('LOWER(name) = ?', strtolower($raw) )
        ->first();
        if ($found) return $found->id;

        return PriorityCategory::create(['name' => trim($raw)])->id;
    }
}
