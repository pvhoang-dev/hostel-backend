<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\SystemSettingResource;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Class SystemSettingController
 *
 * @package App\Http\Controllers\Api
 */
class SystemSettingController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $systemSettings = SystemSetting::all();

        return $this->sendResponse(
            SystemSettingResource::collection($systemSettings),
            'System settings retrieved successfully'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string|max:50|unique:system_settings,key',
            'value' => 'required|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $data = $request->all();
        $data['created_by'] = Auth::id();

        $systemSetting = SystemSetting::create($data);

        return $this->sendResponse(
            new SystemSettingResource($systemSetting),
            'System setting created successfully'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        $systemSetting = SystemSetting::find($id);

        if (is_null($systemSetting)) {
            return $this->sendError('System setting not found');
        }

        return $this->sendResponse(
            new SystemSettingResource($systemSetting),
            'System setting retrieved successfully'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SystemSetting $systemSetting): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'key' => 'sometimes|required|string|max:50|unique:system_settings,key,' . $systemSetting->id,
            'value' => 'sometimes|required|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $systemSetting->fill($request->only([
            'key',
            'value',
            'description',
        ]));

        $systemSetting->updated_by = Auth::id();
        $systemSetting->save();

        return $this->sendResponse(
            new SystemSettingResource($systemSetting),
            'System setting updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $systemSetting = SystemSetting::find($id);

        if (is_null($systemSetting)) {
            return $this->sendError('System setting not found');
        }

        $systemSetting->delete();

        return $this->sendResponse([], 'System setting deleted successfully');
    }
}
