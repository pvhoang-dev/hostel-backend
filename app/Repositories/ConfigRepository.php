<?php

namespace App\Repositories;

use App\Models\Config;
use App\Repositories\Interfaces\ConfigRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ConfigRepository implements ConfigRepositoryInterface
{
    protected $model;

    public function __construct(Config $model)
    {
        $this->model = $model;
    }

    /**
     * Lấy danh sách cấu hình theo bộ lọc
     *
     * @param array $filters Các bộ lọc
     * @param string $sortField Trường cần sắp xếp
     * @param string $sortDirection Hướng sắp xếp ('asc' hoặc 'desc')
     * @param int $perPage Số lượng kết quả mỗi trang
     * @return LengthAwarePaginator
     */
    public function getAllWithFilters(array $filters, string $sortField = 'id', string $sortDirection = 'asc', int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Lọc theo nhóm
        if (isset($filters['group'])) {
            $query->where('group', $filters['group']);
        }

        // Lọc theo trạng thái
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Lọc theo key
        if (isset($filters['key'])) {
            $query->where('key', 'like', '%' . $filters['key'] . '%');
        }

        // Sắp xếp
        $allowedSortFields = ['id', 'key', 'group', 'status', 'created_at', 'updated_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('group', 'asc')->orderBy('key', 'asc');
        }

        return $query->paginate($perPage);
    }
    
    /**
     * Lấy thông tin cấu hình theo ID
     *
     * @param int $id
     * @return Config|null
     */
    public function getById(int $id)
    {
        return $this->model->find($id);
    }
    
    /**
     * Tạo cấu hình mới
     *
     * @param array $data
     * @return Config
     */
    public function create(array $data): Config
    {
        return $this->model->create($data);
    }
    
    /**
     * Cập nhật cấu hình
     *
     * @param Config $config
     * @param array $data
     * @return Config
     */
    public function update(Config $config, array $data): Config
    {
        $config->update($data);
        return $config;
    }
    
    /**
     * Xóa cấu hình
     *
     * @param Config $config
     * @return bool
     */
    public function delete(Config $config): bool
    {
        return $config->delete();
    }
    
    /**
     * Lấy tất cả cấu hình của PayOS
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPayosConfigs()
    {
        return $this->model->where('group', 'payos')
            ->where('status', 'active')
            ->get();
    }
} 