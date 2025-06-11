<?php

namespace App\Repositories;

use App\Models\House;
use App\Repositories\Interfaces\HouseRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class HouseRepository implements HouseRepositoryInterface
{
    protected $model;

    public function __construct(House $model)
    {
        $this->model = $model;
    }

    /**
     * Lấy danh sách nhà trọ với các bộ lọc
     */
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'created_at', string $sortDirection = 'desc', int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Phân quyền dựa trên vai trò
        if (isset($filters['current_user'])) {
            $user = $filters['current_user'];
            
            if ($user->role->code === 'manager') {
                // Manager chỉ thấy nhà họ quản lý
                $query->where('manager_id', $user->id);
            } elseif ($user->role->code === 'tenant') {
                // Tenant chỉ thấy nhà họ đang thuê thông qua hợp đồng active
                $housesOfTenant = House::whereHas('rooms', function($q) use ($user) {
                    $q->whereHas('contracts', function($q2) use ($user) {
                        $q2->where('status', 'active')
                          ->whereHas('users', function($q3) use ($user) {
                              $q3->where('users.id', $user->id);
                          });
                    });
                });
                
                // Lấy các house_id mà tenant có hợp đồng active
                $houseIds = $housesOfTenant->pluck('id')->toArray();
                
                if (empty($houseIds)) {
                    // Nếu không có nhà nào, trả về kết quả rỗng
                    return new LengthAwarePaginator([], 0, $perPage);
                }
                
                $query->whereIn('id', $houseIds);
            }
            // Admin có thể xem tất cả nhà
        }

        // Filter by name
        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        // Filter by status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by manager_id 
        if (isset($filters['manager_id'])) {
            $query->where('manager_id', $filters['manager_id']);
        }

        // Filter by address
        if (isset($filters['address'])) {
            $query->where('address', 'like', '%' . $filters['address'] . '%');
        }

        // Filter by created/updated date ranges
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
        $query->with($with);

        // Sorting
        $allowedSortFields = ['id', 'name', 'created_at', 'updated_at', 'status', 'address'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        return $query->paginate($perPage);
    }

    /**
     * Lấy thông tin nhà trọ theo ID
     */
    public function getById(string $id, array $with = [])
    {
        return $this->model->with($with)->findOrFail($id);
    }

    /**
     * Tạo nhà trọ mới
     */
    public function create(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * Cập nhật nhà trọ
     */
    public function update(string $id, array $data)
    {
        $house = $this->model->findOrFail($id);
        $house->update($data);
        return $house;
    }

    /**
     * Xóa nhà trọ
     */
    public function delete(string $id)
    {
        $house = $this->model->findOrFail($id);
        return $house->delete();
    }

    /**
     * Kiểm tra xem người dùng có quyền xem thông tin nhà trọ không
     */
    public function canViewHouse($user, $house)
    {
        if ($user->role->code === 'admin') {
            return true;
        }

        if ($user->role->code === 'manager' && $house->manager_id === $user->id) {
            return true;
        }

        if ($user->role->code === 'tenant') {
            return House::where('id', $house->id)
                ->whereHas('rooms', function($q) use ($user) {
                    $q->whereHas('contracts', function($q2) use ($user) {
                        $q2->where('status', 'active')
                          ->whereHas('users', function($q3) use ($user) {
                              $q3->where('users.id', $user->id);
                          });
                    });
                })
                ->exists();
        }

        return false;
    }

    /**
     * Kiểm tra xem người dùng có quyền quản lý nhà trọ không
     */
    public function canManageHouse($user, $house)
    {
        if ($user->role->code === 'admin') {
            return true;
        }

        if ($user->role->code === 'manager' && $house->manager_id === $user->id) {
            return true;
        }

        return false;
    }
} 