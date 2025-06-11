<?php

namespace App\Repositories\Interfaces;

use App\Models\Service;
use Illuminate\Pagination\LengthAwarePaginator;

interface ServiceRepositoryInterface
{
    /**
     * Lấy danh sách dịch vụ có áp dụng bộ lọc
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
     * Lấy dịch vụ theo ID
     *
     * @param int $id
     * @return Service|null
     */
    public function getById(int $id);
    
    /**
     * Tạo dịch vụ mới
     *
     * @param array $data
     * @return Service
     */
    public function create(array $data): Service;
    
    /**
     * Cập nhật dịch vụ
     *
     * @param Service $service
     * @param array $data
     * @return Service
     */
    public function update(Service $service, array $data): Service;
    
    /**
     * Xóa dịch vụ
     *
     * @param Service $service
     * @return bool
     */
    public function delete(Service $service): bool;
} 