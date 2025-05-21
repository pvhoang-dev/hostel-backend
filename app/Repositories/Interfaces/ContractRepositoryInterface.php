<?php

namespace App\Repositories\Interfaces;

use App\Models\Contract;
use Illuminate\Pagination\LengthAwarePaginator;

interface ContractRepositoryInterface
{
    /**
     * Lấy danh sách hợp đồng theo các bộ lọc
     *
     * @param array $filters
     * @param array $with
     * @param string $sortField
     * @param string $sortDirection
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'created_at', string $sortDirection = 'desc', int $perPage = 10): LengthAwarePaginator;
    
    /**
     * Lấy thông tin hợp đồng theo ID
     *
     * @param int $id
     * @param array $with
     * @return Contract|null
     */
    public function getById(int $id, array $with = []);
    
    /**
     * Tạo hợp đồng mới
     *
     * @param array $data
     * @param array $userIds
     * @return Contract
     */
    public function create(array $data, array $userIds): Contract;
    
    /**
     * Cập nhật hợp đồng
     *
     * @param Contract $contract
     * @param array $data
     * @param array|null $userIds
     * @return Contract
     */
    public function update(Contract $contract, array $data, ?array $userIds = null): Contract;
    
    /**
     * Xóa hợp đồng
     *
     * @param Contract $contract
     * @return bool
     */
    public function delete(Contract $contract): bool;
    
    /**
     * Lấy danh sách người thuê có thể thuê phòng
     *
     * @param int $roomId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailableTenants(int $roomId);
} 