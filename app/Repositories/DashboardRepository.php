<?php

namespace App\Repositories;

use App\Models\Contract;
use App\Models\House;
use App\Models\Room;
use App\Models\User;
use App\Repositories\Interfaces\DashboardRepositoryInterface;
use Illuminate\Support\Facades\DB;

class DashboardRepository implements DashboardRepositoryInterface
{
    /**
     * Lấy số lượng nhà
     *
     * @param User $user
     * @param array $filters
     * @return int
     */
    public function getHousesCount(User $user, array $filters = []): int
    {
        $isAdmin = $user->role->code === 'admin';
        $isManager = $user->role->code === 'manager';

        $query = House::query();

        if ($isManager && !$isAdmin) {
            // Managers can only see houses they manage
            $managedHouseIds = $user->managedHouses()->pluck('id')->toArray();
            $query->whereIn('id', $managedHouseIds);
        } elseif (!$isAdmin && !$isManager) {
            // Regular users/tenants can only see houses where they have contracts
            $query->whereHas('rooms.contracts', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Apply additional filters if provided
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->count();
    }

    /**
     * Lấy số lượng phòng
     *
     * @param User $user
     * @param array $filters
     * @return int
     */
    public function getRoomsCount(User $user, array $filters = []): int
    {
        $isAdmin = $user->role->code === 'admin';
        $isManager = $user->role->code === 'manager';

        $query = Room::query();

        if ($isManager && !$isAdmin) {
            // Managers can only see rooms in houses they manage
            $managedHouseIds = $user->managedHouses()->pluck('id')->toArray();
            $query->whereIn('house_id', $managedHouseIds);
        } elseif (!$isAdmin && !$isManager) {
            // Regular users/tenants can only see rooms where they have contracts
            $query->whereHas('contracts', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Apply additional filters if provided
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->count();
    }

    /**
     * Lấy số lượng người thuê
     *
     * @param User $user
     * @param array $filters
     * @return int
     */
    public function getTenantsCount(User $user, array $filters = []): int
    {
        $isAdmin = $user->role->code === 'admin';
        $isManager = $user->role->code === 'manager';

        $query = User::where('role_id', function ($q) {
            $q->select('id')->from('roles')->where('code', 'tenant');
        });

        if ($isManager && !$isAdmin) {
            // Managers can only see tenants with contracts in houses they manage
            $managedHouseIds = $user->managedHouses()->pluck('id')->toArray();
            $query->whereHas('contracts', function ($q) use ($managedHouseIds) {
                $q->whereHas('room', function ($subQuery) use ($managedHouseIds) {
                    $subQuery->whereIn('house_id', $managedHouseIds);
                });
            });
        } elseif (!$isAdmin && !$isManager) {
            // Regular users/tenants can only see themselves
            $query->where('id', $user->id);
        }

        // Apply additional filters if provided
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->count();
    }

    /**
     * Lấy số lượng hợp đồng
     *
     * @param User $user
     * @param array $filters
     * @return int
     */
    public function getContractsCount(User $user, array $filters = []): int
    {
        $isAdmin = $user->role->code === 'admin';
        $isManager = $user->role->code === 'manager';

        $query = Contract::query();

        if ($isManager && !$isAdmin) {
            // Managers can only see contracts in houses they manage
            $managedHouseIds = $user->managedHouses()->pluck('id')->toArray();
            $query->whereHas('room', function ($q) use ($managedHouseIds) {
                $q->whereIn('house_id', $managedHouseIds);
            });
        } elseif (!$isAdmin && !$isManager) {
            // Regular users/tenants can only see their own contracts
            $query->where('user_id', $user->id);
        }

        $query->where('status', 'active');

        return $query->count();
    }

    /**
     * Lấy thông tin hệ thống
     *
     * @return array
     */
    public function getSystemInfo(): array
    {
        return [
            'version' => config('app.version', '1.0.0'),
            'server_time' => now()->toDateTimeString(),
            'status' => 'active',
        ];
    }
}
