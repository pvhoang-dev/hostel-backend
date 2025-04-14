<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\RoomEquipmentResource;
use App\Models\Room;
use App\Models\RoomEquipment;
use App\Models\Equipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoomEquipmentController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $roomEquipments = RoomEquipment::with(['room', 'equipment'])->get();

        return $this->sendResponse(
            RoomEquipmentResource::collection($roomEquipments),
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
            'source' => 'required|in:storage,custom',
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
            'equipment_id' => 'sometimes|required|exists:equipments,id', // Changed from nullable to required
            'room_id' => 'sometimes|exists:rooms,id',
            'source' => 'sometimes|in:storage,custom',
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
