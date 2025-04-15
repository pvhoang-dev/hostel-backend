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

        foreach ($houses as $house) {
            $roomCount = rand(3, 5);

            for ($i = 1; $i <= $roomCount; $i++) {
                Room::firstOrCreate(
                    ['house_id' => $house->id, 'room_number' => "Room $i"],
                    [
                        'capacity' => rand(1, 4),
                        'base_price' => rand(2000000, 5000000),
                        'description' => "Standard room",
                        'status' => ['available', 'maintain', 'used'][array_rand(['available', 'maintain', 'used'])],
                        'created_by' => $admin->id
                    ]
                );
            }
        }
    }
}
