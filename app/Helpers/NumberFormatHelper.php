<?php

namespace App\Helpers;

use App\Models\DecimalFormat;

class NumberFormatHelper
{
    /**
     * Format số theo cấu hình hiển thị thập phân hiện tại
     */
    public static function format($number, $decimals = 2)
    {
        // Lấy cấu hình của user hiện tại
        if (auth()->check()) {
            $userFormat = auth()->user()->getDecimalFormat();
            if ($userFormat) {
                return $userFormat->formatNumber($number, $decimals);
            }
        }
        
        // Fallback về format mặc định nếu không có cấu hình
        return number_format($number, $decimals, ',', '.');
    }

    /**
     * Format số phần trăm
     */
    public static function formatPercent($number, $decimals = 2)
    {
        return self::format($number, $decimals) . '%';
    }

    /**
     * Format tiền tệ
     */
    public static function formatCurrency($number, $currency = 'VND', $decimals = 0)
    {
        return self::format($number, $decimals) . ' ' . $currency;
    }

    /**
     * Lấy cấu hình hiển thị thập phân hiện tại
     */
    public static function getCurrentFormat()
    {
        if (auth()->check()) {
            return auth()->user()->getDecimalFormat();
        }
        return DecimalFormat::getDefaultFormat();
    }

    /**
     * Kiểm tra xem có cấu hình nào đang được sử dụng không
     */
    public static function hasActiveFormat()
    {
        if (auth()->check()) {
            return auth()->user()->getDecimalFormat() !== null;
        }
        return DecimalFormat::getDefaultFormat() !== null;
    }
}
