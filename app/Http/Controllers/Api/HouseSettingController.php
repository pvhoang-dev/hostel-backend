<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\HouseSettingResource;
use App\Models\House;
use App\Models\HouseSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HouseSettingController extends BaseController
{
    /**
     * Display a listing of the house settings.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = auth()->user();
        $query = HouseSetting::query();

        // Apply role-based filters
        if ($currentUser && $currentUser->role->code === 'manager') {
            // Managers can only see settings for houses they manage
            $query->whereHas('house', function ($q) use ($currentUser) {
                $q->where('manager_id', $currentUser->id);
            });
        }
        // Admins can see all settings (no filter)
        // Tenants shouldn't see house settings

        // Filter by house_id
        if ($request->has('house_id')) {
            $query->where('house_id', $request->house_id);
        }

        // Filter by key
        if ($request->has('key')) {
            $query->where('key', 'like', '%' . $request->key . '%');
        }

        // Filter by value
        if ($request->has('value')) {
            $query->where('value', 'like', '%' . $request->value . '%');
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
            if (in_array('house', $includes)) $with[] = 'house';
            if (in_array('creator', $includes)) $with[] = 'creator';
            if (in_array('updater', $includes)) $with[] = 'updater';
        }

        if (!empty($with)) {
            $query->with($with);
        }

        // Sorting
        $sortField = $request->get('sort_by', 'key');
        $sortDirection = $request->get('sort_dir', 'desc');
        $allowedSortFields = ['id', 'key', 'value', 'house_id', 'created_at', 'updated_at'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $settings = $query->paginate($perPage);

        return $this->sendResponse(
            HouseSettingResource::collection($settings)->response()->getData(true),
            'House settings retrieved successfully.'
        );
    }

    /**
     * Store a newly created house setting in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser || $currentUser->role->code !== 'admin') {
            return $this->sendError('Unauthorized. Only admins can create house settings.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'house_id' => 'required|exists:houses,id',
            'key' => 'required|string|max:50|unique:house_settings,key',
            'value' => 'required|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $input = $request->all();
        $input['created_by'] = $currentUser->id;
        $input['updated_by'] = $currentUser->id;

        $setting = HouseSetting::create($input);

        return $this->sendResponse(
            new HouseSettingResource($setting),
            'House setting created successfully.'
        );
    }

    /**
     * Display the specified house setting.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $setting = HouseSetting::find($id);

        if (is_null($setting)) {
            return $this->sendError('House setting not found.');
        }

        return $this->sendResponse(
            new HouseSettingResource($setting),
            'House setting retrieved successfully.'
        );
    }

    /**
     * Update the specified house setting in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $setting = HouseSetting::find($id);

        if (is_null($setting)) {
            return $this->sendError('House setting not found.');
        }

        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        $house = $setting->house;
        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $house && $house->manager_id === $currentUser->id;

        if (!$isAdmin && !$isManager) {
            return $this->sendError('Unauthorized. Only admins or house managers can update settings.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'key' => 'sometimes|required|string|max:50|unique:house_settings,key,' . $setting->id,
            'value' => 'sometimes|required|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $input = $request->all();
        $input['updated_by'] = $currentUser->id;

        $setting->update($input);

        return $this->sendResponse(
            new HouseSettingResource($setting),
            'House setting updated successfully.'
        );
    }

    /**
     * Remove the specified house setting from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $setting = HouseSetting::find($id);

        if (is_null($setting)) {
            return $this->sendError('House setting not found.');
        }

        $currentUser = auth()->user();
        if (!$currentUser || $currentUser->role->code !== 'admin') {
            return $this->sendError('Unauthorized. Only admins can delete house settings.', [], 403);
        }

        $setting->delete();

        return $this->sendResponse(
            [],
            'House setting deleted successfully.'
        );
    }
}
