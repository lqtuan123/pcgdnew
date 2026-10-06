<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_group_id',
        'report_detail_id',
        'submitted_by_user_id',
        'school_id',
        'ward_id',
        'academic_year',
        'deadline',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'stage',
        'status',
        'note',
        'feedback',
        'reviewed_by_user_id',
        'reviewed_at',
        'feedback_at',
        'forwarded_at',
    ];

    protected $casts = [
        'deadline' => 'date',
        'reviewed_at' => 'datetime',
        'feedback_at' => 'datetime',
        'forwarded_at' => 'datetime',
    ];

    public function group(): BelongsTo { return $this->belongsTo(ReportGroup::class, 'report_group_id'); }
    public function detail(): BelongsTo { return $this->belongsTo(ReportDetail::class, 'report_detail_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by_user_id'); }
    public function school(): BelongsTo { return $this->belongsTo(School::class, 'school_id'); }
    public function ward(): BelongsTo { return $this->belongsTo(GeoUnit::class, 'ward_id'); }
    public function province(): BelongsTo { return $this->belongsTo(GeoUnit::class, 'province_id'); }

    // Scopes
    public function scopeAtStage($query, string $stage) { return $query->where('stage', $stage); }
    public function scopeStatus($query, string $status) { return $query->where('status', $status); }
}


