<?php

namespace App\Repositories;

use App\Models\House;
use App\Models\RoomService;
use App\Models\User;
use App\Repositories\Interfaces\RoomServiceRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class RoomServiceRepository implements RoomServiceRepositoryInterface
{
    protected $model;

    public function __construct(RoomService $model)
    {
        $this->model = $model;
    }

    /**
     * Get all room services with filters
     */
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'created_at', string $sortDirection = 'desc', int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Apply role-based filters
        if (isset($filters['user']) && $filters['user'] instanceof User) {
            $user = $filters['user'];

            if ($user->role->code === 'tenant') {
                // Tenants can only see services for rooms they occupy
                $query->whereHas('room.contracts.users', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                });
            } elseif ($user->role->code === 'manager') {
                // Managers can see services for houses they manage
                $managedHouseIds = House::where('manager_id', $user->id)->pluck('id');
                $query->whereHas('room', function ($q) use ($managedHouseIds) {
                    $q->whereIn('house_id', $managedHouseIds);
                });
            }
            // Admins can see all services, so no filter needed
        }

        // Apply additional filters
        if (isset($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        if (isset($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['is_fixed'])) {
            $query->where('is_fixed', $filters['is_fixed']);
        }

        // Price range filters
        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        // Text search in description
        if (isset($filters['description'])) {
            $query->where('description', 'like', '%' . $filters['description'] . '%');
        }

        // Date range filters
        if (isset($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }

        if (isset($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to']);
        }

        if (isset($filters['updated_from'])) {
            $query->where('updated_at', '>=', $filters['updated_from']);
        }

        if (isset($filters['updated_to'])) {
            $query->where('updated_at', '<=', $filters['updated_to']);
        }

        // Include relationships
        if (!empty($with)) {
            $query->with($with);
        }

        // Sorting
        $allowedSortFields = ['id', 'room_id', 'service_id', 'price', 'is_fixed', 'status', 'created_at', 'updated_at'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get room service by ID
     */
    public function getById(string $id, array $with = []): ?RoomService
    {
        return $this->model->with($with)->find($id);
    }

    /**
     * Create new room service
     */
    public function create(array $data): RoomService
    {
        return $this->model->create($data);
    }

    /**
     * Update room service
     */
    public function update(string $id, array $data): RoomService
    {
        $roomService = $this->model->findOrFail($id);
        $roomService->update($data);
        return $roomService;
    }

    /**
     * Delete room service
     */
    public function delete(string $id): bool
    {
        $roomService = $this->model->findOrFail($id);
        return $roomService->delete();
    }

    /**
     * Find existing room service by room_id and service_id
     */
    public function findByRoomAndService(string $roomId, string $serviceId): ?RoomService
    {
        return $this->model->where('room_id', $roomId)
            ->where('service_id', $serviceId)
            ->first();
    }

    /**
     * Check if user can access room service
     */
    public function canAccessRoomService(User $user, RoomService $roomService): bool
    {
        // Admins can access all room services
        if ($user->role->code === 'admin') {
            return true;
        }

        // Tenants can only access room services for rooms they occupy
        if ($user->role->code === 'tenant') {
            return $roomService->room->contracts()
                ->whereHas('users', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                })
                ->exists();
        }

        // Managers can access room services for houses they manage
        if ($user->role->code === 'manager') {
            return $user->id === $roomService->room->house->manager_id;
        }

        return false;
    }

    /**
     * Check if user can manage room service (update/delete)
     */
    public function canManageRoomService(User $user, RoomService $roomService): bool
    {
        // Admins can manage all room services
        if ($user->role->code === 'admin') {
            return true;
        }

        // Tenants cannot manage room services
        if ($user->role->code === 'tenant') {
            return false;
        }

        // Managers can manage room services for houses they manage
        if ($user->role->code === 'manager') {
            return $user->id === $roomService->room->house->manager_id;
        }

        return false;
    }
}
