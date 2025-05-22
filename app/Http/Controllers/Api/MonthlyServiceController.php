<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Models\House;
use App\Models\Room;
use App\Services\MonthlyServiceService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MonthlyServiceController extends BaseController
{
    protected $monthlyServiceService;
    protected $notificationService;

    public function __construct(
        MonthlyServiceService $monthlyServiceService,
        NotificationService $notificationService
    ) {
        $this->monthlyServiceService = $monthlyServiceService;
        $this->notificationService = $notificationService;
    }

    /**
     * Get rooms that need service usage updates for a specific month/year
     */
    public function getRoomsNeedingUpdate(Request $request): JsonResponse
    {
        $result = $this->monthlyServiceService->getRoomsNeedingUpdate($request);
        
        if (!$result['success']) {
            return $this->sendError('Lỗi dữ liệu.', $result['errors']);
        }
        
        return $this->sendResponse($result['data'], 'Rooms retrieved successfully.');
    }

    /**
     * Get services for a room with their latest usage
     */
    public function getRoomServices(Request $request, $roomId): JsonResponse
    {
        $result = $this->monthlyServiceService->getRoomServices($request, $roomId);
        
        if (!$result['success']) {
            return $this->sendError('Lỗi dữ liệu.', $result['errors']);
        }
        
        return $this->sendResponse($result['data'], 'Room services retrieved successfully.');
    }

    /**
     * Save monthly service usage for a room
     */
    public function saveRoomServiceUsage(Request $request): JsonResponse
    {
        $result = $this->monthlyServiceService->saveRoomServiceUsage($request);
        
        if (!$result['success']) {
            return $this->sendError('Lỗi lưu dịch vụ', ['error' => $result['errors']]);
        }
        
        return $this->sendResponse($result['data'], $result['message']);
    }

    /**
     * Get available houses for service management
     */
    public function getAvailableHouses(): JsonResponse
    {
        $result = $this->monthlyServiceService->getAvailableHouses();
        
        if (!$result['success']) {
            return $this->sendError('Lỗi xác thực.', ['error' => $result['errors']], 403);
        }
        
        return $this->sendResponse($result['data'], 'Nhà trọ khả dụng đã được lấy thành công.');
    }
}