<?php

namespace App\Repositories;

use App\Models\Equipment;
use App\Repositories\Interfaces\EquipmentRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class EquipmentRepository implements EquipmentRepositoryInterface
{
    protected $model;

    public function __construct(Equipment $model)
    {
        $this->model = $model;
    }

    /**
     * Lấy danh sách thiết bị có áp dụng bộ lọc
     *
     * @param array $filters
     * @param array $with
     * @param string $sortField
     * @param string $sortDirection
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'id', string $sortDirection = 'asc', int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Apply filters
        if (isset($filters['name'])) {
            if (isset($filters['exact']) && $filters['exact']) {
                $query->where('name', $filters['name']);
            } else {
                $query->where('name', 'like', '%' . $filters['name'] . '%');
            }
        }

        if (isset($filters['room_id'])) {
            $query->whereHas('roomEquipments', function ($q) use ($filters) {
                $q->where('room_id', $filters['room_id']);
            });
        }

        if (isset($filters['storage_id'])) {
            $query->whereHas('storages', function ($q) use ($filters) {
                $q->where('storage_id', $filters['storage_id']);
            });
        }

        // Sorting
        $allowedSortFields = ['id', 'name', 'created_at', 'updated_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('id', 'asc');
        }

        return $query->with($with)->paginate($perPage);
    }

    /**
     * Lấy thiết bị theo ID
     *
     * @param int $id
     * @return Equipment|null
     */
    public function getById(int $id)
    {
        return $this->model->find($id);
    }

    /**
     * Tạo thiết bị mới
     *
     * @param array $data
     * @return Equipment
     */
    public function create(array $data): Equipment
    {
        return $this->model->create($data);
    }

    /**
     * Cập nhật thiết bị
     *
     * @param Equipment $equipment
     * @param array $data
     * @return Equipment
     */
    public function update(Equipment $equipment, array $data): Equipment
    {
        if (isset($data['name'])) {
            $equipment->name = $data['name'];
        }
        if (isset($data['description'])) {
            $equipment->description = $data['description'];
        }

        $equipment->save();
        return $equipment;
    }

    /**
     * Xóa thiết bị
     *
     * @param Equipment $equipment
     * @return bool
     */
    public function delete(Equipment $equipment): bool
    {
        return $equipment->delete();
    }
} 