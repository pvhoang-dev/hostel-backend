<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Room;
use App\Models\House;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\NotificationResource;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class NotificationService
{
    protected $notificationRepository;

    public function __construct(NotificationRepositoryInterface $notificationRepository)
    {
        $this->notificationRepository = $notificationRepository;
    }

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

    /**
     * Get all notifications with filters
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function getAllNotifications($request)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Lỗi xác thực.');
        }

        $filters = [
            'current_user' => $currentUser,
            'viewAll' => $request->has('viewAll') && filter_var($request->viewAll, FILTER_VALIDATE_BOOLEAN),
            'type' => $request->type ?? null,
            'is_read' => $request->has('is_read') ? filter_var($request->is_read, FILTER_VALIDATE_BOOLEAN) : null,
            'created_from' => $request->created_from ?? null,
            'created_to' => $request->created_to ?? null,
        ];

        // Add user_id filter if present
        if ($request->has('user_id')) {
            $filters['user_id'] = $request->user_id;
        }

        // Include relationships
        $with = [];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('user', $includes)) $with[] = 'user';
        }

        // Pagination and sorting
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);

        $notifications = $this->notificationRepository->getAllWithFilters(
            $filters,
            $with,
            'created_at',
            'desc',
            $perPage
        );

        return NotificationResource::collection($notifications)->response()->getData(true);
    }

    /**
     * Get notification by ID
     *
     * @param string $id
     * @return \App\Models\Notification
     */
    public function getNotificationById($id)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Lỗi xác thực.');
        }

        $notification = $this->notificationRepository->getById($id, ['user']);

        if (is_null($notification)) {
            throw new \Exception('Thông báo không tồn tại.');
        }

        // Check permissions
        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $currentUser->role->code === 'manager';
        $isOwnNotification = $notification->user_id === $currentUser->id;

        // Admin can view any notification
        if ($isAdmin) {
            // Allow access
        }
        // Manager can view their own notifications or notifications of tenants they manage
        else if ($isManager) {
            if (!$isOwnNotification && !$this->notificationRepository->isTenantManagedByManager($notification->user_id, $currentUser->id)) {
                throw new \Exception('Bạn chỉ có thể xem thông báo của chính mình hoặc thông báo của khách trọ mà bạn quản lý.');
            }
        }
        // Others can only view their own notifications
        else if (!$isOwnNotification) {
            throw new \Exception('Bạn chỉ có thể xem thông báo của chính mình.');
        }

        // Mark notification as read if the user is viewing their own notification
        if ($isOwnNotification && !$notification->is_read) {
            $this->notificationRepository->update($notification->id, ['is_read' => true]);
        }

        return $notification;
    }

    /**
     * Create a new notification
     *
     * @param \Illuminate\Http\Request $request
     * @return \App\Models\Notification
     */
    public function createNotification($request)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Lỗi xác thực.');
        }

        $input = $request->all();
        $validator = Validator::make($input, [
            'user_id' => 'required|exists:users,id',
            'type' => 'required|string|max:50',
            'content' => 'required|string',
            'url' => 'nullable|string',
            'is_read' => 'boolean',
        ], [
            'user_id.required' => 'User ID là bắt buộc.',
            'user_id.exists' => 'User ID không hợp lệ.',
            'type.required' => 'Loại thông báo là bắt buộc.',
            'type.string' => 'Loại thông báo phải là chuỗi.',
            'type.max' => 'Loại thông báo phải nhỏ hơn 50 ký tự.',
            'content.required' => 'Nội dung thông báo là bắt buộc.',
            'content.string' => 'Nội dung thông báo phải là chuỗi.',
            'url.string' => 'URL phải là chuỗi.',
            'is_read.boolean' => 'Trạng thái đã đọc phải là boolean.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $currentUser->role->code === 'manager';
        $isTenant = $currentUser->role->code === 'tenant';

        // Tenants cannot create notifications
        if ($isTenant) {
            throw new \Exception('Bạn không có quyền tạo thông báo.');
        }

        // If manager, check if they can create notifications for this user
        if ($isManager && $input['user_id'] != $currentUser->id) {
            // Get user info to check role
            $targetUser = User::find($input['user_id']);
            $isTargetAdmin = $targetUser && $targetUser->role->code === 'admin';

            // Managers can create notifications for admin or tenants they manage
            if (!$isTargetAdmin && !$this->notificationRepository->isTenantManagedByManager($input['user_id'], $currentUser->id)) {
                throw new \Exception('Bạn chỉ có thể tạo thông báo cho admin hoặc tenant mà bạn quản lý.');
            }
        }

        return $this->notificationRepository->create($input);
    }

    /**
     * Update notification
     *
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @return \App\Models\Notification
     */
    public function updateNotification($request, $id)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Lỗi xác thực.');
        }

        $notification = $this->notificationRepository->getById($id);

        if (is_null($notification)) {
            throw new \Exception('Thông báo không tồn tại.');
        }

        // Check permissions
        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $currentUser->role->code === 'manager';
        $isOwnNotification = $notification->user_id === $currentUser->id;

        // Admin can update any notification
        if ($isAdmin) {
            // Allow access
        }
        // Manager can update their own notifications or notifications of tenants they manage
        else if ($isManager) {
            if (!$isOwnNotification && !$this->notificationRepository->isTenantManagedByManager($notification->user_id, $currentUser->id)) {
                throw new \Exception('Bạn chỉ có thể cập nhật thông báo của chính mình hoặc thông báo của khách trọ mà bạn quản lý.');
            }
        }
        // Others can only update their own notifications
        else if (!$isOwnNotification) {
            throw new \Exception('Bạn chỉ có thể cập nhật thông báo của chính mình.');
        }

        $input = $request->only(['type', 'content', 'url', 'is_read']);

        // Only add user_id to input if explicitly provided
        if ($request->has('user_id') && !is_null($request->user_id)) {
            // Check permissions for changing user_id
            if ($isAdmin) {
                // Admin can change user_id to any user
                $input['user_id'] = $request->user_id;
            } else if ($isManager) {
                // Manager can only change user_id to tenants they manage
                if ($this->notificationRepository->isTenantManagedByManager($request->user_id, $currentUser->id)) {
                    $input['user_id'] = $request->user_id;
                } else {
                    throw new \Exception('Bạn chỉ có thể gán thông báo cho khách trọ mà bạn quản lý.');
                }
            }
            // Others cannot change user_id (not added to input)
        }

        $validator = Validator::make($input, [
            'type' => 'sometimes|required|string|max:50',
            'content' => 'sometimes|required|string',
            'url' => 'nullable|string',
            'is_read' => 'sometimes|boolean',
            'user_id' => 'sometimes|exists:users,id',
        ], [
            'type.string' => 'Loại thông báo phải là chuỗi.',
            'type.max' => 'Loại thông báo phải nhỏ hơn 50 ký tự.',
            'content.string' => 'Nội dung thông báo phải là chuỗi.',
            'url.string' => 'URL phải là chuỗi.',
            'is_read.boolean' => 'Trạng thái đã đọc phải là boolean.',
            'user_id.exists' => 'User ID không hợp lệ.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $this->notificationRepository->update($id, $input);
    }

    /**
     * Delete notification
     *
     * @param string $id
     * @return bool
     */
    public function deleteNotification($id)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Lỗi xác thực.');
        }

        $notification = $this->notificationRepository->getById($id);

        if (is_null($notification)) {
            throw new \Exception('Thông báo không tồn tại.');
        }

        // Check permissions
        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $currentUser->role->code === 'manager';
        $isOwnNotification = $notification->user_id === $currentUser->id;

        // Admin can delete any notification
        if ($isAdmin) {
            // Allow access
        }
        // Manager can delete their own notifications or notifications of tenants they manage
        else if ($isManager) {
            if (!$isOwnNotification && !$this->notificationRepository->isTenantManagedByManager($notification->user_id, $currentUser->id)) {
                throw new \Exception('Bạn chỉ có thể xóa thông báo của chính mình hoặc thông báo của khách trọ mà bạn quản lý.');
            }
        }
        // Others can only delete their own notifications
        else if (!$isOwnNotification) {
            throw new \Exception('Bạn chỉ có thể xóa thông báo của chính mình.');
        }

        return $this->notificationRepository->delete($id);
    }

    /**
     * Mark all notifications as read
     *
     * @param int|null $userIdToMark
     * @return bool
     */
    public function markAllAsRead($userIdToMark = null)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Lỗi xác thực.');
        }

        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $currentUser->role->code === 'manager';

        // If user_id is provided, try to mark notifications for that user as read
        if ($userIdToMark) {
            // Admin can mark any user's notifications as read
            if ($isAdmin) {
                return $this->notificationRepository->markAllAsRead($userIdToMark);
            }
            // Manager can mark their tenants' notifications as read
            else if ($isManager && $this->notificationRepository->isTenantManagedByManager($userIdToMark, $currentUser->id)) {
                return $this->notificationRepository->markAllAsRead($userIdToMark);
            }
            // Others can only mark their own notifications as read
            else if ($userIdToMark == $currentUser->id) {
                return $this->notificationRepository->markAllAsRead($currentUser->id);
            } else {
                throw new \Exception('Bạn chỉ có thể đánh dấu thông báo của chính mình.');
            }
        }
        // No user_id provided, mark current user's notifications as read
        else {
            return $this->notificationRepository->markAllAsRead($currentUser->id);
        }
    }
}
