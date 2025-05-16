<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\RoomEquipmentResource;
use App\Models\Room;
use App\Models\RoomEquipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoomEquipmentController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = RoomEquipment::query();

        // Apply role-based filters
        if ($user->role->code === 'manager') {
            // Managers can only see equipment in rooms of houses they manage
            $query->whereHas('room.house', function ($q) use ($user) {
                $q->where('manager_id', $user->id);
            });
        } elseif ($user->role->code === 'tenant') {
            // Tenants can only see equipment in rooms they occupy
            $query->whereHas('room.contracts.users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }
        // Admins can see all equipment, so no filter needed

        // Apply additional filters
        if ($request->has('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        if ($request->has('equipment_id')) {
            $query->where('equipment_id', $request->equipment_id);
        }

        if ($request->has('min_quantity')) {
            $query->where('quantity', '>=', $request->min_quantity);
        }

        if ($request->has('max_quantity')) {
            $query->where('quantity', '<=', $request->max_quantity);
        }

        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Filter by date ranges
        if ($request->has('created_from')) {
            $query->where('created_at', '>=', $request->created_from);
        }

        if ($request->has('created_to')) {
            $query->where('created_at', '<=', $request->created_to);
        }

        if ($request->has('updated_from')) {
            $query->where('updated_at', '>=', $request->updated_from);
        }

        if ($request->has('updated_to')) {
            $query->where('updated_at', '<=', $request->updated_to);
        }

        // Include relationships
        $with = [];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('room', $includes)) $with[] = 'room';
            if (in_array('equipment', $includes)) $with[] = 'equipment';
            if (in_array('room.house', $includes)) $with[] = 'room.house';
        }

        // Sorting
        $sortField = $request->get('sort_by', 'id');
        $sortDirection = $request->get('sort_dir', 'asc');
        $allowedSortFields = ['id', 'room_id', 'equipment_id', 'quantity', 'price', 'created_at', 'updated_at'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('id', 'asc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $roomEquipments = $query->with($with)->paginate($perPage);

        return $this->sendResponse(
            RoomEquipmentResource::collection($roomEquipments)->response()->getData(true),
            'Room equipments retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        $input = $request->all();
        $validator = Validator::make($input, [
            'room_id' => 'required|exists:rooms,id',
            'equipment_id' => 'required|exists:equipments,id',
            'quantity' => 'required|integer|min:1',
            'price' => 'required|integer|min:0',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        // Check authorization
        $room = Room::with('house')->find($input['room_id']);
        if (!$room) {
            return $this->sendError('Room not found.');
        }

        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $room->house->manager_id === $currentUser->id;

        if (!$isAdmin && !$isManager) {
            return $this->sendError('Unauthorized. Only admins or house managers can manage room equipments.', [], 403);
        }

        // Create room equipment
        $roomEquipment = RoomEquipment::create($input);
        $roomEquipment->load(['room', 'equipment']);

        return $this->sendResponse(new RoomEquipmentResource($roomEquipment), 'Room equipment created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $roomEquipment = RoomEquipment::with(['room', 'equipment'])->find($id);

        if (is_null($roomEquipment)) {
            return $this->sendError('Room equipment not found.');
        }

        return $this->sendResponse(new RoomEquipmentResource($roomEquipment), 'Room equipment retrieved successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $roomEquipment = RoomEquipment::find($id);

        if (is_null($roomEquipment)) {
            return $this->sendError('Room equipment not found.');
        }

        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        // Check authorization
        $room = Room::with('house')->find($roomEquipment->room_id);
        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $room->house->manager_id === $currentUser->id;

        if (!$isAdmin && !$isManager) {
            return $this->sendError('Unauthorized. Only admins or house managers can update room equipments.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'equipment_id' => 'sometimes|required|exists:equipments,id',
            'room_id' => 'sometimes|exists:rooms,id',
            'quantity' => 'sometimes|integer|min:1',
            'price' => 'sometimes|integer|min:0',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        // If room_id is being changed, check if user has permission for the new room
        if (isset($request->room_id) && $request->room_id != $roomEquipment->room_id) {
            $newRoom = Room::with('house')->find($request->room_id);

            if (!$newRoom) {
                return $this->sendError('New room not found.');
            }

            $isManagerOfNewRoom = $newRoom->house->manager_id === $currentUser->id;

            if (!$isAdmin && !$isManagerOfNewRoom) {
                return $this->sendError('Unauthorized. You do not have permission to move equipment to this room.', [], 403);
            }
        }

        $roomEquipment->update($request->all());
        $roomEquipment->load(['room', 'equipment']);

        return $this->sendResponse(new RoomEquipmentResource($roomEquipment), 'Room equipment updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $roomEquipment = RoomEquipment::find($id);

        if (is_null($roomEquipment)) {
            return $this->sendError('Room equipment not found.');
        }

        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        // Check authorization
        $room = Room::with('house')->find($roomEquipment->room_id);
        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $room->house->manager_id === $currentUser->id;

        if (!$isAdmin && !$isManager) {
            return $this->sendError('Unauthorized. Only admins or house managers can delete room equipments.', [], 403);
        }

        $roomEquipment->delete();

        return $this->sendResponse([], 'Room equipment deleted successfully.');
    }
}
