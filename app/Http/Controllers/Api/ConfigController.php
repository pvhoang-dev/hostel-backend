<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\ConfigResource;
use App\Services\ConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConfigController extends BaseController
{
    protected $configService;

    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Lấy danh sách cấu hình.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $result = $this->configService->getAllConfigs($request);
            return $this->sendResponse($result, 'Lấy danh sách cấu hình thành công');
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError($e->getMessage(), [], $code);
        }
    }

    /**
     * Lấy thông tin cấu hình cụ thể.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $config = $this->configService->getConfigById($id);
            return $this->sendResponse(
                new ConfigResource($config),
                'Lấy thông tin cấu hình thành công'
            );
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError($e->getMessage(), [], $code);
        }
    }

    /**
     * Tạo cấu hình mới.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $config = $this->configService->createConfig($request);
            return $this->sendResponse(
                new ConfigResource($config),
                'Tạo cấu hình thành công'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu', $e->errors());
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError($e->getMessage(), [], $code);
        }
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
        try {
            $config = $this->configService->updateConfig($request, $id);
            return $this->sendResponse(
                new ConfigResource($config),
                'Cập nhật cấu hình thành công'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu', $e->errors());
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError($e->getMessage(), [], $code);
        }
    }

    /**
     * Xóa cấu hình.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->configService->deleteConfig($id);
            return $this->sendResponse([], 'Xóa cấu hình thành công');
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError($e->getMessage(), [], $code);
        }
    }

    /**
     * Lấy tất cả cấu hình của PayOS.
     *
     * @return JsonResponse
     */
    public function getPayosConfigs(): JsonResponse
    {
        try {
            $configs = $this->configService->getPayosConfigs();
            return $this->sendResponse(
                ConfigResource::collection($configs),
                'Lấy danh sách cấu hình PayOS thành công'
            );
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError($e->getMessage(), [], $code);
        }
    }
} 