<?php

namespace App\Repositories;

use App\Models\House;
use App\Models\ServiceUsage;
use App\Models\User;
use App\Repositories\Interfaces\ServiceUsageRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ServiceUsageRepository implements ServiceUsageRepositoryInterface
{
    protected $model;

    public function __construct(ServiceUsage $model)
    {
        $this->model = $model;
    }

    /**
     * Get all service usages with filters
     */
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'created_at', string $sortDirection = 'desc', int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Apply role-based filters
        if (isset($filters['user']) && $filters['user'] instanceof User) {
            $user = $filters['user'];

            if ($user->role->code === 'tenant') {
                // Tenants can only see service usages for rooms they occupy
                $query->whereHas('roomService.room.contracts.users', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                });
            } elseif ($user->role->code === 'manager') {
                // Managers can see service usages for houses they manage
                $managedHouseIds = House::where('manager_id', $user->id)->pluck('id');
                $query->whereHas('roomService.room', function ($q) use ($managedHouseIds) {
                    $q->whereIn('house_id', $managedHouseIds);
                });
            }
            // Admins can see all service usages, so no filter needed
        }

        // Apply additional filters
        if (isset($filters['room_service_id'])) {
            $query->where('room_service_id', $filters['room_service_id']);
        }

        if (isset($filters['room_id'])) {
            $query->whereHas('roomService', function ($q) use ($filters) {
                $q->where('room_id', $filters['room_id']);
            });
        }

        if (isset($filters['service_id'])) {
            $query->whereHas('roomService', function ($q) use ($filters) {
                $q->where('service_id', $filters['service_id']);
            });
        }

        if (isset($filters['month'])) {
            $query->where('month', $filters['month']);
        }

        if (isset($filters['year'])) {
            $query->where('year', $filters['year']);
        }

        // Usage value range filters
        if (isset($filters['min_usage'])) {
            $query->where('usage_value', '>=', $filters['min_usage']);
        }

        if (isset($filters['max_usage'])) {
            $query->where('usage_value', '<=', $filters['max_usage']);
        }

        // Price used range filters
        if (isset($filters['min_price'])) {
            $query->where('price_used', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price_used', '<=', $filters['max_price']);
        }

        // Meter reading filters
        if (isset($filters['min_start_meter'])) {
            $query->where('start_meter', '>=', $filters['min_start_meter']);
        }

        if (isset($filters['max_start_meter'])) {
            $query->where('start_meter', '<=', $filters['max_start_meter']);
        }

        if (isset($filters['min_end_meter'])) {
            $query->where('end_meter', '>=', $filters['min_end_meter']);
        }

        if (isset($filters['max_end_meter'])) {
            $query->where('end_meter', '<=', $filters['max_end_meter']);
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

        // Include relationships
        if (!empty($with)) {
            $query->with($with);
        }

        // Sorting
        $allowedSortFields = [
            'id',
            'room_service_id',
            'start_meter',
            'end_meter',
            'usage_value',
            'month',
            'year',
            'price_used',
            'created_at',
            'updated_at'
        ];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('year', 'desc')->orderBy('month', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get service usage by ID
     */
    public function getById(string $id, array $with = []): ?ServiceUsage
    {
        return $this->model->with($with)->find($id);
    }

    /**
     * Create new service usage
     */
    public function create(array $data): ServiceUsage
    {
        return $this->model->create($data);
    }

    /**
     * Update service usage
     */
    public function update(string $id, array $data): ServiceUsage
    {
        $serviceUsage = $this->model->findOrFail($id);
        $serviceUsage->update($data);
        return $serviceUsage;
    }

    /**
     * Delete service usage
     */
    public function delete(string $id): bool
    {
        $serviceUsage = $this->model->findOrFail($id);
        return $serviceUsage->delete();
    }

    /**
     * Find existing service usage by room_service_id, month and year
     */
    public function findByRoomServiceAndPeriod(string $roomServiceId, int $month, int $year): ?ServiceUsage
    {
        return $this->model->where('room_service_id', $roomServiceId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();
    }

    /**
     * Check if user can access service usage
     */
    public function canAccessServiceUsage(User $user, ServiceUsage $serviceUsage): bool
    {
        // Admins can access all service usages
        if ($user->role->code === 'admin') {
            return true;
        }

        // Tenants can only access service usages for rooms they occupy
        if ($user->role->code === 'tenant') {
            return $serviceUsage->roomService->room->contracts()
                ->whereHas('users', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                })
                ->exists();
        }

        // Managers can access service usages for houses they manage
        if ($user->role->code === 'manager') {
            return $user->id === $serviceUsage->roomService->room->house->manager_id;
        }

        return false;
    }

    /**
     * Check if user can manage service usage (update/delete)
     */
    public function canManageServiceUsage(User $user, ServiceUsage $serviceUsage): bool
    {
        // Admins can manage all service usages
        if ($user->role->code === 'admin') {
            return true;
        }

        // Tenants cannot manage service usages
        if ($user->role->code === 'tenant') {
            return false;
        }

        // Managers can manage service usages for houses they manage
        if ($user->role->code === 'manager') {
            return $user->id === $serviceUsage->roomService->room->house->manager_id;
        }

        return false;
    }
}
