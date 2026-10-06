<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonDisability extends Model
{
    protected $fillable = [
        'person_id',
        'disability_ids',   // JSON array các ID category
        'has_certificate',
        'can_learn',
        'note',
        'is_deleted',
    ];

    protected $casts = [
        'disability_ids' => 'array',
        'has_certificate'=> 'boolean',
        'can_learn'      => 'boolean',
        'is_deleted'     => 'boolean',
    ];

    /* Quan hệ */
    public function person()
    {
        return $this->belongsTo(Person::class);
    }
    public function getCategoryNamesAttribute(): array
    {
        $ids = $this->disability_ids ?? [];
        if (!$ids) return [];
        return DisabilityCategory::whereIn('id', $ids)->pluck('name')->all();
    }
    /* Helpers cho mảng khuyết tật */
    public function setDisabilities(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $this->disability_ids = $ids;
    }

    public function addDisabilities(array $ids): void
    {
        $current = $this->disability_ids ?? [];
        $merged  = array_values(array_unique(array_map('intval', array_merge($current, $ids))));
        $this->disability_ids = $merged;
    }

    public function removeDisability(int $categoryId): void
    {
        $this->disability_ids = array_values(array_filter(
            $this->disability_ids ?? [],
            fn($v) => (int)$v !== $categoryId
        ));
    }

    public function hasDisability(int $categoryId): bool
    {
        return in_array($categoryId, $this->disability_ids ?? [], true);
    }

    /* Scopes */
    public function scopeHasCategory($q, int $categoryId)
    {
        return $q->whereRaw('JSON_CONTAINS(disability_ids, ?)', [json_encode($categoryId)]);
    }

    public function scopeNotDeleted($q)
    {
        return $q->where('is_deleted', false);
    }
}
