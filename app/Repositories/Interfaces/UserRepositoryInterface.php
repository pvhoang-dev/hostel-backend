<?php

namespace App\Repositories\Interfaces;

interface UserRepositoryInterface
{
    /**
     * Lấy danh sách người dùng có áp dụng bộ lọc
     * 
     * @param array $filters
     * @param array $with
     * @param string $sortField
     * @param string $sortDirection
     * @param int $perPage
     * @return void
     */
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'id', string $sortDirection = 'asc', int $perPage = 15);
   
    /**
     * Lấy người dùng theo ID
     * 
     * @param string $id
     * @param array $with
     * @return void
     */
    public function getById(string $id, array $with = []);

    /**
     * Tạo người dùng mới
     * 
     * @param array $data
     * @return void
     */
    public function create(array $data);

    /**
     * Cập nhật người dùng
     * 
     * @param string $id
     * @param array $data
     * @return void
     */
    public function update(string $id, array $data);

    /**
     * Xóa người dùng
     * 
     * @param string $id
     * @return void
     */
    public function delete(string $id);
    
    /**
     * Kiểm tra xem người dùng có thể xem người dùng khác không
     * 
     * @param mixed $currentUser
     * @param mixed $targetUser
     * @return void
     */
    public function canViewUser($currentUser, $targetUser);

    /**
     * Kiểm tra xem người dùng có thể xóa người dùng khác không
     * 
     * @param mixed $currentUser
     * @param mixed $targetUser
     * @return void
     */
    public function canDeleteUser($currentUser, $targetUser);

    /**
     * Lấy danh sách quản lý cho người thuê
     * 
     * @param mixed $tenantId
     * @return void
     */
    public function getManagersForTenant($tenantId);
    
    /**
     * Lấy danh sách người thuê cho quản lý
     * 
     * @param mixed $managerId
     * @return void
     */
    public function getTenantsForManager($managerId);
    
    /**
     * Lấy vai trò theo mã
     * 
     * @param mixed $roleCode
     * @return void
     */
    public function getRoleByCode($roleCode);
    
    /**
     * Lấy danh sách quản lý cho tòa nhà
     * 
     * @param array $houseIds
     * @return void
     */
    public function getManagersForHouse(array $houseIds);
    
    /**
     * Lấy danh sách người thuê cho tòa nhà
     * 
     * @param array $houseIds
     * @return void
     */
    public function getTenantsForHouses(array $houseIds);
    
    /**
     * Lấy danh sách người dùng không có hợp đồng hoạt động
     * 
     * @return void
     */
    public function getUsersWithoutActiveContract();
} 