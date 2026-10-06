<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LevelTransferLock extends Model
{
    protected $table = 'level_transfer_locks';

    protected $fillable = [
        'scope_type',
        'scope_id',
        'academic_year',
        'lock_type',
        'locked_by',
        'locked_at',
    ];

    /**
     * Relationship với User model
     */
    public function lockedByUser()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}


