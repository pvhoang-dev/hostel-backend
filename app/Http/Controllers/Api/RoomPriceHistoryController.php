<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\RoomPriceHistoryResource;
use App\Models\Room;
use App\Models\RoomPriceHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoomPriceHistoryController extends BaseController
{
    /**
     * Display a listing of the price histories.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $priceHistories = RoomPriceHistory::with(['room'])->get();

        return $this->sendResponse(
            RoomPriceHistoryResource::collection($priceHistories),
            'Room price histories retrieved successfully.'
        );
    }

    /**
     * Store a newly created price history in storage.
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
            'room_id' => 'required|exists:rooms,id',
            'price' => 'required|integer|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        // Check if user is authorized to update room prices
        $room = Room::with('house')->find($input['room_id']);
        if (!$room) {
            return $this->sendError('Room not found.');
        }

        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $room->house->manager_id === $currentUser->id;

        if (!$isAdmin && !$isManager) {
            return $this->sendError('Unauthorized. Only admins or house managers can update room prices.', [], 403);
        }

        $priceHistory = RoomPriceHistory::create($input);
        $priceHistory->load('room');

        return $this->sendResponse(new RoomPriceHistoryResource($priceHistory), 'Room price history created successfully.');
    }

    /**
     * Display the specified price history.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $priceHistory = RoomPriceHistory::with(['room'])->find($id);

        if (is_null($priceHistory)) {
            return $this->sendError('Room price history not found.');
        }

        return $this->sendResponse(new RoomPriceHistoryResource($priceHistory), 'Room price history retrieved successfully.');
    }

    /**
     * Update the specified price history in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $priceHistory = RoomPriceHistory::find($id);

        if (is_null($priceHistory)) {
            return $this->sendError('Room price history not found.');
        }

        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        // Get the room and check authorization
        $room = Room::with('house')->find($priceHistory->room_id);
        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $room->house->manager_id === $currentUser->id;

        if (!$isAdmin && !$isManager) {
            return $this->sendError('Unauthorized. Only admins or house managers can update room prices.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'price' => 'sometimes|required|integer|min:0',
            'effective_from' => 'sometimes|required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $priceHistory->update($request->all());
        $priceHistory->load('room');

        return $this->sendResponse(new RoomPriceHistoryResource($priceHistory), 'Room price history updated successfully.');
    }

    /**
     * Remove the specified price history from storage.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $priceHistory = RoomPriceHistory::find($id);

        if (is_null($priceHistory)) {
            return $this->sendError('Room price history not found.');
        }

        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        // Get the room and check authorization
        $room = Room::with('house')->find($priceHistory->room_id);
        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $room->house->manager_id === $currentUser->id;

        if (!$isAdmin && !$isManager) {
            return $this->sendError('Unauthorized. Only admins or house managers can delete room price histories.', [], 403);
        }

        $priceHistory->delete();

        return $this->sendResponse([], 'Room price history deleted successfully.');
    }
}
