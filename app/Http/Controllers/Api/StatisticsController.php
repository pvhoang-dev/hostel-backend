<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\StatisticsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StatisticsController extends BaseController
{
    protected $statisticsService;

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    protected function checkAuthentication()
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Người dùng không được xác thực', 401);
        }
        return $user;
    }

    /**
     * Kiểm tra quyền truy cập
     */
    protected function checkPermission($user, $role = 'admin')
    {
        if ($user->role->code !== $role) {
            throw new \Exception('Bạn không có quyền truy cập tính năng này', 403);
        }
        return true;
    }

    /**
     * Lấy thống kê tổng quan
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function overview(Request $request): JsonResponse
    {
        try {
            // Xác thực người dùng và kiểm tra quyền
            $user = $this->checkAuthentication();
            $this->checkPermission($user, 'admin');
            
            // Validate request if needed
            $validator = validator($request->all(), [
                'house_id' => 'nullable|exists:houses,id',
            ]);
            
            if ($validator->fails()) {
                return $this->sendError('Lỗi dữ liệu đầu vào', $validator->errors(), 422);
            }
            
            $data = $this->statisticsService->getOverview($request);
            return $this->sendResponse($data, 'Lấy thống kê tổng quan thành công');
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError(
                $e->getMessage(),
                [],
                $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Lấy thống kê về hợp đồng và khách thuê
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function contracts(Request $request): JsonResponse
    {
        try {
            // Xác thực người dùng và kiểm tra quyền
            $user = $this->checkAuthentication();
            $this->checkPermission($user, 'admin');
            
            // Validate request
            $validator = validator($request->all(), [
                'house_id' => 'nullable|exists:houses,id',
                'days' => 'nullable|integer|min:1|max:365',
                'period' => 'nullable|in:week,month,monthly,quarterly,yearly',
            ]);
            
            if ($validator->fails()) {
                return $this->sendError('Lỗi dữ liệu đầu vào', $validator->errors(), 422);
            }
            
            $data = $this->statisticsService->getContractsStats($request);
            return $this->sendResponse($data, 'Lấy thống kê hợp đồng thành công');
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError(
                $e->getMessage(),
                [],
                $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Lấy thống kê về doanh thu và công nợ
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function revenue(Request $request): JsonResponse
    {
        try {
            // Xác thực người dùng và kiểm tra quyền
            $user = $this->checkAuthentication();
            $this->checkPermission($user, 'admin');
            
            // Validate request
            $validator = validator($request->all(), [
                'house_id' => 'nullable|exists:houses,id',
                'year' => 'nullable|integer|min:2000|max:' . (date('Y') + 1),
                'month' => 'nullable|integer|min:1|max:12',
                'period' => 'nullable|in:monthly,quarterly,yearly',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);
            
            if ($validator->fails()) {
                return $this->sendError('Lỗi dữ liệu đầu vào', $validator->errors(), 422);
            }
            
            $data = $this->statisticsService->getRevenueStats($request);
            return $this->sendResponse($data, 'Lấy thống kê doanh thu thành công');
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError(
                $e->getMessage(),
                [],
                $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Lấy thống kê về trang thiết bị và kho
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function equipment(Request $request): JsonResponse
    {
        try {
            // Xác thực người dùng và kiểm tra quyền
            $user = $this->checkAuthentication();
            $this->checkPermission($user, 'admin');
            
            // Validate request
            $validator = validator($request->all(), [
                'house_id' => 'nullable|exists:houses,id',
            ]);
            
            if ($validator->fails()) {
                return $this->sendError('Lỗi dữ liệu đầu vào', $validator->errors(), 422);
            }
            
            $data = $this->statisticsService->getEquipmentStats($request);
            return $this->sendResponse($data, 'Lấy thống kê trang thiết bị thành công');
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError(
                $e->getMessage(),
                [],
                $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }
} 