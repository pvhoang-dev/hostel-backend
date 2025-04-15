<?php

namespace Database\Seeders;

use App\Models\Equipment;
use Illuminate\Database\Seeder;

class EquipmentSeeder extends Seeder
{
    public function run(): void
    {
        $equipments = [
            ['name' => 'Điều hòa'],
            ['name' => 'Giường'],
            ['name' => 'Tủ lạnh'],
            ['name' => 'Tủ bát'],
            ['name' => 'Bàn làm việc'],
            ['name' => 'Ghế'],
            ['name' => 'WiFi'],
            ['name' => 'Tivi'],
        ];

        foreach ($equipments as $equipment) {
            Equipment::firstOrCreate(
                ['name' => $equipment['name']],
            );
        }
    }
}
