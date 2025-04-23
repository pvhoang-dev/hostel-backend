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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = Room::query();

        // Apply role-based filters
        if ($user && $user->role->code === 'manager') {
            // Managers can only see rooms in houses they manage
            $managedHouseIds = House::where('manager_id', $user->id)->pluck('id');
            $query->whereIn('house_id', $managedHouseIds);
        }
        // Admins can see all rooms (no filter)

        // Apply additional filters
        if ($request->has('house_id')) {
            $query->where('house_id', $request->house_id);
        }

        if ($request->has('room_number')) {
            $query->where('room_number', 'like', '%' . $request->room_number . '%');
        }

        if ($request->has('capacity')) {
            $query->where('capacity', $request->capacity);
        }

        if ($request->has('min_capacity')) {
            $query->where('capacity', '>=', $request->min_capacity);
        }

        if ($request->has('max_capacity')) {
            $query->where('capacity', '<=', $request->max_capacity);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('min_price')) {
            $query->where('base_price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('base_price', '<=', $request->max_price);
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
        $with = ['house'];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('services', $includes)) $with[] = 'services';
            if (in_array('contracts', $includes)) $with[] = 'contracts';
            if (in_array('currentContract', $includes)) $with[] = 'currentContract';
            if (in_array('creator', $includes)) $with[] = 'creator';
            if (in_array('updater', $includes)) $with[] = 'updater';
        }

        // Sorting
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_dir', 'desc');
        $allowedSortFields = ['id', 'house_id', 'room_number', 'capacity', 'base_price', 'status', 'created_at', 'updated_at'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $rooms = $query->with($with)->paginate($perPage);

        return $this->sendResponse(
            RoomResource::collection($rooms)->response()->getData(true),
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
