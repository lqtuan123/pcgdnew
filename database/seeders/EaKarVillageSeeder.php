<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\GeoUnit;
use App\Models\GeoType;

class EaKarVillageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tìm xã Ea Kar
        $eaKarWard = GeoUnit::where('code', '24373')
            ->where('scheme', 'new')
            ->where('type_level', 3)
            ->first();
            
        if (!$eaKarWard) {
            $this->command->error('Xã Ea Kar không tìm thấy trong hệ thống.');
            return;
        }

        // Lấy geo_type_id cho thôn/xóm (level 4)
        $villageType = GeoType::where('level', 4)->first();
        if (!$villageType) {
            $this->command->error('Không tìm thấy geo_type cho thôn/xóm (level 4).');
            return;
        }

        // Danh sách các thôn xóm của xã Ea Kar
        $villages = [
            'An Cư',
            'Buôn M Ar', 
            'Buôn M Briu',
            'Buôn M Hang',
            'Buôn M Oa',
            'Buôn Tưng Kuroa',
            'Cư An',
            'Ea Kung',
            'Hợp Thành',
            'Thôn Cư Nghia',
            'Thôn Đoàn Kết',
            'Thôn Đồng Tâm',
            'Thôn Sơn Lộc',
            'Thôn Tân Lộc',
            'Thôn Tân Tiến',
            'Thôn Tứ Xuân'
        ];

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($villages as $villageName) {
            // Thêm prefix "Cư_Huê" vào tên thôn xóm
            $fullVillageName = 'Cư_Huê ' . $villageName;
            
            // Tạo mã code cho thôn xóm (sử dụng mã xã + số thứ tự)
            $villageCode = '24373' . str_pad($createdCount + 1, 3, '0', STR_PAD_LEFT);
            
            // Kiểm tra xem thôn xóm đã tồn tại chưa
            $existingVillage = GeoUnit::where('code', $villageCode)
                ->where('scheme', 'new')
                ->where('type_level', 4)
                ->first();
                
            if ($existingVillage) {
                $this->command->warn("Thôn xóm '{$fullVillageName}' đã tồn tại với mã {$villageCode}");
                $skippedCount++;
                continue;
            }

            // Tạo thôn xóm mới
            GeoUnit::create([
                'geo_type_id' => $villageType->id,
                'type_level' => 4,
                'parent_id' => $eaKarWard->id,
                'uuid' => (string) Str::uuid(),
                'code' => $villageCode,
                'name' => $fullVillageName,
                'slug' => Str::slug($fullVillageName),
                'scheme' => 'new',
                'valid_from' => now(),
                'valid_to' => null,
                'meta' => [
                    'original_name' => $villageName,
                    'ward_code' => '24373',
                    'ward_name' => 'Ea Kar',
                    'created_by' => 'EaKarVillageSeeder'
                ]
            ]);

            $createdCount++;
            $this->command->info("Đã tạo thôn xóm: {$fullVillageName} (Mã: {$villageCode})");
        }

        $this->command->info("Hoàn thành tạo thôn xóm cho xã Ea Kar:");
        $this->command->info("- Đã tạo: {$createdCount} thôn xóm");
        $this->command->info("- Đã bỏ qua: {$skippedCount} thôn xóm (đã tồn tại)");
        $this->command->info("- Tổng cộng: " . count($villages) . " thôn xóm");
    }
}
