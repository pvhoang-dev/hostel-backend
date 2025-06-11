<?php

namespace App\Services;

use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Repositories\Interfaces\ServiceRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ServiceService
{
    protected $serviceRepository;

    public function __construct(ServiceRepositoryInterface $serviceRepository)
    {
        $this->serviceRepository = $serviceRepository;
    }

    /**
     * Lấy danh sách dịch vụ
     *
     * @param Request $request
     * @return array
     */
    public function getAllServices(Request $request)
    {
        $filters = [
            'name' => $request->name ?? null,
            'unit' => $request->unit ?? null,
            'is_metered' => $request->is_metered ?? null,
            'min_price' => $request->min_price ?? null,
            'max_price' => $request->max_price ?? null,
            'created_from' => $request->created_from ?? null,
            'created_to' => $request->created_to ?? null,
            'updated_from' => $request->updated_from ?? null,
            'updated_to' => $request->updated_to ?? null,
        ];

        // Thêm các mối quan hệ cần eager loading
        $with = [];

        $sortField = $request->get('sort_by', 'id');
        $sortDirection = $request->get('sort_dir', 'asc');
        $perPage = $request->get('per_page', 15);

        $services = $this->serviceRepository->getAllWithFilters($filters, $with, $sortField, $sortDirection, $perPage);
        
        $result = ServiceResource::collection($services);
        return $result->response()->getData(true);
    }

    /**
     * Tạo dịch vụ mới
     *
     * @param Request $request
     * @return Service
     * @throws \Exception
     */
    public function createService(Request $request)
    {
        // Chỉ admin mới có thể tạo dịch vụ
        $user = Auth::user();
        if (!$user || $user->role->code !== 'admin' && $user->role->code !== 'manager') {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        $input = $request->all();
        $validator = Validator::make($input, [
            'name' => 'required|string|max:50|unique:services,name',
            'default_price' => 'required|integer',
            'unit' => 'required|string|max:20',
            'is_metered' => 'sometimes|boolean'
        ], [
            'name.required' => 'Tên dịch vụ là bắt buộc.',
            'name.unique' => 'Tên dịch vụ đã tồn tại.',
            'default_price.required' => 'Giá mặc định là bắt buộc.',
            'default_price.integer' => 'Giá mặc định phải là một số nguyên.',
            'unit.required' => 'Đơn vị là bắt buộc.',
            'unit.max' => 'Đơn vị không được vượt quá 20 ký tự.',
            'is_metered.boolean' => 'Trạng thái đo lường phải là true hoặc false.'
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $this->serviceRepository->create($input);
    }

    /**
     * Lấy thông tin chi tiết dịch vụ
     *
     * @param int $id
     * @return Service
     * @throws \Exception
     */
    public function getServiceById(int $id)
    {
        $service = $this->serviceRepository->getById($id);
        if (is_null($service)) {
            throw new \Exception('Không tìm thấy dịch vụ.', 404);
        }

        return $service;
    }

    /**
     * Cập nhật dịch vụ
     *
     * @param Request $request
     * @param Service $service
     * @return Service
     * @throws \Exception
     */
    public function updateService(Request $request, Service $service)
    {
        // Chỉ admin mới có thể cập nhật dịch vụ
        $user = Auth::user();
        if (!$user || $user->role->code !== 'admin') {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        $input = $request->all();
        $validator = Validator::make($input, [
            'name' => 'sometimes|required|string|max:50|unique:services,name,' . $service->id,
            'default_price' => 'sometimes|required|integer',
            'unit' => 'sometimes|required|string|max:20',
            'is_metered' => 'sometimes|boolean'
        ], [
            'name.required' => 'Tên dịch vụ là bắt buộc.',
            'name.unique' => 'Tên dịch vụ đã tồn tại.',
            'default_price.required' => 'Giá mặc định là bắt buộc.',
            'default_price.integer' => 'Giá mặc định phải là một số nguyên.',
            'unit.required' => 'Đơn vị là bắt buộc.',
            'unit.max' => 'Đơn vị không được vượt quá 20 ký tự.',
            'is_metered.boolean' => 'Trạng thái đo lường phải là true hoặc false.'
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Chỉ cập nhật các trường được gửi lên
        $data = $request->only([
            'name',
            'default_price',
            'unit',
            'is_metered'
        ]);

        return $this->serviceRepository->update($service, $data);
    }

    /**
     * Xóa dịch vụ
     *
     * @param Service $service
     * @return bool
     * @throws \Exception
     */
    public function deleteService(Service $service)
    {
        // Chỉ admin mới có thể xóa dịch vụ
        $user = Auth::user();
        if (!$user || $user->role->code !== 'admin') {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        // Kiểm tra xem dịch vụ có đang được sử dụng không
        if ($service->roomServices()->count() > 0) {
            throw new \Exception('Không thể xóa dịch vụ đang được sử dụng', 422);
        }

        return $this->serviceRepository->delete($service);
    }
} 