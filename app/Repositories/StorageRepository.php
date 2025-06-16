<?php

namespace App\Repositories;

use App\Models\EquipmentStorage;
use App\Repositories\Interfaces\StorageRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class StorageRepository implements StorageRepositoryInterface
{
    protected $model;

    public function __construct(EquipmentStorage $model)
    {
        $this->model = $model;
    }

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
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'id', string $sortDirection = 'asc', int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Áp dụng bộ lọc cho người dùng dựa vào vai trò
        if (isset($filters['user']) && isset($filters['user_role'])) {
            $userRole = $filters['user_role'];

            if ($userRole === 'manager') {
                // Managers chỉ có thể xem kho thiết bị cho các nhà mà họ quản lý
                $managedHouseIds = $filters['managed_house_ids'] ?? [];
                $query->whereIn('house_id', $managedHouseIds);
            }
            // Admins có thể xem tất cả kho thiết bị (không cần lọc)
        }

        // Áp dụng bộ lọc bổ sung
        if (isset($filters['house_id'])) {
            $query->where('house_id', $filters['house_id']);
        }

        if (isset($filters['equipment_id'])) {
            $query->where('equipment_id', $filters['equipment_id']);
        }

        // Bộ lọc khoảng số lượng
        if (isset($filters['min_quantity'])) {
            $query->where('quantity', '>=', $filters['min_quantity']);
        }

        if (isset($filters['max_quantity'])) {
            $query->where('quantity', '<=', $filters['max_quantity']);
        }

        // Bộ lọc khoảng giá
        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        // Tìm kiếm theo mô tả
        if (isset($filters['description'])) {
            $query->where('description', 'like', '%' . $filters['description'] . '%');
        }

        // Bộ lọc khoảng thời gian
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
        $allowedSortFields = ['id', 'house_id', 'equipment_id', 'quantity', 'price', 'created_at', 'updated_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('id', 'asc');
        }

        return $query->with($with)->paginate($perPage);
    }

    /**
     * Lấy kho thiết bị theo ID
     *
     * @param int $id
     * @return EquipmentStorage|null
     */
    public function getById(int $id)
    {
        return $this->model->find($id);
    }

    /**
     * Tạo kho thiết bị mới
     *
     * @param array $data
     * @return EquipmentStorage
     */
    public function create(array $data): EquipmentStorage
    {
        return $this->model->with('house','equipment')->create($data);
    }

    /**
     * Cập nhật kho thiết bị
     *
     * @param EquipmentStorage $storage
     * @param array $data
     * @return EquipmentStorage
     */
    public function update(EquipmentStorage $storage, array $data): EquipmentStorage
    {
        $storage->update($data);
        return $storage;
    }

    /**
     * Xóa kho thiết bị
     *
     * @param EquipmentStorage $storage
     * @return bool
     */
    public function delete(EquipmentStorage $storage): bool
    {
        return $storage->delete();
    }
} 