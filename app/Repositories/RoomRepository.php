<?php

namespace App\Repositories;

use App\Models\Room;
use App\Repositories\Interfaces\RoomRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class RoomRepository implements RoomRepositoryInterface
{
    protected $model;

    public function __construct(Room $model)
    {
        $this->model = $model;
    }

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
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'id', string $sortDirection = 'asc', int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Áp dụng bộ lọc cho người dùng dựa vào vai trò
        if (isset($filters['user']) && isset($filters['user_role'])) {
            $user = $filters['user'];
            $userRole = $filters['user_role'];

            if ($userRole === 'manager') {
                // Managers chỉ có thể xem các phòng trong các nhà mà họ quản lý
                $managedHouseIds = $filters['managed_house_ids'] ?? [];
                $query->whereIn('house_id', $managedHouseIds);
            } elseif ($userRole === 'tenant') {
                // Tenants chỉ có thể xem các phòng mà họ đang thuê thông qua các hợp đồng đang hoạt động
                $activeContractRoomIds = $filters['active_contract_room_ids'] ?? [];
                if (empty($activeContractRoomIds)) {
                    // Nếu tenant không có bất kỳ hợp đồng đang hoạt động nào, trả về kết quả trống
                    return new LengthAwarePaginator([], 0, $perPage);
                }
                $query->whereIn('id', $activeContractRoomIds);
            }
        }

        // Áp dụng bộ lọc bổ sung
        if (isset($filters['house_id'])) {
            $query->where('house_id', $filters['house_id']);
        }

        if (isset($filters['room_number'])) {
            $query->where('room_number', 'like', '%' . $filters['room_number'] . '%');
        }

        if (isset($filters['capacity'])) {
            $query->where('capacity', $filters['capacity']);
        }

        if (isset($filters['min_capacity'])) {
            $query->where('capacity', '>=', $filters['min_capacity']);
        }

        if (isset($filters['max_capacity'])) {
            $query->where('capacity', '<=', $filters['max_capacity']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['min_price'])) {
            $query->where('base_price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('base_price', '<=', $filters['max_price']);
        }

        // Lọc theo khoảng thời gian
        if (isset($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }

        if (isset($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to']);
        }

        if (isset($filters['updated_from'])) {
            $query->where('updated_at', '>=', $filters['updated_from']);
        }

        if (isset($filters['updated_to'])) {
            $query->where('updated_at', '<=', $filters['updated_to']);
        }

        // Sắp xếp
        $allowedSortFields = ['id', 'house_id', 'room_number', 'capacity', 'base_price', 'status', 'created_at', 'updated_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->with($with)->paginate($perPage);
    }

    /**
     * Lấy phòng theo ID
     *
     * @param int $id
     * @return Room|null
     */
    public function getById(int $id)
    {
        return $this->model->with('currentContract.users')->find($id);
    }

    /**
     * Tạo phòng mới
     *
     * @param array $data
     * @return Room
     */
    public function create(array $data): Room
    {
        return $this->model->create($data);
    }

    /**
     * Cập nhật phòng
     *
     * @param Room $room
     * @param array $data
     * @return Room
     */
    public function update(Room $room, array $data): Room
    {
        $room->update($data);
        return $room;
    }

    /**
     * Xóa phòng
     *
     * @param Room $room
     * @return bool
     */
    public function delete(Room $room): bool
    {
        return $room->delete();
    }
} 