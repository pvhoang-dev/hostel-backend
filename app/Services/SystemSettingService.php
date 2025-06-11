<?php

namespace App\Services;

use App\Http\Resources\SystemSettingResource;
use App\Models\SystemSetting;
use App\Repositories\Interfaces\SystemSettingRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SystemSettingService
{
    protected $systemSettingRepository;

    public function __construct(SystemSettingRepositoryInterface $systemSettingRepository)
    {
        $this->systemSettingRepository = $systemSettingRepository;
    }

    /**
     * Lấy danh sách cài đặt hệ thống
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getAllSettings(Request $request)
    {
        $user = Auth::user();

        // Chỉ cho phép admin truy cập
        if (!$user) {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        $filters = [
            'key' => $request->key ?? null,
            'value' => $request->value ?? null,
            'description' => $request->description ?? null,
            'created_from' => $request->created_from ?? null,
            'created_to' => $request->created_to ?? null,
            'updated_from' => $request->updated_from ?? null,
            'updated_to' => $request->updated_to ?? null,
        ];

        $with = [];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            // Add relationships if needed in the future
        }

        $sortField = $request->get('sort_by', 'id');
        $sortDirection = $request->get('sort_dir', 'asc');
        $perPage = $request->get('per_page', 15);

        $settings = $this->systemSettingRepository->getAllWithFilters($filters, $with, $sortField, $sortDirection, $perPage);

        $result = SystemSettingResource::collection($settings);
        return $result->response()->getData(true);
    }

    /**
     * Tạo cài đặt mới
     *
     * @param Request $request
     * @return SystemSetting
     * @throws \Exception
     */
    public function createSetting(Request $request)
    {
        $user = Auth::user();

        // Chỉ cho phép admin tạo cài đặt
        if (!$user || $user->role->code !== 'admin') {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        $input = $request->all();

        $validator = Validator::make($input, [
            'key' => 'required|unique:system_settings,key',
            'value' => 'required',
            'description' => 'required'
        ], [
            'key.required' => 'Mã key là bắt buộc.',
            'key.unique' => 'Mã key đã tồn tại.',
            'value.required' => 'Giá trị là bắt buộc.',
            'description.required' => 'Mô tả là bắt buộc.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $this->systemSettingRepository->create($input);
    }

    /**
     * Lấy thông tin chi tiết cài đặt
     *
     * @param int $id
     * @return SystemSetting
     * @throws \Exception
     */
    public function getSettingById(int $id)
    {
        $user = Auth::user();

        // Chỉ cho phép admin xem chi tiết cài đặt
        if (!$user) {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        $setting = $this->systemSettingRepository->getById($id);

        if (is_null($setting)) {
            throw new \Exception('Cài đặt không tồn tại.', 404);
        }

        return $setting;
    }

    /**
     * Cập nhật cài đặt
     *
     * @param Request $request
     * @param SystemSetting $setting
     * @return SystemSetting
     * @throws \Exception
     */
    public function updateSetting(Request $request, SystemSetting $setting)
    {
        $user = Auth::user();

        // Chỉ cho phép admin cập nhật cài đặt
        if (!$user || $user->role->code !== 'admin') {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        $input = $request->all();

        $validator = Validator::make($input, [
            'key' => 'sometimes|required|unique:system_settings,key,' . $setting->id,
            'value' => 'sometimes|required',
            'description' => 'sometimes|required'
        ], [
            'key.required' => 'Mã key là bắt buộc.',
            'key.unique' => 'Mã key đã tồn tại.',
            'value.required' => 'Giá trị là bắt buộc.',
            'description.required' => 'Mô tả là bắt buộc.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $this->systemSettingRepository->update($setting, $input);
    }

    /**
     * Xóa cài đặt
     *
     * @param SystemSetting $setting
     * @return bool
     * @throws \Exception
     */
    public function deleteSetting(SystemSetting $setting)
    {
        $user = Auth::user();

        // Chỉ cho phép admin xóa cài đặt
        if (!$user || $user->role->code !== 'admin') {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        return $this->systemSettingRepository->delete($setting);
    }
} 