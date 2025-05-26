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
     * Lấy danh sách hóa đơn có giá trị lớn đang chờ thanh toán
     *
     * @param User $user Người dùng hiện tại
     * @param int $limit Giới hạn kết quả
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getLargestPendingInvoices(User $user, int $limit = 10, array $filters = []): array;

    /**
     * Lấy thống kê sử dụng dịch vụ theo tháng
     *
     * @param User $user Người dùng hiện tại
     * @param array $serviceTypes Loại dịch vụ
     * @param int $year Năm
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getMonthlyServiceUsageStats(User $user, array $serviceTypes, int $year = null, array $filters = []): array;

    /**
     * Lấy so sánh doanh thu từ dịch vụ theo loại
     *
     * @param User $user Người dùng hiện tại
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getServiceRevenueComparison(User $user, array $filters = []): array;

    /**
     * Lấy thống kê thiết bị trong kho theo nhà
     *
     * @param User $user Người dùng hiện tại
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getEquipmentInventoryStats(User $user, array $filters = []): array;

    /**
     * Lấy danh sách phòng thiếu thiết bị so với định mức
     *
     * @param User $user Người dùng hiện tại
     * @param array $filters Các bộ lọc
     * @return array
     */
    public function getRoomsMissingEquipment(User $user, array $filters = []): array;

    /**
     * Xuất báo cáo tùy chỉnh
     *
     * @param User $user Người dùng hiện tại
     * @param Request $request
     * @return mixed
     */
    public function generateCustomReport(User $user, Request $request): mixed;
} 