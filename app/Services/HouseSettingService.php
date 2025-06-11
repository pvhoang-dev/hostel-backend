<?php

namespace App\Services;

use App\Http\Resources\HouseSettingResource;
use App\Models\House;
use App\Repositories\Interfaces\HouseSettingRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HouseSettingService
{
    protected $houseSettingRepository;

    public function __construct(HouseSettingRepositoryInterface $houseSettingRepository)
    {
        $this->houseSettingRepository = $houseSettingRepository;
    }

    /**
     * Lấy danh sách nội quy nhà
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function getAllHouseSettings($request)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Không có quyền truy cập.');
        }

        $filters = [
            'current_user' => $currentUser,
            'house_id' => $request->house_id ?? null,
            'key' => $request->key ?? null,
            'value' => $request->value ?? null,
            'created_from' => $request->created_from ?? null,
            'created_to' => $request->created_to ?? null,
            'updated_from' => $request->updated_from ?? null,
            'updated_to' => $request->updated_to ?? null
        ];

        // Include relationships
        $with = [];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('house', $includes)) $with[] = 'house';
            if (in_array('creator', $includes)) $with[] = 'creator';
            if (in_array('updater', $includes)) $with[] = 'updater';
        }

        // Sorting
        $sortField = $request->get('sort_by', 'key');
        $sortDirection = $request->get('sort_dir', 'desc');
        $perPage = $request->get('per_page', 15);

        $settings = $this->houseSettingRepository->getAllWithFilters(
            $filters,
            $with,
            $sortField,
            $sortDirection,
            $perPage
        );

        return HouseSettingResource::collection($settings)->response()->getData(true);
    }

    /**
     * Lấy thông tin nội quy nhà theo ID
     *
     * @param int $id
     * @return \App\Models\HouseSetting
     */
    public function getHouseSettingById($id)
    {
        $setting = $this->houseSettingRepository->getById($id, ['house.manager']);

        if (is_null($setting)) {
            throw new \Exception('Nội quy nhà không tồn tại.');
        }

        return $setting;
    }

    /**
     * Tạo nội quy nhà mới
     *
     * @param \Illuminate\Http\Request $request
     * @return \App\Models\HouseSetting
     */
    public function createHouseSetting($request)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Không có quyền truy cập.');
        }

        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $currentUser->role->code === 'manager';

        if (!$isAdmin && !$isManager) {
            throw new \Exception('Chỉ quản trị viên và quản lý mới có thể tạo nội quy nhà.');
        }

        $validator = Validator::make($request->all(), [
            'house_id' => 'required|exists:houses,id',
            'key' => [
                'required',
                'string',
                'max:50',
                Rule::unique('house_settings')->where(function ($query) use ($request) {
                    return $query->where('house_id', $request->house_id);
                })
            ],
            'value' => 'required|string',
            'description' => 'nullable|string',
        ], [
            'house_id.required' => 'Mã nhà là bắt buộc.',
            'house_id.exists' => 'Mã nhà không tồn tại.',
            'key.required' => 'Số thứ tự là bắt buộc.',
            'key.unique' => 'Số thứ tự đã tồn tại.',
            'value.required' => 'Nội quy là bắt buộc.',
            'value.string' => 'Nội quy phải là chuỗi.',
            'description.string' => 'Mô tả phải là chuỗi.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // If manager, check if they manage this house
        if ($isManager) {
            $house = House::find($request->house_id);
            if (!$house || $house->manager_id !== $currentUser->id) {
                throw new \Exception('Bạn không có quyền tạo nội quy cho nhà này.');
            }
        }

        $input = $request->all();
        $input['created_by'] = $currentUser->id;
        $input['updated_by'] = $currentUser->id;

        $setting = $this->houseSettingRepository->create($input);
        
        return $setting;
    }

    /**
     * Cập nhật nội quy nhà
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \App\Models\HouseSetting
     */
    public function updateHouseSetting($request, $id)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Không có quyền truy cập.');
        }

        $setting = $this->houseSettingRepository->getById($id, ['house']);

        if (is_null($setting)) {
            throw new \Exception('Nội quy nhà không tồn tại.');
        }

        if (!$this->houseSettingRepository->canManageHouseSetting($currentUser, $setting)) {
            throw new \Exception('Chỉ quản trị viên hoặc quản lý nhà mới có thể cập nhật nội quy.');
        }

        $validator = Validator::make($request->all(), [
            'key' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('house_settings')->where(function ($query) use ($request, $setting) {
                    return $query->where('house_id', $setting->house_id);
                })->ignore($setting->id)
            ],
            'value' => 'sometimes|required|string',
            'description' => 'nullable|string',
        ], [
            'key.required' => 'Số thứ tự là bắt buộc.',
            'key.unique' => 'Số thứ tự đã tồn tại.',
            'value.required' => 'Nội quy là bắt buộc.',
            'house_id.required' => 'Mã nhà là bắt buộc.',
            'house_id.exists' => 'Mã nhà không tồn tại.',
            'description.string' => 'Mô tả phải là chuỗi.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $input = $request->all();
        $input['updated_by'] = $currentUser->id;

        $setting = $this->houseSettingRepository->update($id, $input);
        
        return $setting;
    }

    /**
     * Xóa nội quy nhà
     *
     * @param int $id
     * @return bool
     */
    public function deleteHouseSetting($id)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Không có quyền truy cập.');
        }

        $setting = $this->houseSettingRepository->getById($id, ['house']);

        if (is_null($setting)) {
            throw new \Exception('Nội quy nhà không tồn tại.');
        }

        if (!$this->houseSettingRepository->canManageHouseSetting($currentUser, $setting)) {
            throw new \Exception('Chỉ quản trị viên hoặc quản lý nhà mới có thể xóa nội quy.');
        }

        return $this->houseSettingRepository->delete($id);
    }
} 