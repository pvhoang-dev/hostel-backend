<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'name' => 'Điện',
                'default_price' => 4,
                'unit' => 'kWh',
                'is_metered' => true,
                'description' => 'Tiền điện được tính theo đồng hồ riêng của từng phòng'
            ],
            [
                'name' => 'Nước',
                'default_price' => 15,
                'unit' => 'm³',
                'is_metered' => true,
                'description' => 'Tiền nước được tính theo lượng sử dụng thực tế'
            ],
            [
                'name' => 'Internet',
                'default_price' => 100,
                'unit' => 'tháng',
                'is_metered' => false,
                'description' => 'Dịch vụ Internet cáp quang tốc độ cao'
            ],
            [
                'name' => 'Phí vệ sinh',
                'default_price' => 50,
                'unit' => 'tháng',
                'is_metered' => false,
                'description' => 'Phí dịch vụ vệ sinh khu vực chung của tòa nhà'
            ],
            [
                'name' => 'Gửi xe',
                'default_price' => 100,
                'unit' => 'tháng',
                'is_metered' => false,
                'description' => 'Phí gửi xe máy/xe đạp tại bãi xe của tòa nhà'
            ],
            [
                'name' => 'Phí rác thải',
                'default_price' => 20,
                'unit' => 'tháng',
                'is_metered' => false,
                'description' => 'Phí thu gom và xử lý rác thải'
            ],
            [
                'name' => 'Tiền phòng',
                'default_price' => 0,
                'unit' => 'tháng',
                'is_metered' => false,
                'description' => 'Phí thuê phòng cơ bản theo hợp đồng'
            ],
        ];

        foreach ($services as $service) {
            Service::firstOrCreate(
                ['name' => $service['name']],
                [
                    'default_price' => $service['default_price'],
                    'unit' => $service['unit'],
                    'is_metered' => $service['is_metered'],
                    // 'description' => $service['description'],
                ]
            );
        }
    }
}
