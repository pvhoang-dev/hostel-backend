<?php

namespace App\Repositories\Interfaces;

use App\Models\Room;
use Illuminate\Pagination\LengthAwarePaginator;

interface RoomRepositoryInterface
{
    /**
     * Lấy danh sách phòng có áp dụng bộ lọc
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
     * Lấy phòng theo ID
     *
     * @param int $id
     * @return Room|null
     */
    public function getById(int $id);
    
    /**
     * Tạo phòng mới
     *
     * @param array $data
     * @return Room
     */
    public function create(array $data): Room;
    
    /**
     * Cập nhật phòng
     *
     * @param Room $room
     * @param array $data
     * @return Room
     */
    public function update(Room $room, array $data): Room;
    
    /**
     * Xóa phòng
     *
     * @param Room $room
     * @return bool
     */
    public function delete(Room $room): bool;
} 