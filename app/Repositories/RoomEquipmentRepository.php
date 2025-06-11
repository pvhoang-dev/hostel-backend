<?php

namespace App\Repositories;

use App\Models\Room;
use App\Models\RoomEquipment;
use App\Models\User;
use App\Repositories\Interfaces\RoomEquipmentRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class RoomEquipmentRepository implements RoomEquipmentRepositoryInterface
{
    protected $model;

    public function __construct(RoomEquipment $model)
    {
        $this->model = $model;
    }

    /**
     * Get all room equipment with filters
     */
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'id', string $sortDirection = 'asc', int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Apply role-based filters
        if (isset($filters['user']) && $filters['user'] instanceof User) {
            $user = $filters['user'];

            if ($user->role->code === 'manager') {
                // Managers can only see equipment in rooms of houses they manage
                $query->whereHas('room.house', function ($q) use ($user) {
                    $q->where('manager_id', $user->id);
                });
            } elseif ($user->role->code === 'tenant') {
                // Tenants can only see equipment in rooms they occupy
                $query->whereHas('room.contracts.users', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                });
            }
            // Admins can see all equipment, so no filter needed
        }

        // Apply additional filters
        if (isset($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        if (isset($filters['equipment_id'])) {
            $query->where('equipment_id', $filters['equipment_id']);
        }

        if (isset($filters['min_quantity'])) {
            $query->where('quantity', '>=', $filters['min_quantity']);
        }

        if (isset($filters['max_quantity'])) {
            $query->where('quantity', '<=', $filters['max_quantity']);
        }

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        // Filter by date ranges
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
        $allowedSortFields = ['id', 'room_id', 'equipment_id', 'quantity', 'price', 'created_at', 'updated_at'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('id', 'asc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get room equipment by ID
     */
    public function getById(string $id, array $with = []): ?RoomEquipment
    {
        return $this->model->with($with)->find($id);
    }

    /**
     * Create new room equipment
     */
    public function create(array $data): RoomEquipment
    {
        return $this->model->create($data);
    }

    /**
     * Update room equipment
     */
    public function update(string $id, array $data): RoomEquipment
    {
        $roomEquipment = $this->model->findOrFail($id);
        $roomEquipment->update($data);
        return $roomEquipment;
    }

    /**
     * Delete room equipment
     */
    public function delete(string $id): bool
    {
        $roomEquipment = $this->model->findOrFail($id);
        return $roomEquipment->delete();
    }

    /**
     * Find existing room equipment by room_id and equipment_id
     */
    public function findByRoomAndEquipment(string $roomId, string $equipmentId): ?RoomEquipment
    {
        return $this->model->where('room_id', $roomId)
            ->where('equipment_id', $equipmentId)
            ->first();
    }

    /**
     * Check if user can manage room equipment
     */
    public function canManageRoomEquipment(User $user, string $roomId): bool
    {
        // Check if room exists
        $room = Room::with('house')->find($roomId);
        if (!$room) {
            return false;
        }

        // Admins can manage all room equipment
        if ($user->role->code === 'admin') {
            return true;
        }

        // Managers can manage equipment in rooms of houses they manage
        if ($user->role->code === 'manager') {
            return $room->house->manager_id === $user->id;
        }

        // Tenants cannot manage room equipment
        return false;
    }
}
