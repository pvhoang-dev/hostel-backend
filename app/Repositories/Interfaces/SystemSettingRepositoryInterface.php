<?php

namespace App\Repositories\Interfaces;

use App\Models\SystemSetting;
use Illuminate\Pagination\LengthAwarePaginator;

interface SystemSettingRepositoryInterface
{
    /**
     * Lấy danh sách cài đặt hệ thống có áp dụng bộ lọc
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
     * Lấy cài đặt theo ID
     *
     * @param int $id
     * @return SystemSetting|null
     */
    public function getById(int $id);
    
    /**
     * Tạo cài đặt mới
     *
     * @param array $data
     * @return SystemSetting
     */
    public function create(array $data): SystemSetting;
    
    /**
     * Cập nhật cài đặt
     *
     * @param SystemSetting $setting
     * @param array $data
     * @return SystemSetting
     */
    public function update(SystemSetting $setting, array $data): SystemSetting;
    
    /**
     * Xóa cài đặt
     *
     * @param SystemSetting $setting
     * @return bool
     */
    public function delete(SystemSetting $setting): bool;
} 