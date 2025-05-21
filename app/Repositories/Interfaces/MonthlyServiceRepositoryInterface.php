<?php

namespace App\Repositories\Interfaces;

use App\Models\Room;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

interface MonthlyServiceRepositoryInterface
{
    /**
     * Lấy danh sách phòng cần cập nhật dịch vụ
     */
    public function getRoomsNeedingUpdate(Request $request): array;

    /**
     * Lấy dịch vụ của phòng theo tháng/năm
     */
    public function getRoomServices(string $roomId, int $month, int $year): array;

    /**
     * Lưu thông tin sử dụng dịch vụ hàng tháng
     */
    public function saveRoomServiceUsage(Request $request): array;

    /**
     * Lấy danh sách nhà trọ khả dụng cho quản lý dịch vụ
     */
    public function getAvailableHouses(): Collection;
} 