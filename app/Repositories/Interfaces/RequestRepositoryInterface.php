<?php

namespace App\Repositories\Interfaces;

use App\Models\Request;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface RequestRepositoryInterface
{
    /**
     * Get all requests with filters
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
     * Get request by ID
     *
     * @param string $id
     * @param array $with
     * @return Request|null
     */
    public function getById(string $id, array $with = []): ?Request;

    /**
     * Create a new request
     *
     * @param array $data
     * @return Request
     */
    public function create(array $data): Request;

    /**
     * Update a request
     *
     * @param string $id
     * @param array $data
     * @return Request
     */
    public function update(string $id, array $data): Request;

    /**
     * Delete a request
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool;

    /**
     * Check if user can access a request
     *
     * @param User $user
     * @param Request $request
     * @return bool
     */
    public function canAccessRequest(User $user, Request $request): bool;
}
