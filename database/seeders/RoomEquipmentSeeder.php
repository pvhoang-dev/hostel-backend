<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\Room;
use App\Models\RoomEquipment;
use Illuminate\Database\Seeder;

class RoomEquipmentSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = Room::all();
        $equipments = Equipment::all();
        
        // Nhóm thiết bị theo loại và mức độ phổ biến
        $commonEquipment = [
            'Giường đơn', 'Giường đôi', 'Tủ quần áo', 'Quạt trần', 'Quạt đứng',
            'Ghế thường', 'Router wifi', 'Vòi sen'
        ];
        
        $kitchenEquipment = [
            'Tủ lạnh', 'Bếp từ', 'Bếp gas', 'Ấm đun nước', 'Tủ bếp', 'Lò vi sóng'
        ];
        
        $premiumEquipment = [
            'Điều hòa', 'Tivi', 'Sofa', 'Máy nước nóng', 'Khóa cửa thông minh', 'Camera an ninh',
            'Đầu thu K+'
        ];
        
        // Định nghĩa giá thiết bị (đã giảm 3 số 0)
        $equipmentPrices = [
            'Điều hòa' => [3000, 7000],             // 3-7 triệu
            'Quạt trần' => [250, 800],              // 250-800 nghìn
            'Quạt đứng' => [150, 400],              // 150-400 nghìn
            'Giường đơn' => [400, 1200],            // 400k-1.2 triệu
            'Giường đôi' => [800, 2500],            // 800k-2.5 triệu
            'Tủ quần áo' => [600, 1800],            // 600k-1.8 triệu
            'Kệ đầu giường' => [100, 300],          // 100-300 nghìn
            'Đệm' => [300, 2000],                   // 300k-2 triệu
            'Tủ lạnh' => [1500, 5000],              // 1.5-5 triệu
            'Bếp từ' => [400, 1500],                // 400k-1.5 triệu
            'Bếp gas' => [200, 800],                // 200-800 nghìn
            'Lò vi sóng' => [400, 1500],            // 400k-1.5 triệu
            'Ấm đun nước' => [100, 400],            // 100-400 nghìn
            'Tủ bếp' => [500, 2000],                // 500k-2 triệu
            'Máy nước nóng' => [800, 2000],         // 800k-2 triệu
            'Vòi sen' => [150, 700],                // 150-700 nghìn
            'Bàn làm việc' => [300, 1000],          // 300k-1 triệu
            'Ghế văn phòng' => [200, 1000],         // 200k-1 triệu
            'Ghế thường' => [80, 250],              // 80-250 nghìn
            'Sofa' => [1000, 4000],                 // 1-4 triệu
            'Bàn trà' => [150, 600],                // 150-600 nghìn
            'Kệ sách' => [200, 800],                // 200-800 nghìn
            'Tivi' => [2000, 8000],                 // 2-8 triệu
            'Đầu thu K+' => [300, 800],             // 300-800 nghìn
            'Router wifi' => [200, 800],            // 200-800 nghìn
            'Khóa cửa thông minh' => [500, 2000],   // 500k-2 triệu
            'Camera an ninh' => [300, 1200]         // 300k-1.2 triệu
        ];

        foreach ($rooms as $room) {
            // Xác định loại phòng dựa trên giá phòng
            $isPremium = $room->base_price > 4000; // Phòng có giá > 4 triệu được coi là cao cấp
            $isStudio = $room->base_price > 3500 && $room->area > 30; // Phòng studio có diện tích lớn
            
            // Thiết bị cơ bản cho mọi phòng
            $roomEquipments = $commonEquipment;
            
            // Thiết bị nhà bếp cho phòng studio và cao cấp
            if ($isStudio || $isPremium) {
                // Lấy 3-5 thiết bị nhà bếp
                $numKitchenItems = rand(3, min(5, count($kitchenEquipment)));
                $selectedKitchenEquipment = array_slice($kitchenEquipment, 0, $numKitchenItems);
                $roomEquipments = array_merge($roomEquipments, $selectedKitchenEquipment);
            } else {
                // Phòng thường chỉ có 1-2 thiết bị nhà bếp
                $numKitchenItems = rand(1, 2);
                $selectedKitchenEquipment = array_slice($kitchenEquipment, 0, $numKitchenItems);
                $roomEquipments = array_merge($roomEquipments, $selectedKitchenEquipment);
            }
            
            // Thiết bị cao cấp
            if ($isPremium) {
                // Phòng cao cấp có 3-5 thiết bị cao cấp
                $numPremiumItems = rand(3, min(5, count($premiumEquipment)));
                $selectedPremiumEquipment = array_slice($premiumEquipment, 0, $numPremiumItems);
                $roomEquipments = array_merge($roomEquipments, $selectedPremiumEquipment);
            } else {
                // Phòng thường có 1-2 thiết bị cao cấp (như điều hòa)
                $numPremiumItems = rand(1, 2);
                $selectedPremiumEquipment = array_slice($premiumEquipment, 0, $numPremiumItems);
                $roomEquipments = array_merge($roomEquipments, $selectedPremiumEquipment);
            }
            
            // Loại bỏ trùng lặp
            $roomEquipments = array_unique($roomEquipments);
            
            // Tạo thiết bị cho phòng
            foreach ($equipments as $equipment) {
                if (in_array($equipment->name, $roomEquipments)) {
                    // Lấy khoảng giá của thiết bị
                    $priceRange = $equipmentPrices[$equipment->name] ?? [100, 1000];
                    $price = rand($priceRange[0], $priceRange[1]);
                    
                    // Số lượng thường là 1, nhưng một số đồ có thể có nhiều hơn
                    $quantity = 1;
                    if (in_array($equipment->name, ['Ghế thường', 'Quạt đứng'])) {
                        $quantity = rand(1, $room->capacity);
                    } elseif ($equipment->name === 'Giường đơn' && $room->capacity > 1) {
                        $quantity = rand(1, $room->capacity);
                    }
                    
                    // Mô tả chi tiết
                    $descriptions = [
                        'Điều hòa' => ['Điều hòa Panasonic 1 chiều 9000BTU', 'Điều hòa Samsung Inverter 12000BTU', 'Điều hòa LG 2 chiều 9000BTU'],
                        'Tivi' => ['Smart TV Samsung 32 inch', 'Android TV Sony 43 inch', 'TV LG 40 inch'],
                        'Tủ lạnh' => ['Tủ lạnh mini Sanyo 90L', 'Tủ lạnh Toshiba 150L', 'Tủ lạnh Sharp 180L 2 cánh'],
                        'Giường đơn' => ['Giường đơn khung gỗ tự nhiên', 'Giường đơn khung sắt chắc chắn', 'Giường đơn gỗ công nghiệp'],
                        'Giường đôi' => ['Giường đôi 1m6 khung gỗ tự nhiên', 'Giường đôi 1m8 cao cấp', 'Giường đôi 1m6 gỗ công nghiệp']
                    ];
                    
                    $description = isset($descriptions[$equipment->name]) 
                        ? $descriptions[$equipment->name][array_rand($descriptions[$equipment->name])]
                        : "Tiêu chuẩn - {$equipment->description}";

                    RoomEquipment::firstOrCreate(
                        ['room_id' => $room->id, 'equipment_id' => $equipment->id],
                        [
                            'quantity' => $quantity,
                            'price' => $price,
                            'description' => $description,
                        ]
                    );
                }
            }
        }
    }
}