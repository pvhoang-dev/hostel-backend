<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\ServiceUsageResource;
use App\Models\House;
use App\Models\RoomService;
use App\Models\ServiceUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ServiceUsageController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = ServiceUsage::query();

        // Apply role-based filters
        if ($user->role->code === 'tenant') {
            // Tenants can only see service usages for rooms they occupy
            $query->whereHas('roomService.room.contracts.users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        } elseif ($user->role->code === 'manager') {
            // Managers can see service usages for houses they manage
            $managedHouseIds = House::where('manager_id', $user->id)->pluck('id');
            $query->whereHas('roomService.room', function ($q) use ($managedHouseIds) {
                $q->whereIn('house_id', $managedHouseIds);
            });
        }
        // Admins can see all service usages, so no filter needed

        // Apply additional filters
        if ($request->has('room_service_id')) {
            $query->where('room_service_id', $request->room_service_id);
        }

        if ($request->has('room_id')) {
            $query->whereHas('roomService', function($q) use ($request) {
                $q->where('room_id', $request->room_id);
            });
        }

        if ($request->has('service_id')) {
            $query->whereHas('roomService', function($q) use ($request) {
                $q->where('service_id', $request->service_id);
            });
        }

        if ($request->has('month')) {
            $query->where('month', $request->month);
        }

        if ($request->has('year')) {
            $query->where('year', $request->year);
        }

        // Usage value range filters
        if ($request->has('min_usage')) {
            $query->where('usage_value', '>=', $request->min_usage);
        }

        if ($request->has('max_usage')) {
            $query->where('usage_value', '<=', $request->max_usage);
        }

        // Price used range filters
        if ($request->has('min_price')) {
            $query->where('price_used', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('price_used', '<=', $request->max_price);
        }

        // Meter reading filters
        if ($request->has('min_start_meter')) {
            $query->where('start_meter', '>=', $request->min_start_meter);
        }

        if ($request->has('max_start_meter')) {
            $query->where('start_meter', '<=', $request->max_start_meter);
        }

        if ($request->has('min_end_meter')) {
            $query->where('end_meter', '>=', $request->min_end_meter);
        }

        if ($request->has('max_end_meter')) {
            $query->where('end_meter', '<=', $request->max_end_meter);
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

        // Include relationships
        $with = [];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('roomService', $includes)) $with[] = 'roomService';
            if (in_array('roomService.room', $includes)) $with[] = 'roomService.room';
            if (in_array('roomService.service', $includes)) $with[] = 'roomService.service';
            if (in_array('roomService.room.house', $includes)) $with[] = 'roomService.room.house';
            if (in_array('invoiceItems', $includes)) $with[] = 'invoiceItems';
        }

        // Sorting
        $sortField = $request->get('sort_by', 'year');
        $sortDirection = $request->get('sort_dir', 'desc');
        $allowedSortFields = [
            'id', 'room_service_id', 'start_meter', 'end_meter', 'usage_value',
            'month', 'year', 'price_used', 'created_at', 'updated_at'
        ];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('year', 'desc')->orderBy('month', 'desc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $serviceUsages = $query->with($with)->paginate($perPage);

        return $this->sendResponse(
            ServiceUsageResource::collection($serviceUsages)->response()->getData(true),
            'Service usages retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        $input = $request->all();

        $validator = Validator::make($input, [
            'room_service_id' => 'required|exists:room_services,id',
            'start_meter' => 'sometimes|nullable|numeric|min:0',
            'end_meter' => 'sometimes|nullable|numeric|min:0',
            'usage_value' => 'required|numeric|min:0',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'price_used' => 'required|integer|min:0',
            'description' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        // Check if meters are valid
        if (isset($input['start_meter']) && isset($input['end_meter']) && $input['start_meter'] > $input['end_meter']) {
            return $this->sendError('Validation Error.', ['end_meter' => 'End meter must be greater than or equal to start meter']);
        }

        // Check authorization
        $roomService = RoomService::with('room.house')->find($input['room_service_id']);
        if (!$roomService) {
            return $this->sendError('Room service not found.');
        }

        // Only managers of the house or admins can add service usages
        if ($user->role->code === 'tenant') {
            return $this->sendError('Unauthorized', ['error' => 'Tenants cannot add service usages'], 403);
        } elseif ($user->role->code === 'manager' && $roomService->room->house->manager_id !== $user->id) {
            return $this->sendError('Unauthorized', ['error' => 'You can only add service usages for rooms in houses you manage'], 403);
        }

        // Check if a record already exists for this service in the specified month/year
        $existingUsage = ServiceUsage::where('room_service_id', $input['room_service_id'])
            ->where('month', $input['month'])
            ->where('year', $input['year'])
            ->first();

        if ($existingUsage) {
            return $this->sendError('Validation Error.', ['service_usage' => 'A usage record already exists for this service in the specified month/year']);
        }

        $serviceUsage = ServiceUsage::create($input);

        return $this->sendResponse(
            new ServiceUsageResource($serviceUsage->load('roomService')),
            'Service usage created successfully.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();
        $serviceUsage = ServiceUsage::with('roomService.room.house', 'roomService.service')->find($id);

        if (is_null($serviceUsage)) {
            return $this->sendError('Service usage not found.');
        }

        // Authorization check
        if (!$this->canAccessServiceUsage($user, $serviceUsage)) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to view this service usage'], 403);
        }

        return $this->sendResponse(
            new ServiceUsageResource($serviceUsage),
            'Service usage retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $input = $request->all();
        $serviceUsage = ServiceUsage::with('roomService.room.house')->find($id);

        if (is_null($serviceUsage)) {
            return $this->sendError('Service usage not found.');
        }

        // Authorization check
        if (!$this->canManageServiceUsage($user, $serviceUsage)) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to update this service usage'], 403);
        }

        $validator = Validator::make($input, [
            'start_meter' => 'sometimes|nullable|numeric|min:0',
            'end_meter' => 'sometimes|nullable|numeric|min:0',
            'usage_value' => 'sometimes|numeric|min:0',
            'price_used' => 'sometimes|integer|min:0',
            'description' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        // Check if meters are valid when both are provided
        if (isset($input['start_meter']) && isset($input['end_meter']) && $input['start_meter'] > $input['end_meter']) {
            return $this->sendError('Validation Error.', ['end_meter' => 'End meter must be greater than or equal to start meter']);
        }

        // When only one meter is provided, check against existing value
        if (isset($input['start_meter']) && !isset($input['end_meter']) && $input['start_meter'] > $serviceUsage->end_meter) {
            return $this->sendError('Validation Error.', ['start_meter' => 'Start meter cannot be greater than end meter']);
        }

        if (!isset($input['start_meter']) && isset($input['end_meter']) && $serviceUsage->start_meter > $input['end_meter']) {
            return $this->sendError('Validation Error.', ['end_meter' => 'End meter cannot be less than start meter']);
        }

        $serviceUsage->update($input);

        return $this->sendResponse(
            new ServiceUsageResource($serviceUsage->load('roomService')),
            'Service usage updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();
        $serviceUsage = ServiceUsage::with('roomService.room.house')->find($id);

        if (is_null($serviceUsage)) {
            return $this->sendError('Service usage not found.');
        }

        // Authorization check - only admins and managers can delete service usages
        if (!$this->canManageServiceUsage($user, $serviceUsage)) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to delete this service usage'], 403);
        }

        $serviceUsage->delete();

        return $this->sendResponse([], 'Service usage deleted successfully.');
    }

    /**
     * Check if user can access a service usage
     */
    private function canAccessServiceUsage($user, $serviceUsage): bool
    {
        // Admins can access all service usages
        if ($user->role->code === 'admin') {
            return true;
        }

        // Tenants can only access service usages for rooms they occupy
        if ($user->role->code === 'tenant') {
            return $serviceUsage->roomService->room->contracts()
                ->whereHas('users', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                })
                ->exists();
        }

        // Managers can access service usages for houses they manage
        if ($user->role->code === 'manager') {
            return $user->id === $serviceUsage->roomService->room->house->manager_id;
        }

        return false;
    }

    /**
     * Check if user can manage a service usage (update/delete)
     */
    private function canManageServiceUsage($user, $serviceUsage): bool
    {
        // Admins can manage all service usages
        if ($user->role->code === 'admin') {
            return true;
        }

        // Tenants cannot manage service usages
        if ($user->role->code === 'tenant') {
            return false;
        }

        // Managers can manage service usages for houses they manage
        if ($user->role->code === 'manager') {
            return $user->id === $serviceUsage->roomService->room->house->manager_id;
        }

        return false;
    }
}
