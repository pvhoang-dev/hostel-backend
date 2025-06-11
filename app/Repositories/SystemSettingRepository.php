<?php

namespace App\Repositories;

use App\Models\SystemSetting;
use App\Repositories\Interfaces\SystemSettingRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class SystemSettingRepository implements SystemSettingRepositoryInterface
{
    protected $model;

    public function __construct(SystemSetting $model)
    {
        $this->model = $model;
    }

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
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'id', string $sortDirection = 'asc', int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Apply filters
        if (isset($filters['key'])) {
            $query->where('key', 'like', '%' . $filters['key'] . '%');
        }

        if (isset($filters['value'])) {
            $query->where('value', 'like', '%' . $filters['value'] . '%');
        }

        if (isset($filters['description'])) {
            $query->where('description', 'like', '%' . $filters['description'] . '%');
        }

        // Date range filters
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

        // Sorting
        $allowedSortFields = ['id', 'key', 'value', 'description', 'created_at', 'updated_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('id', 'asc');
        }

        return $query->with($with)->paginate($perPage);
    }

    /**
     * Lấy cài đặt theo ID
     *
     * @param int $id
     * @return SystemSetting|null
     */
    public function getById(int $id)
    {
        return $this->model->find($id);
    }

    /**
     * Tạo cài đặt mới
     *
     * @param array $data
     * @return SystemSetting
     */
    public function create(array $data): SystemSetting
    {
        return $this->model->create($data);
    }

    /**
     * Cập nhật cài đặt
     *
     * @param SystemSetting $setting
     * @param array $data
     * @return SystemSetting
     */
    public function update(SystemSetting $setting, array $data): SystemSetting
    {
        if (isset($data['key'])) {
            $setting->key = $data['key'];
        }
        if (isset($data['value'])) {
            $setting->value = $data['value'];
        }
        if (isset($data['description'])) {
            $setting->description = $data['description'];
        }

        $setting->save();
        return $setting;
    }

    /**
     * Xóa cài đặt
     *
     * @param SystemSetting $setting
     * @return bool
     */
    public function delete(SystemSetting $setting): bool
    {
        return $setting->delete();
    }
} 