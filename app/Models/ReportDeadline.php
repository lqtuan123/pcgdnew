<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportDeadline extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_group_id',
        'report_detail_id',
        'set_by_user_id',
        'target_ward_id',
        'target_school_id',
        'deadline',
        'academic_year',
        'note',
        'is_active',
    ];

    protected $casts = [
        'deadline' => 'date',
        'is_active' => 'boolean',
    ];

    public function ward(): BelongsTo
    {
        return $this->belongsTo(GeoUnit::class, 'target_ward_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'target_school_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ReportGroup::class, 'report_group_id');
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(ReportDetail::class, 'report_detail_id');
    }

    public static function findEffectiveDeadline(?int $groupId, ?int $detailId, ?int $schoolId, ?int $wardId)
    {
        // Priority: school-specific > ward-specific
        if ($schoolId) {
            $schoolDeadline = static::query()
                ->where('is_active', true)
                ->where('target_school_id', $schoolId)
                ->where('report_group_id', $groupId)
                ->when($detailId, fn($q) => $q->where('report_detail_id', $detailId))
                ->orderByDesc('report_detail_id')
                ->orderByDesc('id')
                ->first();
            if ($schoolDeadline) {
                return $schoolDeadline;
            }
        }

        if ($wardId) {
            $wardDeadline = static::query()
                ->where('is_active', true)
                ->where('target_ward_id', $wardId)
                ->where('report_group_id', $groupId)
                ->when($detailId, fn($q) => $q->where('report_detail_id', $detailId))
                ->orderByDesc('report_detail_id')
                ->orderByDesc('id')
                ->first();
            if ($wardDeadline) {
                return $wardDeadline;
            }
        }

        return null;
    }
}


