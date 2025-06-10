<?php

namespace App\Services;

use App\Repositories\Interfaces\StatisticsRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class StatisticsService
{
    protected $statisticsRepository;
    protected $cachePrefix = 'statistics_';
    protected $cacheTtl = 1200;

    public function __construct(StatisticsRepositoryInterface $statisticsRepository)
    {
        $this->statisticsRepository = $statisticsRepository;
    }

    /**
     * Lấy thống kê tổng quan
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getOverview(Request $request)
    {
        $user = Auth::user();

        // Lấy các bộ lọc từ request
        $filters = [
            'house_id' => $request->house_id ?? null,
        ];

        // Tạo key cache dựa trên user và filters
        $cacheKey = $this->getCacheKey('overview', $user->id, $filters);

        // Thử lấy từ cache hoặc tạo mới nếu không có
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user, $filters) {
            $overviewStats = $this->statisticsRepository->getOverviewStats($user, $filters);
            $occupancyStats = $this->statisticsRepository->getRoomOccupancyStats($user, $filters);
            $revenueComparison = $this->statisticsRepository->getRevenuePeriodComparison($user, 'month', $filters);

            return [
                'overview' => $overviewStats,
                'occupancy' => $occupancyStats,
                'revenue_comparison' => $revenueComparison
            ];
        });
    }

    /**
     * Lấy thống kê về hợp đồng và khách thuê
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getContractsStats(Request $request)
    {
        $user = Auth::user();

        // Lấy các bộ lọc từ request
        $filters = [
            'house_id' => $request->house_id ?? null,
        ];

        $days = $request->days ?? 30;
        $period = $request->period ?? 'month';
        
        // Map frontend period values to backend period values
        if ($period === 'monthly') {
            $period = 'month';
        } else if ($period === 'quarterly') {
            $period = 'quarter';
        } else if ($period === 'yearly') {
            $period = 'year';
        }

        // Tạo key cache dựa trên user và filters
        $cacheKey = $this->getCacheKey('contracts', $user->id, array_merge($filters, ['days' => $days, 'period' => $period]));

        // Thử lấy từ cache trước
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user, $filters, $days, $period) {
            $expiringContracts = $this->statisticsRepository->getExpiringContracts($user, $days, $filters);
            $newContractsStats = $this->statisticsRepository->getNewContractsStats($user, $period, $filters);
            $tenantStats = $this->statisticsRepository->getTenantStats($user, $filters);

            return [
                'expiring_contracts' => $expiringContracts,
                'new_contracts' => $newContractsStats,
                'tenant_stats' => $tenantStats
            ];
        });
    }

    /**
     * Lấy thống kê về doanh thu và công nợ
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getRevenueStats(Request $request)
    {
        $user = Auth::user();

        // Lấy các bộ lọc từ request
        $filters = [
            'house_id' => $request->house_id ?? null,
            'year' => $request->year ?? null,
            'quarter' => $request->quarter ?? null,
            'period' => $request->period ?? 'monthly',
            'filter_type' => 'period',
        ];

        // Lấy thông tin phân trang cho hóa đơn chưa thanh toán
        $page = $request->page ?? 1;
        $perPage = $request->per_page ?? 10;

        // Tạo key cache dựa trên user và filters
        $cacheKey = $this->getCacheKey('revenue', $user->id, array_merge($filters, ['page' => $page, 'per_page' => $perPage]));

        // Thử lấy từ cache trước
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user, $filters, $page, $perPage) {
            // Lấy dữ liệu doanh thu theo kỳ (tháng/quý/năm)
            $revenueData = $this->statisticsRepository->getRevenueByPeriod($user, $filters);

            // Lấy thống kê trạng thái hóa đơn
            $invoiceStatus = $this->statisticsRepository->getInvoiceStatusStats($user, $filters);
            
            // Lấy danh sách hóa đơn chưa thanh toán với phân trang
            $unpaidInvoices = $this->statisticsRepository->getUnpaidInvoices($user, $page, $perPage, $filters);

            return [
                'revenue_data' => $revenueData,
                'invoice_status' => $invoiceStatus,
                'unpaid_invoices' => $unpaidInvoices
            ];
        });
    }

    /**
     * Lấy thống kê về trang thiết bị và kho
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getEquipmentStats(Request $request)
    {
        $user = Auth::user();

        // Lấy các bộ lọc từ request
        $filters = [
            'house_id' => $request->house_id ?? null,
        ];

        // Tạo key cache dựa trên user và filters
        $cacheKey = $this->getCacheKey('equipment', $user->id, $filters);

        // Thử lấy từ cache trước
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user, $filters) {
            $roomsWithLimitedEquipment = $this->statisticsRepository->getRoomsWithLimitedEquipment($user, 2, $filters);

            return [
                'missing_equipment' => $roomsWithLimitedEquipment
            ];
        });
    }

    /**
     * Tạo key cho cache
     *
     * @param string $type Loại thống kê
     * @param int $userId ID người dùng
     * @param array $filters Các bộ lọc
     * @return string
     */
    protected function getCacheKey($type, $userId, $filters)
    {
        $filterKey = md5(json_encode($filters));
        return $this->cachePrefix . $type . '_' . $userId . '_' . $filterKey;
    }
}