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
            ['name' => 'Điều hòa', 'description' => 'Điều hòa nhiệt độ treo tường'],
            ['name' => 'Quạt trần', 'description' => 'Quạt trần loại thông thường'],
            ['name' => 'Quạt đứng', 'description' => 'Quạt đứng có chế độ xoay 180 độ'],
            
            // Đồ nội thất phòng ngủ
            ['name' => 'Giường đơn', 'description' => 'Giường đơn kích thước 1m x 2m'],
            ['name' => 'Giường đôi', 'description' => 'Giường đôi kích thước 1.6m x 2m'],
            ['name' => 'Tủ quần áo', 'description' => 'Tủ đựng quần áo gỗ công nghiệp'],
            ['name' => 'Kệ đầu giường', 'description' => 'Kệ nhỏ đặt đầu giường'],
            ['name' => 'Đệm', 'description' => 'Đệm cao su hoặc mút'],
            
            // Thiết bị nhà bếp
            ['name' => 'Tủ lạnh', 'description' => 'Tủ lạnh mini hoặc loại lớn'],
            ['name' => 'Bếp từ', 'description' => 'Bếp từ đơn hoặc đôi'],
            ['name' => 'Bếp gas', 'description' => 'Bếp gas mini loại 2 lò'],
            ['name' => 'Lò vi sóng', 'description' => 'Lò vi sóng cỡ nhỏ'],
            ['name' => 'Ấm đun nước', 'description' => 'Ấm đun nước siêu tốc'],
            ['name' => 'Tủ bếp', 'description' => 'Tủ đựng đồ nhà bếp'],
            
            // Đồ dùng phòng tắm
            ['name' => 'Máy nước nóng', 'description' => 'Máy nước nóng trực tiếp'],
            ['name' => 'Vòi sen', 'description' => 'Vòi sen cây loại thường'],
            
            // Nội thất phòng khách/làm việc
            ['name' => 'Bàn làm việc', 'description' => 'Bàn làm việc/học tập'],
            ['name' => 'Ghế văn phòng', 'description' => 'Ghế xoay dùng cho bàn làm việc'],
            ['name' => 'Ghế thường', 'description' => 'Ghế gỗ/nhựa thông thường'],
            ['name' => 'Sofa', 'description' => 'Sofa mini hoặc sofa đơn'],
            ['name' => 'Bàn trà', 'description' => 'Bàn nhỏ để tiếp khách'],
            ['name' => 'Kệ sách', 'description' => 'Kệ để sách và đồ dùng cá nhân'],
            
            // Thiết bị điện tử
            ['name' => 'Tivi', 'description' => 'Ti vi màn hình phẳng'],
            ['name' => 'Đầu thu K+', 'description' => 'Đầu thu truyền hình K+'],
            ['name' => 'Router wifi', 'description' => 'Thiết bị phát wifi'],
            
            // Thiết bị an ninh
            ['name' => 'Khóa cửa thông minh', 'description' => 'Khóa cửa điện tử hoặc khóa vân tay'],
            ['name' => 'Camera an ninh', 'description' => 'Camera giám sát khu vực chung'],
        ];

        foreach ($equipments as $equipment) {
            Equipment::firstOrCreate(
                ['name' => $equipment['name']],
                // ['description' => $equipment['description']]
            );
        }
    }
}
