<?php

namespace App\Services;

use App\Http\Resources\RoomEquipmentResource;
use App\Repositories\Interfaces\RoomEquipmentRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RoomEquipmentService
{
    protected $roomEquipmentRepository;

    public function __construct(RoomEquipmentRepositoryInterface $roomEquipmentRepository)
    {
        $this->roomEquipmentRepository = $roomEquipmentRepository;
    }

    /**
     * Get all room equipment with filters
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     * @throws \Exception
     */
    public function getAllRoomEquipment($request)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        // Build filters
        $filters = [
            'user' => $user,
            'room_id' => $request->room_id ?? null,
            'equipment_id' => $request->equipment_id ?? null,
            'min_quantity' => $request->min_quantity ?? null,
            'max_quantity' => $request->max_quantity ?? null,
            'min_price' => $request->min_price ?? null,
            'max_price' => $request->max_price ?? null,
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
            if (in_array('equipment', $includes)) $with[] = 'equipment';
            if (in_array('room.house', $includes)) $with[] = 'room.house';
        }

        // Sorting
        $sortField = $request->get('sort_by', 'id');
        $sortDirection = $request->get('sort_dir', 'asc');
        $perPage = $request->get('per_page', 15);

        $roomEquipments = $this->roomEquipmentRepository->getAllWithFilters(
            $filters,
            $with,
            $sortField,
            $sortDirection,
            $perPage
        );

        return RoomEquipmentResource::collection($roomEquipments)->response()->getData(true);
    }

    /**
     * Create new room equipment
     *
     * @param \Illuminate\Http\Request $request
     * @return \App\Models\RoomEquipment
     * @throws \Exception
     */
    public function createRoomEquipment($request)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $input = $request->all();
        $validator = Validator::make($input, [
            'room_id' => 'required|exists:rooms,id',
            'equipment_id' => 'required|exists:equipments,id',
            'quantity' => 'required|integer|min:1',
            'price' => 'required|integer|min:0',
            'description' => 'nullable|string',
        ], [
            'room_id.required' => 'Mã phòng không được để trống.',
            'room_id.exists' => 'Mã phòng không tồn tại.',
            'equipment_id.required' => 'Mã thiết bị không được để trống.',
            'equipment_id.exists' => 'Mã thiết bị không tồn tại.',
            'quantity.required' => 'Số lượng không được để trống.',
            'quantity.integer' => 'Số lượng phải là một số nguyên.',
            'quantity.min' => 'Số lượng phải lớn hơn 0.',
            'price.required' => 'Giá không được để trống.',
            'price.integer' => 'Giá phải là một số nguyên.',
            'price.min' => 'Giá phải lớn hơn 0.',
            'description.string' => 'Mô tả phải là một chuỗi.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Check authorization
        if (!$this->roomEquipmentRepository->canManageRoomEquipment($user, $input['room_id'])) {
            throw new \Exception('Chỉ quản trị viên hoặc quản lý nhà mới có thể quản lý thiết bị phòng.');
        }

        // Check if equipment already exists in the room
        $existingEquipment = $this->roomEquipmentRepository->findByRoomAndEquipment(
            $input['room_id'],
            $input['equipment_id']
        );

        if ($existingEquipment) {
            // Update existing equipment instead of creating new one
            $newQuantity = $existingEquipment->quantity + $input['quantity'];

            $updateData = [
                'quantity' => $newQuantity
            ];

            // Update price and description if provided
            if (isset($input['price'])) {
                $updateData['price'] = $input['price'];
            }

            if (isset($input['description'])) {
                $updateData['description'] = $input['description'];
            }

            $updatedEquipment = $this->roomEquipmentRepository->update($existingEquipment->id, $updateData);
            return $updatedEquipment->load(['room', 'equipment']);
        }

        // Create new room equipment
        $roomEquipment = $this->roomEquipmentRepository->create($input);
        return $roomEquipment->load(['room', 'equipment']);
    }

    /**
     * Get room equipment by ID
     *
     * @param string $id
     * @return \App\Models\RoomEquipment
     * @throws \Exception
     */
    public function getRoomEquipmentById($id)
    {
        $roomEquipment = $this->roomEquipmentRepository->getById($id, ['room', 'equipment']);

        if (!$roomEquipment) {
            throw new \Exception('Thiết bị phòng không tồn tại.');
        }

        return $roomEquipment;
    }

    /**
     * Update room equipment
     *
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @return \App\Models\RoomEquipment
     * @throws \Exception
     */
    public function updateRoomEquipment($request, $id)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $roomEquipment = $this->roomEquipmentRepository->getById($id);
        if (!$roomEquipment) {
            throw new \Exception('Thiết bị phòng không tồn tại.');
        }

        // Check authorization
        if (!$this->roomEquipmentRepository->canManageRoomEquipment($user, $roomEquipment->room_id)) {
            throw new \Exception('Chỉ quản trị viên hoặc quản lý nhà mới có thể cập nhật thiết bị phòng.');
        }

        $validator = Validator::make($request->all(), [
            'equipment_id' => 'sometimes|required|exists:equipments,id',
            'room_id' => 'sometimes|exists:rooms,id',
            'quantity' => 'sometimes|integer|min:1',
            'price' => 'sometimes|integer|min:0',
            'description' => 'nullable|string',
        ], [
            'equipment_id.exists' => 'Mã thiết bị không tồn tại.',
            'room_id.exists' => 'Mã phòng không tồn tại.',
            'quantity.integer' => 'Số lượng phải là một số nguyên.',
            'quantity.min' => 'Số lượng phải lớn hơn 0.',
            'price.integer' => 'Giá phải là một số nguyên.',
            'price.min' => 'Giá phải lớn hơn 0.',
            'description.string' => 'Mô tả phải là một chuỗi.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // If room_id is being changed, check if user has permission for the new room
        if ($request->has('room_id') && $request->room_id != $roomEquipment->room_id) {
            if (!$this->roomEquipmentRepository->canManageRoomEquipment($user, $request->room_id)) {
                throw new \Exception('Bạn không có quyền chuyển thiết bị sang phòng này.');
            }
        }

        $updatedRoomEquipment = $this->roomEquipmentRepository->update($id, $request->all());
        return $updatedRoomEquipment->load(['room', 'equipment']);
    }

    /**
     * Delete room equipment
     *
     * @param string $id
     * @return bool
     * @throws \Exception
     */
    public function deleteRoomEquipment($id)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $roomEquipment = $this->roomEquipmentRepository->getById($id);
        if (!$roomEquipment) {
            throw new \Exception('Thiết bị phòng không tồn tại.');
        }

        // Check authorization
        if (!$this->roomEquipmentRepository->canManageRoomEquipment($user, $roomEquipment->room_id)) {
            throw new \Exception('Chỉ quản trị viên hoặc quản lý nhà mới có thể xóa thiết bị phòng.');
        }

        return $this->roomEquipmentRepository->delete($id);
    }
}
