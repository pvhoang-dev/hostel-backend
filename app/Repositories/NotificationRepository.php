<?php

namespace App\Repositories;

use App\Models\House;
use App\Models\Notification;
use App\Models\Room;
use App\Models\User;
use App\Repositories\Interfaces\NotificationRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationRepository implements NotificationRepositoryInterface
{
    protected $model;

    public function __construct(Notification $model)
    {
        $this->model = $model;
    }

    /**
     * Get all notifications with filters
     */
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'created_at', string $sortDirection = 'desc', int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->model->query();

        $currentUser = $filters['current_user'] ?? null;
        if (!$currentUser) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $currentUser->role->code === 'manager';
        $viewAll = $filters['viewAll'] ?? false;

        if (!$viewAll) {
            // User only sees their own notifications
            $query->where('user_id', $currentUser->id);
        } else {
            if ($isAdmin) {
                // Admin can see all notifications - no additional filters
            } elseif ($isManager) {
                // Manager can only see notifications of tenants they manage
                $tenantIds = $this->getManagedTenantIds($currentUser->id);

                $query->where(function ($query) use ($currentUser, $tenantIds) {
                    $query->where('user_id', $currentUser->id)
                        ->orWhereIn('user_id', $tenantIds);
                });
            } else {
                // Others can only see their own notifications
                $query->where('user_id', $currentUser->id);
            }
        }

        // Filter by specific user_id
        if (isset($filters['user_id'])) {
            $userIdToFilter = $filters['user_id'];
            $canFilterByUserId = false;

            if ($isAdmin) {
                $canFilterByUserId = true;
            } elseif ($isManager && $this->isTenantManagedByManager($userIdToFilter, $currentUser->id)) {
                $canFilterByUserId = true;
            } elseif ($userIdToFilter == $currentUser->id) {
                $canFilterByUserId = true;
            }

            if ($canFilterByUserId) {
                $query->where('user_id', $userIdToFilter);
            }
        }

        // Filter by type
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Filter by read status
        if (isset($filters['is_read'])) {
            $query->where('is_read', $filters['is_read']);
        }

        // Filter by date ranges
        if (isset($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }

        if (isset($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to']);
        }

        // Include relationships
        if (!empty($with)) {
            $query->with($with);
        }

        // Sorting
        $allowedSortFields = ['id', 'created_at', 'updated_at', 'type', 'is_read'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            // Default sort by read status (unread first) and then by created_at desc
            $query->orderBy('is_read', 'asc')
                ->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get notification by ID
     */
    public function getById(string $id, array $with = [])
    {
        return $this->model->with($with)->findOrFail($id);
    }

    /**
     * Create new notification
     */
    public function create(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * Update notification
     */
    public function update(string $id, array $data)
    {
        $notification = $this->model->findOrFail($id);
        $notification->update($data);
        return $notification;
    }

    /**
     * Delete notification
     */
    public function delete(string $id)
    {
        $notification = $this->model->findOrFail($id);
        return $notification->delete();
    }

    /**
     * Mark all notifications as read for a specific user
     */
    public function markAllAsRead(int $userId)
    {
        return $this->model->where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    /**
     * Get tenant IDs managed by a manager
     */
    public function getManagedTenantIds(int $managerId)
    {
        // Get houses managed by this manager
        $managedHouseIds = House::where('manager_id', $managerId)->pluck('id')->toArray();

        // Get rooms from those houses
        $managedRoomIds = Room::whereIn('house_id', $managedHouseIds)->pluck('id')->toArray();

        // Get tenants from those rooms
        return User::whereHas('contracts', function ($query) use ($managedRoomIds) {
            $query->whereIn('room_id', $managedRoomIds)->where('status', 'active');
        })->where('role_id', function ($query) {
            $query->select('id')->from('roles')->where('code', 'tenant');
        })->pluck('id')->toArray();
    }

    /**
     * Check if a tenant is managed by the manager
     */
    public function isTenantManagedByManager(int $tenantId, int $managerId)
    {
        $managedTenantIds = $this->getManagedTenantIds($managerId);
        return in_array($tenantId, $managedTenantIds);
    }
}
