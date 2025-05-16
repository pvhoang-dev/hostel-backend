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
            'Sử dụng dịch vụ đã được lấy thành công.'
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
        ], [
            'room_service_id.required' => 'ID dịch vụ phòng là bắt buộc.',
            'room_service_id.exists' => 'ID dịch vụ phòng không hợp lệ.',
            'start_meter.numeric' => 'Đọc công tơ bắt đầu phải là một số.',
            'start_meter.min' => 'Đọc công tơ bắt đầu phải lớn hơn 0.',
            'end_meter.numeric' => 'Đọc công tơ kết thúc phải là một số.',
            'end_meter.min' => 'Đọc công tơ kết thúc phải lớn hơn 0.',
            'usage_value.required' => 'Giá trị sử dụng là bắt buộc.',
            'usage_value.numeric' => 'Giá trị sử dụng phải là một số.',
            'usage_value.min' => 'Giá trị sử dụng phải lớn hơn 0.',
            'month.required' => 'Tháng là bắt buộc.',
            'month.integer' => 'Tháng phải là một số.',
            'month.min' => 'Tháng phải lớn hơn 0.',
            'month.max' => 'Tháng phải nhỏ hơn 13.',
            'year.required' => 'Năm là bắt buộc.',
            'year.integer' => 'Năm phải là một số.',
            'year.min' => 'Năm phải lớn hơn 2000.',
            'year.max' => 'Năm phải nhỏ hơn 2100.',
            'price_used.required' => 'Giá sử dụng là bắt buộc.',
            'price_used.integer' => 'Giá sử dụng phải là một số.',
            'price_used.min' => 'Giá sử dụng phải lớn hơn 0.',
            'description.string' => 'Mô tả phải là một chuỗi.',
        ]);


        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        // Check if meters are valid
        if (isset($input['start_meter']) && isset($input['end_meter']) && $input['start_meter'] > $input['end_meter']) {
            return $this->sendError('Lỗi dữ liệu.', ['end_meter' => 'Đọc công tơ kết thúc phải lớn hơn hoặc bằng đọc công tơ bắt đầu']);
        }

        // Check authorization
        $roomService = RoomService::with('room.house')->find($input['room_service_id']);
        if (!$roomService) {
            return $this->sendError('Dịch vụ phòng không tồn tại.');
        }

        // Only managers of the house or admins can add service usages
        if ($user->role->code === 'tenant') {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Người thuê không thể thêm sử dụng dịch vụ'], 403);
        } elseif ($user->role->code === 'manager' && $roomService->room->house->manager_id !== $user->id) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn chỉ có thể thêm sử dụng dịch vụ cho phòng trong nhà ở bạn quản lý'], 403);
        }

        // Check if a record already exists for this service in the specified month/year
        $existingUsage = ServiceUsage::where('room_service_id', $input['room_service_id'])
            ->where('month', $input['month'])
            ->where('year', $input['year'])
            ->first();

        if ($existingUsage) {
            return $this->sendError('Lỗi dữ liệu.', ['service_usage' => 'Đã có bản ghi sử dụng dịch vụ cho tháng/năm này']);
        }

        $serviceUsage = ServiceUsage::create($input);

        return $this->sendResponse(
            new ServiceUsageResource($serviceUsage->load('roomService')),
            'Sử dụng dịch vụ đã được tạo thành công.'
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
            return $this->sendError('Bản ghi sử dụng dịch vụ không tồn tại.');
        }

        // Authorization check
        if (!$this->canAccessServiceUsage($user, $serviceUsage)) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền xem bản ghi sử dụng dịch vụ này'], 403);
        }

        return $this->sendResponse(
            new ServiceUsageResource($serviceUsage),
            'Sử dụng dịch vụ đã được lấy thành công.'
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
            return $this->sendError('Bản ghi sử dụng dịch vụ không tồn tại.');
        }

        // Authorization check
        if (!$this->canManageServiceUsage($user, $serviceUsage)) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền cập nhật bản ghi sử dụng dịch vụ này'], 403);
        }

        $validator = Validator::make($input, [
            'start_meter' => 'sometimes|nullable|numeric|min:0',
            'end_meter' => 'sometimes|nullable|numeric|min:0',
            'usage_value' => 'sometimes|numeric|min:0',
            'price_used' => 'sometimes|integer|min:0',
            'description' => 'sometimes|nullable|string',
        ], [
            'start_meter.numeric' => 'Đọc công tơ bắt đầu phải là một số.',
            'start_meter.min' => 'Đọc công tơ bắt đầu phải lớn hơn 0.',
            'end_meter.numeric' => 'Đọc công tơ kết thúc phải là một số.',
            'end_meter.min' => 'Đọc công tơ kết thúc phải lớn hơn 0.',
            'usage_value.numeric' => 'Giá trị sử dụng phải là một số.',
            'usage_value.min' => 'Giá trị sử dụng phải lớn hơn 0.',
            'price_used.integer' => 'Giá sử dụng phải là một số.',
            'price_used.min' => 'Giá sử dụng phải lớn hơn 0.',
            'description.string' => 'Mô tả phải là một chuỗi.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Lỗi dữ liệu.', $validator->errors());
        }

        // Check if meters are valid when both are provided
        if (isset($input['start_meter']) && isset($input['end_meter']) && $input['start_meter'] > $input['end_meter']) {
            return $this->sendError('Lỗi dữ liệu.', ['end_meter' => 'Đọc công tơ kết thúc phải lớn hơn hoặc bằng đọc công tơ bắt đầu']);
        }

        // When only one meter is provided, check against existing value
        if (isset($input['start_meter']) && !isset($input['end_meter']) && $input['start_meter'] > $serviceUsage->end_meter) {
            return $this->sendError('Lỗi dữ liệu.', ['start_meter' => 'Đọc công tơ bắt đầu không thể lớn hơn đọc công tơ kết thúc']);
        }

        if (!isset($input['start_meter']) && isset($input['end_meter']) && $serviceUsage->start_meter > $input['end_meter']) {
            return $this->sendError('Lỗi dữ liệu.', ['end_meter' => 'Đọc công tơ kết thúc không thể nhỏ hơn đọc công tơ bắt đầu']);
        }

        $serviceUsage->update($input);

        return $this->sendResponse(
            new ServiceUsageResource($serviceUsage->load('roomService')),
            'Sử dụng dịch vụ đã được cập nhật thành công.'
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
            return $this->sendError('Bản ghi sử dụng dịch vụ không tồn tại.');
        }

        // Authorization check - only admins and managers can delete service usages
        if (!$this->canManageServiceUsage($user, $serviceUsage)) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền xóa bản ghi sử dụng dịch vụ này'], 403);
        }

        $serviceUsage->delete();

        return $this->sendResponse([], 'Bản ghi sử dụng dịch vụ đã được xóa thành công.');
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
