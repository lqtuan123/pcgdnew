<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EthnicMinority;

class EthnicMinoritySeeder extends Seeder
{
    public function run(): void
    {
        $ethnicMinorities = [
            ['name' => 'Kinh', 'code' => 'KINH', 'description' => 'Dân tộc Kinh'],
            ['name' => 'Tày', 'code' => 'TAY', 'description' => 'Dân tộc Tày'],
            ['name' => 'Thái', 'code' => 'THAI', 'description' => 'Dân tộc Thái'],
            ['name' => 'Mường', 'code' => 'MUONG', 'description' => 'Dân tộc Mường'],
            ['name' => 'Khmer', 'code' => 'KHMER', 'description' => 'Dân tộc Khmer'],
            ['name' => 'Hoa', 'code' => 'HOA', 'description' => 'Dân tộc Hoa'],
            ['name' => 'Nùng', 'code' => 'NUNG', 'description' => 'Dân tộc Nùng'],
            ['name' => 'H\'Mông', 'code' => 'HMONG', 'description' => 'Dân tộc H\'Mông'],
            ['name' => 'Dao', 'code' => 'DAO', 'description' => 'Dân tộc Dao'],
            ['name' => 'Gia Rai', 'code' => 'GIA_RAI', 'description' => 'Dân tộc Gia Rai'],
            ['name' => 'Ê Đê', 'code' => 'E_DE', 'description' => 'Dân tộc Ê Đê'],
            ['name' => 'Ba Na', 'code' => 'BA_NA', 'description' => 'Dân tộc Ba Na'],
            ['name' => 'Xơ Đăng', 'code' => 'XO_DANG', 'description' => 'Dân tộc Xơ Đăng'],
            ['name' => 'Sán Chay', 'code' => 'SAN_CHAY', 'description' => 'Dân tộc Sán Chay'],
            ['name' => 'Cơ Ho', 'code' => 'CO_HO', 'description' => 'Dân tộc Cơ Ho'],
            ['name' => 'Chăm', 'code' => 'CHAM', 'description' => 'Dân tộc Chăm'],
            ['name' => 'Sán Dìu', 'code' => 'SAN_DIU', 'description' => 'Dân tộc Sán Dìu'],
            ['name' => 'Hrê', 'code' => 'HRE', 'description' => 'Dân tộc Hrê'],
            ['name' => 'Mnông', 'code' => 'MNONG', 'description' => 'Dân tộc Mnông'],
            ['name' => 'Ra Glai', 'code' => 'RA_GLAI', 'description' => 'Dân tộc Ra Glai'],
            ['name' => 'X\'Tiêng', 'code' => 'XTIENG', 'description' => 'Dân tộc X\'Tiêng'],
            ['name' => 'Bru - Vân Kiều', 'code' => 'BRU_VAN_KIEU', 'description' => 'Dân tộc Bru - Vân Kiều'],
            ['name' => 'Thổ', 'code' => 'THO', 'description' => 'Dân tộc Thổ'],
            ['name' => 'Giáy', 'code' => 'GIAY', 'description' => 'Dân tộc Giáy'],
            ['name' => 'Cơ Tu', 'code' => 'CO_TU', 'description' => 'Dân tộc Cơ Tu'],
            ['name' => 'Gié Triêng', 'code' => 'GIE_TRIENG', 'description' => 'Dân tộc Gié Triêng'],
            ['name' => 'Mạ', 'code' => 'MA', 'description' => 'Dân tộc Mạ'],
            ['name' => 'Khơ Mú', 'code' => 'KHO_MU', 'description' => 'Dân tộc Khơ Mú'],
            ['name' => 'Co', 'code' => 'CO', 'description' => 'Dân tộc Co'],
            ['name' => 'Tà Ôi', 'code' => 'TA_OI', 'description' => 'Dân tộc Tà Ôi'],
            ['name' => 'Chơ Ro', 'code' => 'CHO_RO', 'description' => 'Dân tộc Chơ Ro'],
            ['name' => 'Xinh Mun', 'code' => 'XINH_MUN', 'description' => 'Dân tộc Xinh Mun'],
            ['name' => 'Hà Nhì', 'code' => 'HA_NHI', 'description' => 'Dân tộc Hà Nhì'],
            ['name' => 'Chu Ru', 'code' => 'CHU_RU', 'description' => 'Dân tộc Chu Ru'],
            ['name' => 'Lào', 'code' => 'LAO', 'description' => 'Dân tộc Lào'],
            ['name' => 'La Chí', 'code' => 'LA_CHI', 'description' => 'Dân tộc La Chí'],
            ['name' => 'Kháng', 'code' => 'KHANG', 'description' => 'Dân tộc Kháng'],
            ['name' => 'Lự', 'code' => 'LU', 'description' => 'Dân tộc Lự'],
            ['name' => 'Phù Lá', 'code' => 'PHU_LA', 'description' => 'Dân tộc Phù Lá'],
            ['name' => 'La Hủ', 'code' => 'LA_HU', 'description' => 'Dân tộc La Hủ'],
            ['name' => 'Lô Lô', 'code' => 'LO_LO', 'description' => 'Dân tộc Lô Lô'],
            ['name' => 'Chứt', 'code' => 'CHUT', 'description' => 'Dân tộc Chứt'],
            ['name' => 'Mảng', 'code' => 'MANG', 'description' => 'Dân tộc Mảng'],
            ['name' => 'Cờ Lao', 'code' => 'CO_LAO', 'description' => 'Dân tộc Cờ Lao'],
            ['name' => 'Bố Y', 'code' => 'BO_Y', 'description' => 'Dân tộc Bố Y'],
            ['name' => 'La Ha', 'code' => 'LA_HA', 'description' => 'Dân tộc La Ha'],
            ['name' => 'Pu Péo', 'code' => 'PU_PEO', 'description' => 'Dân tộc Pu Péo'],
            ['name' => 'Rơ Măm', 'code' => 'RO_MAM', 'description' => 'Dân tộc Rơ Măm'],
            ['name' => 'Brâu', 'code' => 'BRAU', 'description' => 'Dân tộc Brâu'],
            ['name' => 'Ơ Đu', 'code' => 'O_DU', 'description' => 'Dân tộc Ơ Đu'],
        ];

        foreach ($ethnicMinorities as $ethnicMinority) {
            EthnicMinority::create($ethnicMinority);
        }
    }
}