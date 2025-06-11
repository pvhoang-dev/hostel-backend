<?php

namespace App\Repositories\Interfaces;

use App\Models\Equipment;
use Illuminate\Pagination\LengthAwarePaginator;

interface EquipmentRepositoryInterface
{
    /**
     * Lấy danh sách thiết bị có áp dụng bộ lọc
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
     * Lấy thiết bị theo ID
     *
     * @param int $id
     * @return Equipment|null
     */
    public function getById(int $id);
    
    /**
     * Tạo thiết bị mới
     *
     * @param array $data
     * @return Equipment
     */
    public function create(array $data): Equipment;
    
    /**
     * Cập nhật thiết bị
     *
     * @param Equipment $equipment
     * @param array $data
     * @return Equipment
     */
    public function update(Equipment $equipment, array $data): Equipment;
    
    /**
     * Xóa thiết bị
     *
     * @param Equipment $equipment
     * @return bool
     */
    public function delete(Equipment $equipment): bool;
} 