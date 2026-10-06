<?php
namespace Database\Seeders;


use App\Models\Ethnicity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;


class EthnicitySeeder extends Seeder
{
public function run(): void
{
$items = [
['name' => 'Kinh', 'is_minority' => false],
['name' => 'Tày'], ['name' => 'Thái'], ['name' => 'Mường'], ['name' => 'Khmer'],
['name' => 'Hoa'], ['name' => 'Nùng'], ['name' => "H'Mông"], ['name' => 'Dao'],
['name' => 'Gia Rai'], ['name' => 'Ê Đê'], ['name' => 'Ba Na'], ['name' => 'Xơ Đăng'],
['name' => 'Sán Chay'], ['name' => 'Chăm'], ['name' => 'Sán Dìu'], ['name' => 'Bru - Vân Kiều'],
['name' => 'Cơ Ho'], ['name' => 'Tà Ôi'], ['name' => 'Chơ Ro'], ['name' => 'Raglai'],
['name' => 'Mạ'], ['name' => "H'Re"], ['name' => 'Mnông'],
];


foreach ($items as $i => $row) {
$row['is_minority'] = $row['is_minority'] ?? true;
$row['slug'] = Str::slug($row['name']);
$row['code'] = $row['code'] ?? null;


Ethnicity::updateOrCreate(
['name' => $row['name']],
$row
);
}
}
}