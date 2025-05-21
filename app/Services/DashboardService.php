<?php

namespace App\Services;

use App\Repositories\Interfaces\DashboardRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardService
{
    protected $dashboardRepository;

    public function __construct(DashboardRepositoryInterface $dashboardRepository)
    {
        $this->dashboardRepository = $dashboardRepository;
    }

    /**
     * Lấy thống kê và thông tin dashboard
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getStats(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Người dùng không được xác thực', 401);
        }

        // Xử lý các bộ lọc từ request
        $filters = [
            'status' => $request->status ?? null,
        ];

        // Lấy các thống kê
        $stats = [
            'houses' => $this->dashboardRepository->getHousesCount($user, $filters),
            'rooms' => $this->dashboardRepository->getRoomsCount($user, $filters),
            'tenants' => $this->dashboardRepository->getTenantsCount($user, $filters),
            'contracts' => $this->dashboardRepository->getContractsCount($user, $filters),
        ];

        // Lấy thông tin hệ thống
        $systemInfo = $this->dashboardRepository->getSystemInfo();

        return [
            'stats' => $stats,
            'system_info' => $systemInfo,
        ];
    }
} 