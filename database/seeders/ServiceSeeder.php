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
                'default_price' => 3500,
                'unit' => 'kWh',
                'is_metered' => true,
            ],
            [
                'name' => 'Nước',
                'default_price' => 15000,
                'unit' => 'm³',
                'is_metered' => true,
            ],
            [
                'name' => 'Internet',
                'default_price' => 100000,
                'unit' => 'month',
                'is_metered' => false,
            ],
            [
                'name' => 'Vệ sinh chung',
                'default_price' => 50000,
                'unit' => 'month',
                'is_metered' => false,
            ],
            [
                'name' => 'Gửi xe',
                'default_price' => 100000,
                'unit' => 'month',
                'is_metered' => false,
            ],
            [
                'name' => 'Room Fee',
                'default_price' => 0,
                'unit' => 'month',
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
