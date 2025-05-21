<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Services\ServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Class ServiceController
 *
 * @package App\Http\Controllers\Api
 */
class ServiceController extends BaseController
{
    protected $serviceService;

    public function __construct(ServiceService $serviceService)
    {
        $this->serviceService = $serviceService;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $result = $this->serviceService->getAllServices($request);
            return $this->sendResponse($result, 'Lấy danh sách dịch vụ thành công');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], $e->getCode() ?: 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $service = $this->serviceService->createService($request);
            return $this->sendResponse(new ServiceResource($service), 'Tạo dịch vụ thành công', 201);
        } catch (ValidationException $e) {
            return $this->sendError($e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], $e->getCode() ?: 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $service = $this->serviceService->getServiceById($id);
            return $this->sendResponse(new ServiceResource($service), 'Lấy dịch vụ thành công');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], $e->getCode() ?: 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Service $service
     * @return JsonResponse
     */
    public function update(Request $request, Service $service): JsonResponse
    {
        try {
            $service = $this->serviceService->updateService($request, $service);
            return $this->sendResponse(new ServiceResource($service), 'Cập nhật dịch vụ thành công');
        } catch (ValidationException $e) {
            return $this->sendError($e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], $e->getCode() ?: 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Service $service
     * @return JsonResponse
     */
    public function destroy(Service $service): JsonResponse
    {
        try {
            $this->serviceService->deleteService($service);
            return $this->sendResponse(null, 'Xóa dịch vụ thành công');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], $e->getCode() ?: 500);
        }
    }
}
