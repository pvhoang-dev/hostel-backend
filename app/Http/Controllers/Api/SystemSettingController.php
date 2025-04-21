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
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = SystemSetting::query();

        // Apply filters for text fields
        if ($request->has('key')) {
            $query->where('key', 'like', '%' . $request->key . '%');
        }

        if ($request->has('value')) {
            $query->where('value', 'like', '%' . $request->value . '%');
        }

        if ($request->has('description')) {
            $query->where('description', 'like', '%' . $request->description . '%');
        }

        // Filter by user IDs
        if ($request->has('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        if ($request->has('updated_by')) {
            $query->where('updated_by', $request->updated_by);
        }

        // Date range filters
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

        // Include relationships if needed
        $with = [];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('creator', $includes)) $with[] = 'creator';
            if (in_array('updater', $includes)) $with[] = 'updater';
        }

        // Sorting
        $sortField = $request->get('sort_by', 'key');
        $sortDirection = $request->get('sort_dir', 'asc');
        $allowedSortFields = ['id', 'key', 'value', 'created_at', 'updated_at', 'created_by', 'updated_by'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('key', 'asc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $systemSettings = $query->with($with)->paginate($perPage);

        return $this->sendResponse(
            SystemSettingResource::collection($systemSettings)->response()->getData(true),
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
        $systemSetting = SystemSetting::find($id)->load(['creator', 'updater']);

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
