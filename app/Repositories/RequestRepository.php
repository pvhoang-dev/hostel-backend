<?php

namespace App\Repositories;

use App\Models\House;
use App\Models\Request;
use App\Models\User;
use App\Repositories\Interfaces\RequestRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class RequestRepository implements RequestRepositoryInterface
{
    protected $model;

    public function __construct(Request $model)
    {
        $this->model = $model;
    }

    /**
     * Get all requests with filters
     */
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'created_at', string $sortDirection = 'desc', int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Apply role-based filters
        if (isset($filters['user']) && $filters['user'] instanceof User) {
            $user = $filters['user'];

            if ($user->role->code === 'tenant') {
                // Tenants can only see requests they sent or received
                $query->where(function ($q) use ($user) {
                    $q->where('sender_id', $user->id)
                        ->orWhere('recipient_id', $user->id);
                });

                // Add room and house details to tenant requests if needed
                if (isset($filters['include_room_house']) && $filters['include_room_house'] === true) {
                    $query->with('room.house');
                }
            } elseif ($user->role->code === 'manager') {
                // Managers can see requests they sent/received or from their houses
                $managedHouseIds = House::where('manager_id', $user->id)->pluck('id');
                $query->where(function ($q) use ($user, $managedHouseIds) {
                    $q->where('sender_id', $user->id)
                        ->orWhere('recipient_id', $user->id);
                });

                // Add house details to manager requests if needed
                if (isset($filters['include_house']) && $filters['include_house'] === true) {
                    $query->with(['sender', 'recipient']);
                }
            }
            // Admins can see all requests, so no filter needed
        }

        // Filter by sender_id
        if (isset($filters['sender_id'])) {
            $query->where('sender_id', $filters['sender_id']);
        }

        // Filter by recipient_id
        if (isset($filters['recipient_id'])) {
            $query->where('recipient_id', $filters['recipient_id']);
        }

        // Filter by status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by request_type
        if (isset($filters['request_type'])) {
            $query->where('request_type', $filters['request_type']);
        }

        // Text search in description
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

        // Include relationships
        if (!empty($with)) {
            $query->with($with);
        }

        // Sorting
        $allowedSortFields = ['id', 'created_at', 'updated_at', 'status', 'request_type'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get request by ID
     */
    public function getById(string $id, array $with = []): ?Request
    {
        return $this->model->with($with)->find($id);
    }

    /**
     * Create a new request
     */
    public function create(array $data): Request
    {
        return $this->model->create($data);
    }

    /**
     * Update a request
     */
    public function update(string $id, array $data): Request
    {
        $request = $this->model->findOrFail($id);
        $request->update($data);
        return $request;
    }

    /**
     * Delete a request
     */
    public function delete(string $id): bool
    {
        $request = $this->model->findOrFail($id);
        return $request->delete();
    }

    /**
     * Check if user can access a request
     */
    public function canAccessRequest(User $user, Request $request): bool
    {
        // Admins can access all requests
        if ($user->role->code === 'admin') {
            return true;
        }

        // Tenants can only access requests they sent or received
        if ($user->role->code === 'tenant') {
            return $user->id === $request->sender_id || $user->id === $request->recipient_id;
        }

        // Managers can access requests they sent/received
        if ($user->role->code === 'manager') {
            return $user->id === $request->sender_id || $user->id === $request->recipient_id;
        }

        return false;
    }
}
