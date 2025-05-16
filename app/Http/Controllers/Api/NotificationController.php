<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\NotificationResource;
use App\Models\House;
use App\Models\Notification;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends BaseController
{
    /**
     * Helper method to get tenant IDs managed by a manager
     */
    private function getManagedTenantIds($managerId)
    {
        // Lấy danh sách tenant của các nhà mà manager quản lý
        $managedHouseIds = House::where('manager_id', $managerId)->pluck('id')->toArray();

        // Lấy danh sách phòng từ các nhà đó
        $managedRoomIds = Room::whereIn('house_id', $managedHouseIds)->pluck('id')->toArray();

        // Lấy danh sách tenant từ các phòng đó
        return User::whereHas('contracts', function ($query) use ($managedRoomIds) {
            $query->whereIn('room_id', $managedRoomIds)->where('status', 'active');
        })->where('role_id', function ($query) {
            $query->select('id')->from('roles')->where('code', 'tenant');
        })->pluck('id')->toArray();
    }

    /**
     * Check if a user is managed by the manager
     */
    private function isTenantManagedByManager($tenantId, $managerId)
    {
        $managedTenantIds = $this->getManagedTenantIds($managerId);
        return in_array($tenantId, $managedTenantIds);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        $query = Notification::query();

        // Xác định quyền của người dùng hiện tại
        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $currentUser->role->code === 'manager';

        // Mặc định: Người dùng chỉ thấy thông báo của họ
        $viewAll = $request->has('viewAll') && filter_var($request->viewAll, FILTER_VALIDATE_BOOLEAN);

        if (!$viewAll) {
            // Người dùng chỉ thấy thông báo của họ
            $query->where('user_id', $currentUser->id);
        } else {
            // Admin có thể xem tất cả
            if ($isAdmin) {
                // Không cần filter thêm gì, lấy tất cả
            }
            // Manager chỉ có thể xem thông báo của tenant trong nhà mà họ quản lý
            else if ($isManager) {
                // Lấy danh sách tenant IDs của các nhà mà manager quản lý
                $tenantIds = $this->getManagedTenantIds($currentUser->id);

                // Lấy thông báo của manager hoặc của các tenant
                $query->where(function ($query) use ($currentUser, $tenantIds) {
                    $query->where('user_id', $currentUser->id)
                        ->orWhereIn('user_id', $tenantIds);
                });
            } else {
                // Nếu không phải admin hoặc manager nhưng có param viewAll, vẫn chỉ hiện của họ
                $query->where('user_id', $currentUser->id);
            }
        }

        // Filter by specific user_id (nếu có quyền)
        if ($request->has('user_id')) {
            $userIdToFilter = $request->user_id;

            // Kiểm tra quyền để filter theo user_id
            $canFilterByUserId = false;

            if ($isAdmin) {
                // Admin có thể filter theo bất kỳ user_id nào
                $canFilterByUserId = true;
            } else if ($isManager && $this->isTenantManagedByManager($userIdToFilter, $currentUser->id)) {
                // Manager chỉ có thể filter theo user_id của tenant mà họ quản lý
                $canFilterByUserId = true;
            } else if ($userIdToFilter == $currentUser->id) {
                // Người dùng có thể filter theo chính họ
                $canFilterByUserId = true;
            }

            if ($canFilterByUserId) {
                $query->where('user_id', $userIdToFilter);
            }
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by read status
        if ($request->has('is_read')) {
            $isRead = filter_var($request->is_read, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_read', $isRead);
        }

        // Filter by date ranges
        if ($request->has('created_from')) {
            $query->where('created_at', '>=', $request->created_from);
        }

        if ($request->has('created_to')) {
            $query->where('created_at', '<=', $request->created_to);
        }

        // Include relationships
        $with = [];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('user', $includes)) $with[] = 'user';
        }

        if (!empty($with)) {
            $query->with($with);
        }

        // Pagination
        $perPage = $request->get('per_page', 10); // Default to 10 per page
        $page = $request->get('page', 1);

        // Sort by read status (unread first) and then by created_at desc
        $notifications = $query->orderBy('is_read', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->sendResponse(
            NotificationResource::collection($notifications)->response()->getData(true),
            'Notifications retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        $input = $request->all();
        $validator = Validator::make($input, [
            'user_id' => 'required|exists:users,id',
            'type' => 'required|string|max:50',
            'content' => 'required|string',
            'url' => 'nullable|string',
            'is_read' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $currentUser->role->code === 'manager';
        $isTenant = $currentUser->role->code === 'tenant';

        // Tenants cannot create notifications
        if ($isTenant) {
            return $this->sendError('Unauthorized. As a tenant, you can not create notifications.', [], 403);
        }

        // If manager, check if they can create notifications for this user
        if ($isManager && $input['user_id'] != $currentUser->id) {
            // Lấy thông tin người dùng để kiểm tra role
            $targetUser = User::find($input['user_id']);
            $isTargetAdmin = $targetUser && $targetUser->role->code === 'admin';
            
            // Managers có thể tạo thông báo cho admin hoặc tenant họ quản lý
            if (!$isTargetAdmin && !$this->isTenantManagedByManager($input['user_id'], $currentUser->id)) {
                return $this->sendError('You can only create notifications for admins or tenants you manage.', [], 403);
            }
        }

        $notification = Notification::create($input);

        return $this->sendResponse(
            new NotificationResource($notification),
            'Notification created successfully.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        $notification = Notification::find($id);

        if (is_null($notification)) {
            return $this->sendError('Notification not found.');
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
            if (!$isOwnNotification && !$this->isTenantManagedByManager($notification->user_id, $currentUser->id)) {
                return $this->sendError('Unauthorized. You can only view your own notifications or notifications of tenants you manage.', [], 403);
            }
        }
        // Others can only view their own notifications
        else if (!$isOwnNotification) {
            return $this->sendError('Unauthorized. You can only view your own notifications.', [], 403);
        }

        // Mark notification as read if the user is viewing their own notification
        if ($isOwnNotification && !$notification->is_read) {
            $notification->is_read = true;
            $notification->save();
        }

        return $this->sendResponse(
            new NotificationResource($notification),
            'Notification retrieved successfully.'
        );
    }

    /**
     * Update the specified notification.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        $notification = Notification::find($id);

        if (is_null($notification)) {
            return $this->sendError('Notification not found.');
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
            if (!$isOwnNotification && !$this->isTenantManagedByManager($notification->user_id, $currentUser->id)) {
                return $this->sendError('Unauthorized. You can only update your own notifications or notifications of tenants you manage.', [], 403);
            }
        }
        // Others can only update their own notifications
        else if (!$isOwnNotification) {
            return $this->sendError('Unauthorized. You can only update your own notifications.', [], 403);
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
                if ($this->isTenantManagedByManager($request->user_id, $currentUser->id)) {
                    $input['user_id'] = $request->user_id;
                } else {
                    return $this->sendError('Unauthorized. You can only assign notifications to tenants you manage.', [], 403);
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
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        // Update only provided fields, keeping the original user_id if not changed
        $notification->update($input);

        return $this->sendResponse(
            new NotificationResource($notification),
            'Notification updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        $notification = Notification::find($id);

        if (is_null($notification)) {
            return $this->sendError('Notification not found.');
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
            if (!$isOwnNotification && !$this->isTenantManagedByManager($notification->user_id, $currentUser->id)) {
                return $this->sendError('Unauthorized. You can only delete your own notifications or notifications of tenants you manage.', [], 403);
            }
        }
        // Others can only delete their own notifications
        else if (!$isOwnNotification) {
            return $this->sendError('Unauthorized. You can only delete your own notifications.', [], 403);
        }

        $notification->delete();

        return $this->sendResponse([], 'Notification deleted successfully.');
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $currentUser->role->code === 'manager';

        // If user_id is provided, try to mark notifications for that user as read
        if (request()->has('user_id')) {
            $userIdToMark = request()->user_id;

            // Admin can mark any user's notifications as read
            if ($isAdmin) {
                Notification::where('user_id', $userIdToMark)
                    ->where('is_read', false)
                    ->update(['is_read' => true]);
            }
            // Manager can mark their tenants' notifications as read
            else if ($isManager && $this->isTenantManagedByManager($userIdToMark, $currentUser->id)) {
                Notification::where('user_id', $userIdToMark)
                    ->where('is_read', false)
                    ->update(['is_read' => true]);
            }
            // Others can only mark their own notifications as read
            else if ($userIdToMark == $currentUser->id) {
                Notification::where('user_id', $currentUser->id)
                    ->where('is_read', false)
                    ->update(['is_read' => true]);
            } else {
                return $this->sendError('Unauthorized. You can only mark your own notifications as read.', [], 403);
            }
        }
        // No user_id provided, mark current user's notifications as read
        else {
            Notification::where('user_id', $currentUser->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        return $this->sendResponse([], 'All notifications marked as read.');
    }
}
