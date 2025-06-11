<?php

namespace App\Observers;

use App\Models\Room;
use App\Models\Contract;
use Illuminate\Support\Facades\Log;

class RoomObserver
{
    /**
     * Handle the Room "updated" event.
     */
    public function updated(Room $room): void
    {
        // Nếu trạng thái phòng đã thay đổi
        if ($room->isDirty('status')) {
            $oldStatus = $room->getOriginal('status');
            $newStatus = $room->status;
            
            Log::info("Trạng thái phòng ID #{$room->id} đã thay đổi từ '{$oldStatus}' thành '{$newStatus}'");
            
            // Nếu phòng chuyển sang trạng thái available
            if ($newStatus === 'available' && $oldStatus !== 'available') {
                $this->updateActiveContracts($room, 'expired', "Phòng đã chuyển sang trạng thái available");
            }
            // Nếu phòng đang chuyển sang trạng thái khác (không phải available và không phải used)
            else if ($newStatus !== 'available' && $newStatus !== 'used') {
                $this->updateActiveContracts($room, 'terminated', "Phòng đã chuyển sang trạng thái {$newStatus}");
            }
            // Nếu phòng chuyển từ trạng thái đang sử dụng (used) sang trạng thái khác không phải available
            // (trường hợp chuyển sang available đã được xử lý ở điều kiện đầu tiên)
            else if ($oldStatus === 'used' && $newStatus !== 'used' && $newStatus !== 'available') {
                $this->updateActiveContracts($room, 'terminated', "Phòng không còn trong trạng thái sử dụng");
            }
        }
    }

    /**
     * Cập nhật trạng thái các hợp đồng active của phòng
     */
    private function updateActiveContracts(Room $room, string $newStatus, string $reason): void
    {
        try {
            // Lấy tất cả hợp đồng active của phòng
            $activeContracts = Contract::where('room_id', $room->id)
                ->where('status', 'active')
                ->get();
            
            if ($activeContracts->count() > 0) {
                foreach ($activeContracts as $contract) {
                    $contract->update([
                        'status' => $newStatus,
                        'termination_reason' => $reason,
                        'updated_by' => $contract->created_by
                    ]);
                    
                    Log::info("Hợp đồng ID #{$contract->id} đã được cập nhật trạng thái thành '{$newStatus}' do thay đổi trạng thái phòng");
                }
            }
        } catch (\Exception $e) {
            Log::error("Lỗi khi cập nhật hợp đồng cho phòng ID #{$room->id}: " . $e->getMessage());
        }
    }
} 