<?php

namespace Database\Seeders;

use App\Models\House;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $houses = House::all();
        $admin = User::where('username', 'admin')->first();

        // Các mô tả phòng đa dạng
        $roomDescriptions = [
            'Phòng rộng rãi với đầy đủ tiện nghi, phù hợp cho cặp đôi hoặc ở riêng',
            'Phòng tiêu chuẩn cao cấp, đầy đủ tiện nghi cơ bản',
            'Phòng tiêu chuẩn, đầy đủ nội thất cơ bản',
            'Phòng rộng, view đẹp, đầy đủ tiện nghi cao cấp',
            'Phòng tiết kiệm, đầy đủ tiện nghi cơ bản',
            'Phòng rộng rãi, thiết kế hiện đại',
            'Phòng nhỏ gọn, phù hợp ở một mình',
            'Phòng dành cho 2 người, có thể kê 2 giường đơn hoặc 1 giường đôi',
            'Phòng rộng dành cho 3 người, thích hợp cho nhóm bạn hoặc gia đình nhỏ',
        ];

        foreach ($houses as $house) {
            // Tạo 3-5 phòng cho mỗi nhà
            $roomCount = rand(3, 5);

            // Giá phòng tùy theo khu vực và loại nhà
            if (strpos($house->name, 'Căn hộ') !== false || strpos($house->name, 'Chung cư') !== false) {
                $minPrice = 3000; // 3 triệu (đã giảm 3 số 0)
                $maxPrice = 5000; // 5 triệu
            } else {
                $minPrice = 1500; // 1.5 triệu
                $maxPrice = 3000; // 3 triệu
            }

            for ($i = 1; $i <= $roomCount; $i++) {
                // Tên phòng có thể là P101, P102 hoặc 101, 102
                $roomName = rand(0, 1) ? 'P' . $i . '0' . rand(1, 3) : (100 + $i) . (rand(0, 1) ? 'A' : '');

                // Giá phòng ngẫu nhiên trong khoảng min-max, đã giảm 3 số 0
                $basePrice = rand($minPrice, $maxPrice);

                // Sức chứa phòng (1-4 người)
                $capacity = rand(1, 4);

                // Trạng thái phòng (ưu tiên trạng thái available nhiều hơn)
                $statusOptions = ['available', 'available', 'available', 'available', 'available', 'maintain'];
                $status = $statusOptions[array_rand($statusOptions)];

                // Chọn một mô tả ngẫu nhiên
                $description = $roomDescriptions[array_rand($roomDescriptions)];

                Room::firstOrCreate(
                    ['house_id' => $house->id, 'room_number' => $roomName],
                    [
                        'capacity' => $capacity,
                        'base_price' => $basePrice,
                        'description' => $description,
                        'status' => $status,
                        'created_by' => $admin->id
                    ]
                );
            }
        }
    }
}
