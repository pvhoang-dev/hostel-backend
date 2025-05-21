<?php

namespace App\Repositories\Interfaces;

interface HouseRepositoryInterface
{
    /**
     * Lấy danh sách tòa nhà có áp dụng bộ lọc
     * 
     * @param array $filters
     * @param array $with
     * @param string $sortField
     * @param string $sortDirection
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'created_at', string $sortDirection = 'desc', int $perPage = 15);

    /**
     * Lấy tòa nhà theo ID
     * 
     * @param string $id
     * @param array $with
     * @return \App\Models\House
     */
    public function getById(string $id, array $with = []);

    /**
     * Tạo tòa nhà mới
     * 
     * @param array $data
     * @return \App\Models\House
     */
    public function create(array $data);

    /**
     * Cập nhật tòa nhà
     * 
     * @param string $id
     * @param array $data
     * @return \App\Models\House
     */
    public function update(string $id, array $data);

    /**
     * Xóa tòa nhà
     * 
     * @param string $id
     * @return bool
     */
    public function delete(string $id);

    /**
     * Kiểm tra xem người dùng có thể xem tòa nhà không
     * 
     * @param \App\Models\User $user
     * @param \App\Models\House $house
     * @return bool
     */
    public function canViewHouse($user, $house);

    /**
     * Kiểm tra xem người dùng có thể quản lý tòa nhà không
     * 
     * @param \App\Models\User $user
     * @param \App\Models\House $house
     * @return bool
     */
    public function canManageHouse($user, $house);
} 