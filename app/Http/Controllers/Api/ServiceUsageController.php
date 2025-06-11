<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\ServiceUsageResource;
use App\Services\ServiceUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ServiceUsageController extends BaseController
{
    protected $serviceUsageService;

    public function __construct(ServiceUsageService $serviceUsageService)
    {
        $this->serviceUsageService = $serviceUsageService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $result = $this->serviceUsageService->getAllServiceUsages($request);
            return $this->sendResponse($result, 'Sử dụng dịch vụ đã được lấy thành công.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $serviceUsage = $this->serviceUsageService->createServiceUsage($request);
            return $this->sendResponse(
                new ServiceUsageResource($serviceUsage),
                'Sử dụng dịch vụ đã được tạo thành công.'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $serviceUsage = $this->serviceUsageService->getServiceUsageById($id);
            return $this->sendResponse(
                new ServiceUsageResource($serviceUsage),
                'Sử dụng dịch vụ đã được lấy thành công.'
            );
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $serviceUsage = $this->serviceUsageService->updateServiceUsage($request, $id);
            return $this->sendResponse(
                new ServiceUsageResource($serviceUsage),
                'Sử dụng dịch vụ đã được cập nhật thành công.'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->serviceUsageService->deleteServiceUsage($id);
            return $this->sendResponse([], 'Sử dụng dịch vụ đã được xóa thành công.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }
}
