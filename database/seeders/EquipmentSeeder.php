<?php

namespace Database\Seeders;

use App\Models\Equipment;
use Illuminate\Database\Seeder;

class EquipmentSeeder extends Seeder
{
    public function run(): void
    {
        $equipments = [
            // Thiết bị điện lạnh
            ['name' => 'Điều hòa'],
            ['name' => 'Quạt trần'],
            ['name' => 'Quạt đứng'],
            
            // Đồ nội thất phòng ngủ
            ['name' => 'Giường đơn'],
            ['name' => 'Giường đôi'],
            ['name' => 'Tủ quần áo'],
            ['name' => 'Kệ đầu giường'],
            ['name' => 'Đệm'],
            
            // Thiết bị nhà bếp
            ['name' => 'Tủ lạnh'],
            ['name' => 'Bếp từ'],
            ['name' => 'Bếp gas'],
            ['name' => 'Lò vi sóng'],
            ['name' => 'Ấm đun nước'],
            ['name' => 'Tủ bếp'],
            
            // Đồ dùng phòng tắm
            ['name' => 'Máy nước nóng'],
            ['name' => 'Vòi sen'],
            
            // Nội thất phòng khách/làm việc
            ['name' => 'Bàn làm việc'],
            ['name' => 'Ghế văn phòng'],
            ['name' => 'Ghế thường'],
            ['name' => 'Sofa'],
            ['name' => 'Bàn trà'],
            ['name' => 'Kệ sách'],
            
            // Thiết bị điện tử
            ['name' => 'Tivi'],
            ['name' => 'Đầu thu K+'],
            ['name' => 'Router wifi'],
            
            // Thiết bị an ninh
            ['name' => 'Khóa cửa thông minh'],
            ['name' => 'Camera an ninh'],
        ];

        foreach ($equipments as $equipment) {
            Equipment::firstOrCreate(
                ['name' => $equipment['name']],
            );
        }
    }
}
