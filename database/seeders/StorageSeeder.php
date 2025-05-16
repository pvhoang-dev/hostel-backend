<?php

namespace Database\Seeders;

use App\Models\House;
use App\Models\Equipment;
use App\Models\EquipmentStorage;
use Illuminate\Database\Seeder;

class StorageSeeder extends Seeder
{
    public function run(): void
    {
        $houses = House::all();
        $equipments = Equipment::all();
        
        // Tạo kho lưu trữ thiết bị cho mỗi nhà
        foreach ($houses as $house) {
            // Số lượng mỗi thiết bị trong kho sẽ phụ thuộc vào loại nhà và số phòng
            $roomCount = $house->rooms()->count();
            $isApartment = (strpos($house->name, 'Căn hộ') !== false || strpos($house->name, 'Chung cư') !== false);
            
            // Các thiết bị thường có trong kho
            $commonEquipment = [
                'Quạt đứng', 'Quạt trần', 'Bóng đèn', 'Ổ cắm điện', 
                'Vòi sen', 'Ấm đun nước', 'Ghế thường', 'Bàn học'
            ];
            
            // Thiết bị cao cấp
            $premiumEquipment = [
                'Điều hòa', 'Tủ lạnh', 'Máy nước nóng', 'Tivi', 'Bếp từ'
            ];
            
            // Thiết bị trong kho
            foreach ($equipments as $equipment) {
                // Xác định số lượng thiết bị dựa trên loại thiết bị và loại nhà
                $quantity = 0;
                
                if (in_array($equipment->name, $commonEquipment)) {
                    // Thiết bị thông thường: lưu trữ nhiều hơn
                    $quantity = rand(1, 3) + floor($roomCount / 5);
                } elseif (in_array($equipment->name, $premiumEquipment)) {
                    // Thiết bị cao cấp: lưu trữ ít hơn
                    $quantity = $isApartment ? rand(0, 2) : rand(0, 1);
                } else {
                    // Thiết bị khác: lưu trữ ngẫu nhiên
                    $quantity = rand(0, 2);
                }
                
                // Chỉ tạo bản ghi nếu có lưu trữ thiết bị
                if ($quantity > 0) {
                    // Tình trạng thiết bị (không sử dụng trường status)
                    $conditions = ['mới', 'đã qua sử dụng', 'hư hỏng nhẹ'];
                    $conditionWeights = [3, 6, 1]; // Trọng số cho tình trạng (mới: 30%, đã qua sử dụng: 60%, hư hỏng nhẹ: 10%)
                    
                    $conditionIndex = $this->weightedRandom($conditionWeights);
                    $condition = $conditions[$conditionIndex];
                    
                    // Giá thiết bị (đã giảm 3 số 0 để phù hợp testing)
                    $price = 0;
                    switch ($equipment->name) {
                        case 'Điều hòa':
                            $price = rand(3000, 7000);
                            break;
                        case 'Tủ lạnh':
                            $price = rand(1500, 5000);
                            break;
                        case 'Tivi':
                            $price = rand(2000, 8000);
                            break;
                        case 'Máy nước nóng':
                            $price = rand(800, 2000);
                            break;
                        case 'Bếp từ':
                            $price = rand(400, 1500);
                            break;
                        case 'Quạt trần':
                            $price = rand(250, 800);
                            break;
                        case 'Quạt đứng':
                            $price = rand(150, 400);
                            break;
                        default:
                            $price = rand(100, 1000);
                    }
                    
                    // Mô tả thiết bị
                    $descriptions = [
                        'Điều hòa' => ['Điều hòa Panasonic 9000BTU', 'Điều hòa Samsung 12000BTU', 'Điều hòa LG 2 chiều'],
                        'Tủ lạnh' => ['Tủ lạnh mini Sanyo 90L', 'Tủ lạnh Toshiba 150L', 'Tủ lạnh Sharp 180L 2 cánh'],
                        'Tivi' => ['Smart TV Samsung 32 inch', 'Android TV Sony 43 inch', 'TV LG 40 inch'],
                        'Máy nước nóng' => ['Máy nước nóng Ariston 15L', 'Máy nước nóng Panasonic trực tiếp', 'Máy nước nóng Ferroli 20L'],
                        'Bếp từ' => ['Bếp từ đơn Midea', 'Bếp từ đôi Sunhouse', 'Bếp từ Electrolux 3 vùng nấu'],
                        'Quạt trần' => ['Quạt trần KDK 3 cánh', 'Quạt trần Panasonic 4 cánh', 'Quạt trần Asia 5 cánh'],
                        'Quạt đứng' => ['Quạt đứng Senko 3 tốc độ', 'Quạt đứng Sunhouse có remote', 'Quạt đứng Asia điều khiển từ xa']
                    ];
                    
                    $description = isset($descriptions[$equipment->name]) 
                        ? $descriptions[$equipment->name][array_rand($descriptions[$equipment->name])]
                        : "Thiết bị {$equipment->name} dự trữ trong kho";
                    
                    // Bổ sung thông tin tình trạng vào mô tả
                    $description .= " - Tình trạng: " . $condition;
                    
                    // Giảm giá thiết bị hư hỏng
                    if ($condition === 'hư hỏng nhẹ') {
                        $price = floor($price * 0.6);
                    }
                    
                    // Tạo bản ghi trong kho
                    EquipmentStorage::create([
                        'equipment_id' => $equipment->id,
                        'house_id' => $house->id,
                        'quantity' => $quantity,
                        'price' => $price,
                        'description' => $description
                    ]);
                }
            }
        }
    }
    
    /**
     * Lựa chọn ngẫu nhiên có trọng số
     * 
     * @param array $weights Mảng trọng số
     * @return int Chỉ số được chọn
     */
    private function weightedRandom(array $weights): int
    {
        $sum = array_sum($weights);
        $rand = rand(1, $sum);
        
        $total = 0;
        foreach ($weights as $i => $weight) {
            $total += $weight;
            if ($rand <= $total) {
                return $i;
            }
        }
        
        return 0;
    }
}