<?php

namespace App\Repositories\Interfaces;

use App\Models\Role;
use Illuminate\Pagination\LengthAwarePaginator;

interface RoleRepositoryInterface
{
    /**
     * Lấy danh sách role có áp dụng bộ lọc
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
     * Lấy role theo ID
     *
     * @param int $id
     * @return Role|null
     */
    public function getById(int $id);
    
    /**
     * Tạo role mới
     *
     * @param array $data
     * @return Role
     */
    public function create(array $data): Role;
    
    /**
     * Cập nhật role
     *
     * @param Role $role
     * @param array $data
     * @return Role
     */
    public function update(Role $role, array $data): Role;
    
    /**
     * Xóa role
     *
     * @param Role $role
     * @return bool
     */
    public function delete(Role $role): bool;
} 