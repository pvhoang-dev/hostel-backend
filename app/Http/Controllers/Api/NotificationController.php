<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NotificationController extends BaseController
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $result = $this->notificationService->getAllNotifications($request);
            return $this->sendResponse($result, 'Notifications retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $notification = $this->notificationService->createNotification($request);
            return $this->sendResponse(
                new NotificationResource($notification),
                'Thông báo đã được tạo thành công.'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $notification = $this->notificationService->getNotificationById($id);
            return $this->sendResponse(
                new NotificationResource($notification),
                'Thông báo đã được lấy thành công.'
            );
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * Update the specified notification.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $notification = $this->notificationService->updateNotification($request, $id);
            return $this->sendResponse(
                new NotificationResource($notification),
                'Thông báo đã được cập nhật thành công.'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->notificationService->deleteNotification($id);
            return $this->sendResponse([], 'Thông báo đã được xóa thành công.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $userId = $request->has('user_id') ? $request->user_id : null;
            $this->notificationService->markAllAsRead($userId);
            return $this->sendResponse([], 'Tất cả thông báo đã được đánh dấu đã đọc.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }
}