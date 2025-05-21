<?php

namespace App\Services;

use App\Http\Resources\RoomResource;
use App\Models\House;
use App\Models\Room;
use App\Repositories\Interfaces\RoomRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoomService
{
    protected $roomRepository;
    protected $notificationService;

    public function __construct(
        RoomRepositoryInterface $roomRepository,
        NotificationService $notificationService
    ) {
        $this->roomRepository = $roomRepository;
        $this->notificationService = $notificationService;
    }

    /**
     * Lấy danh sách phòng
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getAllRooms(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.', 401);
        }

        $filters = [
            'user' => $user,
            'user_role' => $user->role->code,
            'house_id' => $request->house_id ?? null,
            'room_number' => $request->room_number ?? null,
            'capacity' => $request->capacity ?? null,
            'min_capacity' => $request->min_capacity ?? null,
            'max_capacity' => $request->max_capacity ?? null,
            'status' => $request->status ?? null,
            'min_price' => $request->min_price ?? null,
            'max_price' => $request->max_price ?? null,
            'created_from' => $request->created_from ?? null,
            'created_to' => $request->created_to ?? null,
            'updated_from' => $request->updated_from ?? null,
            'updated_to' => $request->updated_to ?? null,
        ];

        // Thêm danh sách nhà được quản lý cho manager
        if ($user->role->code === 'manager') {
            $filters['managed_house_ids'] = House::where('manager_id', $user->id)->pluck('id')->toArray();
        } 
        // Thêm danh sách phòng có hợp đồng đang hoạt động cho tenant
        elseif ($user->role->code === 'tenant') {
            $filters['active_contract_room_ids'] = $user->contracts()
                ->where('status', 'active')
                ->pluck('room_id')
                ->toArray();
        }

        // Thêm các mối quan hệ cần eager loading
        $with = ['house'];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('services', $includes)) $with[] = 'services';
            if (in_array('contracts', $includes)) $with[] = 'contracts';
            if (in_array('currentContract', $includes)) $with[] = 'currentContract';
            if (in_array('creator', $includes)) $with[] = 'creator';
            if (in_array('updater', $includes)) $with[] = 'updater';
        }

        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_dir', 'desc');
        $perPage = $request->get('per_page', 15);

        $rooms = $this->roomRepository->getAllWithFilters($filters, $with, $sortField, $sortDirection, $perPage);
        
        $result = RoomResource::collection($rooms);
        return $result->response()->getData(true);
    }

    /**
     * Tạo phòng mới
     *
     * @param Request $request
     * @return Room
     * @throws \Exception
     */
    public function createRoom(Request $request)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Lỗi xác thực.', 401);
        }

        $input = $request->all();
        $validator = Validator::make($input, [
            'house_id' => 'required|exists:houses,id',
            'room_number' => [
                'required',
                'string',
                'max:10',
                Rule::unique('rooms')->where(function ($query) use ($request) {
                    return $query->where('house_id', $request->house_id);
                }),
            ],
            'capacity' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'status' => 'sometimes|string|max:10',
            'base_price' => 'required|integer|min:0',
        ], [
            'house_id.required' => 'Vui lòng chọn nhà.',
            'house_id.exists' => 'Nhà không tồn tại.',
            'room_number.required' => 'Số phòng là bắt buộc.',
            'room_number.string' => 'Số phòng phải là chuỗi.',
            'room_number.max' => 'Số phòng không được vượt quá 10 ký tự.',
            'room_number.unique' => 'Số phòng đã tồn tại trong nhà này.',
            'capacity.required' => 'Sức chứa là bắt buộc.',
            'capacity.integer' => 'Sức chứa phải là số nguyên.',
            'capacity.min' => 'Sức chứa phải lớn hơn hoặc bằng 1.',
            'status.string' => 'Trạng thái phải là chuỗi.',
            'status.max' => 'Trạng thái không được vượt quá 10 ký tự.',
            'base_price.required' => 'Giá cơ bản là bắt buộc.',
            'base_price.integer' => 'Giá cơ bản phải là số nguyên.',
            'base_price.min' => 'Giá cơ bản phải lớn hơn hoặc bằng 0.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Kiểm tra quyền: chỉ admin hoặc quản lý nhà mới có thể tạo phòng
        $house = House::find($input['house_id']);
        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $house && $house->manager_id === $currentUser->id;

        if (!$isAdmin && !$isManager) {
            throw new \Exception('Chỉ quản trị viên hoặc quản lý nhà mới có thể tạo phòng.', 403);
        }

        // Thêm thông tin người tạo
        $input['created_by'] = $currentUser->id;

        // Tạo phòng mới
        $room = $this->roomRepository->create($input);

        // Gửi thông báo cho quản lý nhà
        if ($room && $house->manager_id && $currentUser->id !== $house->manager_id) {
            $this->notificationService->sendNotification(
                $house->manager_id,
                'Phòng mới đã được tạo',
                'Phòng ' . $room->room_number . ' đã được tạo trong nhà ' . $house->name,
                [
                    'type' => 'room_created',
                    'room_id' => $room->id
                ]
            );
        }

        return $room;
    }

    /**
     * Lấy thông tin chi tiết phòng
     *
     * @param int $id
     * @return Room
     * @throws \Exception
     */
    public function getRoomById(int $id)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.', 401);
        }

        $room = $this->roomRepository->getById($id);
        if (is_null($room)) {
            throw new \Exception('Phòng không tồn tại.', 404);
        }

        // Kiểm tra quyền truy cập
        $isAdmin = $user->role->code === 'admin';
        $isManager = $room->house && $room->house->manager_id === $user->id;
        $isTenant = $user->role->code === 'tenant' && $user->contracts()
            ->where('room_id', $room->id)
            ->where('status', 'active')
            ->exists();

        if (!$isAdmin && !$isManager && !$isTenant) {
            throw new \Exception('Bạn không có quyền xem thông tin phòng này.', 403);
        }

        return $room;
    }

    /**
     * Cập nhật thông tin phòng
     *
     * @param Request $request
     * @param int $id
     * @return Room
     * @throws \Exception
     */
    public function updateRoom(Request $request, int $id)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Lỗi xác thực.', 401);
        }

        $room = $this->roomRepository->getById($id);
        if (is_null($room)) {
            throw new \Exception('Phòng không tồn tại.', 404);
        }

        // Kiểm tra quyền: chỉ admin hoặc quản lý nhà mới có thể cập nhật phòng
        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $room->house && $room->house->manager_id === $currentUser->id;

        if (!$isAdmin && !$isManager) {
            throw new \Exception('Chỉ quản trị viên hoặc quản lý nhà mới có thể cập nhật phòng.', 403);
        }

        $input = $request->all();
        $validator = Validator::make($input, [
            'house_id' => 'sometimes|exists:houses,id',
            'room_number' => [
                'sometimes',
                'required',
                'string',
                'max:10',
                Rule::unique('rooms')->where(function ($query) use ($request, $room) {
                    return $query->where('house_id', $request->input('house_id', $room->house_id));
                })->ignore($id),
            ],
            'capacity' => 'sometimes|required|integer|min:1',
            'description' => 'nullable|string',
            'status' => 'sometimes|string|max:10',
            'base_price' => 'sometimes|required|integer|min:0',
        ], [
            'house_id.exists' => 'Nhà không tồn tại.',
            'room_number.required' => 'Số phòng là bắt buộc.',
            'room_number.string' => 'Số phòng phải là chuỗi.',
            'room_number.max' => 'Số phòng không được vượt quá 10 ký tự.',
            'room_number.unique' => 'Số phòng đã tồn tại trong nhà này.',
            'capacity.required' => 'Sức chứa là bắt buộc.',
            'capacity.integer' => 'Sức chứa phải là số nguyên.',
            'capacity.min' => 'Sức chứa phải lớn hơn hoặc bằng 1.',
            'status.string' => 'Trạng thái phải là chuỗi.',
            'status.max' => 'Trạng thái không được vượt quá 10 ký tự.',
            'base_price.required' => 'Giá cơ bản là bắt buộc.',
            'base_price.integer' => 'Giá cơ bản phải là số nguyên.',
            'base_price.min' => 'Giá cơ bản phải lớn hơn hoặc bằng 0.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Nếu thay đổi house_id, kiểm tra quyền
        if (isset($input['house_id']) && $input['house_id'] != $room->house_id) {
            $newHouse = House::find($input['house_id']);
            $canChangeHouse = $isAdmin || ($isManager && $newHouse && $newHouse->manager_id === $currentUser->id);
            
            if (!$canChangeHouse) {
                throw new \Exception('Bạn không có quyền chuyển phòng sang nhà khác.', 403);
            }
        }

        // Thêm thông tin người cập nhật
        $input['updated_by'] = $currentUser->id;

        // Cập nhật phòng
        $room = $this->roomRepository->update($room, $input);

        return $room;
    }

    /**
     * Xóa phòng
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function deleteRoom(int $id)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Lỗi xác thực.', 401);
        }

        $room = $this->roomRepository->getById($id);
        if (is_null($room)) {
            throw new \Exception('Phòng không tồn tại.', 404);
        }

        // Kiểm tra quyền: chỉ admin hoặc quản lý nhà mới có thể xóa phòng
        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $room->house && $room->house->manager_id === $currentUser->id;

        if (!$isAdmin && !$isManager) {
            throw new \Exception('Chỉ quản trị viên hoặc quản lý nhà mới có thể xóa phòng.', 403);
        }

        // Kiểm tra xem có hợp đồng active nào không
        if ($room->contracts()->where('status', 'active')->exists()) {
            throw new \Exception('Không thể xóa phòng có hợp đồng chưa chấm dứt.', 422);
        }

        return $this->roomRepository->delete($room);
    }
} 