<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\HouseSettingResource;
use App\Services\HouseSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HouseSettingController extends BaseController
{
    protected $houseSettingService;

    public function __construct(HouseSettingService $houseSettingService)
    {
        $this->houseSettingService = $houseSettingService;
    }

    /**
     * Display a listing of the house settings.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $result = $this->houseSettingService->getAllHouseSettings($request);
            return $this->sendResponse($result, 'Lấy danh sách nội quy nhà thành công.');
        } catch (\Exception $e) {
            return $this->sendError('Lỗi khi lấy danh sách nội quy nhà', [$e->getMessage()]);
        }
    }

    /**
     * Store a newly created house setting in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $setting = $this->houseSettingService->createHouseSetting($request);
            return $this->sendResponse(
                new HouseSettingResource($setting),
                'Nội quy nhà được tạo thành công.'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi xác thực dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Lỗi khi tạo nội quy nhà', [$e->getMessage()]);
        }
    }

    /**
     * Display the specified house setting.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $setting = $this->houseSettingService->getHouseSettingById($id);
            return $this->sendResponse(
                new HouseSettingResource($setting),
                'Lấy thông tin nội quy nhà thành công.'
            );
        } catch (\Exception $e) {
            return $this->sendError('Lỗi khi lấy thông tin nội quy nhà', [$e->getMessage()]);
        }
    }

    /**
     * Update the specified house setting in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $setting = $this->houseSettingService->updateHouseSetting($request, $id);
            return $this->sendResponse(
                new HouseSettingResource($setting),
                'Cập nhật nội quy nhà thành công.'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi xác thực dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Lỗi khi cập nhật nội quy nhà', [$e->getMessage()]);
        }
    }

    /**
     * Remove the specified house setting from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->houseSettingService->deleteHouseSetting($id);
            return $this->sendResponse(
                [],
                'Xóa nội quy nhà thành công.'
            );
        } catch (\Exception $e) {
            return $this->sendError('Lỗi khi xóa nội quy nhà', [$e->getMessage()]);
        }
    }
}
