<?php

namespace App\Repositories\Interfaces;

use App\Models\Config;
use Illuminate\Pagination\LengthAwarePaginator;

interface ConfigRepositoryInterface
{
    /**
     * Lấy danh sách cấu hình theo bộ lọc
     *
     * @param array $filters Các bộ lọc
     * @param string $sortField Trường cần sắp xếp
     * @param string $sortDirection Hướng sắp xếp ('asc' hoặc 'desc')
     * @param int $perPage Số lượng kết quả mỗi trang
     * @return LengthAwarePaginator
     */
    public function getAllWithFilters(array $filters, string $sortField = 'id', string $sortDirection = 'asc', int $perPage = 15): LengthAwarePaginator;
    
    /**
     * Lấy thông tin cấu hình theo ID
     *
     * @param int $id
     * @return Config|null
     */
    public function getById(int $id);
    
    /**
     * Tạo cấu hình mới
     *
     * @param array $data
     * @return Config
     */
    public function create(array $data): Config;
    
    /**
     * Cập nhật cấu hình
     *
     * @param Config $config
     * @param array $data
     * @return Config
     */
    public function update(Config $config, array $data): Config;
    
    /**
     * Xóa cấu hình
     *
     * @param Config $config
     * @return bool
     */
    public function delete(Config $config): bool;
    
    /**
     * Lấy tất cả cấu hình của PayOS
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPayosConfigs();
} 