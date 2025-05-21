<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\SystemSettingResource;
use App\Models\SystemSetting;
use App\Services\SystemSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Class SystemSettingController
 *
 * @package App\Http\Controllers\Api
 */
class SystemSettingController extends BaseController
{
    protected $systemSettingService;

    public function __construct(SystemSettingService $systemSettingService)
    {
        $this->systemSettingService = $systemSettingService;
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
            $result = $this->systemSettingService->getAllSettings($request);
            return $this->sendResponse($result, 'Lấy danh sách cài đặt hệ thống thành công.');
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError('Lỗi khi lấy danh sách cài đặt hệ thống', ['error' => $e->getMessage()], $code);
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
            $setting = $this->systemSettingService->createSetting($request);
            return $this->sendResponse(new SystemSettingResource($setting), 'Tạo cài đặt hệ thống thành công.');
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi khi tạo cài đặt hệ thống', $e->errors(), 422);
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError('Lỗi khi tạo cài đặt hệ thống', ['error' => $e->getMessage()], $code);
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
            $setting = $this->systemSettingService->getSettingById($id);
            return $this->sendResponse(new SystemSettingResource($setting), 'Lấy thông tin cài đặt hệ thống thành công.');
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError('Lỗi khi lấy thông tin cài đặt hệ thống', ['error' => $e->getMessage()], $code);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param SystemSetting $systemSetting
     * @return JsonResponse
     */
    public function update(Request $request, SystemSetting $systemSetting): JsonResponse
    {
        try {
            $updatedSetting = $this->systemSettingService->updateSetting($request, $systemSetting);
            return $this->sendResponse(new SystemSettingResource($updatedSetting), 'Cập nhật cài đặt hệ thống thành công.');
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi khi cập nhật cài đặt hệ thống', $e->errors(), 422);
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError('Lỗi khi cập nhật cài đặt hệ thống', ['error' => $e->getMessage()], $code);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param SystemSetting $systemSetting
     * @return JsonResponse
     */
    public function destroy(SystemSetting $systemSetting): JsonResponse
    {
        try {
            $this->systemSettingService->deleteSetting($systemSetting);
            return $this->sendResponse([], 'Xóa cài đặt hệ thống thành công.');
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError('Lỗi khi xóa cài đặt hệ thống', ['error' => $e->getMessage()], $code);
        }
    }
}
