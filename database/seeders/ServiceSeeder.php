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
            ],
            [
                'name' => 'Nước',
                'default_price' => 15,
                'unit' => 'm³',
                'is_metered' => true,
            ],
            [
                'name' => 'Internet',
                'default_price' => 100,
                'unit' => 'tháng',
                'is_metered' => false,
            ],
            [
                'name' => 'Phí vệ sinh',
                'default_price' => 50,
                'unit' => 'tháng',
                'is_metered' => false,
            ],
            [
                'name' => 'Gửi xe',
                'default_price' => 100,
                'unit' => 'tháng',
                'is_metered' => false,
            ],
            [
                'name' => 'Phí rác thải',
                'default_price' => 20,
                'unit' => 'tháng',
                'is_metered' => false,
            ],
            [
                'name' => 'Tiền phòng',
                'default_price' => 0,
                'unit' => 'tháng',
                'is_metered' => false,
            ],
        ];

        foreach ($services as $service) {
            Service::firstOrCreate(
                ['name' => $service['name']],
                [
                    'default_price' => $service['default_price'],
                    'unit' => $service['unit'],
                    'is_metered' => $service['is_metered'],
                ]
            );
        }
    }
}
