<?php

namespace App\Repositories\Interfaces;

use App\Models\ServiceUsage;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface ServiceUsageRepositoryInterface
{
    /**
     * Get all service usages with filters
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
     * Get service usage by ID
     *
     * @param string $id
     * @param array $with
     * @return ServiceUsage|null
     */
    public function getById(string $id, array $with = []): ?ServiceUsage;

    /**
     * Create new service usage
     *
     * @param array $data
     * @return ServiceUsage
     */
    public function create(array $data): ServiceUsage;

    /**
     * Update service usage
     *
     * @param string $id
     * @param array $data
     * @return ServiceUsage
     */
    public function update(string $id, array $data): ServiceUsage;

    /**
     * Delete service usage
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool;

    /**
     * Find existing service usage by room_service_id, month and year
     *
     * @param string $roomServiceId
     * @param int $month
     * @param int $year
     * @return ServiceUsage|null
     */
    public function findByRoomServiceAndPeriod(string $roomServiceId, int $month, int $year): ?ServiceUsage;

    /**
     * Check if user can access service usage
     *
     * @param User $user
     * @param ServiceUsage $serviceUsage
     * @return bool
     */
    public function canAccessServiceUsage(User $user, ServiceUsage $serviceUsage): bool;

    /**
     * Check if user can manage service usage (update/delete)
     *
     * @param User $user
     * @param ServiceUsage $serviceUsage
     * @return bool
     */
    public function canManageServiceUsage(User $user, ServiceUsage $serviceUsage): bool;
}
