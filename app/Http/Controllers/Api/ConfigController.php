<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\ConfigResource;
use App\Models\Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ConfigController extends BaseController
{
    /**
     * Lấy danh sách cấu hình.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Chỉ cho phép admin truy cập
        if ($user->role->code !== 'admin') {
            return $this->sendError('Không có quyền truy cập', [], 403);
        }

        $query = Config::query();

        // Lọc theo nhóm
        if ($request->has('group')) {
            $query->where('group', $request->group);
        }

        // Lọc theo trạng thái
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Lọc theo key
        if ($request->has('key')) {
            $query->where('key', 'like', '%' . $request->key . '%');
        }

        // Sắp xếp
        $sortField = $request->get('sort_by', 'id');
        $sortDirection = $request->get('sort_dir', 'asc');
        $allowedSortFields = ['id', 'key', 'group', 'status', 'created_at', 'updated_at'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('group', 'asc')->orderBy('key', 'asc');
        }

        // Phân trang
        $perPage = $request->get('per_page', 15);
        $configs = $query->paginate($perPage);

        return $this->sendResponse(
            ConfigResource::collection($configs)->response()->getData(true),
            'Lấy danh sách cấu hình thành công'
        );
    }

    /**
     * Lấy thông tin cấu hình cụ thể.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();

        // Chỉ cho phép admin truy cập
        if ($user->role->code !== 'admin') {
            return $this->sendError('Không có quyền truy cập', [], 403);
        }

        $config = Config::find($id);

        if (is_null($config)) {
            return $this->sendError('Không tìm thấy cấu hình');
        }

        return $this->sendResponse(
            new ConfigResource($config),
            'Lấy thông tin cấu hình thành công'
        );
    }

    /**
     * Tạo cấu hình mới.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Chỉ cho phép admin truy cập
        if ($user->role->code !== 'admin') {
            return $this->sendError('Không có quyền truy cập', [], 403);
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
            return $this->sendError('Lỗi dữ liệu', $validator->errors());
        }

        $config = Config::create($request->all());

        return $this->sendResponse(
            new ConfigResource($config),
            'Tạo cấu hình thành công'
        );
    }

    /**
     * Cập nhật cấu hình.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        // Chỉ cho phép admin truy cập
        if ($user->role->code !== 'admin') {
            return $this->sendError('Không có quyền truy cập', [], 403);
        }

        $config = Config::find($id);

        if (is_null($config)) {
            return $this->sendError('Không tìm thấy cấu hình');
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
            return $this->sendError('Lỗi dữ liệu', $validator->errors());
        }

        $config->update($request->all());

        return $this->sendResponse(
            new ConfigResource($config),
            'Cập nhật cấu hình thành công'
        );
    }

    /**
     * Xóa cấu hình.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();

        // Chỉ cho phép admin truy cập
        if ($user->role->code !== 'admin') {
            return $this->sendError('Không có quyền truy cập', [], 403);
        }

        $config = Config::find($id);

        if (is_null($config)) {
            return $this->sendError('Không tìm thấy cấu hình');
        }

        $config->delete();

        return $this->sendResponse([], 'Xóa cấu hình thành công');
    }

    /**
     * Lấy tất cả cấu hình của PayOS.
     *
     * @return JsonResponse
     */
    public function getPayosConfigs(): JsonResponse
    {
        $user = Auth::user();

        // Chỉ cho phép admin truy cập
        if ($user->role->code !== 'admin') {
            return $this->sendError('Không có quyền truy cập', [], 403);
        }

        $configs = Config::where('group', 'payos')
            ->where('status', 'active')
            ->get();

        return $this->sendResponse(
            ConfigResource::collection($configs),
            'Lấy danh sách cấu hình PayOS thành công'
        );
    }
} 