<?php

namespace App\Helpers;

class NumberToWordsHelper
{
    private static $ones = [
        '', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'
    ];

    private static $tens = [
        '', '', 'hai mươi', 'ba mươi', 'bốn mươi', 'năm mươi', 
        'sáu mươi', 'bảy mươi', 'tám mươi', 'chín mươi'
    ];

    private static $units = [
        '',
        'nghìn',
        'triệu',
        'tỷ',
        'nghìn tỷ',
        'triệu tỷ'
    ];

    /**
     * Chuyển đổi số thành chữ tiếng Việt
     * 
     * @param float|string $number Số cần chuyển đổi
     * @param string $unit Đơn vị tính (mặc định: 'đồng')
     * @return string Số đã chuyển thành chữ
     */
    public static function convert($number, $unit = 'đồng')
    {
        // Xử lý số âm
        $isNegative = false;
        if ($number < 0) {
            $isNegative = true;
            $number = abs($number);
        }

        // Tách phần nguyên và phần thập phân
        $parts = explode('.', (string)$number);
        $integerPart = $parts[0];
        $decimalPart = isset($parts[1]) ? $parts[1] : '';

        // Chuyển đổi phần nguyên
        $words = self::convertInteger($integerPart);

        // Thêm phần thập phân nếu có
        if (!empty($decimalPart) && intval($decimalPart) > 0) {
            // Loại bỏ các số 0 ở cuối
            $decimalPart = rtrim($decimalPart, '0');
            if (!empty($decimalPart)) {
                $words .= ' phẩy ' . self::convertDecimal($decimalPart);
            }
        }

        // Thêm đơn vị
        if (!empty($words)) {
            $words .= ' ' . $unit;
        } else {
            $words = 'không ' . $unit;
        }

        // Thêm từ "âm" nếu là số âm
        if ($isNegative) {
            $words = 'âm ' . $words;
        }

        // Viết hoa chữ cái đầu
        return ucfirst(trim($words));
    }

    /**
     * Chuyển đổi phần nguyên
     */
    private static function convertInteger($number)
    {
        if ($number == 0) {
            return '';
        }

        $number = ltrim($number, '0');
        if (empty($number)) {
            return '';
        }

        // Chia số thành các nhóm 3 chữ số
        $groups = [];
        $length = strlen($number);
        
        for ($i = $length; $i > 0; $i -= 3) {
            $start = max(0, $i - 3);
            $groupLength = $i - $start;
            $groups[] = substr($number, $start, $groupLength);
        }
        
        $groups = array_reverse($groups);
        
        // Chuyển đổi từng nhóm
        $result = [];
        $groupCount = count($groups);
        
        for ($i = 0; $i < $groupCount; $i++) {
            $groupValue = intval($groups[$i]);
            if ($groupValue > 0) {
                $groupWords = self::convertGroup($groupValue);
                $unitIndex = $groupCount - $i - 1;
                
                if ($unitIndex > 0 && isset(self::$units[$unitIndex])) {
                    $groupWords .= ' ' . self::$units[$unitIndex];
                }
                
                $result[] = $groupWords;
            } else {
                // Bỏ qua nhóm có giá trị 0, trừ khi là nhóm cuối cùng
                if ($i < $groupCount - 1) {
                    // Không thêm gì
                }
            }
        }
        
        return implode(' ', $result);
    }

    /**
     * Chuyển đổi một nhóm 3 chữ số
     */
    private static function convertGroup($number)
    {
        $result = '';
        
        // Hàng trăm
        $hundreds = floor($number / 100);
        if ($hundreds > 0) {
            $result .= self::$ones[$hundreds] . ' trăm';
            $number %= 100;
            
            if ($number > 0 && $number < 10) {
                $result .= ' lẻ';
            }
        }
        
        // Hàng chục
        $tens = floor($number / 10);
        $ones = $number % 10;
        
        if ($tens > 1) {
            if (!empty($result)) {
                $result .= ' ';
            }
            $result .= self::$tens[$tens];
            
            if ($ones == 1) {
                $result .= ' mốt';
            } elseif ($ones == 5 && $tens > 0) {
                $result .= ' lăm';
            } elseif ($ones > 0) {
                $result .= ' ' . self::$ones[$ones];
            }
        } elseif ($tens == 1) {
            if (!empty($result)) {
                $result .= ' ';
            }
            $result .= 'mười';
            
            if ($ones == 5) {
                $result .= ' lăm';
            } elseif ($ones > 0) {
                $result .= ' ' . self::$ones[$ones];
            }
        } else {
            if ($ones > 0) {
                if (!empty($result)) {
                    $result .= ' ';
                }
                $result .= self::$ones[$ones];
            }
        }
        
        return $result;
    }

    /**
     * Chuyển đổi phần thập phân
     */
    private static function convertDecimal($decimal)
    {
        $result = [];
        for ($i = 0; $i < strlen($decimal); $i++) {
            $digit = intval($decimal[$i]);
            if ($digit == 0) {
                $result[] = 'không';
            } else {
                $result[] = self::$ones[$digit];
            }
        }
        return implode(' ', $result);
    }

    /**
     * Chuyển đổi số tiền (triệu đồng) thành chữ
     * Ví dụ: 450 Tr.Đồng -> "bốn trăm năm mươi triệu đồng"
     */
    public static function convertCurrency($number, $inMillions = true)
    {
        if ($inMillions) {
            // Nếu số đang ở đơn vị triệu, nhân với 1 triệu
            $actualNumber = $number * 1000000;
            return self::convert($actualNumber, 'đồng');
        }
        
        return self::convert($number, 'đồng');
    }
}

