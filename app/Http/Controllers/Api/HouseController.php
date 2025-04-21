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
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = House::query();

        // Filter by name
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by manager_id
        if ($request->has('manager_id')) {
            $query->where('manager_id', $request->manager_id);
        }

        // Filter by address
        if ($request->has('address')) {
            $query->where('address', 'like', '%' . $request->address . '%');
        }

        // Filter by created/updated date ranges
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
        $with = ['manager'];

        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('updater', $includes)) {
                $with[] = 'updater';
            }
            if (in_array('rooms', $includes)) {
                $with[] = 'rooms';
            }
        }

        $query->with($with);

        // Sorting
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_dir', 'desc');
        $allowedSortFields = ['id', 'name', 'created_at', 'updated_at', 'status', 'address'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $houses = $query->paginate($perPage);

        return $this->sendResponse(
            HouseResource::collection($houses)->response()->getData(true),
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

        if (empty($input['manager_id'])) {
            $input['manager_id'] = $currentUser->id;
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

        if ($isAdmin && isset($input['manager_id']) && empty($input['manager_id'])) {
            $input['manager_id'] = $currentUser->id;
        }

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
