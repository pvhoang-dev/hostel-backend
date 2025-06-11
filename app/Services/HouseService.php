<?php

namespace App\Services;

use App\Http\Resources\HouseResource;
use App\Repositories\Interfaces\HouseRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class HouseService
{
    protected $houseRepository;
    protected $notificationService;

    public function __construct(
        HouseRepositoryInterface $houseRepository,
        NotificationService $notificationService
    ) {
        $this->houseRepository = $houseRepository;
        $this->notificationService = $notificationService;
    }

    /**
     * Lấy danh sách nhà trọ
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function getAllHouses($request)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $filters = [
            'current_user' => $user,
            'name' => $request->name ?? null,
            'status' => $request->status ?? null,
            'address' => $request->address ?? null,
            'created_from' => $request->created_from ?? null,
            'created_to' => $request->created_to ?? null,
            'updated_from' => $request->updated_from ?? null,
            'updated_to' => $request->updated_to ?? null
        ];

        // Filter by manager_id (admin only)
        if ($user->role->code === 'admin' && $request->has('manager_id')) {
            $filters['manager_id'] = $request->manager_id;
        }

        // Include relationships
        $with = ['manager'];

        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('updater', $includes)) {
                $with[] = 'updater';
            }
            if (in_array('rooms', $includes)) {
                $with[] = 'rooms';
            }
        }

        // Sorting
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_dir', 'desc');
        $perPage = $request->get('per_page', 15);

        $houses = $this->houseRepository->getAllWithFilters(
            $filters,
            $with,
            $sortField,
            $sortDirection,
            $perPage
        );

        return HouseResource::collection($houses)->response()->getData(true);
    }

    /**
     * Lấy thông tin nhà trọ theo ID
     *
     * @param string $id
     * @return \App\Models\House
     */
    public function getHouseById($id)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $house = $this->houseRepository->getById($id, ['manager', 'updater']);

        if (is_null($house)) {
            throw new \Exception('Không tìm thấy nhà trọ.');
        }

        // Kiểm tra quyền truy cập
        if (!$this->houseRepository->canViewHouse($user, $house)) {
            throw new \Exception('Lỗi xác thực. Bạn không có quyền xem thông tin nhà trọ này');
        }

        return $house;
    }

    /**
     * Tạo nhà trọ mới
     *
     * @param \Illuminate\Http\Request $request
     * @return \App\Models\House
     */
    public function createHouse($request)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Lỗi xác thực.');
        }

        // Chỉ admin mới có thể tạo nhà trọ mới
        if ($currentUser->role->code !== 'admin') {
            throw new \Exception('Lỗi xác thực. Chỉ quản trị viên mới có thể tạo nhà trọ mới');
        }

        $input = $request->all();
        $validator = Validator::make($input, [
            'name' => 'required|string|max:100',
            'address' => 'required|string|max:255',
            'manager_id' => 'nullable|exists:users,id',
            'status' => 'sometimes|string',
            'description' => 'nullable|string',
        ], [
            'name.required' => 'Tên nhà trọ là bắt buộc.',
            'name.string' => 'Tên nhà trọ phải là chuỗi.',
            'name.max' => 'Tên nhà trọ không được vượt quá 100 ký tự.',
            'address.required' => 'Địa chỉ nhà trọ là bắt buộc.',
            'address.string' => 'Địa chỉ nhà trọ phải là chuỗi.',
            'address.max' => 'Địa chỉ nhà trọ không được vượt quá 255 ký tự.',
            'manager_id.exists' => 'Quản lý không tồn tại.',
            'status.string' => 'Trạng thái phải là chuỗi.',
            'description.string' => 'Mô tả phải là chuỗi.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        if (empty($input['manager_id'])) {
            $input['manager_id'] = $currentUser->id;
        }

        $input['created_by'] = $currentUser->id;
        $input['updated_by'] = $currentUser->id;

        $house = $this->houseRepository->create($input);
        
        if ($house->manager_id !== $currentUser->id) {
            $this->notificationService->create(
                $house->manager_id,
                'house',
                "Nhà trọ {$house->name} đã được tạo. Bạn là quản lý của nhà trọ này.",
                "/houses/{$house->id}",
                false
            );
        }

        // Tải thêm thông tin quan hệ để trả về
        $house->load(['manager', 'updater']);

        return $house;
    }

    /**
     * Cập nhật nhà trọ
     *
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @return \App\Models\House
     */
    public function updateHouse($request, $id)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Lỗi xác thực.');
        }

        // Check if user is admin or manager
        if (!in_array($currentUser->role->code, ['admin', 'manager'])) {
            throw new \Exception('Lỗi xác thực. Chỉ quản trị viên hoặc quản lý mới có quyền cập nhật nhà trọ');
        }

        $house = $this->houseRepository->getById($id);

        if (is_null($house)) {
            throw new \Exception('Không tìm thấy nhà trọ.');
        }

        // Check permissions
        if (!$this->houseRepository->canManageHouse($currentUser, $house)) {
            throw new \Exception('Lỗi xác thực. Bạn không có quyền cập nhật nhà trọ này');
        }

        // Determine which fields can be updated based on role
        $isAdmin = $currentUser->role->code === 'admin';
        $fieldsAllowed = $isAdmin
            ? ['name', 'address', 'manager_id', 'status', 'description']
            : ['name', 'address', 'status', 'description'];

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'address' => 'sometimes|required|string|max:255',
            'manager_id' => $isAdmin ? 'sometimes|nullable|exists:users,id' : '',
            'status' => 'sometimes|string',
            'description' => 'sometimes|nullable|string',
        ], [
            'name.required' => 'Tên nhà trọ là bắt buộc.',
            'name.string' => 'Tên nhà trọ phải là chuỗi.',
            'name.max' => 'Tên nhà trọ không được vượt quá 100 ký tự.',
            'address.required' => 'Địa chỉ nhà trọ là bắt buộc.',
            'address.string' => 'Địa chỉ nhà trọ phải là chuỗi.',
            'address.max' => 'Địa chỉ nhà trọ không được vượt quá 255 ký tự.',
            'manager_id.exists' => 'Quản lý không tồn tại.',
            'status.string' => 'Trạng thái phải là chuỗi.',
            'description.string' => 'Mô tả phải là chuỗi.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Filter request data based on allowed fields
        $input = array_intersect_key($request->all(), array_flip($fieldsAllowed));

        if ($isAdmin && isset($input['manager_id']) && empty($input['manager_id'])) {
            $input['manager_id'] = $currentUser->id;
        }

        // Add updated_by
        $input['updated_by'] = $currentUser->id;

        $house = $this->houseRepository->update($id, $input);
        $house->load(['manager', 'updater']);

        if ($house->manager_id !== $currentUser->id) {
            $this->notificationService->create(
                $house->manager_id,
                'house',
                "Nhà trọ {$house->name} đã được cập nhật. Bạn là quản lý của nhà trọ này.",
                "/houses/{$house->id}",
                false
            );
        }

        return $house;
    }

    /**
     * Xóa nhà trọ
     *
     * @param string $id
     * @return bool
     */
    public function deleteHouse($id)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Lỗi xác thực.');
        }

        // Chỉ admin mới có thể xóa nhà trọ
        if ($currentUser->role->code !== 'admin') {
            throw new \Exception('Lỗi xác thực. Chỉ quản trị viên mới có thể xóa nhà trọ');
        }

        $house = $this->houseRepository->getById($id);

        if (is_null($house)) {
            throw new \Exception('Không tìm thấy nhà trọ.');
        }

        if ($house->manager_id !== $currentUser->id) {
            $this->notificationService->create(
                $house->manager_id,
                'house',
                "Nhà trọ {$house->name} đã bị xóa.",
                "/houses",
                false
            );
        }

        return $this->houseRepository->delete($id);
    }
} 