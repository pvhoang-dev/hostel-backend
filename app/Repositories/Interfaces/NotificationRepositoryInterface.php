<?php

namespace App\Repositories\Interfaces;

interface NotificationRepositoryInterface
{
    /**
     * Get all notifications with filters
     * 
     * @param array $filters
     * @param array $with
     * @param string $sortField
     * @param string $sortDirection
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'created_at', string $sortDirection = 'desc', int $perPage = 10);

    /**
     * Get notification by ID
     * 
     * @param string $id
     * @param array $with
     * @return \App\Models\Notification
     */
    public function getById(string $id, array $with = []);

    /**
     * Create new notification
     * 
     * @param array $data
     * @return \App\Models\Notification
     */
    public function create(array $data);

    /**
     * Update notification
     * 
     * @param string $id
     * @param array $data
     * @return \App\Models\Notification
     */
    public function update(string $id, array $data);

    /**
     * Delete notification
     * 
     * @param string $id
     * @return bool
     */
    public function delete(string $id);

    /**
     * Mark all notifications as read for a specific user
     * 
     * @param int $userId
     * @return bool
     */
    public function markAllAsRead(int $userId);

    /**
     * Get tenant IDs managed by a manager
     * 
     * @param int $managerId
     * @return array
     */
    public function getManagedTenantIds(int $managerId);

    /**
     * Check if a tenant is managed by the manager
     * 
     * @param int $tenantId
     * @param int $managerId
     * @return bool
     */
    public function isTenantManagedByManager(int $tenantId, int $managerId);
}
