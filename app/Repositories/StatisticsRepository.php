<?php

namespace App\Repositories;

use App\Models\Contract;
use App\Models\House;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Room;
use App\Models\RoomEquipment;
use App\Models\Service;
use App\Models\ServiceUsage;
use App\Models\EquipmentStorage;
use App\Models\User;
use App\Repositories\Interfaces\StatisticsRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $isAdmin = $user->role->code === 'admin';
        $isManager = $user->role->code === 'manager';

        // Lấy nhà trọ theo quyền
        $housesQuery = House::query();
        if (!$isAdmin) {
            if ($isManager) {
                $housesQuery->where('manager_id', $user->id);
            } else {
                // Tenant chỉ xem được nhà mình đang ở
                $housesQuery->whereHas('rooms.contracts.users', function($q) use ($user) {
                    $q->where('users.id', $user->id)
                      ->where('contracts.status', 'active');
                });
            }
        }

        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            $housesQuery->where('id', $filters['house_id']);
        }

        $housesCount = $housesQuery->count();

        // Lấy số phòng
        $roomsQuery = Room::query();
        if (!$isAdmin) {
            if ($isManager) {
                $roomsQuery->whereHas('house', function($q) use ($user) {
                    $q->where('manager_id', $user->id);
                });
            } else {
                // Tenant chỉ xem được phòng mình đang ở
                $roomsQuery->whereHas('contracts.users', function($q) use ($user) {
                    $q->where('users.id', $user->id)
                      ->where('contracts.status', 'active');
                });
            }
        }

        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            $roomsQuery->where('house_id', $filters['house_id']);
        }

        $roomsCount = $roomsQuery->count();

        // Lấy số hợp đồng đang hoạt động
        $contractsQuery = Contract::where('status', 'active');
        if (!$isAdmin) {
            if ($isManager) {
                $contractsQuery->whereHas('room.house', function($q) use ($user) {
                    $q->where('manager_id', $user->id);
                });
            } else {
                // Tenant chỉ xem được hợp đồng của mình
                $contractsQuery->whereHas('users', function($q) use ($user) {
                    $q->where('users.id', $user->id);
                });
            }
        }

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
        $isAdmin = $user->role->code === 'admin';
        $isManager = $user->role->code === 'manager';
        
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
        $currentQuery = Invoice::where('payment_status', 'paid');
        
        if ($period === 'month') {
            $currentQuery->where('month', $currentMonth)
                         ->where('year', $currentYear);
        } else { // year
            $currentQuery->where('year', $currentYear);
        }
        
        // Query doanh thu kỳ trước
        $previousQuery = Invoice::where('payment_status', 'paid');
        
        if ($period === 'month') {
            $previousQuery->where('month', $previousMonth)
                          ->where('year', $previousYear);
        } else { // year
            $previousQuery->where('year', $previousYear);
        }
        
        // Áp dụng phân quyền
        if (!$isAdmin) {
            if ($isManager) {
                $managerHouses = House::where('manager_id', $user->id)->pluck('id')->toArray();
                $currentQuery->whereHas('room', function($q) use ($managerHouses) {
                    $q->whereIn('house_id', $managerHouses);
                });
                $previousQuery->whereHas('room', function($q) use ($managerHouses) {
                    $q->whereIn('house_id', $managerHouses);
                });
            } else {
                // Tenant chỉ xem được hóa đơn của phòng mình
                $currentQuery->whereHas('room.contracts.users', function($q) use ($user) {
                    $q->where('users.id', $user->id);
                });
                $previousQuery->whereHas('room.contracts.users', function($q) use ($user) {
                    $q->where('users.id', $user->id);
                });
            }
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
        $change = 0;
        $changePercent = 0;
        
        if ($previousRevenue > 0) {
            $change = $currentRevenue - $previousRevenue;
            $changePercent = round(($change / $previousRevenue) * 100, 2);
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
    public function getExpiringContracts(User $user, int $days = 30, array $filters = []): array
    {
        $isAdmin = $user->role->code === 'admin';
        $isManager = $user->role->code === 'manager';
        
        // Lấy ngày hiện tại và ngày kết thúc
        $today = Carbon::today();
        $endDate = Carbon::today()->addDays($days);
        
        // Query các hợp đồng sắp đáo hạn
        $contractsQuery = Contract::where('status', 'active')
                                ->where('end_date', '>=', $today->format('Y-m-d'))
                                ->where('end_date', '<=', $endDate->format('Y-m-d'))
                                ->with(['room', 'room.house', 'users']);
        
        // Áp dụng phân quyền
        if (!$isAdmin) {
            if ($isManager) {
                $contractsQuery->whereHas('room.house', function($q) use ($user) {
                    $q->where('manager_id', $user->id);
                });
            } else {
                // Tenant chỉ xem được hợp đồng của mình
                $contractsQuery->whereHas('users', function($q) use ($user) {
                    $q->where('users.id', $user->id);
                });
            }
        }
        
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
        $isAdmin = $user->role->code === 'admin';
        $isManager = $user->role->code === 'manager';
        
        $today = Carbon::today();
        $result = [];
        
        if ($period === 'week') {
            // Lấy 4 tuần gần nhất
            for ($i = 0; $i < 4; $i++) {
                $weekStart = Carbon::now()->subWeeks($i)->startOfWeek();
                $weekEnd = Carbon::now()->subWeeks($i)->endOfWeek();
                
                $query = Contract::whereBetween('start_date', [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')]);
                
                // Áp dụng phân quyền
                if (!$isAdmin) {
                    if ($isManager) {
                        $query->whereHas('room.house', function($q) use ($user) {
                            $q->where('manager_id', $user->id);
                        });
                    } else {
                        // Tenant chỉ xem được hợp đồng của mình
                        $query->whereHas('users', function($q) use ($user) {
                            $q->where('users.id', $user->id);
                        });
                    }
                }
                
                // Áp dụng filter house_id nếu có
                if (isset($filters['house_id'])) {
                    $query->whereHas('room', function($q) use ($filters) {
                        $q->where('house_id', $filters['house_id']);
                    });
                }
                
                $count = $query->count();
                
                $result[] = [
                    'period' => 'Tuần ' . (4 - $i) . ' (' . $weekStart->format('d/m') . ' - ' . $weekEnd->format('d/m') . ')',
                    'count' => $count
                ];
            }
            
            // Đảo ngược mảng để tuần cũ nhất ở đầu
            $result = array_reverse($result);
            
        } else { // month
            // Lấy 6 tháng gần nhất
            for ($i = 0; $i < 6; $i++) {
                $month = Carbon::now()->subMonths($i);
                $monthStart = Carbon::create($month->year, $month->month, 1);
                $monthEnd = $monthStart->copy()->endOfMonth();
                
                $query = Contract::whereBetween('start_date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')]);
                
                // Áp dụng phân quyền
                if (!$isAdmin) {
                    if ($isManager) {
                        $query->whereHas('room.house', function($q) use ($user) {
                            $q->where('manager_id', $user->id);
                        });
                    } else {
                        // Tenant chỉ xem được hợp đồng của mình
                        $query->whereHas('users', function($q) use ($user) {
                            $q->where('users.id', $user->id);
                        });
                    }
                }
                
                // Áp dụng filter house_id nếu có
                if (isset($filters['house_id'])) {
                    $query->whereHas('room', function($q) use ($filters) {
                        $q->where('house_id', $filters['house_id']);
                    });
                }
                
                $count = $query->count();
                
                $result[] = [
                    'period' => 'Tháng ' . $month->month . '/' . $month->year,
                    'count' => $count
                ];
            }
            
            // Đảo ngược mảng để tháng cũ nhất ở đầu
            $result = array_reverse($result);
        }
        
        return $result;
    }

    /**
     * Lấy thống kê khách thuê theo nhà và theo độ tuổi
     *
     * @param User $user Người dùng hiện tại
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getTenantStats(User $user, array $filters = []): array
    {
        $isAdmin = $user->role->code === 'admin';
        $isManager = $user->role->code === 'manager';
        
        // Lấy danh sách nhà theo quyền
        $housesQuery = House::query();
        
        if (!$isAdmin) {
            if ($isManager) {
                $housesQuery->where('manager_id', $user->id);
            } else {
                // Tenant chỉ xem được nhà mình đang ở
                $housesQuery->whereHas('rooms.contracts.users', function($q) use ($user) {
                    $q->where('users.id', $user->id)
                      ->where('contracts.status', 'active');
                });
            }
        }
        
        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            $housesQuery->where('id', $filters['house_id']);
        }
        
        $houses = $housesQuery->get(['id', 'name']);
        
        $result = [
            'by_house' => [],
            'by_age' => [
                'under_18' => 0,
                '18_to_25' => 0,
                '26_to_35' => 0,
                '36_to_45' => 0,
                '46_to_55' => 0,
                'over_55' => 0,
                'unknown' => 0
            ]
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
     * Lấy doanh thu theo tháng trong năm
     *
     * @param User $user Người dùng hiện tại
     * @param int $year Năm
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getMonthlyRevenueStats(User $user, int $year = null, array $filters = []): array
    {
        $isAdmin = $user->role->code === 'admin';
        $isManager = $user->role->code === 'manager';
        
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
        ->where('payment_status', 'paid')
        ->groupBy('month');
        
        // Áp dụng phân quyền
        if (!$isAdmin) {
            if ($isManager) {
                $revenueQuery->whereHas('room.house', function($q) use ($user) {
                    $q->where('manager_id', $user->id);
                });
            } else {
                // Tenant chỉ xem được hóa đơn của phòng mình
                $revenueQuery->whereHas('room.contracts.users', function($q) use ($user) {
                    $q->where('users.id', $user->id);
                });
            }
        }
        
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
        
        // Format kết quả
        return [
            'year' => $year,
            'monthly_data' => array_values($result)
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
        $isAdmin = $user->role->code === 'admin';
        $isManager = $user->role->code === 'manager';
        
        // Query cơ bản
        $query = Invoice::select('payment_status', DB::raw('count(*) as total'))
                     ->groupBy('payment_status');
        
        // Áp dụng filter khoảng thời gian nếu có
        if (isset($filters['year'])) {
            $query->where('year', $filters['year']);
        }
        
        if (isset($filters['month'])) {
            $query->where('month', $filters['month']);
        }
        
        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        
        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }
        
        // Áp dụng phân quyền
        if (!$isAdmin) {
            if ($isManager) {
                $query->whereHas('room.house', function($q) use ($user) {
                    $q->where('manager_id', $user->id);
                });
            } else {
                // Tenant chỉ xem được hóa đơn của phòng mình
                $query->whereHas('room.contracts.users', function($q) use ($user) {
                    $q->where('users.id', $user->id);
                });
            }
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
            'paid' => 0,
            'pending' => 0,
            'cancelled' => 0,
            'partial' => 0
        ];
        
        foreach ($stats as $stat) {
            if (isset($result[$stat->payment_status])) {
                $result[$stat->payment_status] = $stat->total;
            }
        }
        
        // Thêm tổng số
        $result['total'] = array_sum($result);
        
        return $result;
    }

    /**
     * Lấy danh sách hóa đơn có giá trị lớn đang chờ thanh toán
     *
     * @param User $user Người dùng hiện tại
     * @param int $limit Giới hạn kết quả
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getLargestPendingInvoices(User $user, int $limit = 10, array $filters = []): array
    {
        $isAdmin = $user->role->code === 'admin';
        $isManager = $user->role->code === 'manager';
        
        // Query cơ bản
        $query = Invoice::where('payment_status', 'pending')
                     ->orderBy('total_amount', 'desc')
                     ->with(['room', 'room.house', 'items']);
        
        // Áp dụng phân quyền
        if (!$isAdmin) {
            if ($isManager) {
                $query->whereHas('room.house', function($q) use ($user) {
                    $q->where('manager_id', $user->id);
                });
            } else {
                // Tenant chỉ xem được hóa đơn của phòng mình
                $query->whereHas('room.contracts.users', function($q) use ($user) {
                    $q->where('users.id', $user->id);
                });
            }
        }
        
        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            $query->whereHas('room', function($q) use ($filters) {
                $q->where('house_id', $filters['house_id']);
            });
        }
        
        // Lấy dữ liệu với limit
        $invoices = $query->limit($limit)->get();
        
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
                'created_at' => $invoice->created_at->format('Y-m-d H:i:s')
            ];
        }
        
        return $result;
    }

    /**
     * Lấy thống kê sử dụng dịch vụ theo tháng
     *
     * @param User $user Người dùng hiện tại
     * @param array $serviceTypes Loại dịch vụ
     * @param int $year Năm
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getMonthlyServiceUsageStats(User $user, array $serviceTypes, int $year = null, array $filters = []): array
    {
        $isAdmin = $user->role->code === 'admin';
        $isManager = $user->role->code === 'manager';
        
        // Nếu không có năm thì lấy năm hiện tại
        if ($year === null) {
            $year = Carbon::now()->year;
        }
        
        // Mảng kết quả - 12 tháng
        $result = [];
        foreach ($serviceTypes as $serviceType) {
            $result[$serviceType] = [];
            for ($month = 1; $month <= 12; $month++) {
                $result[$serviceType][$month] = [
                    'month' => $month,
                    'usage' => 0,
                    'revenue' => 0
                ];
            }
        }
        
        // Query sử dụng dịch vụ theo tháng
        $usageQuery = ServiceUsage::select(
            'service_usage.month', 
            'services.name as service_name',
            DB::raw('SUM(service_usage.usage_value) as total_usage'),
            DB::raw('SUM(service_usage.usage_value * service_usage.price_used) as total_revenue')
        )
        ->join('room_services', 'service_usage.room_service_id', '=', 'room_services.id')
        ->join('services', 'room_services.service_id', '=', 'services.id')
        ->where('service_usage.year', $year)
        ->whereIn('services.name', $serviceTypes)
        ->groupBy('service_usage.month', 'services.name');
        
        // Áp dụng phân quyền
        if (!$isAdmin) {
            if ($isManager) {
                $usageQuery->join('rooms', 'room_services.room_id', '=', 'rooms.id')
                          ->join('houses', 'rooms.house_id', '=', 'houses.id')
                          ->where('houses.manager_id', $user->id);
            } else {
                // Tenant chỉ xem được sử dụng dịch vụ của phòng mình
                $usageQuery->join('rooms', 'room_services.room_id', '=', 'rooms.id')
                          ->whereHas('rooms.contracts.users', function($q) use ($user) {
                              $q->where('users.id', $user->id);
                          });
            }
        }
        
        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            $usageQuery->join('rooms', 'room_services.room_id', '=', 'rooms.id')
                       ->where('rooms.house_id', $filters['house_id']);
        }
        
        // Thực hiện query
        $usageData = $usageQuery->get();
        
        // Cập nhật dữ liệu vào mảng kết quả
        foreach ($usageData as $data) {
            if (isset($result[$data->service_name][$data->month])) {
                $result[$data->service_name][$data->month]['usage'] = (float) $data->total_usage;
                $result[$data->service_name][$data->month]['revenue'] = (int) $data->total_revenue;
            }
        }
        
        // Format kết quả
        $formattedResult = [];
        foreach ($serviceTypes as $serviceType) {
            $formattedResult[$serviceType] = [
                'service_name' => $serviceType,
                'monthly_data' => array_values($result[$serviceType])
            ];
        }
        
        return [
            'year' => $year,
            'services' => $formattedResult
        ];
    }

    /**
     * Lấy so sánh doanh thu từ dịch vụ theo loại
     *
     * @param User $user Người dùng hiện tại
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getServiceRevenueComparison(User $user, array $filters = []): array
    {
        $isAdmin = $user->role->code === 'admin';
        $isManager = $user->role->code === 'manager';
        
        // Năm và tháng mặc định
        $year = $filters['year'] ?? Carbon::now()->year;
        $month = $filters['month'] ?? Carbon::now()->month;
        
        // Query doanh thu dịch vụ không cố định (service_usage)
        $variableQuery = ServiceUsage::select(DB::raw('SUM(usage_value * price_used) as total_revenue'))
                                  ->where('year', $year);
        
        // Query doanh thu dịch vụ cố định (invoice_items với is_fixed)
        $fixedQuery = InvoiceItem::select(DB::raw('SUM(amount) as total_revenue'))
                                ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                                ->where('invoices.year', $year)
                                ->where('invoice_items.type', 'custom')
                                ->whereExists(function ($query) {
                                    $query->select(DB::raw(1))
                                          ->from('room_services')
                                          ->whereRaw('invoice_items.description LIKE CONCAT("%", room_services.description, "%")')
                                          ->where('room_services.is_fixed', 1);
                                });
        
        // Áp dụng filter tháng nếu có
        if (isset($filters['month'])) {
            $variableQuery->where('month', $filters['month']);
            $fixedQuery->where('invoices.month', $filters['month']);
        }
        
        // Áp dụng phân quyền
        if (!$isAdmin) {
            if ($isManager) {
                // Dịch vụ không cố định
                $variableQuery->join('room_services', 'service_usage.room_service_id', '=', 'room_services.id')
                            ->join('rooms', 'room_services.room_id', '=', 'rooms.id')
                            ->join('houses', 'rooms.house_id', '=', 'houses.id')
                            ->where('houses.manager_id', $user->id);
                
                // Dịch vụ cố định
                $fixedQuery->join('rooms', 'invoices.room_id', '=', 'rooms.id')
                          ->join('houses', 'rooms.house_id', '=', 'houses.id')
                          ->where('houses.manager_id', $user->id);
            } else {
                // Tenant chỉ xem được doanh thu dịch vụ của phòng mình
                // Dịch vụ không cố định
                $variableQuery->join('room_services', 'service_usage.room_service_id', '=', 'room_services.id')
                            ->join('rooms', 'room_services.room_id', '=', 'rooms.id')
                            ->join('contracts', 'rooms.id', '=', 'contracts.room_id')
                            ->join('contract_users', 'contracts.id', '=', 'contract_users.contract_id')
                            ->where('contract_users.user_id', $user->id)
                            ->where('contracts.status', 'active');
                
                // Dịch vụ cố định
                $fixedQuery->join('rooms', 'invoices.room_id', '=', 'rooms.id')
                          ->join('contracts', 'rooms.id', '=', 'contracts.room_id')
                          ->join('contract_users', 'contracts.id', '=', 'contract_users.contract_id')
                          ->where('contract_users.user_id', $user->id)
                          ->where('contracts.status', 'active');
            }
        }
        
        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            // Dịch vụ không cố định
            $variableQuery->join('room_services', 'service_usage.room_service_id', '=', 'room_services.id')
                         ->join('rooms', 'room_services.room_id', '=', 'rooms.id')
                         ->where('rooms.house_id', $filters['house_id']);
            
            // Dịch vụ cố định
            $fixedQuery->join('rooms', 'invoices.room_id', '=', 'rooms.id')
                       ->where('rooms.house_id', $filters['house_id']);
        }
        
        // Thực hiện query
        $variableRevenue = $variableQuery->first()->total_revenue ?? 0;
        $fixedRevenue = $fixedQuery->first()->total_revenue ?? 0;
        
        // Format kết quả
        $total = $variableRevenue + $fixedRevenue;
        $variablePercent = $total > 0 ? round(($variableRevenue / $total) * 100, 2) : 0;
        $fixedPercent = $total > 0 ? round(($fixedRevenue / $total) * 100, 2) : 0;
        
        return [
            'year' => $year,
            'month' => $month ?? null,
            'variable_revenue' => $variableRevenue,
            'variable_percent' => $variablePercent,
            'fixed_revenue' => $fixedRevenue,
            'fixed_percent' => $fixedPercent,
            'total_revenue' => $total
        ];
    }

    /**
     * Lấy thống kê thiết bị trong kho theo nhà
     *
     * @param User $user Người dùng hiện tại
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getEquipmentInventoryStats(User $user, array $filters = []): array
    {
        $isAdmin = $user->role->code === 'admin';
        $isManager = $user->role->code === 'manager';
        
        // Query cơ bản
        $query = EquipmentStorage::select(
                    'equipment_storage.house_id',
                    'houses.name as house_name',
                    'equipments.id as equipment_id',
                    'equipments.name as equipment_name',
                    DB::raw('SUM(equipment_storage.quantity) as total_quantity')
                )
                ->join('houses', 'equipment_storage.house_id', '=', 'houses.id')
                ->join('equipments', 'equipment_storage.equipment_id', '=', 'equipments.id')
                ->groupBy('equipment_storage.house_id', 'houses.name', 'equipments.id', 'equipments.name');
        
        // Áp dụng phân quyền
        if (!$isAdmin) {
            if ($isManager) {
                $query->where('houses.manager_id', $user->id);
            } else {
                // Tenant chỉ xem được kho của nhà mình đang ở
                $query->whereHas('house.rooms.contracts.users', function($q) use ($user) {
                    $q->where('users.id', $user->id)
                      ->where('contracts.status', 'active');
                });
            }
        }
        
        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            $query->where('equipment_storage.house_id', $filters['house_id']);
        }
        
        // Thực hiện query
        $inventoryData = $query->get();
        
        // Format kết quả
        $result = [];
        foreach ($inventoryData as $data) {
            if (!isset($result[$data->house_id])) {
                $result[$data->house_id] = [
                    'house_id' => $data->house_id,
                    'house_name' => $data->house_name,
                    'equipments' => []
                ];
            }
            
            $result[$data->house_id]['equipments'][] = [
                'equipment_id' => $data->equipment_id,
                'equipment_name' => $data->equipment_name,
                'quantity' => $data->total_quantity
            ];
        }
        
        return array_values($result);
    }

    /**
     * Lấy danh sách phòng thiếu thiết bị so với định mức
     *
     * @param User $user Người dùng hiện tại
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getRoomsMissingEquipment(User $user, array $filters = []): array
    {
        $isAdmin = $user->role->code === 'admin';
        $isManager = $user->role->code === 'manager';
        
        // Lấy danh sách phòng
        $roomsQuery = Room::with(['house', 'equipments', 'equipments.equipment']);
        
        // Áp dụng phân quyền
        if (!$isAdmin) {
            if ($isManager) {
                $roomsQuery->whereHas('house', function($q) use ($user) {
                    $q->where('manager_id', $user->id);
                });
            } else {
                // Tenant chỉ xem được phòng mình đang ở
                $roomsQuery->whereHas('contracts.users', function($q) use ($user) {
                    $q->where('users.id', $user->id)
                      ->where('contracts.status', 'active');
                });
            }
        }
        
        // Áp dụng filter house_id nếu có
        if (isset($filters['house_id'])) {
            $roomsQuery->where('house_id', $filters['house_id']);
        }
        
        // Thực hiện query
        $rooms = $roomsQuery->get();
        
        // Lấy danh sách thiết bị tiêu chuẩn cho mỗi phòng
        // Giả định: Có bảng cài đặt với định mức thiết bị cần thiết cho mỗi phòng
        // Nếu không có, có thể dựa vào thiết lập cứng hoặc cấu hình hệ thống
        $standardEquipments = [
            'Giường' => 1,
            'Bàn học' => 1,
            'Tủ quần áo' => 1,
            'Điều hòa' => 1
        ];
        
        // Format kết quả
        $result = [];
        foreach ($rooms as $room) {
            $missingEquipments = [];
            
            // Thiết bị hiện có trong phòng
            $existingEquipments = [];
            foreach ($room->equipments as $roomEquipment) {
                $equipmentName = $roomEquipment->equipment->name;
                $existingEquipments[$equipmentName] = $roomEquipment->quantity;
            }
            
            // Kiểm tra thiếu thiết bị
            foreach ($standardEquipments as $equipment => $requiredQuantity) {
                $currentQuantity = $existingEquipments[$equipment] ?? 0;
                if ($currentQuantity < $requiredQuantity) {
                    $missingEquipments[] = [
                        'equipment_name' => $equipment,
                        'required_quantity' => $requiredQuantity,
                        'current_quantity' => $currentQuantity,
                        'missing_quantity' => $requiredQuantity - $currentQuantity
                    ];
                }
            }
            
            // Chỉ thêm vào kết quả nếu có thiết bị thiếu
            if (count($missingEquipments) > 0) {
                $result[] = [
                    'room_id' => $room->id,
                    'room_number' => $room->room_number,
                    'house_id' => $room->house_id,
                    'house_name' => $room->house->name,
                    'missing_equipments' => $missingEquipments
                ];
            }
        }
        
        return $result;
    }

    /**
     * Xuất báo cáo tùy chỉnh
     *
     * @param User $user Người dùng hiện tại
     * @param Request $request
     * @return mixed
     */
    public function generateCustomReport(User $user, Request $request): mixed
    {
        $reportType = $request->input('report_type');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $houseId = $request->input('house_id');
        $format = $request->input('format', 'json'); // json, csv, pdf
        
        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'house_id' => $houseId
        ];
        
        // Dữ liệu báo cáo dựa trên loại
        $reportData = [];
        
        switch ($reportType) {
            case 'overview':
                $reportData = $this->getOverviewStats($user, $filters);
                break;
                
            case 'occupancy':
                $reportData = $this->getRoomOccupancyStats($user, $filters);
                break;
                
            case 'revenue':
                $year = $request->input('year', Carbon::now()->year);
                $reportData = $this->getMonthlyRevenueStats($user, $year, $filters);
                break;
                
            case 'contracts':
                $days = $request->input('days', 30);
                $reportData = $this->getExpiringContracts($user, $days, $filters);
                break;
                
            case 'services':
                $year = $request->input('year', Carbon::now()->year);
                $serviceTypes = $request->input('service_types', ['Điện', 'Nước']);
                $reportData = $this->getMonthlyServiceUsageStats($user, $serviceTypes, $year, $filters);
                break;
                
            case 'equipment':
                $reportData = $this->getEquipmentInventoryStats($user, $filters);
                break;
                
            case 'invoices':
                $reportData = $this->getInvoiceStatusStats($user, $filters);
                break;
                
            default:
                throw new \Exception('Loại báo cáo không hợp lệ');
        }
        
        // Xử lý dữ liệu dựa trên định dạng xuất
        if ($format === 'json') {
            return $reportData;
        } else if ($format === 'csv') {
            // Trong thực tế, sẽ có xử lý export CSV ở đây
            return $reportData; // Placeholder
        } else if ($format === 'pdf') {
            // Trong thực tế, sẽ có xử lý export PDF ở đây
            return $reportData; // Placeholder
        }
        
        return $reportData;
    }
} 