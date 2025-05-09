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

        foreach ($rooms as $room) {
            // Add 3-5 random equipments to each room
            $randomEquipments = $equipments->random(rand(3, 5));

            foreach ($randomEquipments as $equipment) {
                RoomEquipment::firstOrCreate(
                    ['room_id' => $room->id, 'equipment_id' => $equipment->id],
                    [
                        'quantity' => rand(1, 2),
                        'price' => rand(500000, 3000000),
                        'description' => "Standard {$equipment->name} for room use",
                    ]
                );
            }
        }
    }
}