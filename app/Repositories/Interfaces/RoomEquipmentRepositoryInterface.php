<?php

namespace App\Repositories\Interfaces;

use App\Models\RoomEquipment;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface RoomEquipmentRepositoryInterface
{
    /**
     * Get all room equipment with filters
     *
     * @param array $filters
     * @param array $with
     * @param string $sortField
     * @param string $sortDirection
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'id', string $sortDirection = 'asc', int $perPage = 15): LengthAwarePaginator;

    /**
     * Get room equipment by ID
     *
     * @param string $id
     * @param array $with
     * @return RoomEquipment|null
     */
    public function getById(string $id, array $with = []): ?RoomEquipment;

    /**
     * Create new room equipment
     *
     * @param array $data
     * @return RoomEquipment
     */
    public function create(array $data): RoomEquipment;

    /**
     * Update room equipment
     *
     * @param string $id
     * @param array $data
     * @return RoomEquipment
     */
    public function update(string $id, array $data): RoomEquipment;

    /**
     * Delete room equipment
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool;

    /**
     * Find existing room equipment by room_id and equipment_id
     *
     * @param string $roomId
     * @param string $equipmentId
     * @return RoomEquipment|null
     */
    public function findByRoomAndEquipment(string $roomId, string $equipmentId): ?RoomEquipment;

    /**
     * Check if user can manage room equipment
     *
     * @param User $user
     * @param string $roomId
     * @return bool
     */
    public function canManageRoomEquipment(User $user, string $roomId): bool;
}
