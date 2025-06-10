<?php

namespace App\Repositories;

use App\Models\Contract;
use App\Models\House;
use App\Models\Invoice;
use App\Models\Room;
use App\Models\User;
use App\Repositories\Interfaces\StatisticsRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class StatisticsRepository implements StatisticsRepositoryInterface
{
    /**
     * Lấy thống kê tổng quan
     *
     * @param User $user Người dùng hiện tại
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getOverviewStats(User $user, array $filters = []): array
    {
        $housesQuery = House::query();

        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            $housesQuery->where('id', $filters['house_id']);
        }

        $housesCount = $housesQuery->count();

        // Lấy số phòng
        $roomsQuery = Room::query();

        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            $roomsQuery->where('house_id', $filters['house_id']);
        }

        $roomsCount = $roomsQuery->count();

        // Lấy số hợp đồng đang hoạt động
        $contractsQuery = Contract::where('status', 'active');

        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            $contractsQuery->whereHas('room', function($q) use ($filters) {
                $q->where('house_id', $filters['house_id']);
            });
        }

        $activeContractsCount = $contractsQuery->count();

        return [
            'houses_count' => $housesCount,
            'rooms_count' => $roomsCount,
            'active_contracts_count' => $activeContractsCount,
        ];
    }

    /**
     * Lấy tỷ lệ phòng trống / phòng đã thuê
     *
     * @param User $user Người dùng hiện tại
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getRoomOccupancyStats(User $user, array $filters = []): array
    {
        $isAdmin = $user->role->code === 'admin';
        $isManager = $user->role->code === 'manager';
        
        // Query cơ bản
        $roomsQuery = Room::query();
        
        // Áp dụng phân quyền
        if (!$isAdmin) {
            if ($isManager) {
                $roomsQuery->whereHas('house', function($q) use ($user) {
                    $q->where('manager_id', $user->id);
                });
            } else {
                // Tenant chỉ xem được nhà mình đang ở
                $roomsQuery->whereHas('house.rooms.contracts.users', function($q) use ($user) {
                    $q->where('users.id', $user->id)
                      ->where('contracts.status', 'active');
                });
            }
        }
        
        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            $roomsQuery->where('house_id', $filters['house_id']);
        }
        
        // Lấy tổng số phòng
        $totalRooms = $roomsQuery->count();
        
        // Lấy số phòng đã có hợp đồng active
        $occupiedRooms = (clone $roomsQuery)
            ->whereHas('contracts', function($q) {
                $q->where('status', 'active');
            })
            ->count();
        
        // Số phòng trống
        $vacantRooms = $totalRooms - $occupiedRooms;
        
        // Tính tỷ lệ
        $occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 2) : 0;
        $vacancyRate = $totalRooms > 0 ? round(($vacantRooms / $totalRooms) * 100, 2) : 0;
        
        return [
            'total_rooms' => $totalRooms,
            'occupied_rooms' => $occupiedRooms,
            'vacant_rooms' => $vacantRooms,
            'occupancy_rate' => $occupancyRate,
            'vacancy_rate' => $vacancyRate
        ];
    }

    /**
     * Lấy doanh thu theo kỳ
     *
     * @param User $user Người dùng hiện tại
     * @param string $period Kỳ (month/year)
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getRevenuePeriodComparison(User $user, string $period = 'month', array $filters = []): array
    {
        // Lấy tháng và năm hiện tại
        $currentDate = Carbon::now();
        $currentMonth = $currentDate->month;
        $currentYear = $currentDate->year;
        
        // Lấy năm trước nếu so sánh theo năm
        $previousYear = $currentYear - 1;
        $previousMonth = $currentMonth;
        if ($period === 'month') {
            // Lấy tháng trước của cùng năm
            $previousDate = Carbon::now()->subMonth();
            $previousMonth = $previousDate->month;
            $previousYear = $previousDate->year;
        }
        
        // Query doanh thu hiện tại
        $currentQuery = Invoice::where('payment_status', 'completed');
        
        if ($period === 'month') {
            $currentQuery->whereMonth('payment_date', $currentMonth)
                         ->whereYear('payment_date', $currentYear);
        } else { // year
            $currentQuery->whereYear('payment_date', $currentYear);
        }
        
        // Query doanh thu kỳ trước
        $previousQuery = Invoice::where('payment_status', 'completed');
        
        if ($period === 'month') {
            $previousQuery->whereMonth('payment_date', $previousMonth)
                          ->whereYear('payment_date', $previousYear);
        } else { // year
            $previousQuery->whereYear('payment_date', $previousYear);
        }
        
        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            $currentQuery->whereHas('room', function($q) use ($filters) {
                $q->where('house_id', $filters['house_id']);
            });
            $previousQuery->whereHas('room', function($q) use ($filters) {
                $q->where('house_id', $filters['house_id']);
            });
        }
        
        // Tính tổng doanh thu
        $currentRevenue = $currentQuery->sum('total_amount');
        $previousRevenue = $previousQuery->sum('total_amount');
        
        // Tính % tăng/giảm
        $change = $currentRevenue - $previousRevenue;
        $changePercent = 0;
        
        if ($previousRevenue > 0) {
            $changePercent = round(($change / $previousRevenue) * 100, 2);
        } else if ($currentRevenue > 0) {
            // Nếu doanh thu kỳ trước = 0 và kỳ này > 0, tăng 100%
            $changePercent = 100;
        }
        
        $periodLabel = $period === 'month' ? 'tháng' : 'năm';
        $currentLabel = $period === 'month' ? "Tháng {$currentMonth}/{$currentYear}" : "Năm {$currentYear}";
        $previousLabel = $period === 'month' ? "Tháng {$previousMonth}/{$previousYear}" : "Năm {$previousYear}";
        
        return [
            'period_type' => $periodLabel,
            'current_period' => $currentLabel,
            'previous_period' => $previousLabel,
            'current_revenue' => $currentRevenue,
            'previous_revenue' => $previousRevenue,
            'change' => $change,
            'change_percent' => $changePercent
        ];
    }

    /**
     * Lấy danh sách hợp đồng sắp đáo hạn trong X ngày tới
     *
     * @param User $user Người dùng hiện tại
     * @param int $days Số ngày
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getExpiringContracts(User $user, int $days = 45, array $filters = []): array
    {   
        // Lấy ngày hiện tại và ngày kết thúc
        $today = Carbon::today();
        $endDate = Carbon::today()->addDays($days);
        
        // Query các hợp đồng sắp đáo hạn
        $contractsQuery = Contract::where('status', 'active')
                                ->where('end_date', '>=', $today->format('Y-m-d'))
                                ->where('end_date', '<=', $endDate->format('Y-m-d'))
                                ->with(['room', 'room.house', 'users']);
        
        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            $contractsQuery->whereHas('room', function($q) use ($filters) {
                $q->where('house_id', $filters['house_id']);
            });
        }
        
        // Sắp xếp theo ngày đáo hạn gần nhất
        $contracts = $contractsQuery->orderBy('end_date', 'asc')->get();
        
        // Format kết quả trả về
        $result = [];
        foreach ($contracts as $contract) {
            $daysRemaining = Carbon::today()->diffInDays(Carbon::parse($contract->end_date));
            
            $tenants = [];
            foreach ($contract->users as $tenant) {
                $tenants[] = [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'phone_number' => $tenant->phone_number
                ];
            }
            
            $result[] = [
                'id' => $contract->id,
                'room_id' => $contract->room_id,
                'room_number' => $contract->room->room_number,
                'house_id' => $contract->room->house_id,
                'house_name' => $contract->room->house->name,
                'start_date' => $contract->start_date,
                'end_date' => $contract->end_date,
                'days_remaining' => $daysRemaining,
                'monthly_price' => $contract->monthly_price,
                'tenants' => $tenants,
                'auto_renew' => $contract->auto_renew ?? false
            ];
        }
        
        return $result;
    }

    /**
     * Lấy thống kê hợp đồng mới theo kỳ
     *
     * @param User $user Người dùng hiện tại
     * @param string $period Kỳ (week/month)
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getNewContractsStats(User $user, string $period = 'month', array $filters = []): array
    {
        $result = [];
        
        if ($period === 'month' || $period === 'monthly') {
            // Lấy 12 tháng gần nhất
            for ($i = 0; $i < 12; $i++) {
                $month = Carbon::now()->subMonths($i);
                $monthStart = Carbon::create($month->year, $month->month, 1);
                $monthEnd = $monthStart->copy()->endOfMonth();
                
                // Query tất cả hợp đồng trong tháng
                $query = Contract::whereBetween('start_date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')]);
                
                // Áp dụng filter house_id nếu có
                if (isset($filters['house_id'])) {
                    $query->whereHas('room', function($q) use ($filters) {
                        $q->where('house_id', $filters['house_id']);
                    });
                }
                
                // Đếm số lượng hợp đồng mới (không phải là auto_renew)
                $newContracts = (clone $query)
                    ->where('auto_renew', false)
                    ->get();
                
                $newCount = $newContracts->count();
                
                // Đếm số hợp đồng gia hạn (có auto_renew = true)
                $renewalContracts = (clone $query)
                    ->where('auto_renew', true)
                    ->get();
                
                $renewalCount = $renewalContracts->count();
                
                // Tính tổng giá trị hợp đồng mới
                $totalValue = $newContracts->sum('monthly_price') + $renewalContracts->sum('monthly_price');
                
                $result[] = [
                    'period' => 'Tháng ' . $month->month . '/' . $month->year,
                    'new_count' => $newCount,
                    'renewal_count' => $renewalCount,
                    'total_count' => $newCount + $renewalCount,
                    'total_value' => $totalValue
                ];
            }
            
            // Đảo ngược mảng để tháng cũ nhất ở đầu
            $result = array_reverse($result);
        } else if ($period === 'quarterly' || $period === 'quarter') {
            // Lấy 4 quý gần nhất
            for ($i = 0; $i < 4; $i++) {
                $date = Carbon::now()->subQuarters($i);
                $quarterNumber = ceil($date->month / 3);
                $quarterStart = Carbon::create($date->year, ($quarterNumber - 1) * 3 + 1, 1)->startOfDay();
                $quarterEnd = Carbon::create($date->year, $quarterNumber * 3, 1)->endOfMonth();
                
                // Query tất cả hợp đồng trong quý
                $query = Contract::whereBetween('start_date', [$quarterStart->format('Y-m-d'), $quarterEnd->format('Y-m-d')]);
                
                // Áp dụng filter house_id nếu có
                if (isset($filters['house_id'])) {
                    $query->whereHas('room', function($q) use ($filters) {
                        $q->where('house_id', $filters['house_id']);
                    });
                }
                
                // Đếm số lượng hợp đồng mới (không phải là auto_renew)
                $newContracts = (clone $query)
                    ->where('auto_renew', false)
                    ->get();
                
                $newCount = $newContracts->count();
                
                // Đếm số hợp đồng gia hạn (có auto_renew = true)
                $renewalContracts = (clone $query)
                    ->where('auto_renew', true)
                    ->get();
                
                $renewalCount = $renewalContracts->count();
                
                // Tính tổng giá trị hợp đồng mới
                $totalValue = $newContracts->sum('monthly_price') + $renewalContracts->sum('monthly_price');
                
                $result[] = [
                    'period' => 'Quý ' . $quarterNumber . '/' . $date->year,
                    'new_count' => $newCount,
                    'renewal_count' => $renewalCount,
                    'total_count' => $newCount + $renewalCount,
                    'total_value' => $totalValue
                ];
            }
            
            // Đảo ngược mảng để quý cũ nhất ở đầu
            $result = array_reverse($result);
        } else if ($period === 'yearly' || $period === 'year') {
            // Lấy 3 năm gần nhất
            for ($i = 0; $i < 3; $i++) {
                $year = Carbon::now()->subYears($i)->year;
                $yearStart = Carbon::createFromDate($year, 1, 1)->startOfDay();
                $yearEnd = Carbon::createFromDate($year, 12, 31)->endOfDay();
                
                // Query tất cả hợp đồng trong năm
                $query = Contract::whereBetween('start_date', [$yearStart->format('Y-m-d'), $yearEnd->format('Y-m-d')]);
                
                // Áp dụng filter house_id nếu có
                if (isset($filters['house_id'])) {
                    $query->whereHas('room', function($q) use ($filters) {
                        $q->where('house_id', $filters['house_id']);
                    });
                }
                
                // Đếm số lượng hợp đồng mới (không phải là auto_renew)
                $newContracts = (clone $query)
                    ->where('auto_renew', false)
                    ->get();
                
                $newCount = $newContracts->count();
                
                // Đếm số hợp đồng gia hạn (có auto_renew = true)
                $renewalContracts = (clone $query)
                    ->where('auto_renew', true)
                    ->get();
                
                $renewalCount = $renewalContracts->count();
                
                // Tính tổng giá trị hợp đồng mới
                $totalValue = $newContracts->sum('monthly_price') + $renewalContracts->sum('monthly_price');
                
                $result[] = [
                    'period' => 'Năm ' . $year,
                    'new_count' => $newCount,
                    'renewal_count' => $renewalCount,
                    'total_count' => $newCount + $renewalCount,
                    'total_value' => $totalValue
                ];
            }
            
            // Đảo ngược mảng để năm cũ nhất ở đầu
            $result = array_reverse($result);
        }
        
        return $result;
    }

    /**
     * Lấy thống kê khách thuê theo nhà
     *
     * @param User $user Người dùng hiện tại
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getTenantStats(User $user, array $filters = []): array
    {
        // Lấy danh sách nhà theo quyền
        $housesQuery = House::query();
        
        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            $housesQuery->where('id', $filters['house_id']);
        }
        
        $houses = $housesQuery->get(['id', 'name']);
        
        $result = [
            'by_house' => [],
        ];
        
        // Thống kê theo nhà
        foreach ($houses as $house) {
            $tenantsCount = User::whereHas('role', function($q) {
                $q->where('code', 'tenant');
            })->whereHas('contracts', function($q) use ($house) {
                $q->where('status', 'active')
                  ->whereHas('room', function($q) use ($house) {
                    $q->where('house_id', $house->id);
                  });
            })->count();
            
            $result['by_house'][] = [
                'house_id' => $house->id,
                'house_name' => $house->name,
                'tenants_count' => $tenantsCount
            ];
        }
        
        // Thống kê theo độ tuổi (Giả sử có trường ngày sinh)
        // Trong thực tế, nếu không có trường này, chúng ta có thể bỏ qua phần này
        
        return $result;
    }

    /**
     * Lấy doanh thu theo loại kỳ báo cáo (tháng/quý/năm)
     *
     * @param User $user Người dùng hiện tại
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getRevenueByPeriod(User $user, array $filters = []): array
    {   
        $period = $filters['period'] ?? 'monthly';
        $year = $filters['year'] ?? Carbon::now()->year;
        
        // Luôn trả về dữ liệu đầy đủ dựa theo loại kỳ
        if ($period === 'monthly') {
            // Luôn trả về dữ liệu 12 tháng của năm được chọn
            return $this->getMonthlyRevenueStats($user, $year, $filters);
        }
        
        if ($period === 'quarterly') {
            // Luôn trả về dữ liệu 4 quý của năm được chọn
            return $this->getQuarterlyRevenueStats($user, $year, null, $filters);
        }
        
        if ($period === 'yearly') {
            // Trả về dữ liệu 5 năm gần nhất
            return $this->getYearlyRevenueStats($user, null, $filters);
        }
        
        // Mặc định trả về theo tháng
        return $this->getMonthlyRevenueStats($user, $year, $filters);
    }

    /**
     * Lấy doanh thu theo tháng trong năm
     *
     * @param User $user Người dùng hiện tại
     * @param int $year Năm
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getMonthlyRevenueStats(User $user, int $year = null, array $filters = []): array
    {
        // Nếu không có năm thì lấy năm hiện tại
        if ($year === null) {
            $year = Carbon::now()->year;
        }
        
        // Mảng kết quả - 12 tháng
        $result = [];
        for ($month = 1; $month <= 12; $month++) {
            $result[$month] = [
                'month' => $month,
                'revenue' => 0
            ];
        }
        
        // Query doanh thu theo tháng
        $revenueQuery = Invoice::select(
            'month', 
            DB::raw('SUM(total_amount) as total_revenue')
        )
        ->where('year', $year)
        ->where(function($query) {
            $query->where('payment_status', 'completed');
        });
        
        $revenueQuery->groupBy('month');
        
        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            $revenueQuery->whereHas('room', function($q) use ($filters) {
                $q->where('house_id', $filters['house_id']);
            });
        }
        
        // Thực hiện query
        $revenueData = $revenueQuery->get();
        
        // Cập nhật dữ liệu vào mảng kết quả
        foreach ($revenueData as $data) {
            $result[$data->month]['revenue'] = (int) $data->total_revenue;
        }
        
        $title = "Doanh thu theo tháng năm $year";
        
        // Format kết quả
        return [
            'year' => $year,
            'title' => $title,
            'monthly_data' => array_values($result)
        ];
    }
    
    /**
     * Lấy doanh thu theo quý trong năm
     *
     * @param User $user Người dùng hiện tại
     * @param int $year Năm
     * @param int $quarter Quý (1-4), nếu null thì lấy tất cả các quý
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getQuarterlyRevenueStats(User $user, int $year = null, int $quarter = null, array $filters = []): array
    {   
        // Nếu không có năm thì lấy năm hiện tại
        if ($year === null) {
            $year = Carbon::now()->year;
        }
        
        // Tính tháng bắt đầu và kết thúc của quý
        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $quarter * 3;
        
        // Lấy dữ liệu theo tháng để tổng hợp theo quý
        $monthlyData = $this->getMonthlyRevenueStats($user, $year, $filters);
        $monthlyDataArray = $monthlyData['monthly_data'];
        
        // Tổng hợp dữ liệu theo quý
        $quarterlyData = [];
        if ($quarter !== null) {
            // Nếu có quý cụ thể, chỉ tính cho quý đó
            $quarterlyData = [
                'quarter' => $quarter,
                'revenue' => 0
            ];
            
            // Tính tổng doanh thu cho quý
            foreach ($monthlyDataArray as $data) {
                $month = $data['month'];
                if ($month >= $startMonth && $month <= $endMonth) {
                    $quarterlyData['revenue'] += $data['revenue'];
                }
            }
            
            $title = "Doanh thu quý $quarter năm $year";
        } else {
            // Nếu không có quý cụ thể, tính cho tất cả các quý
            for ($q = 1; $q <= 4; $q++) {
                $quarterlyData[$q] = [
                    'quarter' => $q,
                    'revenue' => 0
                ];
                
                $qStartMonth = ($q - 1) * 3 + 1;
                $qEndMonth = $q * 3;
                
                // Tính tổng doanh thu cho từng quý
                foreach ($monthlyDataArray as $data) {
                    $month = $data['month'];
                    if ($month >= $qStartMonth && $month <= $qEndMonth) {
                        $quarterlyData[$q]['revenue'] += $data['revenue'];
                    }
                }
            }
            
            $quarterlyData = array_values($quarterlyData);
            $title = "Doanh thu theo quý năm $year";
        }
        
        // Format kết quả
        return [
            'year' => $year,
            'title' => $title,
            'quarterly_data' => $quarter !== null ? [$quarterlyData] : $quarterlyData
        ];
    }
    
    /**
     * Lấy doanh thu theo năm
     *
     * @param User $user Người dùng hiện tại
     * @param int $year Năm cụ thể, nếu null thì lấy 5 năm gần nhất
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getYearlyRevenueStats(User $user, int $year = null, array $filters = []): array
    {
        // Nếu có năm cụ thể, lấy doanh thu của năm đó
        if ($year !== null) {
            // Tính tổng doanh thu của năm
            $revenueQuery = Invoice::select(
                DB::raw('SUM(total_amount) as total_revenue')
            )
            ->where('year', $year)
            ->where('payment_status', 'completed');
            

            
            // Áp dụng filter house_id nếu có
            if (isset($filters['house_id'])) {
                $revenueQuery->whereHas('room', function($q) use ($filters) {
                    $q->where('house_id', $filters['house_id']);
                });
            }
            
            // Thực hiện query
            $revenue = $revenueQuery->first()->total_revenue ?? 0;
            
            return [
                'title' => "Doanh thu năm $year",
                'yearly_data' => [
                    [
                        'year' => $year,
                        'revenue' => (int) $revenue
                    ]
                ]
            ];
        }
        
        // Nếu không có năm cụ thể, lấy doanh thu 5 năm gần nhất
        $currentYear = Carbon::now()->year;
        $startYear = $currentYear - 4; // 5 năm gần nhất
        
        // Query doanh thu theo năm
        $revenueQuery = Invoice::select(
            'year',
            DB::raw('SUM(total_amount) as total_revenue')
        )
        ->where('payment_status', 'completed')
        ->where('year', '>=', $startYear)
        ->where('year', '<=', $currentYear)
        ->groupBy('year')
        ->orderBy('year');
        
        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            $revenueQuery->whereHas('room', function($q) use ($filters) {
                $q->where('house_id', $filters['house_id']);
            });
        }
        
        // Thực hiện query
        $revenueData = $revenueQuery->get();
        
        // Tạo mảng kết quả
        $result = [];
        for ($y = $startYear; $y <= $currentYear; $y++) {
            $result[$y] = [
                'year' => $y,
                'revenue' => 0
            ];
        }
        
        // Cập nhật dữ liệu vào mảng kết quả
        foreach ($revenueData as $data) {
            if (isset($result[$data->year])) {
                $result[$data->year]['revenue'] = (int) $data->total_revenue;
            }
        }
        
        return [
            'title' => "Doanh thu theo năm",
            'yearly_data' => array_values($result)
        ];
    }

    /**
     * Lấy thống kê hóa đơn theo trạng thái
     *
     * @param User $user Người dùng hiện tại
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getInvoiceStatusStats(User $user, array $filters = []): array
    {
        // Query cơ bản
        $query = Invoice::select('payment_status', DB::raw('count(*) as total'))
                     ->groupBy('payment_status');
        
        // Áp dụng filter khoảng thời gian nếu có
        if (isset($filters['year'])) {
            $query->where('year', $filters['year']);
        }
        
        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            $query->whereHas('room', function($q) use ($filters) {
                $q->where('house_id', $filters['house_id']);
            });
        }
        
        // Thực hiện query
        $stats = $query->get();
        
        // Format kết quả
        $result = [
            'completed' => 0,
            'waiting' => 0,
            'pending' => 0
        ];
        
        foreach ($stats as $stat) {
            if (isset($result[$stat->payment_status])) {
                $result[$stat->payment_status] = (int) $stat->total;
            }
        }
        
        // Thêm tổng số
        $result['total'] = array_sum($result);
        
        return $result;
    }

    /**
     * Lấy danh sách hóa đơn chưa thanh toán với phân trang
     *
     * @param User $user Người dùng hiện tại
     * @param int $page Trang hiện tại
     * @param int $perPage Số bản ghi mỗi trang
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getUnpaidInvoices(User $user, int $page = 1, int $perPage = 10, array $filters = []): array
    {
        // Query cơ bản
        $query = Invoice::where(function($q) {
                // Include all non-completed statuses
                $q->where('payment_status', 'pending')
                  ->orWhere('payment_status', 'waiting');
            })
            ->orderBy('created_at', 'desc') // Order by created date, newest first
            ->with(['room', 'room.house', 'items']);
        
        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            $query->whereHas('room', function($q) use ($filters) {
                $q->where('house_id', $filters['house_id']);
            });
        }
        
        // Tính tổng số bản ghi để phân trang
        $total = $query->count();
        
        // Phân trang
        $offset = ($page - 1) * $perPage;
        $invoices = $query->skip($offset)->take($perPage)->get();
        
        // Format kết quả
        $result = [];
        foreach ($invoices as $invoice) {
            // Lấy thông tin người thuê gần nhất của phòng (nếu có)
            $tenant = null;
            $contract = $invoice->room->currentContract;
            
            if ($contract && $contract->users->count() > 0) {
                $tenant = $contract->users->first();
                $tenant = [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'phone_number' => $tenant->phone_number
                ];
            }
            
            $result[] = [
                'id' => $invoice->id,
                'room_id' => $invoice->room_id,
                'room_number' => $invoice->room->room_number,
                'house_id' => $invoice->room->house_id,
                'house_name' => $invoice->room->house->name,
                'tenant' => $tenant,
                'total_amount' => $invoice->total_amount,
                'month' => $invoice->month,
                'year' => $invoice->year,
                'payment_status' => $invoice->payment_status,
                'created_at' => $invoice->created_at->format('Y-m-d H:i:s')
            ];
        }
        
        return [
            'data' => $result,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ];
    }
    
    /**
     * Lấy danh sách phòng có số lượng thiết bị ít hơn hoặc bằng giới hạn
     *
     * @param User $user Người dùng hiện tại
     * @param int $limit Giới hạn số lượng thiết bị
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getRoomsWithLimitedEquipment(User $user, int $limit = 2, array $filters = []): array
    {
        // Lấy danh sách phòng
        $roomsQuery = Room::with(['house', 'equipments'])
            ->withCount('equipments as equipment_count')
            ->having('equipment_count', '<=', $limit);
        
        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            $roomsQuery->where('house_id', $filters['house_id']);
        }
        
        // Thực hiện query
        $rooms = $roomsQuery->get();
        
        // Format kết quả
        $result = [];
        foreach ($rooms as $room) {         
            $warning = "Phòng này " . ($room->equipment_count === 0 ? "không có" : "chỉ có {$room->equipment_count} thiết bị") . ", có thể thiếu trang thiết bị cần thiết";

            $roomEquipments = [];

            foreach ($room->equipments as $equipment) {
                $roomEquipments[] = [
                    'equipment_name' => $equipment->equipment->name,
                    'quantity' => $equipment->quantity
                ];
            }

            $result[] = [
                'room_id' => $room->id,
                'room_number' => $room->room_number,
                'house_id' => $room->house_id,
                'house_name' => $room->house->name,
                'equipment_count' => $room->equipment_count,
                'equipments' => $roomEquipments,
                'warning' => $warning
            ];
        }
        
        return $result;
    }
}