<?php
// app/Models/SurveyLock.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyLock extends Model
{
    protected $fillable = [
        'scope','geo_level','geo_id','is_active','locked_until','note','locked_by'
    ];

    public const SCOPE_SURVEY     = 'SURVEY';      // CRUD phiếu điều tra
    public const SCOPE_STATS_SEND = 'STATS_SEND';  // gửi thống kê
    public const SCOPE_STATS_INPUT= 'STATS_INPUT'; // nhập liệu thống kê
     public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'locked_by');
    }
}
