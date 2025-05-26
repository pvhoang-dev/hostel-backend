<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\StatisticsService;

class StatisticsController extends BaseController
{
    protected $statisticsService;

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
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
            $data = $this->statisticsService->getOverview($request);
            return $this->sendResponse($data, 'Lấy thống kê tổng quan thành công');
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
            $data = $this->statisticsService->getContractsStats($request);
            return $this->sendResponse($data, 'Lấy thống kê hợp đồng thành công');
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
            $data = $this->statisticsService->getRevenueStats($request);
            return $this->sendResponse($data, 'Lấy thống kê doanh thu thành công');
        } catch (\Exception $e) {
            return $this->sendError(
                $e->getMessage(),
                [],
                $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Lấy thống kê về sử dụng dịch vụ
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function services(Request $request): JsonResponse
    {
        try {
            $data = $this->statisticsService->getServicesStats($request);
            return $this->sendResponse($data, 'Lấy thống kê dịch vụ thành công');
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
            $data = $this->statisticsService->getEquipmentStats($request);
            return $this->sendResponse($data, 'Lấy thống kê trang thiết bị thành công');
        } catch (\Exception $e) {
            return $this->sendError(
                $e->getMessage(),
                [],
                $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Xuất báo cáo tùy chỉnh
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function exportReport(Request $request): JsonResponse
    {
        try {
            $data = $this->statisticsService->exportCustomReport($request);
            return $this->sendResponse($data, 'Xuất báo cáo thành công');
        } catch (\Exception $e) {
            return $this->sendError(
                $e->getMessage(),
                [],
                $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }
} 