<?php

namespace App\Observers;

use App\Models\Contract;
use App\Models\Room;

class ContractObserver
{
    /**
     * Handle the Contract "created" event.
     */
    public function created(Contract $contract): void
    {
        // Khi tạo hợp đồng mới với trạng thái active, cập nhật phòng thành used
        if ($contract->status === 'active') {
            $this->updateRoomStatus($contract->room_id, 'used');
        }
    }

    /**
     * Handle the Contract "updated" event.
     */
    public function updated(Contract $contract): void
    {
        // Nếu trạng thái hợp đồng thay đổi
        if ($contract->isDirty('status')) {
            $oldStatus = $contract->getOriginal('status');
            $newStatus = $contract->status;

            // Nếu từ active sang trạng thái khác
            if ($oldStatus === 'active' && $newStatus !== 'active') {
                // Kiểm tra xem phòng này còn hợp đồng active nào khác không
                $hasActiveContract = Contract::where('room_id', $contract->room_id)
                    ->where('id', '!=', $contract->id)
                    ->where('status', 'active')
                    ->exists();

                // Nếu không còn hợp đồng active nào khác, cập nhật phòng thành available
                if (!$hasActiveContract) {
                    $this->updateRoomStatus($contract->room_id, 'available');
                }
            }
            // Nếu từ trạng thái khác sang active
            elseif ($oldStatus !== 'active' && $newStatus === 'active') {
                // Cập nhật phòng sang used
                $this->updateRoomStatus($contract->room_id, 'used');
                
                // Đảm bảo chỉ có một hợp đồng active duy nhất cho phòng này
                Contract::where('room_id', $contract->room_id)
                    ->where('id', '!=', $contract->id)
                    ->where('status', 'active')
                    ->update(['status' => 'expired']);
            }
        }
    }

    /**
     * Handle the Contract "deleted" event.
     */
    public function deleted(Contract $contract): void
    {
        // Nếu xóa hợp đồng đang active, cập nhật trạng thái phòng
        if ($contract->status === 'active') {
            // Kiểm tra xem phòng này còn hợp đồng active nào khác không
            $hasActiveContract = Contract::where('room_id', $contract->room_id)
                ->where('id', '!=', $contract->id)
                ->where('status', 'active')
                ->exists();

            // Nếu không còn hợp đồng active nào khác, cập nhật phòng thành available
            if (!$hasActiveContract) {
                $this->updateRoomStatus($contract->room_id, 'available');
            }
        }
    }

    /**
     * Cập nhật trạng thái phòng
     */
    private function updateRoomStatus($roomId, $status): void
    {
        $room = Room::find($roomId);
        if ($room) {
            $room->update(['status' => $status]);
        }
    }
} 