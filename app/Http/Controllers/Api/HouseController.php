<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\HouseResource;
use App\Models\House;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HouseController extends BaseController
{
    /**
     * Display a listing of the houses.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $houses = House::with(['manager'])->get();

        return $this->sendResponse(
            HouseResource::collection($houses),
            'Houses retrieved successfully.'
        );
    }

    /**
     * Store a newly created house in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser || $currentUser->role->code !== 'admin') {
            return $this->sendError('Unauthorized. Only admins can create houses.', [], 403);
        }

        $input = $request->all();
        $validator = Validator::make($input, [
            'name' => 'required|string|max:100',
            'address' => 'required|string|max:255',
            'manager_id' => 'nullable|exists:users,id',
            'status' => 'sometimes|string|max:10',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $input['created_by'] = $currentUser->id;
        $input['updated_by'] = $currentUser->id;

        $house = House::create($input);
        $house->load(['manager', 'updater']);

        return $this->sendResponse(new HouseResource($house), 'House created successfully.');
    }

    /**
     * Display the specified house.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $house = House::with(['manager', 'updater'])->find($id);

        if (is_null($house)) {
            return $this->sendError('House not found.');
        }

        return $this->sendResponse(new HouseResource($house), 'House retrieved successfully.');
    }

    /**
     * Update the specified house in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $house = House::find($id);

        if (is_null($house)) {
            return $this->sendError('House not found.');
        }

        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        // Only admins or the house manager can update the house
        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $house->manager_id === $currentUser->id;

        if (!$isAdmin && !$isManager) {
            return $this->sendError('Unauthorized. Only admins or house managers can update houses.', [], 403);
        }

        // If not admin, restrict fields that can be updated
        $fieldsAllowed = $isAdmin ?
            ['name', 'address', 'manager_id', 'status', 'description'] :
            ['name', 'address', 'status', 'description'];

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'address' => 'sometimes|required|string|max:255',
            'manager_id' => 'sometimes|nullable|exists:users,id',
            'status' => 'sometimes|string|max:10',
            'description' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        // Filter request data based on allowed fields
        $input = array_intersect_key($request->all(), array_flip($fieldsAllowed));

        // Add updated_by
        $input['updated_by'] = $currentUser->id;

        $house->update($input);
        $house->load(['manager', 'updater']);

        return $this->sendResponse(new HouseResource($house), 'House updated successfully.');
    }

    /**
     * Remove the specified house from storage.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $house = House::find($id);

        if (is_null($house)) {
            return $this->sendError('House not found.');
        }

        $currentUser = auth()->user();
        if (!$currentUser || $currentUser->role->code !== 'admin') {
            return $this->sendError('Unauthorized. Only admins can delete houses.', [], 403);
        }

        $house->delete();

        return $this->sendResponse([], 'House deleted successfully.');
    }
}
