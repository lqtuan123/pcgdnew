<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DecimalFormat extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'decimal_separator',
        'thousands_separator',
        'description'
    ];

    /**
     * Relationship với UserDecimalFormat
     */
    public function userDecimalFormats()
    {
        return $this->hasMany(UserDecimalFormat::class);
    }

    /**
     * Format số theo cấu hình hiện tại
     */
    public function formatNumber($number, $decimals = 2)
    {
        return number_format($number, $decimals, $this->decimal_separator, $this->thousands_separator);
    }

    /**
     * Tạo cấu hình mặc định
     */
    public static function createDefaultFormats()
    {
        // Cấu hình 1: Dấu tách thập phân: , Dấu tách hàng nghìn: .
        static::firstOrCreate([
            'name' => 'Cấu hình Việt Nam',
            'decimal_separator' => ',',
            'thousands_separator' => '.',
        ], [
            'description' => 'Dấu tách thập phân: , Dấu tách hàng nghìn: .'
        ]);

        // Cấu hình 2: Dấu tách thập phân: . Dấu tách hàng nghìn: ,
        static::firstOrCreate([
            'name' => 'Cấu hình Quốc tế',
            'decimal_separator' => '.',
            'thousands_separator' => ',',
        ], [
            'description' => 'Dấu tách thập phân: . Dấu tách hàng nghìn: ,'
        ]);
    }

    /**
     * Lấy cấu hình mặc định (Việt Nam)
     */
    public static function getDefaultFormat()
    {
        return static::where('name', 'Cấu hình Việt Nam')->first();
    }
     public static function getActiveFormat()
    {
        return static::where('name', 'Cấu hình Việt Nam')->first();
    }
}
