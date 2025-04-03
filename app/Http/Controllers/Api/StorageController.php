<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\EquipmentStorageResource;
use App\Models\EquipmentStorage;
use App\Models\House;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StorageController extends BaseController
{
    /**
     * Display a listing of equipment storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $storage = EquipmentStorage::with('equipment')->get();
        return $this->sendResponse(
            EquipmentStorageResource::collection($storage),
            'Equipment storage retrieved successfully.'
        );
    }

    /**
     * Store a newly created equipment storage in database.
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

        $validator = Validator::make($request->all(), [
            'house_id' => 'required|exists:houses,id',
            'equipment_id' => [
                'required',
                'exists:equipments,id',
                Rule::unique('equipment_storage')->where(function ($query) use ($request) {
                    return $query->where('house_id', $request->house_id)
                        ->where('equipment_id', $request->equipment_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'quantity' => 'required|integer|min:0',
            'price' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $house = House::find($request->house_id);

        if (!$this->canManageStorage($currentUser, $house)) {
            return $this->sendError('Unauthorized. Only admins or house managers can create equipment storage.', [], 403);
        }

        $storage = EquipmentStorage::create($request->all());
        $storage->load('equipment');

        return $this->sendResponse(
            new EquipmentStorageResource($storage),
            'Equipment storage created successfully.'
        );
    }

    /**
     * Display the specified equipment storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $storage = EquipmentStorage::with('equipment')->find($id);

        if (is_null($storage)) {
            return $this->sendError('Equipment storage not found.');
        }

        return $this->sendResponse(
            new EquipmentStorageResource($storage),
            'Equipment storage retrieved successfully.'
        );
    }

    /**
     * Update the specified equipment storage in database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $storage = EquipmentStorage::find($id);

        if (is_null($storage)) {
            return $this->sendError('Equipment storage not found.');
        }

        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        if (!$this->canManageStorage($currentUser, $storage->house)) {
            return $this->sendError('Unauthorized. Only admins or house managers can update equipment storage.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'house_id' => 'sometimes|exists:houses,id',
            'equipment_id' => [
                'sometimes',
                'exists:equipments,id',
                Rule::unique('equipment_storage')->where(function ($query) use ($request, $storage) {
                    $query->where('house_id', $request->house_id ?? $storage->house_id)
                        ->where('equipment_id', $request->equipment_id ?? $storage->equipment_id)
                        ->whereNull('deleted_at');
                })->ignore($storage->id),
            ],
            'quantity' => 'sometimes|integer|min:0',
            'price' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $storage->update($request->all());
        $storage->load('equipment');

        return $this->sendResponse(
            new EquipmentStorageResource($storage),
            'Equipment storage updated successfully.'
        );
    }

    /**
     * Remove the specified equipment storage from database.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $storage = EquipmentStorage::find($id);

        if (is_null($storage)) {
            return $this->sendError('Equipment storage not found.');
        }

        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        if (!$this->canManageStorage($currentUser, $storage->house)) {
            return $this->sendError('Unauthorized. Only admins or house managers can delete equipment storage.', [], 403);
        }

        $storage->delete();

        return $this->sendResponse(
            [],
            'Equipment storage deleted successfully.'
        );
    }

    /**
     * Check if user has permission to manage storage for a house
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\House|null  $house
     * @return bool
     */
    private function canManageStorage($user, $house): bool
    {
        $isAdmin = $user->role->code === 'admin';
        $isManager = $house && $house->manager_id === $user->id;

        return $isAdmin || $isManager;
    }
}
