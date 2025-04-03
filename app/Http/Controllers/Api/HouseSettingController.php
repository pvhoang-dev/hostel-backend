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
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $settings = HouseSetting::all();
        return $this->sendResponse(
            HouseSettingResource::collection($settings),
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
