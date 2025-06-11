<?php

namespace App\Services;

use App\Http\Resources\ConfigResource;
use App\Models\Config;
use App\Repositories\Interfaces\ConfigRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ConfigService
{
    protected $configRepository;

    public function __construct(ConfigRepositoryInterface $configRepository)
    {
        $this->configRepository = $configRepository;
    }

    /**
     * Lấy danh sách cấu hình
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getAllConfigs(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Người dùng không được xác thực', 401);
        }
        
        // Chỉ cho phép admin truy cập
        if ($user->role->code !== 'admin') {
            throw new \Exception('Không có quyền truy cập', 403);
        }

        // Xử lý các bộ lọc từ request
        $filters = [
            'group' => $request->group ?? null,
            'status' => $request->status ?? null,
            'key' => $request->key ?? null,
        ];

        // Xác định thông tin sắp xếp và phân trang
        $sortField = $request->get('sort_by', 'id');
        $sortDirection = $request->get('sort_dir', 'asc');
        $perPage = $request->get('per_page', 15);

        $configs = $this->configRepository->getAllWithFilters(
            $filters, 
            $sortField, 
            $sortDirection, 
            $perPage
        );

        // Chuyển đổi kết quả thành resource và trả về
        return ConfigResource::collection($configs)->response()->getData(true);
    }

    /**
     * Lấy thông tin cấu hình theo ID
     *
     * @param int $id
     * @return Config
     * @throws \Exception
     */
    public function getConfigById(int $id)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Người dùng không được xác thực', 401);
        }
        
        // Chỉ cho phép admin truy cập
        if ($user->role->code !== 'admin') {
            throw new \Exception('Không có quyền truy cập', 403);
        }

        $config = $this->configRepository->getById($id);
        
        if (is_null($config)) {
            throw new \Exception('Không tìm thấy cấu hình', 404);
        }

        return $config;
    }

    /**
     * Tạo cấu hình mới
     *
     * @param Request $request
     * @return Config
     * @throws \Exception
     */
    public function createConfig(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Người dùng không được xác thực', 401);
        }
        
        // Chỉ cho phép admin truy cập
        if ($user->role->code !== 'admin') {
            throw new \Exception('Không có quyền truy cập', 403);
        }

        $validator = Validator::make($request->all(), [
            'key' => 'required|string|unique:configs,key|max:255',
            'value' => 'nullable|string',
            'description' => 'nullable|string',
            'group' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ], [
            'key.required' => 'Key là bắt buộc',
            'key.string' => 'Key phải là chuỗi',
            'key.unique' => 'Key đã tồn tại',
            'key.max' => 'Key không được vượt quá 255 ký tự',
            'value.string' => 'Giá trị phải là chuỗi',
            'description.string' => 'Mô tả phải là chuỗi',
            'group.required' => 'Nhóm là bắt buộc',
            'group.string' => 'Nhóm phải là chuỗi',
            'group.max' => 'Nhóm không được vượt quá 255 ký tự',
            'status.required' => 'Trạng thái là bắt buộc',
            'status.in' => 'Trạng thái không hợp lệ',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $this->configRepository->create($request->all());
    }

    /**
     * Cập nhật cấu hình
     *
     * @param Request $request
     * @param int $id
     * @return Config
     * @throws \Exception
     */
    public function updateConfig(Request $request, int $id)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Người dùng không được xác thực', 401);
        }
        
        // Chỉ cho phép admin truy cập
        if ($user->role->code !== 'admin') {
            throw new \Exception('Không có quyền truy cập', 403);
        }

        $config = $this->configRepository->getById($id);
        
        if (is_null($config)) {
            throw new \Exception('Không tìm thấy cấu hình', 404);
        }

        $validator = Validator::make($request->all(), [
            'key' => 'sometimes|required|string|max:255|unique:configs,key,'.$id,
            'value' => 'nullable|string',
            'description' => 'nullable|string',
            'group' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|required|in:active,inactive',
        ], [
            'key.required' => 'Key là bắt buộc',
            'key.string' => 'Key phải là chuỗi',
            'key.unique' => 'Key đã tồn tại',
            'key.max' => 'Key không được vượt quá 255 ký tự',
            'value.string' => 'Giá trị phải là chuỗi',
            'description.string' => 'Mô tả phải là chuỗi',
            'group.required' => 'Nhóm là bắt buộc',
            'group.string' => 'Nhóm phải là chuỗi',
            'group.max' => 'Nhóm không được vượt quá 255 ký tự',
            'status.required' => 'Trạng thái là bắt buộc',
            'status.in' => 'Trạng thái không hợp lệ',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $this->configRepository->update($config, $request->all());
    }

    /**
     * Xóa cấu hình
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function deleteConfig(int $id)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Người dùng không được xác thực', 401);
        }
        
        // Chỉ cho phép admin truy cập
        if ($user->role->code !== 'admin') {
            throw new \Exception('Không có quyền truy cập', 403);
        }

        $config = $this->configRepository->getById($id);
        
        if (is_null($config)) {
            throw new \Exception('Không tìm thấy cấu hình', 404);
        }

        return $this->configRepository->delete($config);
    }

    /**
     * Lấy tất cả cấu hình của PayOS
     *
     * @return \Illuminate\Database\Eloquent\Collection
     * @throws \Exception
     */
    public function getPayosConfigs()
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Người dùng không được xác thực', 401);
        }
        
        // Chỉ cho phép admin truy cập
        if ($user->role->code !== 'admin') {
            throw new \Exception('Không có quyền truy cập', 403);
        }

        return $this->configRepository->getPayosConfigs();
    }
} 