<?php

namespace App\Repositories;

use App\Models\Role;
use App\Models\User;
use App\Models\House;
use App\Models\Contract;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepository implements UserRepositoryInterface
{
    protected $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    /**
     * Lấy danh sách người dùng có áp dụng bộ lọc
     */
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'id', string $sortDirection = 'asc', int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Apply filters
        if (isset($filters['username'])) {
            $query->where('username', 'like', '%' . $filters['username'] . '%');
        }

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (isset($filters['email'])) {
            $query->where('email', 'like', '%' . $filters['email'] . '%');
        }

        if (isset($filters['phone_number'])) {
            $query->where('phone_number', 'like', '%' . $filters['phone_number'] . '%');
        }

        if (isset($filters['hometown'])) {
            $query->where('hometown', 'like', '%' . $filters['hometown'] . '%');
        }

        if (isset($filters['identity_card'])) {
            $query->where('identity_card', 'like', '%' . $filters['identity_card'] . '%');
        }

        if (isset($filters['vehicle_plate'])) {
            $query->where('vehicle_plate', 'like', '%' . $filters['vehicle_plate'] . '%');
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['role_id'])) {
            $query->where('role_id', $filters['role_id']);
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

        if (isset($filters['role'])) {
            $roleCodes = is_array($filters['role']) ? $filters['role'] : explode(',', $filters['role']);
            $query->whereHas('role', function ($query) use ($roleCodes) {
                $query->whereIn('code', $roleCodes);
            });
        }

        // RBAC filters
        if (isset($filters['current_user'])) {
            $currentUser = $filters['current_user'];
            
            // Nếu là yêu cầu lấy danh sách người nhận cho Request
            if (isset($filters['for_requests']) && $filters['for_requests'] === true) {
                $this->applyRequestRecipientsFilters($query, $currentUser, $filters);
            } else {
                $this->applyRoleBasedFilters($query, $currentUser, $filters);
            }
        }

        // Lọc người dùng không có hợp đồng active
        if (isset($filters['without_active_contract']) && $filters['without_active_contract'] === true) {
            $query->whereDoesntHave('contracts', function ($query) {
                $query->where('status', 'active');
            });
        }

        // Eager Loading
        if (isset($filters['for_requests']) && $filters['for_requests'] === true && isset($filters['current_user'])) {
            $currentUser = $filters['current_user'];
            
            $this->applyEagerLoadingForRequests($query, $with, $currentUser);
        } else {
            $query->with($with);
        }

        // Sorting
        $allowedSortFields = ['id', 'username', 'name', 'email', 'status', 'role_id', 'created_at', 'updated_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('id', 'asc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Lấy thông tin người dùng theo ID
     */
    public function getById(string $id, array $with = [])
    {
        return $this->model->with($with)->findOrFail($id);
    }

    /**
     * Tạo người dùng mới
     */
    public function create(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * Cập nhật người dùng
     */
    public function update(string $id, array $data)
    {
        $user = $this->model->findOrFail($id);
        $user->update($data);
        return $user;
    }

    /**
     * Xóa người dùng
     */
    public function delete(string $id)
    {
        $user = $this->model->findOrFail($id);
        return $user->delete();
    }

    /**
     * Kiểm tra xem người dùng có quyền xem thông tin của người dùng khác không
     */
    public function canViewUser($currentUser, $targetUser)
    {
        if ($currentUser->id == $targetUser->id) {
            return true;
        }

        if ($currentUser->role?->code === 'admin') {
            return true;
        }

        if ($currentUser->role?->code === 'manager' && $targetUser->role?->code === 'tenant') {
            $managedHouseIds = $currentUser->housesManaged()->pluck('id')->toArray();
            
            return $targetUser->contracts()
                ->whereHas('room', function ($roomQuery) use ($managedHouseIds) {
                    $roomQuery->whereIn('house_id', $managedHouseIds);
                })
                ->exists();
        }

        if ($currentUser->role?->code === 'tenant' && $targetUser->role?->code === 'manager') {
            $activeContracts = $currentUser->contracts()->where('status', 'active')->with('room.house')->get();
            
            foreach ($activeContracts as $contract) {
                if ($contract->room && $contract->room->house && $contract->room->house->manager_id == $targetUser->id) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Kiểm tra xem người dùng có quyền xóa người dùng khác không
     */
    public function canDeleteUser($currentUser, $targetUser)
    {
        if ($currentUser->id == $targetUser->id) {
            return false;
        }

        if ($currentUser->role?->code === 'admin') {
            return true;
        }

        if ($currentUser->role?->code === 'manager') {
            $managedHouseIds = $currentUser->housesManaged()->pluck('id')->toArray();
            
            return $targetUser->role?->code === 'tenant' &&
                $targetUser->contracts()
                ->whereHas('room', function ($roomQuery) use ($managedHouseIds) {
                    $roomQuery->whereIn('house_id', $managedHouseIds);
                })
                ->exists();
        }

        return false;
    }

    /**
     * Lấy danh sách quản lý cho tenant
     */
    public function getManagersForTenant($tenantId)
    {
        $tenant = $this->model->with(['role', 'contracts' => function($query) {
            $query->where('status', 'active')->with('room.house.manager');
        }])->find($tenantId);
        
        if (!$tenant) {
            return null;
        }
        
        $managers = [];
        $managersIds = [];
        
        // Lấy manager từ các hợp đồng active
        if ($tenant->contracts && count($tenant->contracts) > 0) {
            foreach ($tenant->contracts as $contract) {
                if ($contract->room && $contract->room->house && $contract->room->house->manager) {
                    $manager = $contract->room->house->manager;
                    
                    // Tránh trùng lặp
                    if (!in_array($manager->id, $managersIds)) {
                        $managersIds[] = $manager->id;
                        $managers[] = [
                            'id' => $manager->id,
                            'name' => $manager->name,
                            'role' => $manager->role,
                            'house' => [
                                'id' => $contract->room->house->id,
                                'name' => $contract->room->house->name
                            ]
                        ];
                    }
                }
            }
        }
        
        // Thêm admin vào danh sách
        $admins = $this->model->whereHas('role', function($query) {
            $query->where('code', 'admin');
        })->get();
        
        foreach ($admins as $admin) {
            if (!in_array($admin->id, $managersIds)) {
                $managersIds[] = $admin->id;
                $managers[] = [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'role' => $admin->role,
                    'house' => [
                        'id' => 0,
                        'name' => 'Admin hệ thống'
                    ]
                ];
            }
        }
        
        return [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name
            ],
            'managers' => $managers
        ];
    }

    /**
     * Lấy danh sách tenant cho manager
     */
    public function getTenantsForManager($managerId)
    {
        $manager = $this->model->with('role')->find($managerId);
        
        if (!$manager || $manager->role?->code !== 'manager') {
            return null;
        }
        
        // Lấy danh sách nhà mà manager quản lý
        $houses = House::where('manager_id', $managerId)->pluck('id')->toArray();
        
        if (empty($houses)) {
            return [
                'manager' => [
                    'id' => $manager->id,
                    'name' => $manager->name
                ],
                'tenants' => []
            ];
        }
        
        // Lấy danh sách tenant qua hợp đồng
        $tenants = [];
        $tenantIds = [];
        
        // Lấy các tenant từ các contract có phòng thuộc nhà do manager quản lý
        $contracts = Contract::whereHas('room', function($query) use ($houses) {
            $query->whereIn('house_id', $houses);
        })->where('status', 'active')->with(['users' => function($query) {
            $query->whereHas('role', function($q) {
                $q->where('code', 'tenant');
            });
        }, 'room.house'])->get();
        
        foreach ($contracts as $contract) {
            foreach ($contract->users as $tenant) {
                if ($tenant->role?->code === 'tenant' && !in_array($tenant->id, $tenantIds)) {
                    $tenantIds[] = $tenant->id;
                    $tenants[] = [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'role' => $tenant->role,
                        'room' => $contract->room ? [
                            'id' => $contract->room->id,
                            'room_number' => $contract->room->room_number,
                            'house' => $contract->room->house ? [
                                'id' => $contract->room->house->id,
                                'name' => $contract->room->house->name
                            ] : null
                        ] : null
                    ];
                }
            }
        }
        
        return [
            'manager' => [
                'id' => $manager->id,
                'name' => $manager->name
            ],
            'tenants' => $tenants
        ];
    }

    /**
     * Lấy tất cả role
     */
    public function getRoleByCode($roleCode)
    {
        return Role::where('code', $roleCode)->first();
    }

    /**
     * Lấy danh sách manager cho nhà (Để tương thích ngược)
     */
    public function getManagersForHouse(array $houseIds)
    {
        return $this->model->whereHas('role', function ($query) {
            $query->where('code', 'manager');
        })
        ->whereHas('housesManaged', function ($query) use ($houseIds) {
            $query->whereIn('id', $houseIds);
        })
        ->get();
    }

    /**
     * Lấy danh sách tenant cho nhà (Để tương thích ngược)
     */
    public function getTenantsForHouses(array $houseIds)
    {
        return $this->model->whereHas('role', function ($query) {
            $query->where('code', 'tenant');
        })
        ->whereHas('contracts', function ($query) use ($houseIds) {
            $query->where('status', 'active')
                ->whereHas('room', function ($query) use ($houseIds) {
                    $query->whereIn('house_id', $houseIds);
                });
        })
        ->get();
    }

    /**
     * Lấy danh sách người dùng không có hợp đồng active (Để tương thích ngược)
     */
    public function getUsersWithoutActiveContract()
    {
        return $this->model->whereDoesntHave('contracts', function ($query) {
            $query->where('status', 'active');
        })->get();
    }

    /**
     * Áp dụng filter phân quyền dựa trên role
     */
    private function applyRoleBasedFilters($query, $currentUser, $filters)
    {
        if ($currentUser->role?->code === 'admin') {
            // Admin có thể thấy tất cả người dùng
            return;
        } elseif ($currentUser->role?->code === 'manager') {
            // Manager can see their own profile and tenants from houses they manage
            $managedHouseIds = $currentUser->housesManaged()->pluck('id')->toArray();

            $query->where(function ($q) use ($currentUser, $managedHouseIds) {
                // Manager can see their own profile
                $q->where('id', $currentUser->id)
                    // Or tenants from contracts in rooms of houses they manage
                    ->orWhere(function ($q2) use ($managedHouseIds) {
                        $q2->whereHas('role', function ($roleQuery) {
                            $roleQuery->where('code', 'tenant');
                        })
                            ->whereHas('contracts', function ($contractQuery) use ($managedHouseIds) {
                                $contractQuery->whereHas('room', function ($roomQuery) use ($managedHouseIds) {
                                    $roomQuery->whereIn('house_id', $managedHouseIds);
                                });
                            });
                    });
            });
        } else {
            // Other users (tenants) can only see their own profile
            $query->where('id', $currentUser->id);
        }
    }

    /**
     * Áp dụng filter cho danh sách người nhận request
     */
    private function applyRequestRecipientsFilters($query, $currentUser, $filters)
    {
        if ($currentUser->role?->code === 'admin') {
            // Admin có thể thấy tất cả người dùng
            return;
        } elseif ($currentUser->role?->code === 'manager') {
            $managedHouseIds = $currentUser->housesManaged()->pluck('id')->toArray();
            
            $query->where(function ($q) use ($currentUser, $managedHouseIds) {
                // Manager có thể thấy admin
                $q->whereHas('role', function ($roleQuery) {
                    $roleQuery->where('code', 'admin');
                })
                // Hoặc tenant từ nhà họ quản lý
                ->orWhere(function ($q2) use ($managedHouseIds, $currentUser) {
                    $q2->whereHas('role', function ($roleQuery) {
                        $roleQuery->where('code', 'tenant');
                    })
                    ->whereHas('contracts', function ($contractQuery) use ($managedHouseIds) {
                        $contractQuery->whereHas('room', function ($roomQuery) use ($managedHouseIds) {
                            $roomQuery->whereIn('house_id', $managedHouseIds);
                        });
                    });
                });
            });
        } else {
            // Tìm tất cả manager quản lý nhà mà tenant đang có hợp đồng
            $contracts = $currentUser->contracts()->where('status', 'active')->with('room.house')->get();
            $houseIds = [];
            
            // Lấy tất cả house_id mà tenant đang ở
            foreach($contracts as $contract) {
                if ($contract->room && $contract->room->house) {
                    $houseIds[] = $contract->room->house->id;
                }
            }
            
            // Lấy tất cả manager quản lý các nhà đó
            $query->whereHas('role', function ($roleQuery) {
                $roleQuery->where('code', 'manager');
            })
            ->whereHas('housesManaged', function ($houseQuery) use ($houseIds) {
                $houseQuery->whereIn('id', $houseIds);
            });
        }
    }

    /**
     * Áp dụng eager loading cho các request
     */
    private function applyEagerLoadingForRequests($query, $with, $currentUser)
    {
        foreach ($with as $relation) {
            $query->with($relation);
        }

        // Load hợp đồng đang active cho tenant
        $query->with(['contracts' => function($q) {
            $q->where('status', 'active');
        }]);
        
        // Load thông tin phòng và nhà từ contract
        $query->with(['contracts.room.house']);
        
        // Load thông tin nhà đang quản lý cho manager
        $query->with(['housesManaged']);
        
        // Đảm bảo eager load nested relationship cho cả admin và manager
        if ($currentUser->role?->code === 'admin' || $currentUser->role?->code === 'manager') {
            // Nếu đang lấy thông tin để hiển thị trong form request, lấy đầy đủ thông tin
            $query->where(function($q) {
                // Lấy tenant với thông tin phòng và nhà
                $q->whereHas('role', function($roleQ) {
                    $roleQ->where('code', 'tenant');
                })->with(['contracts' => function($contractQ) {
                    $contractQ->where('status', 'active')
                              ->with('room.house');
                }])
                // Hoặc lấy manager với thông tin nhà
                ->orWhereHas('role', function($roleQ) {
                    $roleQ->where('code', 'manager');
                })->with('housesManaged')
                // Hoặc lấy admin
                ->orWhereHas('role', function($roleQ) {
                    $roleQ->where('code', 'admin');
                });
            });
        }
    }
} 