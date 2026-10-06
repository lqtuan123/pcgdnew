<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Logo;
use App\Models\LogoSize;

class LogoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo logo mẫu
        $logo = Logo::create([
            'type' => 'logo',
            'name' => 'Logo chính',
            'description' => 'Logo chính của website',
            'original_path' => 'logos/original/default-logo.png',
            'is_active' => true,
        ]);

        // Tạo các kích thước cho logo
        $logoSizes = [
            [64, 64],
            [128, 128],
            [256, 256],
            [512, 512],
        ];

        foreach ($logoSizes as [$width, $height]) {
            LogoSize::create([
                'logo_id' => $logo->id,
                'width' => $width,
                'height' => $height,
                'path' => "logos/{$logo->id}/{$width}x{$height}.png",
                'is_active' => $width == 128 && $height == 128, // Kích hoạt size 128x128
            ]);
        }

        // Tạo favicon mẫu
        $favicon = Logo::create([
            'type' => 'favicon',
            'name' => 'Favicon chính',
            'description' => 'Favicon của website',
            'original_path' => 'logos/original/default-favicon.ico',
            'is_active' => true,
        ]);

        // Tạo các kích thước cho favicon
        $faviconSizes = [
            [16, 16],
            [32, 32],
            [48, 48],
            [64, 64],
            [128, 128],
            [192, 192],
        ];

        foreach ($faviconSizes as [$width, $height]) {
            LogoSize::create([
                'logo_id' => $favicon->id,
                'width' => $width,
                'height' => $height,
                'path' => "logos/{$favicon->id}/{$width}x{$height}.png",
                'is_active' => $width == 32 && $height == 32, // Kích hoạt size 32x32
            ]);
        }

        // Tạo email logo mẫu
        $emailLogo = Logo::create([
            'type' => 'email',
            'name' => 'Logo email',
            'description' => 'Logo sử dụng trong email',
            'original_path' => 'logos/original/default-email-logo.png',
            'is_active' => true,
        ]);

        // Tạo các kích thước cho email logo
        $emailSizes = [
            [200, 60],
            [300, 90],
            [400, 120],
        ];

        foreach ($emailSizes as [$width, $height]) {
            LogoSize::create([
                'logo_id' => $emailLogo->id,
                'width' => $width,
                'height' => $height,
                'path' => "logos/{$emailLogo->id}/{$width}x{$height}.png",
                'is_active' => $width == 300 && $height == 90, // Kích hoạt size 300x90
            ]);
        }
    }
}
