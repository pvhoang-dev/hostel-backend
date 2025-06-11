<?php

namespace App\Services;

use App\Http\Resources\RoomServiceResource;
use App\Models\Room;
use App\Repositories\Interfaces\RoomServiceRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RoomServiceService
{
    protected $roomServiceRepository;

    public function __construct(RoomServiceRepositoryInterface $roomServiceRepository)
    {
        $this->roomServiceRepository = $roomServiceRepository;
    }

    /**
     * Get all room services with filters
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     * @throws \Exception
     */
    public function getAllRoomServices($request)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        // Build filters
        $filters = [
            'user' => $user,
            'room_id' => $request->room_id ?? null,
            'service_id' => $request->service_id ?? null,
            'status' => $request->status ?? null,
            'is_fixed' => $request->is_fixed ?? null,
            'min_price' => $request->min_price ?? null,
            'max_price' => $request->max_price ?? null,
            'description' => $request->description ?? null,
            'created_from' => $request->created_from ?? null,
            'created_to' => $request->created_to ?? null,
            'updated_from' => $request->updated_from ?? null,
            'updated_to' => $request->updated_to ?? null
        ];

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
        $perPage = $request->get('per_page', 15);

        $roomServices = $this->roomServiceRepository->getAllWithFilters(
            $filters,
            $with,
            $sortField,
            $sortDirection,
            $perPage
        );

        return RoomServiceResource::collection($roomServices)->response()->getData(true);
    }

    /**
     * Create new room service
     *
     * @param \Illuminate\Http\Request $request
     * @return \App\Models\RoomService
     * @throws \Exception
     */
    public function createRoomService($request)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

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
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Check authorization
        $room = Room::with('house')->find($input['room_id']);
        if (!$room) {
            throw new \Exception('Phòng không tồn tại.');
        }

        // Only managers of the house or admins can add services to rooms
        if ($user->role->code === 'tenant') {
            throw new \Exception('Tenants không thể thêm dịch vụ vào phòng');
        } elseif ($user->role->code === 'manager' && $room->house->manager_id !== $user->id) {
            throw new \Exception('Bạn chỉ có thể thêm dịch vụ vào phòng trong nhà mà bạn quản lý');
        }

        // Check if the service already exists for this room
        $existingService = $this->roomServiceRepository->findByRoomAndService(
            $input['room_id'],
            $input['service_id']
        );

        if ($existingService) {
            throw ValidationException::withMessages(['service' => 'Dịch vụ này đã được gán cho phòng']);
        }

        // Set default status if not provided
        if (!isset($input['status'])) {
            $input['status'] = 'active';
        }

        $roomService = $this->roomServiceRepository->create($input);
        return $roomService->load(['room.house', 'service']);
    }

    /**
     * Get room service by ID
     *
     * @param string $id
     * @return \App\Models\RoomService
     * @throws \Exception
     */
    public function getRoomServiceById($id)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $roomService = $this->roomServiceRepository->getById($id, ['room.house', 'service']);
        if (!$roomService) {
            throw new \Exception('Dịch vụ phòng không tồn tại.');
        }

        // Authorization check
        if (!$this->roomServiceRepository->canAccessRoomService($user, $roomService)) {
            throw new \Exception('Bạn không có quyền xem dịch vụ phòng này');
        }

        return $roomService;
    }

    /**
     * Update room service
     *
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @return \App\Models\RoomService
     * @throws \Exception
     */
    public function updateRoomService($request, $id)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $roomService = $this->roomServiceRepository->getById($id, ['room.house']);
        if (!$roomService) {
            throw new \Exception('Dịch vụ phòng không tồn tại.');
        }

        // Authorization check
        if (!$this->roomServiceRepository->canManageRoomService($user, $roomService)) {
            throw new \Exception('Bạn không có quyền cập nhật dịch vụ phòng này');
        }

        $validator = Validator::make($request->all(), [
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
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $updatedRoomService = $this->roomServiceRepository->update($id, $request->all());
        return $updatedRoomService->load(['room.house', 'service']);
    }

    /**
     * Delete room service
     *
     * @param string $id
     * @return bool
     * @throws \Exception
     */
    public function deleteRoomService($id)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $roomService = $this->roomServiceRepository->getById($id, ['room.house']);
        if (!$roomService) {
            throw new \Exception('Dịch vụ phòng không tồn tại.');
        }

        // Authorization check
        if (!$this->roomServiceRepository->canManageRoomService($user, $roomService)) {
            throw new \Exception('Bạn không có quyền xóa dịch vụ phòng này');
        }

        return $this->roomServiceRepository->delete($id);
    }
}
