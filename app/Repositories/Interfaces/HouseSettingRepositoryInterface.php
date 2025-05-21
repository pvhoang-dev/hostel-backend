<?php

namespace App\Repositories\Interfaces;

interface HouseSettingRepositoryInterface
{
    /**
     * Lấy danh sách nội quy nhà có áp dụng bộ lọc
     * 
     * @param array $filters
     * @param array $with
     * @param string $sortField
     * @param string $sortDirection
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'key', string $sortDirection = 'desc', int $perPage = 15);

    /**
     * Lấy nội quy nhà theo ID
     * 
     * @param int $id
     * @param array $with
     * @return \App\Models\HouseSetting|null
     */
    public function getById(int $id, array $with = []);

    /**
     * Tạo nội quy nhà mới
     * 
     * @param array $data
     * @return \App\Models\HouseSetting
     */
    public function create(array $data);

    /**
     * Cập nhật nội quy nhà
     * 
     * @param int $id
     * @param array $data
     * @return \App\Models\HouseSetting
     */
    public function update(int $id, array $data);

    /**
     * Xóa nội quy nhà
     * 
     * @param int $id
     * @return bool
     */
    public function delete(int $id);

    /**
     * Kiểm tra xem người dùng có quyền quản lý nội quy nhà không
     * 
     * @param \App\Models\User $user
     * @param \App\Models\HouseSetting $setting
     * @return bool
     */
    public function canManageHouseSetting($user, $setting);
} 