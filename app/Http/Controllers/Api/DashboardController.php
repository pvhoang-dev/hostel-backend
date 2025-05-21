<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Models\Contract;
use App\Models\House;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\DashboardService;

class DashboardController extends BaseController
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get dashboard statistics and information.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $data = $this->dashboardService->getStats($request);
            return $this->sendResponse($data, 'Dữ liệu bảng điều khiển được lấy thành công');
        } catch (\Exception $e) {
            return $this->sendError(
                $e->getMessage(),
                [],
                $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }
}
