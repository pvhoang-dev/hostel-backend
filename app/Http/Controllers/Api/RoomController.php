<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RoomController extends BaseController
{
    /**
     * Display a listing of the rooms.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $rooms = Room::with(['house'])->get();

        return $this->sendResponse(
            RoomResource::collection($rooms),
            'Rooms retrieved successfully.'
        );
    }

    /**
     * Store a newly created room in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        $input = $request->all();
        $validator = Validator::make($input, [
            'house_id' => 'required|exists:houses,id',
            'room_number' => [
                'required',
                'string',
                'max:10',
                Rule::unique('rooms')->where(function ($query) use ($request) {
                    return $query->where('house_id', $request->house_id);
                }),
            ],
            'capacity' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'status' => 'sometimes|string|max:10',
            'base_price' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $input['created_by'] = $currentUser->id;
        $input['updated_by'] = $currentUser->id;

        $room = Room::create($input);
        $room->load('house');

        return $this->sendResponse(new RoomResource($room), 'Room created successfully.');
    }

    /**
     * Display the specified room.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $room = Room::with(['house'])->find($id);

        if (is_null($room)) {
            return $this->sendError('Room not found.');
        }

        return $this->sendResponse(new RoomResource($room), 'Room retrieved successfully.');
    }

    /**
     * Update the specified room in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $room = Room::find($id);

        if (is_null($room)) {
            return $this->sendError('Room not found.');
        }

        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        // Check if user is admin or the house manager
        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $room->house->manager_id === $currentUser->id;

        if (!$isAdmin && !$isManager) {
            return $this->sendError('Unauthorized. Only admins or house managers can update rooms.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'house_id' => 'sometimes|exists:houses,id',
            'room_number' => [
                'sometimes',
                'required',
                'string',
                'max:10',
                Rule::unique('rooms')->where(function ($query) use ($request, $room) {
                    return $query->where('house_id', $request->input('house_id', $room->house_id));
                })->ignore($id),
            ],
            'capacity' => 'sometimes|required|integer|min:1',
            'description' => 'nullable|string',
            'status' => 'sometimes|string|max:10',
            'base_price' => 'sometimes|required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        // If not admin, restrict house_id from being changed
        if (!$isAdmin && isset($request->house_id) && $request->house_id != $room->house_id) {
            return $this->sendError('Unauthorized. Only admins can change the house.', [], 403);
        }

        $input = $request->all();
        $input['updated_by'] = $currentUser->id;

        $room->update($input);
        $room->load('house');

        return $this->sendResponse(new RoomResource($room), 'Room updated successfully.');
    }

    /**
     * Remove the specified room from storage.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $room = Room::find($id);

        if (is_null($room)) {
            return $this->sendError('Room not found.');
        }

        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        // Only admin or house manager can delete rooms
        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $room->house->manager_id === $currentUser->id;

        if (!$isAdmin && !$isManager) {
            return $this->sendError('Unauthorized. Only admins or house managers can delete rooms.', [], 403);
        }

        $room->delete();

        return $this->sendResponse([], 'Room deleted successfully.');
    }
}
