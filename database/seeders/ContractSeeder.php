<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ContractSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = User::whereHas('role', function ($query) {
            $query->where('code', 'tenant');
        })->get();

        $occupiedRooms = Room::where('status', 'used')->get();

        foreach ($occupiedRooms as $room) {
            // Assign 1-2 tenants per room
            $tenantCount = rand(1, min(2, $tenants->count()));
            $roomTenants = $tenants->random($tenantCount);
            $tenantIds = $roomTenants->pluck('id')->toArray();

            // Calculate dates
            $startDate = Carbon::now()->subMonths(rand(1, 6));
            $endDate = Carbon::parse($startDate)->addMonths(12);

            $contract = Contract::firstOrCreate(
                ['room_id' => $room->id, 'start_date' => $startDate],
                [
                    'end_date' => $endDate,
                    'monthly_price' => $room->base_price,
                    'deposit_amount' => $room->base_price * 2,
                    'notice_period' => 30,
                    'deposit_status' => ['held', 'returned', 'partially_returned'][array_rand(['held', 'returned', 'partially_returned'])],
                    'status' => 'active',
                    'auto_renew' => (bool)rand(0, 1),
                    'created_by' => User::whereHas('role', function ($query) {
                        $query->where('code', 'manager');
                    })->first()->id,
                ]
            );

            // Attach tenants to the contract
            $contract->users()->sync($tenantIds);

            // Update room status
            $room->update(['status' => 'used']);
        }
    }
}
