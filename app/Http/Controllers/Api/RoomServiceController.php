<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\RoomServiceResource;
use App\Models\House;
use App\Models\Room;
use App\Models\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RoomServiceController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = RoomService::query();

        // Apply role-based filters
        if ($user->role->code === 'tenant') {
            // Tenants can only see services for rooms they occupy
            $query->whereHas('room.contracts.tenants', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        } elseif ($user->role->code === 'manager') {
            // Managers can see services for houses they manage
            $managedHouseIds = House::where('manager_id', $user->id)->pluck('id');
            $query->whereHas('room', function ($q) use ($managedHouseIds) {
                $q->whereIn('house_id', $managedHouseIds);
            });
        }
        // Admins can see all services, so no filter needed

        // Apply additional filters
        if ($request->has('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        if ($request->has('service_id')) {
            $query->where('service_id', $request->service_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('is_fixed')) {
            $query->where('is_fixed', $request->is_fixed);
        }

        // Include relationships
        $with = [];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('room', $includes)) $with[] = 'room';
            if (in_array('service', $includes)) $with[] = 'service';
            if (in_array('usages', $includes)) $with[] = 'usages';
        }

        $roomServices = $query->with($with)->orderBy('created_at', 'desc')->paginate(15);

        return $this->sendResponse(
            RoomServiceResource::collection($roomServices)->response()->getData(true),
            'Room services retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        $input = $request->all();

        $validator = Validator::make($input, [
            'room_id' => 'required|exists:rooms,id',
            'service_id' => 'required|exists:services,id',
            'price' => 'required|integer|min:0',
            'is_fixed' => 'required|boolean',
            'status' => 'sometimes|string|max:20',
            'description' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        // Check authorization
        $room = Room::with('house')->find($input['room_id']);
        if (!$room) {
            return $this->sendError('Room not found.');
        }

        // Only managers of the house or admins can add services to rooms
        if ($user->role->code === 'tenant') {
            return $this->sendError('Unauthorized', ['error' => 'Tenants cannot add services to rooms'], 403);
        } elseif ($user->role->code === 'manager' && $room->house->manager_id !== $user->id) {
            return $this->sendError('Unauthorized', ['error' => 'You can only add services to rooms in houses you manage'], 403);
        }

        // Check if the service already exists for this room
        $existingService = RoomService::where('room_id', $input['room_id'])
            ->where('service_id', $input['service_id'])
            ->first();

        if ($existingService) {
            return $this->sendError('Validation Error.', ['service' => 'This service is already assigned to the room']);
        }

        // Set default status if not provided
        if (!isset($input['status'])) {
            $input['status'] = 'active';
        }

        $roomService = RoomService::create($input);

        return $this->sendResponse(
            new RoomServiceResource($roomService->load(['room.house', 'service'])),
            'Room service created successfully.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();
        $roomService = RoomService::with(['room.house', 'service'])->find($id);

        if (is_null($roomService)) {
            return $this->sendError('Room service not found.');
        }

        // Authorization check
        if (!$this->canAccessRoomService($user, $roomService)) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to view this room service'], 403);
        }

        return $this->sendResponse(
            new RoomServiceResource($roomService),
            'Room service retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $input = $request->all();
        $roomService = RoomService::with('room.house')->find($id);

        if (is_null($roomService)) {
            return $this->sendError('Room service not found.');
        }

        // Authorization check
        if (!$this->canManageRoomService($user, $roomService)) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to update this room service'], 403);
        }

        $validator = Validator::make($input, [
            'price' => 'sometimes|integer|min:0',
            'is_fixed' => 'sometimes|boolean',
            'status' => 'sometimes|string|max:20',
            'description' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $roomService->update($input);

        return $this->sendResponse(
            new RoomServiceResource($roomService->load(['room.house', 'service'])),
            'Room service updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();
        $roomService = RoomService::with('room.house')->find($id);

        if (is_null($roomService)) {
            return $this->sendError('Room service not found.');
        }

        // Authorization check - only admins and managers can delete room services
        if (!$this->canManageRoomService($user, $roomService)) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to delete this room service'], 403);
        }

        $roomService->delete();

        return $this->sendResponse([], 'Room service deleted successfully.');
    }

    /**
     * Check if user can access a room service
     */
    private function canAccessRoomService($user, $roomService): bool
    {
        // Admins can access all room services
        if ($user->role->code === 'admin') {
            return true;
        }

        // Tenants can only access room services for rooms they occupy
        if ($user->role->code === 'tenant') {
            return $roomService->room->contracts()
                ->whereHas('tenants', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                })
                ->exists();
        }

        // Managers can access room services for houses they manage
        if ($user->role->code === 'manager') {
            return $user->id === $roomService->room->house->manager_id;
        }

        return false;
    }

    /**
     * Check if user can manage a room service (update/delete)
     */
    private function canManageRoomService($user, $roomService): bool
    {
        // Admins can manage all room services
        if ($user->role->code === 'admin') {
            return true;
        }

        // Tenants cannot manage room services
        if ($user->role->code === 'tenant') {
            return false;
        }

        // Managers can manage room services for houses they manage
        if ($user->role->code === 'manager') {
            return $user->id === $roomService->room->house->manager_id;
        }

        return false;
    }
}
