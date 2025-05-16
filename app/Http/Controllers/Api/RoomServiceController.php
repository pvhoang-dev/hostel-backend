<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\RoomServiceResource;
use App\Models\House;
use App\Models\Room;
use App\Models\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RoomServiceController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = RoomService::query();

        // Apply role-based filters
        if ($user->role->code === 'tenant') {
            // Tenants can only see services for rooms they occupy
            $query->whereHas('room.contracts.users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        } elseif ($user->role->code === 'manager') {
            // Managers can see services for houses they manage
            $managedHouseIds = House::where('manager_id', $user->id)->pluck('id');
            $query->whereHas('room', function ($q) use ($managedHouseIds) {
                $q->whereIn('house_id', $managedHouseIds);
            });
        }
        // Admins can see all services, so no filter needed

        // Apply additional filters
        if ($request->has('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        if ($request->has('service_id')) {
            $query->where('service_id', $request->service_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('is_fixed')) {
            $query->where('is_fixed', $request->is_fixed);
        }

        // Price range filters
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Text search in description
        if ($request->has('description')) {
            $query->where('description', 'like', '%' . $request->description . '%');
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
            if (in_array('room', $includes)) $with[] = 'room';
            if (in_array('service', $includes)) $with[] = 'service';
            if (in_array('usages', $includes)) $with[] = 'usages';
            if (in_array('room.house', $includes)) $with[] = 'room.house';
        }

        // Sorting
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_dir', 'desc');
        $allowedSortFields = ['id', 'room_id', 'service_id', 'price', 'is_fixed', 'status', 'created_at', 'updated_at'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $roomServices = $query->with($with)->paginate($perPage);

        return $this->sendResponse(
            RoomServiceResource::collection($roomServices)->response()->getData(true),
            'Dịch vụ phòng đã được lấy thành công.'
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
            'room_id' => 'required|exists:rooms,id',
            'service_id' => 'required|exists:services,id',
            'price' => 'required|integer|min:0',
            'is_fixed' => 'required|boolean',
            'status' => 'sometimes|string|max:20',
            'description' => 'nullable|string',
        ], [
            'room_id.required' => 'Mã phòng không được để trống.',
            'room_id.exists' => 'Mã phòng không tồn tại.',
            'service_id.required' => 'Mã dịch vụ không được để trống.',
            'service_id.exists' => 'Mã dịch vụ không tồn tại.',
            'price.required' => 'Giá không được để trống.',
            'price.integer' => 'Giá phải là một số nguyên.',
            'price.min' => 'Giá phải lớn hơn 0.',
            'is_fixed.required' => 'Trạng thái cố định không được để trống.',
            'is_fixed.boolean' => 'Trạng thái cố định phải là true hoặc false.',
            'status.string' => 'Trạng thái phải là một chuỗi.',
            'status.max' => 'Trạng thái không được vượt quá 20 ký tự.',
            'description.string' => 'Mô tả phải là một chuỗi.',
        ]);


        if ($validator->fails()) {
            return $this->sendError('Lỗi dữ liệu.', $validator->errors());
        }

        // Check authorization
        $room = Room::with('house')->find($input['room_id']);
        if (!$room) {
            return $this->sendError('Phòng không tồn tại.');
        }

        // Only managers of the house or admins can add services to rooms
        if ($user->role->code === 'tenant') {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Tenants không thể thêm dịch vụ vào phòng'], 403);
        } elseif ($user->role->code === 'manager' && $room->house->manager_id !== $user->id) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn chỉ có thể thêm dịch vụ vào phòng trong nhà mà bạn quản lý'], 403);
        }

        // Check if the service already exists for this room
        $existingService = RoomService::where('room_id', $input['room_id'])
            ->where('service_id', $input['service_id'])
            ->first();

        if ($existingService) {
            return $this->sendError('Lỗi dữ liệu.', ['service' => 'Dịch vụ này đã được gán cho phòng']);
        }

        // Set default status if not provided
        if (!isset($input['status'])) {
            $input['status'] = 'active';
        }

        $roomService = RoomService::create($input);

        return $this->sendResponse(
            new RoomServiceResource($roomService->load(['room.house', 'service'])),
            'Dịch vụ phòng đã được tạo thành công.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();
        $roomService = RoomService::with(['room.house', 'service'])->find($id);

        if (is_null($roomService)) {
            return $this->sendError('Dịch vụ phòng không tồn tại.');
        }

        // Authorization check
        if (!$this->canAccessRoomService($user, $roomService)) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền xem dịch vụ phòng này'], 403);
        }

        return $this->sendResponse(
            new RoomServiceResource($roomService),
            'Dịch vụ phòng đã được lấy thành công.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $input = $request->all();
        $roomService = RoomService::with('room.house')->find($id);

        if (is_null($roomService)) {
            return $this->sendError('Dịch vụ phòng không tồn tại.');
        }

        // Authorization check
        if (!$this->canManageRoomService($user, $roomService)) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền cập nhật dịch vụ phòng này'], 403);
        }

        $validator = Validator::make($input, [
            'price' => 'sometimes|integer|min:0',
            'is_fixed' => 'sometimes|boolean',
            'status' => 'sometimes|string|max:20',
            'description' => 'sometimes|nullable|string',
        ], [
            'price.integer' => 'Giá phải là một số nguyên.',
            'price.min' => 'Giá phải lớn hơn 0.',
            'is_fixed.boolean' => 'Trạng thái cố định phải là true hoặc false.',
            'status.string' => 'Trạng thái phải là một chuỗi.',
            'status.max' => 'Trạng thái không được vượt quá 20 ký tự.',
            'description.string' => 'Mô tả phải là một chuỗi.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Lỗi dữ liệu.', $validator->errors());
        }

        $roomService->update($input);

        return $this->sendResponse(
            new RoomServiceResource($roomService->load(['room.house', 'service'])),
            'Dịch vụ phòng đã được cập nhật thành công.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();
        $roomService = RoomService::with('room.house')->find($id);

        if (is_null($roomService)) {
            return $this->sendError('Dịch vụ phòng không tồn tại.');
        }

        // Authorization check - only admins and managers can delete room services
        if (!$this->canManageRoomService($user, $roomService)) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền xóa dịch vụ phòng này'], 403);
        }

        $roomService->delete();

        return $this->sendResponse([], 'Dịch vụ phòng đã được xóa thành công.');
    }

    /**
     * Check if user can access a room service
     */
    private function canAccessRoomService($user, $roomService): bool
    {
        // Admins can access all room services
        if ($user->role->code === 'admin') {
            return true;
        }

        // Tenants can only access room services for rooms they occupy
        if ($user->role->code === 'tenant') {
            return $roomService->room->contracts()
                ->whereHas('users', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                })
                ->exists();
        }

        // Managers can access room services for houses they manage
        if ($user->role->code === 'manager') {
            return $user->id === $roomService->room->house->manager_id;
        }

        return false;
    }

    /**
     * Check if user can manage a room service (update/delete)
     */
    private function canManageRoomService($user, $roomService): bool
    {
        // Admins can manage all room services
        if ($user->role->code === 'admin') {
            return true;
        }

        // Tenants cannot manage room services
        if ($user->role->code === 'tenant') {
            return false;
        }

        // Managers can manage room services for houses they manage
        if ($user->role->code === 'manager') {
            return $user->id === $roomService->room->house->manager_id;
        }

        return false;
    }
}
