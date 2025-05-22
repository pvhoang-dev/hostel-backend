<?php

namespace App\Repositories;

use App\Models\Request;
use App\Models\RequestComment;
use App\Models\User;
use App\Repositories\Interfaces\RequestCommentRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class RequestCommentRepository implements RequestCommentRepositoryInterface
{
    protected $model;

    public function __construct(RequestComment $model)
    {
        $this->model = $model;
    }

    /**
     * Get all comments with filters
     */
    public function getAllWithFilters(array $filters, array $with = [], string $sortField = 'created_at', string $sortDirection = 'desc', int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Filter by request_id (required)
        if (isset($filters['request_id'])) {
            $query->where('request_id', $filters['request_id']);
        }

        // Filter by user_id
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Filter by content (partial match)
        if (isset($filters['content'])) {
            $query->where('content', 'like', '%' . $filters['content'] . '%');
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

        // Include relationships
        if (!empty($with)) {
            $query->with($with);
        }

        // Sorting
        $allowedSortFields = ['id', 'user_id', 'created_at', 'updated_at'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get comment by ID
     */
    public function getById(string $id, array $with = []): ?RequestComment
    {
        return $this->model->with($with)->find($id);
    }

    /**
     * Create a new comment
     */
    public function create(array $data): RequestComment
    {
        return $this->model->create($data);
    }

    /**
     * Update a comment
     */
    public function update(string $id, array $data): RequestComment
    {
        $comment = $this->model->findOrFail($id);
        $comment->update($data);
        return $comment;
    }

    /**
     * Delete a comment
     */
    public function delete(string $id): bool
    {
        $comment = $this->model->findOrFail($id);
        return $comment->delete();
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

        // Managers can access requests they sent/received or from their houses
        if ($user->role->code === 'manager') {
            if ($user->id === $request->sender_id || $user->id === $request->recipient_id) {
                return true;
            }

            return $user->id === $request->room->house->manager_id;
        }

        return false;
    }
}
