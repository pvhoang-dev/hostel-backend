<?php

namespace App\Services;

use App\Http\Resources\EquipmentStorageResource;
use App\Models\EquipmentStorage;
use App\Models\House;
use App\Repositories\Interfaces\StorageRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StorageService
{
    protected $storageRepository;

    public function __construct(StorageRepositoryInterface $storageRepository)
    {
        $this->storageRepository = $storageRepository;
    }

    /**
     * Lấy danh sách kho thiết bị
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getAllStorage(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.', 401);
        }

        $filters = [
            'user' => $user,
            'user_role' => $user->role->code,
            'house_id' => $request->house_id ?? null,
            'equipment_id' => $request->equipment_id ?? null,
            'min_quantity' => $request->min_quantity ?? null,
            'max_quantity' => $request->max_quantity ?? null,
            'min_price' => $request->min_price ?? null,
            'max_price' => $request->max_price ?? null,
            'description' => $request->description ?? null,
            'created_from' => $request->created_from ?? null,
            'created_to' => $request->created_to ?? null,
            'updated_from' => $request->updated_from ?? null,
            'updated_to' => $request->updated_to ?? null,
        ];

        // Thêm danh sách nhà được quản lý cho manager
        if ($user->role->code === 'manager') {
            $filters['managed_house_ids'] = House::where('manager_id', $user->id)->pluck('id')->toArray();
        }

        // Thêm các mối quan hệ cần eager loading
        $with = ['equipment'];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('house', $includes)) $with[] = 'house';
        }

        $sortField = $request->get('sort_by', 'id');
        $sortDirection = $request->get('sort_dir', 'asc');
        $perPage = $request->get('per_page', 15);

        $storage = $this->storageRepository->getAllWithFilters($filters, $with, $sortField, $sortDirection, $perPage);
        
        $result = EquipmentStorageResource::collection($storage);
        return $result->response()->getData(true);
    }

    /**
     * Tạo kho thiết bị mới
     *
     * @param Request $request
     * @return EquipmentStorage
     * @throws \Exception
     */
    public function createStorage(Request $request)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Chưa đăng nhập.', 401);
        }

        $input = $request->all();
        $validator = Validator::make($input, [
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
        ], [
            'house_id.required' => 'Mã nhà là bắt buộc.',
            'house_id.exists' => 'Mã nhà không tồn tại.',
            'equipment_id.required' => 'Mã thiết bị là bắt buộc.',
            'equipment_id.exists' => 'Mã thiết bị không tồn tại.',
            'equipment_id.unique' => 'Thiết bị đã tồn tại trong kho.',
            'quantity.required' => 'Số lượng là bắt buộc.',
            'quantity.integer' => 'Số lượng phải là số nguyên.',
            'quantity.min' => 'Số lượng phải lớn hơn 0.',
            'price.integer' => 'Giá phải là số nguyên.',
            'price.min' => 'Giá phải lớn hơn 0.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $house = House::find($request->house_id);
        if (!$this->canManageStorage($currentUser, $house)) {
            throw new \Exception('Không có quyền. Chỉ admin hoặc quản lý nhà mới có thể tạo kho thiết bị.', 403);
        }

        return $this->storageRepository->create($input);
    }

    /**
     * Lấy thông tin chi tiết kho thiết bị
     *
     * @param int $id
     * @return EquipmentStorage
     * @throws \Exception
     */
    public function getStorageById(int $id)
    {
        $storage = $this->storageRepository->getById($id);
        if (is_null($storage)) {
            throw new \Exception('Không tìm thấy kho thiết bị.', 404);
        }

        return $storage;
    }

    /**
     * Cập nhật kho thiết bị
     *
     * @param Request $request
     * @param int $id
     * @return EquipmentStorage
     * @throws \Exception
     */
    public function updateStorage(Request $request, int $id)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Chưa đăng nhập.', 401);
        }

        $storage = $this->storageRepository->getById($id);
        if (is_null($storage)) {
            throw new \Exception('Không tìm thấy kho thiết bị.', 404);
        }

        if (!$this->canManageStorage($currentUser, $storage->house)) {
            throw new \Exception('Không có quyền. Chỉ admin hoặc quản lý nhà mới có thể cập nhật kho thiết bị.', 403);
        }

        $input = $request->all();
        $validator = Validator::make($input, [
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
        ], [
            'house_id.required' => 'Mã nhà là bắt buộc.',
            'house_id.exists' => 'Mã nhà không tồn tại.',
            'equipment_id.required' => 'Mã thiết bị là bắt buộc.',
            'equipment_id.exists' => 'Mã thiết bị không tồn tại.',
            'equipment_id.unique' => 'Thiết bị đã tồn tại trong kho.',
            'quantity.required' => 'Số lượng là bắt buộc.',
            'quantity.integer' => 'Số lượng phải là số nguyên.',
            'quantity.min' => 'Số lượng phải lớn hơn 0.',
            'price.integer' => 'Giá phải là số nguyên.',
            'price.min' => 'Giá phải lớn hơn 0.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $this->storageRepository->update($storage, $input);
    }

    /**
     * Xóa kho thiết bị
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function deleteStorage(int $id)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Chưa đăng nhập.', 401);
        }

        $storage = $this->storageRepository->getById($id);
        if (is_null($storage)) {
            throw new \Exception('Không tìm thấy kho thiết bị.', 404);
        }

        if (!$this->canManageStorage($currentUser, $storage->house)) {
            throw new \Exception('Không có quyền. Chỉ admin hoặc quản lý nhà mới có thể xóa kho thiết bị.', 403);
        }

        return $this->storageRepository->delete($storage);
    }

    /**
     * Kiểm tra quyền quản lý kho thiết bị
     *
     * @param mixed $user
     * @param mixed $house
     * @return bool
     */
    private function canManageStorage($user, $house): bool
    {
        // Admin có thể quản lý tất cả kho
        if ($user->role->code === 'admin') {
            return true;
        }

        // Manager chỉ có thể quản lý kho cho các nhà họ quản lý
        if ($user->role->code === 'manager' && $house && $house->manager_id === $user->id) {
            return true;
        }

        return false;
    }
} 