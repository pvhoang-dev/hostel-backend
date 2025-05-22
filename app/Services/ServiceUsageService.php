<?php

namespace App\Services;

use App\Http\Resources\ServiceUsageResource;
use App\Models\RoomService;
use App\Repositories\Interfaces\ServiceUsageRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ServiceUsageService
{
    protected $serviceUsageRepository;

    public function __construct(ServiceUsageRepositoryInterface $serviceUsageRepository)
    {
        $this->serviceUsageRepository = $serviceUsageRepository;
    }

    /**
     * Get all service usages
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function getAllServiceUsages($request)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $filters = [
            'user' => $user,
            'room_service_id' => $request->room_service_id ?? null,
            'room_id' => $request->room_id ?? null,
            'service_id' => $request->service_id ?? null,
            'month' => $request->month ?? null,
            'year' => $request->year ?? null,
            'min_usage' => $request->min_usage ?? null,
            'max_usage' => $request->max_usage ?? null,
            'min_price' => $request->min_price ?? null,
            'max_price' => $request->max_price ?? null,
            'min_start_meter' => $request->min_start_meter ?? null,
            'max_start_meter' => $request->max_start_meter ?? null,
            'min_end_meter' => $request->min_end_meter ?? null,
            'max_end_meter' => $request->max_end_meter ?? null,
            'created_from' => $request->created_from ?? null,
            'created_to' => $request->created_to ?? null,
            'updated_from' => $request->updated_from ?? null,
            'updated_to' => $request->updated_to ?? null
        ];

        // Include relationships
        $with = [];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('roomService', $includes)) $with[] = 'roomService';
            if (in_array('roomService.room', $includes)) $with[] = 'roomService.room';
            if (in_array('roomService.service', $includes)) $with[] = 'roomService.service';
            if (in_array('roomService.room.house', $includes)) $with[] = 'roomService.room.house';
            if (in_array('invoiceItems', $includes)) $with[] = 'invoiceItems';
        }

        // Sorting
        $sortField = $request->get('sort_by', 'year');
        $sortDirection = $request->get('sort_dir', 'desc');
        $perPage = $request->get('per_page', 15);

        $serviceUsages = $this->serviceUsageRepository->getAllWithFilters(
            $filters,
            $with,
            $sortField,
            $sortDirection,
            $perPage
        );

        return ServiceUsageResource::collection($serviceUsages)->response()->getData(true);
    }

    /**
     * Get a specific service usage by ID
     *
     * @param string $id
     * @return \App\Models\ServiceUsage
     */
    public function getServiceUsageById($id)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $serviceUsage = $this->serviceUsageRepository->getById($id, ['roomService.room.house', 'roomService.service']);

        if (is_null($serviceUsage)) {
            throw new \Exception('Bản ghi sử dụng dịch vụ không tồn tại.');
        }

        // Authorization check
        if (!$this->serviceUsageRepository->canAccessServiceUsage($user, $serviceUsage)) {
            throw new \Exception('Lỗi xác thực. Bạn không có quyền xem bản ghi sử dụng dịch vụ này');
        }

        return $serviceUsage;
    }

    /**
     * Create a new service usage
     *
     * @param \Illuminate\Http\Request $request
     * @return \App\Models\ServiceUsage
     */
    public function createServiceUsage($request)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $input = $request->all();

        $validator = Validator::make($input, [
            'room_service_id' => 'required|exists:room_services,id',
            'start_meter' => 'sometimes|nullable|numeric|min:0',
            'end_meter' => 'sometimes|nullable|numeric|min:0',
            'usage_value' => 'required|numeric|min:0',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'price_used' => 'required|integer|min:0',
            'description' => 'sometimes|nullable|string',
        ], [
            'room_service_id.required' => 'ID dịch vụ phòng là bắt buộc.',
            'room_service_id.exists' => 'ID dịch vụ phòng không hợp lệ.',
            'start_meter.numeric' => 'Đọc công tơ bắt đầu phải là một số.',
            'start_meter.min' => 'Đọc công tơ bắt đầu phải lớn hơn 0.',
            'end_meter.numeric' => 'Đọc công tơ kết thúc phải là một số.',
            'end_meter.min' => 'Đọc công tơ kết thúc phải lớn hơn 0.',
            'usage_value.required' => 'Giá trị sử dụng là bắt buộc.',
            'usage_value.numeric' => 'Giá trị sử dụng phải là một số.',
            'usage_value.min' => 'Giá trị sử dụng phải lớn hơn 0.',
            'month.required' => 'Tháng là bắt buộc.',
            'month.integer' => 'Tháng phải là một số.',
            'month.min' => 'Tháng phải lớn hơn 0.',
            'month.max' => 'Tháng phải nhỏ hơn 13.',
            'year.required' => 'Năm là bắt buộc.',
            'year.integer' => 'Năm phải là một số.',
            'year.min' => 'Năm phải lớn hơn 2000.',
            'year.max' => 'Năm phải nhỏ hơn 2100.',
            'price_used.required' => 'Giá sử dụng là bắt buộc.',
            'price_used.integer' => 'Giá sử dụng phải là một số.',
            'price_used.min' => 'Giá sử dụng phải lớn hơn 0.',
            'description.string' => 'Mô tả phải là một chuỗi.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Check if meters are valid
        if (isset($input['start_meter']) && isset($input['end_meter']) && $input['start_meter'] > $input['end_meter']) {
            throw ValidationException::withMessages(['end_meter' => 'Đọc công tơ kết thúc phải lớn hơn hoặc bằng đọc công tơ bắt đầu']);
        }

        // Check authorization
        $roomService = RoomService::with('room.house')->find($input['room_service_id']);
        if (!$roomService) {
            throw new \Exception('Dịch vụ phòng không tồn tại.');
        }

        // Only managers of the house or admins can add service usages
        if ($user->role->code === 'tenant') {
            throw new \Exception('Lỗi xác thực. Người thuê không thể thêm sử dụng dịch vụ');
        } elseif ($user->role->code === 'manager' && $roomService->room->house->manager_id !== $user->id) {
            throw new \Exception('Lỗi xác thực. Bạn chỉ có thể thêm sử dụng dịch vụ cho phòng trong nhà ở bạn quản lý');
        }

        // Check if a record already exists for this service in the specified month/year
        $existingUsage = $this->serviceUsageRepository->findByRoomServiceAndPeriod(
            $input['room_service_id'],
            $input['month'],
            $input['year']
        );

        if ($existingUsage) {
            throw ValidationException::withMessages(['service_usage' => 'Đã có bản ghi sử dụng dịch vụ cho tháng/năm này']);
        }

        $serviceUsage = $this->serviceUsageRepository->create($input);

        return $serviceUsage->load('roomService');
    }

    /**
     * Update a service usage
     *
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @return \App\Models\ServiceUsage
     */
    public function updateServiceUsage($request, $id)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $input = $request->all();
        $serviceUsage = $this->serviceUsageRepository->getById($id, ['roomService.room.house']);

        if (is_null($serviceUsage)) {
            throw new \Exception('Bản ghi sử dụng dịch vụ không tồn tại.');
        }

        // Authorization check
        if (!$this->serviceUsageRepository->canManageServiceUsage($user, $serviceUsage)) {
            throw new \Exception('Lỗi xác thực. Bạn không có quyền cập nhật bản ghi sử dụng dịch vụ này');
        }

        $validator = Validator::make($input, [
            'start_meter' => 'sometimes|nullable|numeric|min:0',
            'end_meter' => 'sometimes|nullable|numeric|min:0',
            'usage_value' => 'sometimes|numeric|min:0',
            'price_used' => 'sometimes|integer|min:0',
            'description' => 'sometimes|nullable|string',
        ], [
            'start_meter.numeric' => 'Đọc công tơ bắt đầu phải là một số.',
            'start_meter.min' => 'Đọc công tơ bắt đầu phải lớn hơn 0.',
            'end_meter.numeric' => 'Đọc công tơ kết thúc phải là một số.',
            'end_meter.min' => 'Đọc công tơ kết thúc phải lớn hơn 0.',
            'usage_value.numeric' => 'Giá trị sử dụng phải là một số.',
            'usage_value.min' => 'Giá trị sử dụng phải lớn hơn 0.',
            'price_used.integer' => 'Giá sử dụng phải là một số.',
            'price_used.min' => 'Giá sử dụng phải lớn hơn 0.',
            'description.string' => 'Mô tả phải là một chuỗi.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Check if meters are valid when both are provided
        if (isset($input['start_meter']) && isset($input['end_meter']) && $input['start_meter'] > $input['end_meter']) {
            throw ValidationException::withMessages(['end_meter' => 'Đọc công tơ kết thúc phải lớn hơn hoặc bằng đọc công tơ bắt đầu']);
        }

        // When only one meter is provided, check against existing value
        if (isset($input['start_meter']) && !isset($input['end_meter']) && $input['start_meter'] > $serviceUsage->end_meter) {
            throw ValidationException::withMessages(['start_meter' => 'Đọc công tơ bắt đầu không thể lớn hơn đọc công tơ kết thúc']);
        }

        if (!isset($input['start_meter']) && isset($input['end_meter']) && $serviceUsage->start_meter > $input['end_meter']) {
            throw ValidationException::withMessages(['end_meter' => 'Đọc công tơ kết thúc không thể nhỏ hơn đọc công tơ bắt đầu']);
        }

        $serviceUsage = $this->serviceUsageRepository->update($id, $input);

        return $serviceUsage->load('roomService');
    }

    /**
     * Delete a service usage
     *
     * @param string $id
     * @return bool
     */
    public function deleteServiceUsage($id)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $serviceUsage = $this->serviceUsageRepository->getById($id, ['roomService.room.house']);

        if (is_null($serviceUsage)) {
            throw new \Exception('Bản ghi sử dụng dịch vụ không tồn tại.');
        }

        // Authorization check - only admins and managers can delete service usages
        if (!$this->serviceUsageRepository->canManageServiceUsage($user, $serviceUsage)) {
            throw new \Exception('Lỗi xác thực. Bạn không có quyền xóa bản ghi sử dụng dịch vụ này');
        }

        return $this->serviceUsageRepository->delete($id);
    }
}
