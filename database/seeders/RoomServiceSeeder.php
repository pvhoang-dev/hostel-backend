<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomService;
use App\Models\Service;
use Illuminate\Database\Seeder;

class RoomServiceSeeder extends Seeder
{
    public function run(): void
    {
        // Lấy tất cả phòng có status là used hoặc available
        $rooms = Room::whereIn('status', ['used', 'available'])->get();
        $services = Service::all();
        
        // Các dịch vụ cơ bản luôn có trong mọi phòng
        $essentialServices = ['Điện', 'Nước', 'Tiền phòng', 'Phí vệ sinh', 'Internet'];

        foreach ($rooms as $room) {
            // Dịch vụ cơ bản luôn được áp dụng cho mỗi phòng
            foreach ($services as $service) {
                // Nếu là dịch vụ cơ bản hoặc xác suất ngẫu nhiên để thêm dịch vụ khác
                if (in_array($service->name, $essentialServices) || rand(0, 100) < 40) {
                    $isFixed = !$service->is_metered;
                    
                    // Xác định giá dịch vụ
                    $price = 0;
                    
                    switch ($service->name) {
                        case 'Tiền phòng':
                            $price = $room->base_price;
                            break;
                        case 'Điện':
                            // Các tòa nhà cao cấp có thể có giá điện cao hơn
                            if (strpos($room->house->name, 'Căn hộ') !== false || strpos($room->house->name, 'Chung cư') !== false) {
                                $price = $service->default_price * (1 + rand(0, 20) / 100); // Tăng giá lên 0-20%
                            } else {
                                $price = $service->default_price;
                            }
                            break;
                        case 'Nước':
                            $price = $service->default_price;
                            break;
                        case 'Gửi xe':
                            // Khu vực trung tâm có giá gửi xe cao hơn
                            if (strpos($room->house->address, 'Hai Bà Trưng') !== false || 
                                strpos($room->house->address, 'Đống Đa') !== false || 
                                strpos($room->house->address, 'Ba Đình') !== false) {
                                $price = $service->default_price * 1.5;
                            } else {
                                $price = $service->default_price;
                            }
                            break;
                        default:
                            $price = $service->default_price;
                    }
                    
                    // Tạo ghi chú tùy chỉnh cho dịch vụ
                    $descriptions = [
                        'Điện' => ['Giá điện theo quy định của EVN', 'Điện sinh hoạt theo đồng hồ riêng', 'Giá tính theo chỉ số hàng tháng'],
                        'Nước' => ['Nước sạch Hà Nội', 'Nước được tính theo đồng hồ riêng', 'Giá nước theo quy định của nhà nước'],
                        'Internet' => ['Internet tốc độ cao', 'Wifi cáp quang FTTH', 'Internet không giới hạn dung lượng'],
                        'Gửi xe' => ['Bãi xe có bảo vệ 24/7', 'Bãi xe rộng rãi, có mái che', 'Gửi xe không giới hạn lượt ra vào'],
                        'Phí vệ sinh' => ['Dọn dẹp khu vực chung 2 lần/tuần', 'Bao gồm dịch vụ đổ rác hàng ngày', 'Vệ sinh định kỳ hành lang và cầu thang']
                    ];
                    
                    $description = isset($descriptions[$service->name]) 
                        ? $descriptions[$service->name][array_rand($descriptions[$service->name])]
                        : "Dịch vụ {$service->name} tiêu chuẩn";

                    RoomService::firstOrCreate(
                        ['room_id' => $room->id, 'service_id' => $service->id],
                        [
                            'price' => $price,
                            'is_fixed' => $isFixed,
                            'description' => $description,
                            'status' => 'active',
                        ]
                    );
                }
            }
        }
    }
}
