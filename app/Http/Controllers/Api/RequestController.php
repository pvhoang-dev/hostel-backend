<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\RequestResource;
use App\Models\House;
use App\Models\Request;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RequestController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(HttpRequest $httpRequest): JsonResponse
    {
        $user = Auth::user();
        $query = Request::query();

        // Apply role-based filters
        if ($user->role->code === 'tenant') {
            // Tenants can only see requests they sent or received
            $query->where(function ($q) use ($user) {
                $q->where('sender_id', $user->id)
                    ->orWhere('recipient_id', $user->id);
            });
        } elseif ($user->role->code === 'manager') {
            // Managers can see requests they sent/received or from their houses
            $managedHouseIds = House::where('manager_id', $user->id)->pluck('id');
            $query->where(function ($q) use ($user, $managedHouseIds) {
                $q->where('sender_id', $user->id)
                    ->orWhere('recipient_id', $user->id)
                    ->orWhereHas('room', function ($q2) use ($managedHouseIds) {
                        $q2->whereIn('house_id', $managedHouseIds);
                    });
            });
        }
        // Admins can see all requests, so no filter needed

        // Apply additional filters
        if ($httpRequest->has('room_id')) {
            $query->where('room_id', $httpRequest->room_id);
        }

        if ($httpRequest->has('sender_id')) {
            $query->where('sender_id', $httpRequest->sender_id);
        }

        if ($httpRequest->has('recipient_id')) {
            $query->where('recipient_id', $httpRequest->recipient_id);
        }

        if ($httpRequest->has('status')) {
            $query->where('status', $httpRequest->status);
        }

        if ($httpRequest->has('request_type')) {
            $query->where('request_type', $httpRequest->request_type);
        }

        // Text search in description
        if ($httpRequest->has('description')) {
            $query->where('description', 'like', '%' . $httpRequest->description . '%');
        }

        // Date range filters
        if ($httpRequest->has('created_from')) {
            $query->where('created_at', '>=', $httpRequest->created_from);
        }

        if ($httpRequest->has('created_to')) {
            $query->where('created_at', '<=', $httpRequest->created_to);
        }

        if ($httpRequest->has('updated_from')) {
            $query->where('updated_at', '>=', $httpRequest->updated_from);
        }

        if ($httpRequest->has('updated_to')) {
            $query->where('updated_at', '<=', $httpRequest->updated_to);
        }

        // Include relationships
        $with = [];
        if ($httpRequest->has('include')) {
            $includes = explode(',', $httpRequest->include);
            if (in_array('room', $includes)) $with[] = 'room';
            if (in_array('sender', $includes)) $with[] = 'sender';
            if (in_array('recipient', $includes)) $with[] = 'recipient';
            if (in_array('comments', $includes)) $with[] = 'comments.user';
            if (in_array('updater', $includes)) $with[] = 'updater';
        }

        // Sorting
        $sortField = $httpRequest->get('sort_by', 'created_at');
        $sortDirection = $httpRequest->get('sort_dir', 'desc');
        $allowedSortFields = ['id', 'created_at', 'updated_at', 'status', 'request_type'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = $httpRequest->get('per_page', 15);
        $requests = $query->with($with)->paginate($perPage);

        return $this->sendResponse(
            RequestResource::collection($requests)->response()->getData(true),
            'Requests retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(HttpRequest $httpRequest): JsonResponse
    {
        $user = Auth::user();
        $input = $httpRequest->all();

        $validator = Validator::make($input, [
            'room_id' => 'required|exists:rooms,id',
            'sender_id' => 'required|exists:users,id',
            'recipient_id' => 'required|exists:users,id',
            'request_type' => 'required|string|max:50',
            'description' => 'required|string',
            'status' => 'sometimes|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        // Users can only create requests where they are the sender
        if ($user->id !== $input['sender_id']) {
            return $this->sendError('Unauthorized', ['error' => 'You can only create requests as the sender'], 403);
        }

        // Check role-based routing permissions
        $recipient = User::find($input['recipient_id']);
        if (!$recipient) {
            return $this->sendError('Validation Error.', ['recipient_id' => 'Recipient not found']);
        }

        // Enforce role-based request routing
        if ($user->role->code === 'tenant') {
            // Tenants can only send requests to managers
            if ($recipient->role->code !== 'manager') {
                return $this->sendError('Unauthorized', ['error' => 'Tenants can only send requests to managers'], 403);
            }

            // Validate room belongs to tenant
            $hasAccess = Room::where('id', $input['room_id'])
                ->whereHas('contracts', function ($q) use ($user) {
                    $q->whereHas('tenants', function ($q2) use ($user) {
                        $q2->where('users.id', $user->id);
                    });
                })
                ->exists();

            if (!$hasAccess) {
                return $this->sendError('Unauthorized', ['error' => 'You can only create requests for your own room'], 403);
            }
        } elseif ($user->role->code === 'manager') {
            // Managers can send requests to admins or tenants
            if (!in_array($recipient->role->code, ['admin', 'tenant'])) {
                return $this->sendError('Unauthorized', ['error' => 'Managers can only send requests to admins or tenants'], 403);
            }

            // If sending to tenant, ensure they belong to a house managed by this manager
            if ($recipient->role->code === 'tenant') {
                $managedHouseIds = House::where('manager_id', $user->id)->pluck('id');
                $tenantBelongsToManager = Room::where('id', $input['room_id'])
                    ->whereIn('house_id', $managedHouseIds)
                    ->whereHas('contracts', function ($q) use ($recipient) {
                        $q->whereHas('tenants', function ($q2) use ($recipient) {
                            $q2->where('users.id', $recipient->id);
                        });
                    })
                    ->exists();

                if (!$tenantBelongsToManager) {
                    return $this->sendError('Unauthorized', ['error' => 'You can only send requests to tenants in houses you manage'], 403);
                }
            }
        } elseif ($user->role->code === 'admin') {
            // Admins can send requests to managers or tenants
            if (!in_array($recipient->role->code, ['manager', 'tenant'])) {
                return $this->sendError('Unauthorized', ['error' => 'Admins can only send requests to managers or tenants'], 403);
            }
        } else {
            return $this->sendError('Unauthorized', ['error' => 'You are not authorized to create requests'], 403);
        }

        // If no status is provided, set it to 'pending'
        if (!isset($input['status'])) {
            $input['status'] = 'pending';
        }

        // Set updated_by to current user
        $input['updated_by'] = $user->id;

        $request = Request::create($input);

        return $this->sendResponse(
            new RequestResource($request->load(['room', 'sender', 'recipient', 'updater'])),
            'Request created successfully.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();
        $request = Request::with(['room.house', 'sender.role', 'recipient.role', 'comments.user', 'updater'])->find($id);

        if (is_null($request)) {
            return $this->sendError('Request not found.');
        }

        // Authorization check
        if (!$this->canAccessRequest($user, $request)) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to view this request'], 403);
        }

        return $this->sendResponse(
            new RequestResource($request),
            'Request retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(HttpRequest $httpRequest, string $id): JsonResponse
    {
        $user = Auth::user();
        $input = $httpRequest->all();
        $request = Request::with('room.house')->find($id);

        if (is_null($request)) {
            return $this->sendError('Request not found.');
        }

        // Authorization check
        if (!$this->canAccessRequest($user, $request)) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to update this request'], 403);
        }

        // Apply role-specific restrictions
        if ($user->role->code === 'tenant') {
            // Tenants can't change room_id, sender_id, or recipient_id
            if (isset($input['room_id']) || isset($input['sender_id']) || isset($input['recipient_id'])) {
                return $this->sendError('Unauthorized', ['error' => 'Tenants cannot change room, sender, or recipient'], 403);
            }

            // Tenants can only update requests they sent
            if ($request->sender_id !== $user->id) {
                return $this->sendError('Unauthorized', ['error' => 'You can only update requests you sent'], 403);
            }

            // Tenants can only update description, not status
            if (isset($input['status'])) {
                return $this->sendError('Unauthorized', ['error' => 'Tenants cannot change request status'], 403);
            }
        } elseif ($user->role->code === 'manager') {
            // Managers can update recipient_id only to admin users
            if (isset($input['recipient_id'])) {
                $recipient = User::find($input['recipient_id']);
                if (!$recipient || $recipient->role->code !== 'admin') {
                    return $this->sendError('Unauthorized', ['error' => 'Managers can only change recipient to admin users'], 403);
                }
            }
        }

        $validator = Validator::make($input, [
            'room_id' => 'sometimes|exists:rooms,id',
            'sender_id' => 'sometimes|exists:users,id',
            'recipient_id' => 'sometimes|exists:users,id',
            'request_type' => 'sometimes|string|max:50',
            'description' => 'sometimes|string',
            'status' => 'sometimes|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        // Set updated_by to current user
        $input['updated_by'] = $user->id;

        $request->update($input);

        return $this->sendResponse(
            new RequestResource($request->load(['room', 'sender', 'recipient', 'updater'])),
            'Request updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();
        $request = Request::with('room.house')->find($id);

        if (is_null($request)) {
            return $this->sendError('Request not found.');
        }

        // Authorization check - only admins and managers can delete requests
        if ($user->role->code === 'tenant') {
            return $this->sendError('Unauthorized', ['error' => 'Tenants cannot delete requests'], 403);
        }

        if ($user->role->code === 'manager') {
            // Check if manager manages the house
            if ($request->room->house->manager_id !== $user->id) {
                return $this->sendError('Unauthorized', ['error' => 'You can only delete requests for houses you manage'], 403);
            }
        }

        $request->delete();

        return $this->sendResponse([], 'Request deleted successfully.');
    }

    /**
     * Check if user can access a request
     */
    private function canAccessRequest($user, $request): bool
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
