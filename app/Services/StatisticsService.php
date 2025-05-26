<?php

namespace App\Services;

use App\Repositories\Interfaces\StatisticsRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class StatisticsService
{
    protected $statisticsRepository;
    protected $cachePrefix = 'statistics_';
    protected $cacheTtl = 3600; // 1 giờ

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
        $user = $this->checkAuthentication();
        $this->checkPermission($user, 'admin');

        // Lấy các bộ lọc từ request
        $filters = [
            'house_id' => $request->house_id ?? null,
        ];

        // Tạo key cache dựa trên user và filters
        $cacheKey = $this->getCacheKey('overview', $user->id, $filters);

        // Thử lấy từ cache trước
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
        $user = $this->checkAuthentication();
        $this->checkPermission($user, 'admin');

        // Lấy các bộ lọc từ request
        $filters = [
            'house_id' => $request->house_id ?? null,
        ];

        $days = $request->days ?? 30;
        $period = $request->period ?? 'month';

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
        $user = $this->checkAuthentication();
        $this->checkPermission($user, 'admin');

        // Lấy các bộ lọc từ request
        $filters = [
            'house_id' => $request->house_id ?? null,
            'year' => $request->year ?? Carbon::now()->year,
            'month' => $request->month ?? Carbon::now()->month
        ];

        $limit = $request->limit ?? 10;

        // Tạo key cache dựa trên user và filters
        $cacheKey = $this->getCacheKey('revenue', $user->id, array_merge($filters, ['limit' => $limit]));

        // Thử lấy từ cache trước
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user, $filters, $limit) {
            $monthlyRevenue = $this->statisticsRepository->getMonthlyRevenueStats($user, $filters['year'], $filters);
            $invoiceStatus = $this->statisticsRepository->getInvoiceStatusStats($user, $filters);
            $largestPending = $this->statisticsRepository->getLargestPendingInvoices($user, $limit, $filters);

            return [
                'monthly_revenue' => $monthlyRevenue,
                'invoice_status' => $invoiceStatus,
                'largest_pending' => $largestPending
            ];
        });
    }

    /**
     * Lấy thống kê về sử dụng dịch vụ
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getServicesStats(Request $request)
    {
        $user = $this->checkAuthentication();
        $this->checkPermission($user, 'admin');

        // Lấy các bộ lọc từ request
        $filters = [
            'house_id' => $request->house_id ?? null,
            'year' => $request->year ?? Carbon::now()->year,
            'month' => $request->month ?? Carbon::now()->month
        ];

        $serviceTypes = $request->service_types ?? ['Điện', 'Nước', 'Internet'];

        // Tạo key cache dựa trên user và filters
        $cacheKey = $this->getCacheKey('services', $user->id, array_merge($filters, ['service_types' => implode(',', $serviceTypes)]));

        // Thử lấy từ cache trước
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user, $filters, $serviceTypes) {
            $serviceUsage = $this->statisticsRepository->getMonthlyServiceUsageStats($user, $serviceTypes, $filters['year'], $filters);
            $serviceRevenue = $this->statisticsRepository->getServiceRevenueComparison($user, $filters);

            return [
                'service_usage' => $serviceUsage,
                'service_revenue' => $serviceRevenue
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
        $user = $this->checkAuthentication();
        $this->checkPermission($user, 'admin');

        // Lấy các bộ lọc từ request
        $filters = [
            'house_id' => $request->house_id ?? null,
        ];

        // Tạo key cache dựa trên user và filters
        $cacheKey = $this->getCacheKey('equipment', $user->id, $filters);

        // Thử lấy từ cache trước
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user, $filters) {
            $inventoryStats = $this->statisticsRepository->getEquipmentInventoryStats($user, $filters);
            $missingEquipment = $this->statisticsRepository->getRoomsMissingEquipment($user, $filters);

            return [
                'inventory' => $inventoryStats,
                'missing_equipment' => $missingEquipment
            ];
        });
    }

    /**
     * Xuất báo cáo tùy chỉnh
     *
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    public function exportCustomReport(Request $request)
    {
        $user = $this->checkAuthentication();
        $this->checkPermission($user, 'admin');

        // Đối với xuất báo cáo, không sử dụng cache để luôn lấy dữ liệu mới nhất
        return $this->statisticsRepository->generateCustomReport($user, $request);
    }

    /**
     * Kiểm tra xác thực người dùng
     *
     * @return \App\Models\User
     * @throws \Exception
     */
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
     *
     * @param \App\Models\User $user
     * @param string $role
     * @return bool
     * @throws \Exception
     */
    protected function checkPermission($user, $role = 'admin')
    {
        if ($user->role && $user->role->code === $role) {
            return true;
        }

        // Cho phép manager xem thống kê của nhà mình quản lý
        if ($role === 'admin' && $user->role && $user->role->code === 'manager') {
            return true;
        }

        throw new \Exception('Bạn không có quyền truy cập tính năng này', 403);
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