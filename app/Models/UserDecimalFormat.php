<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDecimalFormat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'decimal_format_id'
    ];

    /**
     * Relationship với User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship với DecimalFormat
     */
    public function decimalFormat()
    {
        return $this->belongsTo(DecimalFormat::class);
    }

    /**
     * Lấy cấu hình thập phân của user
     */
    public static function getUserFormat($userId)
    {
        return static::where('user_id', $userId)
            ->with('decimalFormat')
            ->first();
    }

    /**
     * Cập nhật hoặc tạo cấu hình cho user
     */
    public static function updateUserFormat($userId, $decimalFormatId)
    {
        return static::updateOrCreate(
            ['user_id' => $userId],
            ['decimal_format_id' => $decimalFormatId]
        );
    }
}
