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
        // Lấy tất cả tenant và các phòng available
        $tenants = User::whereHas('role', function ($query) {
            $query->where('code', 'tenant');
        })->get();
        
        $availableRooms = Room::where('status', 'available')->get();
        
        // Quản lý danh sách tenant đã được chỉ định phòng
        $assignedTenants = [];
        
        // Mỗi phòng sẽ có 1-3 tenant (tùy thuộc vào sức chứa của phòng)
        foreach ($availableRooms as $room) {
            // Xác định số lượng tenant cho phòng này (tối đa theo sức chứa, hoặc theo số tenant còn lại)
            $maxTenantsForRoom = min($room->capacity, 3); // Tối đa 3 người/phòng
            $remainingTenants = $tenants->count() - count($assignedTenants);
            
            // Nếu không còn tenant nào thì thoát vòng lặp
            if ($remainingTenants <= 0) {
                break;
            }
            
            // Số lượng tenant thực tế sẽ được gán cho phòng này
            $tenantCount = min($remainingTenants, rand(1, $maxTenantsForRoom));
            
            // Lấy ngẫu nhiên các tenant chưa được chỉ định phòng
            $selectedTenants = [];
            
            // Mảng chỉ số của tenant còn trống
            $availableTenantIndices = array_diff(range(0, $tenants->count() - 1), $assignedTenants);
            
            // Nếu còn đủ tenant chưa được chỉ định
            if (count($availableTenantIndices) >= $tenantCount) {
                // Chọn ngẫu nhiên tenant cho phòng
                $randomKeys = array_rand(array_flip($availableTenantIndices), $tenantCount);
                
                if (!is_array($randomKeys)) {
                    $randomKeys = [$randomKeys];
                }
                
                foreach ($randomKeys as $index) {
                    $selectedTenants[] = $tenants[$index]->id;
                    $assignedTenants[] = $index; // Đánh dấu tenant đã được chỉ định
                }
                
                // Tính ngày bắt đầu và kết thúc hợp đồng
                $startDateOptions = [
                    Carbon::now()->subMonths(rand(1, 6)),
                    Carbon::now()->subMonths(rand(1, 3)),
                    Carbon::now()->subWeeks(rand(1, 3)),
                    Carbon::now()->subDays(rand(1, 15))
                ];
                $startDate = $startDateOptions[array_rand($startDateOptions)];
                
                // Thời hạn hợp đồng (6, 12 hoặc 24 tháng)
                $contractTerms = [6, 12, 12, 12, 24]; // Trọng số cho 12 tháng cao hơn
                $termMonths = $contractTerms[array_rand($contractTerms)];
                $endDate = Carbon::parse($startDate)->addMonths($termMonths);
                
                // Giá phòng đã được giảm 3 số 0 trong RoomSeeder
                $contract = Contract::create([
                    'room_id' => $room->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'monthly_price' => $room->base_price,
                    'deposit_amount' => $room->base_price * 2,
                    'notice_period' => 30, // 30 ngày thông báo trước khi hết hạn
                    'deposit_status' => 'held',
                    'status' => 'active',
                    'auto_renew' => (bool)rand(0, 1),
                    'created_by' => User::whereHas('role', function ($query) {
                        $query->where('code', 'manager');
                    })->inRandomOrder()->first()->id,
                ]);
                
                // Gán các tenant cho hợp đồng
                $contract->users()->sync($selectedTenants);
                
                // Cập nhật trạng thái phòng
                $room->update(['status' => 'used']);
            }
        }
    }
}
