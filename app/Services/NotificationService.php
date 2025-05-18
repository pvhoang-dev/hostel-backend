<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Room;
use App\Models\House;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Tạo thông báo cho một người dùng
     * 
     * @param int $userId ID của người dùng nhận thông báo
     * @param string $type Loại thông báo (system, invoice, contract, request, ...)
     * @param string $content Nội dung thông báo
     * @param string|null $url URL liên kết (optional)
     * @param bool $isRead Trạng thái đã đọc (mặc định là false)
     * @return Notification|null Thông báo đã tạo hoặc null nếu có lỗi
     */
    public function create(int $userId, string $type, string $content, ?string $url = null, bool $isRead = false): ?Notification
    {
        try {
            return Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'content' => $content,
                'url' => $url,
                'is_read' => $isRead
            ]);
        } catch (\Exception $e) {
            Log::error('Lỗi khi tạo thông báo: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Tạo thông báo hàng loạt cho nhiều người dùng
     * 
     * @param array $userIds Mảng các ID người dùng
     * @param string $type Loại thông báo
     * @param string $content Nội dung thông báo
     * @param string|null $url URL liên kết (optional)
     * @param bool $isRead Trạng thái đã đọc (mặc định là false)
     * @return Collection Danh sách các thông báo đã tạo
     */
    public function createBulk(array $userIds, string $type, string $content, ?string $url = null, bool $isRead = false): Collection
    {
        $notifications = collect([]);
        
        foreach ($userIds as $userId) {
            $notification = $this->create($userId, $type, $content, $url, $isRead);
            if ($notification) {
                $notifications->push($notification);
            }
        }
        
        return $notifications;
    }

    /**
     * Tạo thông báo cho tất cả người thuê trong một phòng
     * 
     * @param int $roomId ID của phòng
     * @param string $type Loại thông báo
     * @param string $content Nội dung thông báo
     * @param string|null $url URL liên kết (optional)
     * @param bool $isRead Trạng thái đã đọc (mặc định là false)
     * @return Collection Danh sách các thông báo đã tạo
     */
    public function notifyRoomTenants(int $roomId, string $type, string $content, ?string $url = null, bool $isRead = false): Collection
    {
        // Lấy danh sách người thuê của phòng này
        $tenantIds = User::whereHas('contracts', function ($query) use ($roomId) {
            $query->where('room_id', $roomId)
                ->where('status', 'active');
        })->pluck('id')->toArray();
        
        return $this->createBulk($tenantIds, $type, $content, $url, $isRead);
    }

    /**
     * Tạo thông báo cho tất cả người thuê trong một nhà
     * 
     * @param int $houseId ID của nhà
     * @param string $type Loại thông báo
     * @param string $content Nội dung thông báo
     * @param string|null $url URL liên kết (optional)
     * @param bool $isRead Trạng thái đã đọc (mặc định là false)
     * @return Collection Danh sách các thông báo đã tạo
     */
    public function notifyHouseTenants(int $houseId, string $type, string $content, ?string $url = null, bool $isRead = false): Collection
    {
        // Lấy danh sách phòng của nhà
        $roomIds = Room::where('house_id', $houseId)->pluck('id')->toArray();
        
        // Lấy danh sách người thuê của các phòng này
        $tenantIds = User::whereHas('contracts', function ($query) use ($roomIds) {
            $query->whereIn('room_id', $roomIds)
                ->where('status', 'active');
        })->pluck('id')->toArray();
        
        return $this->createBulk($tenantIds, $type, $content, $url, $isRead);
    }

    /**
     * Tạo thông báo cho manager của một nhà
     * 
     * @param int $houseId ID của nhà
     * @param string $type Loại thông báo
     * @param string $content Nội dung thông báo
     * @param string|null $url URL liên kết (optional)
     * @param bool $isRead Trạng thái đã đọc (mặc định là false)
     * @return Notification|null Thông báo đã tạo hoặc null nếu không có manager hoặc có lỗi
     */
    public function notifyHouseManager(int $houseId, string $type, string $content, ?string $url = null, bool $isRead = false): ?Notification
    {
        // Lấy manager ID của nhà
        $managerId = House::where('id', $houseId)->value('manager_id');
        
        if (!$managerId) {
            Log::warning("Không tìm thấy manager cho nhà ID: {$houseId}");
            return null;
        }
        
        return $this->create($managerId, $type, $content, $url, $isRead);
    }

    /**
     * Tạo thông báo cho tất cả admin
     * 
     * @param string $type Loại thông báo
     * @param string $content Nội dung thông báo
     * @param string|null $url URL liên kết (optional)
     * @param bool $isRead Trạng thái đã đọc (mặc định là false)
     * @return Collection Danh sách các thông báo đã tạo
     */
    public function notifyAllAdmins(string $type, string $content, ?string $url = null, bool $isRead = false): Collection
    {
        // Lấy tất cả admin IDs
        $adminIds = User::whereHas('role', function ($query) {
            $query->where('code', 'admin');
        })->pluck('id')->toArray();
        
        return $this->createBulk($adminIds, $type, $content, $url, $isRead);
    }
} 