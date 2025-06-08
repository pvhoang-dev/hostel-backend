<?php

namespace App\Repositories\Interfaces;

use App\Models\User;
use Illuminate\Http\Request;

interface StatisticsRepositoryInterface
{
    /**
     * Lấy thống kê tổng quan
     *
     * @param User $user Người dùng hiện tại
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getOverviewStats(User $user, array $filters = []): array;

    /**
     * Lấy tỷ lệ phòng trống / phòng đã thuê
     *
     * @param User $user Người dùng hiện tại
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getRoomOccupancyStats(User $user, array $filters = []): array;

    /**
     * Lấy doanh thu theo kỳ
     *
     * @param User $user Người dùng hiện tại
     * @param string $period Kỳ (month/year)
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getRevenuePeriodComparison(User $user, string $period = 'month', array $filters = []): array;

    /**
     * Lấy danh sách hợp đồng sắp đáo hạn trong X ngày tới
     *
     * @param User $user Người dùng hiện tại
     * @param int $days Số ngày
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getExpiringContracts(User $user, int $days = 30, array $filters = []): array;

    /**
     * Lấy thống kê hợp đồng mới theo kỳ
     *
     * @param User $user Người dùng hiện tại
     * @param string $period Kỳ (week/month)
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getNewContractsStats(User $user, string $period = 'month', array $filters = []): array;

    /**
     * Lấy thống kê khách thuê theo nhà và theo độ tuổi
     *
     * @param User $user Người dùng hiện tại
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getTenantStats(User $user, array $filters = []): array;

    /**
     * Lấy doanh thu theo tháng trong năm
     *
     * @param User $user Người dùng hiện tại
     * @param int $year Năm
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getMonthlyRevenueStats(User $user, int $year = null, array $filters = []): array;

    /**
     * Lấy thống kê hóa đơn theo trạng thái
     *
     * @param User $user Người dùng hiện tại
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getInvoiceStatusStats(User $user, array $filters = []): array;

    /**
     * Lấy danh sách phòng có số lượng thiết bị ít hơn hoặc bằng giới hạn
     *
     * @param User $user Người dùng hiện tại
     * @param int $limit Giới hạn số lượng thiết bị
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getRoomsWithLimitedEquipment(User $user, int $limit = 2, array $filters = []): array;

    /**
     * Lấy doanh thu theo loại kỳ báo cáo (tháng/quý/năm)
     *
     * @param User $user Người dùng hiện tại
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getRevenueByPeriod(User $user, array $filters = []): array;

    /**
     * Lấy danh sách hóa đơn chưa thanh toán với phân trang
     *
     * @param User $user Người dùng hiện tại
     * @param int $page Trang hiện tại
     * @param int $perPage Số bản ghi mỗi trang
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getUnpaidInvoices(User $user, int $page = 1, int $perPage = 10, array $filters = []): array;
} 