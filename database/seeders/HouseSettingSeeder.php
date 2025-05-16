<?php

namespace Database\Seeders;

use App\Models\House;
use App\Models\HouseSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class HouseSettingSeeder extends Seeder
{
    public function run(): void
    {
        $houses = House::all();
        $admin = User::where('username', 'admin')->first();
        
        foreach ($houses as $house) {
            // Tạo các cài đặt khác nhau cho mỗi nhà
            $isApartment = (strpos($house->name, 'Căn hộ') !== false || strpos($house->name, 'Chung cư') !== false);
            
            // Nội quy nhà
            $settings = [
                // 1. Quy tắc vận hành nhà
                [
                    'key' => '1',
                    'value' => 'Giữ gìn vệ sinh chung, không gây ồn ào sau 22h, không hút thuốc trong khu vực chung',
                    'description' => 'Nội quy ' . $house->name
                ],
                
                // 2. Thời gian hoạt động
                [
                    'key' => '2',
                    'value' => 'Cổng: ' . ($isApartment ? '24/7' : '5:00-23:00') . ', Giờ yên tĩnh: 22:00-6:00',
                    'description' => 'Giờ hoạt động các khu vực ' . $house->name
                ],
                
                // 3. Liên hệ khẩn cấp
                [
                    'key' => '3',
                    'value' => 'Quản lý: 0' . rand(900000000, 999999999) . ', Bảo vệ: 0' . rand(900000000, 999999999),
                    'description' => 'Liên hệ khẩn cấp ' . $house->name
                ],
                
                // 4. Thông tin về phí và dịch vụ
                [
                    'key' => '4',
                    'value' => 'Điện: ' . (rand(3, 4)) . 'k/kWh, Nước: ' . (rand(10, 20)) . 'k/m³, Gửi xe: ' . (rand(80, 150)) . 'k/tháng',
                    'description' => 'Thông tin phí dịch vụ ' . $house->name
                ],
                
                // 5. Lịch bảo trì định kỳ
                [
                    'key' => '5',
                    'value' => 'Điện: hàng quý, Nước: hàng quý, Diệt côn trùng: 3 tháng/lần, PCCC: 6 tháng/lần',
                    'description' => 'Lịch bảo trì định kỳ ' . $house->name
                ],
                
                // 6. Tiện ích xung quanh
                [
                    'key' => '6',
                    'value' => 'Siêu thị: ' . rand(50, 500) . 'm, Bệnh viện: ' . rand(200, 2000) . 'm, Xe buýt: ' . rand(50, 300) . 'm',
                    'description' => 'Tiện ích xung quanh ' . $house->name
                ],
                
                // 7. Nội quy cho khách thăm
                [
                    'key' => '7',
                    'value' => 'Khách phải đăng ký với quản lý, giờ thăm: 8:00-22:00, không cho mượn chìa khóa',
                    'description' => 'Nội quy cho khách thăm ' . $house->name
                ],
                
                // 8. Quy định về gửi xe
                [
                    'key' => '8',
                    'value' => 'Mỗi phòng tối đa ' . ($isApartment ? '2' : '1') . ' xe máy, phải dán thẻ, không để xe ở lối đi chung',
                    'description' => 'Quy định gửi xe ' . $house->name
                ]
            ];
            
            foreach ($settings as $setting) {
                HouseSetting::firstOrCreate(
                    [
                        'house_id' => $house->id,
                        'key' => $setting['key']
                    ],
                    [
                        'value' => $setting['value'],
                        'description' => $setting['description'],
                        'created_by' => $admin->id,
                    ]
                );
            }
        }
    }
} 