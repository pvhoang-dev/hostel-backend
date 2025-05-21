<?php

namespace App\Repositories;

use App\Models\Service;
use App\Repositories\Interfaces\ServiceRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ServiceRepository implements ServiceRepositoryInterface
{
    protected $model;

    public function __construct(Service $model)
    {
        $this->model = $model;
    }

    /**
     * Lấy danh sách dịch vụ có áp dụng bộ lọc
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

        // Áp dụng bộ lọc
        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (isset($filters['unit'])) {
            $query->where('unit', 'like', '%' . $filters['unit'] . '%');
        }

        if (isset($filters['is_metered']) && in_array($filters['is_metered'], ['0', '1'])) {
            $query->where('is_metered', $filters['is_metered']);
        }

        // Bộ lọc khoảng giá
        if (isset($filters['min_price'])) {
            $query->where('default_price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('default_price', '<=', $filters['max_price']);
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
        $allowedSortFields = ['id', 'name', 'default_price', 'unit', 'is_metered', 'created_at', 'updated_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('id', 'asc');
        }

        return $query->with($with)->paginate($perPage);
    }

    /**
     * Lấy dịch vụ theo ID
     *
     * @param int $id
     * @return Service|null
     */
    public function getById(int $id)
    {
        return $this->model->find($id);
    }

    /**
     * Tạo dịch vụ mới
     *
     * @param array $data
     * @return Service
     */
    public function create(array $data): Service
    {
        return $this->model->create($data);
    }

    /**
     * Cập nhật dịch vụ
     *
     * @param Service $service
     * @param array $data
     * @return Service
     */
    public function update(Service $service, array $data): Service
    {
        $service->fill($data);
        $service->save();
        return $service;
    }

    /**
     * Xóa dịch vụ
     *
     * @param Service $service
     * @return bool
     */
    public function delete(Service $service): bool
    {
        return $service->delete();
    }
} 