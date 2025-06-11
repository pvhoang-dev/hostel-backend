<?php

namespace App\Repositories;

use App\Models\Contract;
use App\Models\ContractUser;
use App\Models\User;
use App\Repositories\Interfaces\ContractRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ContractRepository implements ContractRepositoryInterface
{
    protected $model;
    
    public function __construct(Contract $model)
    {
        $this->model = $model;
    }
    
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
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'created_at', string $sortDirection = 'desc', int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->model->query();
        
        // Lọc theo trạng thái
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        // Lọc theo nhà
        if (isset($filters['house_id'])) {
            $query->whereHas('room', function ($q) use ($filters) {
                $q->where('house_id', $filters['house_id']);
            });
        }
        
        // Lọc theo phòng
        if (isset($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }
        
        // Lọc theo ngày bắt đầu
        if (isset($filters['start_date_from'])) {
            $query->where('start_date', '>=', $filters['start_date_from']);
        }
        
        if (isset($filters['start_date_to'])) {
            $query->where('start_date', '<=', $filters['start_date_to']);
        }
        
        // Lọc theo ngày kết thúc
        if (isset($filters['end_date_from'])) {
            $query->where('end_date', '>=', $filters['end_date_from']);
        }
        
        if (isset($filters['end_date_to'])) {
            $query->where('end_date', '<=', $filters['end_date_to']);
        }
        
        // Lọc theo trạng thái cọc
        if (isset($filters['deposit_status'])) {
            $query->where('deposit_status', $filters['deposit_status']);
        }
        
        // Lọc theo tự động gia hạn
        if (isset($filters['auto_renew'])) {
            $query->where('auto_renew', $filters['auto_renew'] === 'true');
        }
        
        // Lọc theo vai trò người dùng
        if (isset($filters['user_role'])) {
            if ($filters['user_role'] === 'tenant' && isset($filters['user_id'])) {
                $query->whereHas('users', function ($q) use ($filters) {
                    $q->where('users.id', $filters['user_id']);
                });
            } elseif ($filters['user_role'] === 'manager' && isset($filters['manager_id'])) {
                $query->whereHas('room.house', function ($q) use ($filters) {
                    $q->where('houses.manager_id', $filters['manager_id']);
                });
            }
        }
        
        // Lọc theo người dùng cụ thể
        if (isset($filters['user_id']) && !isset($filters['user_role'])) {
            $query->whereHas('users', function ($q) use ($filters) {
                $q->where('users.id', $filters['user_id']);
            });
        }
        
        // Eager loading
        if (!empty($with)) {
            $query->with($with);
        }
        
        // Sắp xếp
        $allowedSortFields = ['created_at', 'start_date', 'end_date', 'monthly_price'];
        
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }
        
        return $query->paginate($perPage);
    }
    
    /**
     * Lấy thông tin hợp đồng theo ID
     *
     * @param int $id
     * @param array $with
     * @return Contract|null
     */
    public function getById(int $id, array $with = [])
    {
        $query = $this->model->newQuery();
        
        if (!empty($with)) {
            $query->with($with);
        }
        
        return $query->find($id);
    }
    
    /**
     * Tạo hợp đồng mới
     *
     * @param array $data
     * @param array $userIds
     * @return Contract
     */
    public function create(array $data, array $userIds): Contract
    {
        DB::beginTransaction();
        try {
            $contract = $this->model->create($data);
            
            foreach ($userIds as $userId) {
                ContractUser::create([
                    'contract_id' => $contract->id,
                    'user_id' => $userId
                ]);
            }
            
            DB::commit();
            
            if (isset($data['load_relations']) && $data['load_relations']) {
                $contract->load(['room', 'users', 'creator']);
            }
            
            return $contract;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Cập nhật hợp đồng
     *
     * @param Contract $contract
     * @param array $data
     * @param array|null $userIds
     * @return Contract
     */
    public function update(Contract $contract, array $data, ?array $userIds = null): Contract
    {
        DB::beginTransaction();
        try {
            $contract->update($data);
            
            if ($userIds !== null) {
                ContractUser::where('contract_id', $contract->id)->forceDelete();
                
                foreach ($userIds as $userId) {
                    ContractUser::create([
                        'contract_id' => $contract->id,
                        'user_id' => $userId
                    ]);
                }
            }
            
            DB::commit();
            
            if (isset($data['load_relations']) && $data['load_relations']) {
                $contract->load(['room', 'creator', 'users', 'updater']);
            }
            
            return $contract;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Xóa hợp đồng
     *
     * @param Contract $contract
     * @return bool
     */
    public function delete(Contract $contract): bool
    {
        return $contract->delete();
    }
    
    /**
     * Lấy danh sách người thuê có thể thuê phòng
     *
     * @param int $roomId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailableTenants(int $roomId)
    {
        return User::whereHas('role', function($query) {
            $query->where('code', 'tenant');
        })
        ->whereDoesntHave('contracts', function($query) {
            $query->where('status', 'active');
        })
        ->with('role')
        ->get();
    }
} 