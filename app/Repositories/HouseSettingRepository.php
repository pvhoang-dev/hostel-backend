<?php

namespace App\Repositories;

use App\Models\House;
use App\Models\HouseSetting;
use App\Repositories\Interfaces\HouseSettingRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class HouseSettingRepository implements HouseSettingRepositoryInterface
{
    protected $model;

    public function __construct(HouseSetting $model)
    {
        $this->model = $model;
    }

    /**
     * Lấy danh sách nội quy nhà có áp dụng bộ lọc
     */
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'key', string $sortDirection = 'desc', int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Apply role-based filters
        if (isset($filters['current_user']) && $filters['current_user']->role->code === 'manager') {
            // Managers can only see settings for houses they manage
            $query->whereHas('house', function ($q) use ($filters) {
                $q->where('manager_id', $filters['current_user']->id);
            });
        }
        // Admins can see all settings (no filter)
        // Tenants shouldn't see house settings

        // Filter by house_id
        if (isset($filters['house_id'])) {
            $query->where('house_id', $filters['house_id']);
        }

        // Filter by key
        if (isset($filters['key'])) {
            $query->where('key', 'like', '%' . $filters['key'] . '%');
        }

        // Filter by value
        if (isset($filters['value'])) {
            $query->where('value', 'like', '%' . $filters['value'] . '%');
        }

        // Filter by date ranges
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

        // Eager loading
        if (!empty($with)) {
            $query->with($with);
        }

        // Sorting
        $allowedSortFields = ['id', 'key', 'value', 'house_id', 'created_at', 'updated_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('key', 'asc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Lấy nội quy nhà theo ID
     */
    public function getById(int $id, array $with = [])
    {
        return $this->model->with($with)->find($id);
    }

    /**
     * Tạo nội quy nhà mới
     */
    public function create(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * Cập nhật nội quy nhà
     */
    public function update(int $id, array $data)
    {
        $setting = $this->model->find($id);
        if (!$setting) {
            return null;
        }
        
        $setting->update($data);
        return $setting;
    }

    /**
     * Xóa nội quy nhà
     */
    public function delete(int $id)
    {
        $setting = $this->model->find($id);
        if (!$setting) {
            return false;
        }
        
        return $setting->delete();
    }

    /**
     * Kiểm tra xem người dùng có quyền quản lý nội quy nhà không
     */
    public function canManageHouseSetting($user, $setting)
    {
        // Kiểm tra xem người dùng có phải admin không
        if ($user->role->code === 'admin') {
            return true;
        }

        // Kiểm tra xem người dùng có phải manager của nhà không
        if ($user->role->code === 'manager' && $setting->house && $setting->house->manager_id === $user->id) {
            return true;
        }

        return false;
    }
} 