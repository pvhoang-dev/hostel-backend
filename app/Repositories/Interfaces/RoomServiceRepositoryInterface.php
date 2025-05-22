<?php

namespace App\Repositories\Interfaces;

use App\Models\RoomService;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface RoomServiceRepositoryInterface
{
    /**
     * Get all room services with filters
     *
     * @param array $filters
     * @param array $with
     * @param string $sortField
     * @param string $sortDirection
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'created_at', string $sortDirection = 'desc', int $perPage = 15): LengthAwarePaginator;

    /**
     * Get room service by ID
     *
     * @param string $id
     * @param array $with
     * @return RoomService|null
     */
    public function getById(string $id, array $with = []): ?RoomService;

    /**
     * Create new room service
     *
     * @param array $data
     * @return RoomService
     */
    public function create(array $data): RoomService;

    /**
     * Update room service
     *
     * @param string $id
     * @param array $data
     * @return RoomService
     */
    public function update(string $id, array $data): RoomService;

    /**
     * Delete room service
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool;

    /**
     * Find existing room service by room_id and service_id
     *
     * @param string $roomId
     * @param string $serviceId
     * @return RoomService|null
     */
    public function findByRoomAndService(string $roomId, string $serviceId): ?RoomService;

    /**
     * Check if user can access room service
     *
     * @param User $user
     * @param RoomService $roomService
     * @return bool
     */
    public function canAccessRoomService(User $user, RoomService $roomService): bool;

    /**
     * Check if user can manage room service (update/delete)
     *
     * @param User $user
     * @param RoomService $roomService
     * @return bool
     */
    public function canManageRoomService(User $user, RoomService $roomService): bool;
}
