<?php

namespace App\Repositories\Interfaces;

use App\Models\User;

interface DashboardRepositoryInterface
{
    /**
     * Lấy số lượng nhà
     *
     * @param User $user
     * @param array $filters
     * @return int
     */
    public function getHousesCount(User $user, array $filters = []): int;

    /**
     * Lấy số lượng phòng
     *
     * @param User $user
     * @param array $filters
     * @return int
     */
    public function getRoomsCount(User $user, array $filters = []): int;

    /**
     * Lấy số lượng người thuê
     *
     * @param User $user
     * @param array $filters
     * @return int
     */
    public function getTenantsCount(User $user, array $filters = []): int;

    /**
     * Lấy số lượng hợp đồng
     *
     * @param User $user
     * @param array $filters
     * @return int
     */
    public function getContractsCount(User $user, array $filters = []): int;

    /**
     * Lấy thông tin hệ thống
     *
     * @return array
     */
    public function getSystemInfo(): array;
} 