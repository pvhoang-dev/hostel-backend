<?php

namespace App\Services;

use App\Http\Resources\EquipmentResource;
use App\Models\Equipment;
use App\Repositories\Interfaces\EquipmentRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EquipmentService
{
    protected $equipmentRepository;

    public function __construct(EquipmentRepositoryInterface $equipmentRepository)
    {
        $this->equipmentRepository = $equipmentRepository;
    }

    /**
     * Lấy danh sách thiết bị
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getAllEquipments(Request $request)
    {
        $user = Auth::user();

        // Chỉ cho phép admin và manager xem danh sách thiết bị
        if (!$user || !in_array($user->role->code, ['admin', 'manager'])) {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        $filters = [
            'name' => $request->name ?? null,
            'exact' => $request->exact ?? false,
            'room_id' => $request->room_id ?? null,
            'storage_id' => $request->storage_id ?? null,
        ];

        $with = [];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            // Add relationships if needed in the future
        }

        $sortField = $request->get('sort_by', 'id');
        $sortDirection = $request->get('sort_dir', 'asc');
        $perPage = $request->get('per_page', 15);

        $equipments = $this->equipmentRepository->getAllWithFilters($filters, $with, $sortField, $sortDirection, $perPage);

        $result = EquipmentResource::collection($equipments);
        return $result->response()->getData(true);
    }

    /**
     * Tạo thiết bị mới
     *
     * @param Request $request
     * @return Equipment
     * @throws \Exception
     */
    public function createEquipment(Request $request)
    {
        $user = Auth::user();

        // Chỉ cho phép admin và manager tạo thiết bị
        if (!$user || !in_array($user->role->code, ['admin', 'manager'])) {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        $input = $request->all();

        $validator = Validator::make($input, [
            'name' => 'required|string|max:255|unique:equipments,name',
            'description' => 'nullable|string'
        ], [
            'name.required' => 'Tên thiết bị là bắt buộc.',
            'name.string' => 'Tên thiết bị phải là chuỗi.',
            'name.max' => 'Tên thiết bị không được vượt quá 255 ký tự.',
            'name.unique' => 'Tên thiết bị đã tồn tại.',
            'description.string' => 'Mô tả phải là chuỗi.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $this->equipmentRepository->create($input);
    }

    /**
     * Lấy thông tin chi tiết thiết bị
     *
     * @param int $id
     * @return Equipment
     * @throws \Exception
     */
    public function getEquipmentById(int $id)
    {
        $user = Auth::user();

        // Chỉ cho phép admin và manager xem chi tiết thiết bị
        if (!$user || !in_array($user->role->code, ['admin', 'manager'])) {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        $equipment = $this->equipmentRepository->getById($id);

        if (is_null($equipment)) {
            throw new \Exception('Thiết bị không tồn tại.', 404);
        }

        return $equipment;
    }

    /**
     * Cập nhật thiết bị
     *
     * @param Request $request
     * @param Equipment $equipment
     * @return Equipment
     * @throws \Exception
     */
    public function updateEquipment(Request $request, Equipment $equipment)
    {
        $user = Auth::user();

        // Chỉ cho phép admin và manager cập nhật thiết bị
        if (!$user || !in_array($user->role->code, ['admin'])) {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        $input = $request->all();

        $validator = Validator::make($input, [
            'name' => 'sometimes|required|string|max:255|unique:equipments,name',
            'description' => 'nullable|string'
        ], [
            'name.required' => 'Tên thiết bị là bắt buộc.',
            'name.string' => 'Tên thiết bị phải là chuỗi.',
            'name.max' => 'Tên thiết bị không được vượt quá 255 ký tự.',
            'name.unique' => 'Tên thiết bị đã tồn tại.',
            'description.string' => 'Mô tả phải là chuỗi.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $this->equipmentRepository->update($equipment, $input);
    }

    /**
     * Xóa thiết bị
     *
     * @param Equipment $equipment
     * @return bool
     * @throws \Exception
     */
    public function deleteEquipment(Equipment $equipment)
    {
        $user = Auth::user();

        // Chỉ cho phép admin và manager xóa thiết bị
        if (!$user || !in_array($user->role->code, ['admin'])) {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        // Kiểm tra xem thiết bị có đang được sử dụng không
        if ($equipment->roomEquipments()->count() > 0 || $equipment->storages()->count() > 0) {
            throw new \Exception('Không thể xóa thiết bị đang được sử dụng', 422);
        }



        return $this->equipmentRepository->delete($equipment);
    }
}
