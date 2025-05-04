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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = EquipmentStorage::query();

        // Apply role-based filters
        if ($user->role->code === 'manager') {
            // Managers can only see storage for houses they manage
            $managedHouseIds = House::where('manager_id', $user->id)->pluck('id');
            $query->whereIn('house_id', $managedHouseIds);
        }
        // Admins can see all storage items (no filter needed)

        // Apply additional filters
        if ($request->has('house_id')) {
            $query->where('house_id', $request->house_id);
        }

        if ($request->has('equipment_id')) {
            $query->where('equipment_id', $request->equipment_id);
        }

        // Quantity range filters
        if ($request->has('min_quantity')) {
            $query->where('quantity', '>=', $request->min_quantity);
        }

        if ($request->has('max_quantity')) {
            $query->where('quantity', '<=', $request->max_quantity);
        }

        // Price range filters
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Search by description
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
        $with = ['equipment'];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('house', $includes)) $with[] = 'house';
        }

        // Sorting
        $sortField = $request->get('sort_by', 'id');
        $sortDirection = $request->get('sort_dir', 'asc');
        $allowedSortFields = ['id', 'house_id', 'equipment_id', 'quantity', 'price', 'created_at', 'updated_at'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('id', 'asc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $storage = $query->with($with)->paginate($perPage);

        return $this->sendResponse(
            EquipmentStorageResource::collection($storage)->response()->getData(true),
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
            return $this->sendError('Chưa đăng nhập.', [], 401);
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
            return $this->sendError('Lỗi dữ liệu.', $validator->errors(), 422);
        }

        $house = House::find($request->house_id);

        if (!$this->canManageStorage($currentUser, $house)) {
            return $this->sendError('Không có quyền. Chỉ admin hoặc quản lý nhà mới có thể tạo kho thiết bị.', [], 403);
        }

        $storage = EquipmentStorage::create($request->all());
        $storage->load('equipment');

        return $this->sendResponse(
            new EquipmentStorageResource($storage),
            'Tạo kho thiết bị thành công.'
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
            return $this->sendError('Không tìm thấy kho thiết bị.');
        }

        return $this->sendResponse(
            new EquipmentStorageResource($storage),
            'Lấy thông tin kho thiết bị thành công.'
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
            return $this->sendError('Không tìm thấy kho thiết bị.');
        }

        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Chưa đăng nhập.', [], 401);
        }

        if (!$this->canManageStorage($currentUser, $storage->house)) {
            return $this->sendError('Không có quyền. Chỉ admin hoặc quản lý nhà mới có thể cập nhật kho thiết bị.', [], 403);
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
            return $this->sendError('Lỗi dữ liệu.', $validator->errors(), 422);
        }

        $storage->update($request->all());
        $storage->load('equipment');

        return $this->sendResponse(
            new EquipmentStorageResource($storage),
            'Cập nhật kho thiết bị thành công.'
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
            return $this->sendError('Không tìm thấy kho thiết bị.');
        }

        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Chưa đăng nhập.', [], 401);
        }

        if (!$this->canManageStorage($currentUser, $storage->house)) {
            return $this->sendError('Không có quyền. Chỉ admin hoặc quản lý nhà mới có thể xóa kho thiết bị.', [], 403);
        }

        $storage->delete();

        return $this->sendResponse([], 'Xóa kho thiết bị thành công.');
    }

    /**
     * Check if user can manage the equipment storage for a house.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\House  $house
     * @return bool
     */
    private function canManageStorage($user, $house): bool
    {
        // Admin can manage all storage
        if ($user->role->code === 'admin') {
            return true;
        }

        // Manager can only manage storage for houses they manage
        if ($user->role->code === 'manager' && $house && $house->manager_id === $user->id) {
            return true;
        }

        return false;
    }
}
