<?php

namespace App\Helpers;

use App\Models\Logo;

class LogoHelper
{
    /**
     * Lấy logo hiện tại
     */
    public static function getActiveLogo()
    {
        $logo = Logo::where('type', 'logo')
            ->where('is_active', true)
            ->with(['sizes' => function ($query) {
                $query->where('is_active', true);
            }])
            ->first();

        if (!$logo || !$logo->sizes->count()) {
            return null;
        }

        $activeSize = $logo->sizes->first();
        return [
            'url' => self::getLogoUrl($activeSize->path),
            'width' => $activeSize->width,
            'height' => $activeSize->height,
            'name' => $logo->name,
            'type' => $logo->type,
        ];
    }

    /**
     * Lấy favicon hiện tại
     */
    public static function getActiveFavicon()
    {
        $favicon = Logo::where('type', 'favicon')
            ->where('is_active', true)
            ->with(['sizes' => function ($query) {
                $query->where('is_active', true);
            }])
            ->first();

        if (!$favicon || !$favicon->sizes->count()) {
            return null;
        }

        $activeSize = $favicon->sizes->first();
        return [
            'url' => self::getLogoUrl($activeSize->path),
            'width' => $activeSize->width,
            'height' => $activeSize->height,
            'name' => $favicon->name,
            'type' => $favicon->type,
        ];
    }

    /**
     * Lấy email logo hiện tại
     */
    public static function getActiveEmailLogo()
    {
        $emailLogo = Logo::where('type', 'email')
            ->where('is_active', true)
            ->with(['sizes' => function ($query) {
                $query->where('is_active', true);
            }])
            ->first();

        if (!$emailLogo || !$emailLogo->sizes->count()) {
            return null;
        }

        $activeSize = $emailLogo->sizes->first();
        return [
            'url' => self::getLogoUrl($activeSize->path),
            'width' => $activeSize->width,
            'height' => $activeSize->height,
            'name' => $emailLogo->name,
            'type' => $emailLogo->type,
        ];
    }

    /**
     * Lấy tất cả kích thước của logo hiện tại
     */
    public static function getActiveLogoAllSizes()
    {
        $logo = Logo::where('type', 'logo')
            ->where('is_active', true)
            ->with('sizes')
            ->first();

        if (!$logo) {
            return [];
        }

        return $logo->sizes->map(function ($size) {
            return [
                'url' => self::getLogoUrl($size->path),
                'width' => $size->width,
                'height' => $size->height,
                'is_active' => $size->is_active,
            ];
        })->toArray();
    }

    /**
     * Lấy tất cả kích thước của favicon hiện tại
     */
    public static function getActiveFaviconAllSizes()
    {
        $favicon = Logo::where('type', 'favicon')
            ->where('is_active', true)
            ->with('sizes')
            ->first();

        if (!$favicon) {
            return [];
        }

        return $favicon->sizes->map(function ($size) {
            return [
                'url' => self::getLogoUrl($size->path),
                'width' => $size->width,
                'height' => $size->height,
                'is_active' => $size->is_active,
            ];
        })->toArray();
    }

    /**
     * Lấy logo theo kích thước cụ thể
     */
    public static function getLogoBySize($type = 'logo', $width = null, $height = null)
    {
        $logo = Logo::where('type', $type)
            ->where('is_active', true)
            ->with('sizes')
            ->first();

        if (!$logo) {
            return null;
        }

        $query = $logo->sizes();
        
        if ($width && $height) {
            $query->where('width', $width)->where('height', $height);
        } elseif ($width) {
            $query->where('width', $width);
        } elseif ($height) {
            $query->where('height', $height);
        } else {
            $query->where('is_active', true);
        }

        $size = $query->first();
        
        if (!$size) {
            return null;
        }

        return [
            'url' => self::getLogoUrl($size->path),
            'width' => $size->width,
            'height' => $size->height,
            'name' => $logo->name,
            'type' => $logo->type,
        ];
    }

    /**
     * Lấy URL logo cho sử dụng trong email
     */
    public static function getEmailLogoUrl($width = 300, $height = 90)
    {
        $logo = self::getLogoBySize('email', $width, $height);
        return $logo ? $logo['url'] : null;
    }

    /**
     * Tạo URL cho logo (chỉ dùng local storage cho VPS)
     */
    private static function getLogoUrl($path)
    {
        return asset('storage/' . $path);
    }
}
