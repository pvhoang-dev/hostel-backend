<?php

namespace Database\Seeders;

use App\Models\House;
use App\Models\User;
use Illuminate\Database\Seeder;

class HouseSeeder extends Seeder
{
    public function run(): void
    {
        // Get managers
        $admin = User::where('username', 'admin')->first();
        $manager1 = User::where('username', 'manager1')->first();
        $manager2 = User::where('username', 'manager2')->first();

        $houses = [
            [
                'name' => '154 Đình Thôn',
                'address' => '154 Đình Thôn',
                'description' => '',
                'manager_id' => $admin->id,
                'status' => 'active',
            ],
            [
                'name' => '99 Trung Kính',
                'address' => '99 Trung Kính',
                'description' => '',
                'manager_id' => $manager1->id,
                'status' => 'active',
            ],
            [
                'name' => '58 Nguyễn Khánh Toàn',
                'address' => '58 Nguyễn Khánh Toàn',
                'description' => '',
                'manager_id' => $manager2->id,
                'status' => 'active',
            ],
        ];

        foreach ($houses as $house) {
            House::firstOrCreate(
                ['name' => $house['name'], 'address' => $house['address']],
                [
                    'description' => $house['description'],
                    'manager_id' => $house['manager_id'],
                    'status' => $house['status'],
                ]
            );
        }
    }
}
