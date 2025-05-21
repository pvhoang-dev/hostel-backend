<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\EquipmentStorageResource;
use App\Models\EquipmentStorage;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorageController extends BaseController
{
    protected $storageService;

    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Display a listing of equipment storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $result = $this->storageService->getAllStorage($request);
            return $this->sendResponse($result, 'Lấy danh sách kho thành công');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], $e->getCode() ?: 500);
        }
    }

    /**
     * Store a newly created equipment storage in database.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $storage = $this->storageService->createStorage($request);
            return $this->sendResponse(new EquipmentStorageResource($storage), 'Tạo kho thành công');
        } catch (ValidationException $e) {
            return $this->sendError($e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], $e->getCode() ?: 500);
        }
    }

    /**
     * Display the specified equipment storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $storage = $this->storageService->getStorageById($id);
            return $this->sendResponse(new EquipmentStorageResource($storage), 'Lấy kho thành công');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], $e->getCode() ?: 500);
        }
    }

    /**
     * Update the specified equipment storage in database.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $storage = $this->storageService->updateStorage($request, $id);
            return $this->sendResponse(new EquipmentStorageResource($storage), 'Cập nhật kho thành công');
        } catch (ValidationException $e) {
            return $this->sendError($e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], $e->getCode() ?: 500);
        }
    }

    /**
     * Remove the specified equipment storage from database.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->storageService->deleteStorage($id);
            return $this->sendResponse(null, 'Xóa kho thành công');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], $e->getCode() ?: 500);
        }
    }
}
