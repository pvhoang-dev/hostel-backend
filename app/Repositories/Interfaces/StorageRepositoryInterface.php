<?php

namespace App\Repositories\Interfaces;

use App\Models\EquipmentStorage;
use Illuminate\Pagination\LengthAwarePaginator;

interface StorageRepositoryInterface
{
    /**
     * Lấy danh sách kho thiết bị có áp dụng bộ lọc
     *
     * @param array $filters Các bộ lọc
     * @param array $with Các quan hệ cần eager loading
     * @param string $sortField Trường cần sắp xếp
     * @param string $sortDirection Hướng sắp xếp ('asc' hoặc 'desc')
     * @param int $perPage Số lượng kết quả mỗi trang
     * @return LengthAwarePaginator
     */
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'id', string $sortDirection = 'asc', int $perPage = 15): LengthAwarePaginator;
    
    /**
     * Lấy kho thiết bị theo ID
     *
     * @param int $id
     * @return EquipmentStorage|null
     */
    public function getById(int $id);
    
    /**
     * Tạo kho thiết bị mới
     *
     * @param array $data
     * @return EquipmentStorage
     */
    public function create(array $data): EquipmentStorage;
    
    /**
     * Cập nhật kho thiết bị
     *
     * @param EquipmentStorage $storage
     * @param array $data
     * @return EquipmentStorage
     */
    public function update(EquipmentStorage $storage, array $data): EquipmentStorage;
    
    /**
     * Xóa kho thiết bị
     *
     * @param EquipmentStorage $storage
     * @return bool
     */
    public function delete(EquipmentStorage $storage): bool;
} 