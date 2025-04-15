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
        $rooms = Room::where('status', 'used')->get();
        $services = Service::all();

        foreach ($rooms as $room) {
            foreach ($services as $service) {
                $isFixed = !$service->is_metered;
                $price = $service->name === 'Room Fee' ? $room->base_price : $service->default_price;

                RoomService::firstOrCreate(
                    ['room_id' => $room->id, 'service_id' => $service->id],
                    [
                        'price' => $price,
                        'is_fixed' => $isFixed,
                        'description' => "Standard {$service->name} service for room",
                        'status' => 'active',
                    ]
                );
            }
        }
    }
}
