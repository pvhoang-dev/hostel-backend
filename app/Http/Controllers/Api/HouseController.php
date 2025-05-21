<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\HouseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\HouseService;
use App\Services\NotificationService;
use Illuminate\Validation\ValidationException;

class HouseController extends BaseController
{
    protected $houseService;
    protected $notificationService;

    public function __construct(
        HouseService $houseService,
        NotificationService $notificationService
    ) {
        $this->houseService = $houseService;
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of the houses.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $result = $this->houseService->getAllHouses($request);
            return $this->sendResponse($result, 'Lấy danh sách nhà trọ thành công.');
        } catch (\Exception $e) {
            return $this->sendError('Lỗi khi lấy danh sách nhà trọ', [$e->getMessage()]);
        }
    }

    /**
     * Store a newly created house in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $house = $this->houseService->createHouse($request);
            return $this->sendResponse(new HouseResource($house), 'Nhà trọ được tạo thành công.');
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Lỗi khi tạo nhà trọ', [$e->getMessage()]);
        }
    }

    /**
     * Display the specified house.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $house = $this->houseService->getHouseById($id);
            return $this->sendResponse(new HouseResource($house), 'Lấy thông tin nhà trọ thành công.');
        } catch (\Exception $e) {
            return $this->sendError('Lỗi khi lấy thông tin nhà trọ', [$e->getMessage()]);
        }
    }

    /**
     * Update the specified house in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $house = $this->houseService->updateHouse($request, $id);
            return $this->sendResponse(new HouseResource($house), 'Cập nhật nhà trọ thành công.');
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Lỗi khi cập nhật nhà trọ', [$e->getMessage()]);
        }
    }

    /**
     * Remove the specified house from storage.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->houseService->deleteHouse($id);
            return $this->sendResponse([], 'Xóa nhà trọ thành công.');
        } catch (\Exception $e) {
            return $this->sendError('Lỗi khi xóa nhà trọ', [$e->getMessage()]);
        }
    }
}
