<?php

namespace App\Repositories\Interfaces;

use App\Models\PaymentMethod;
use Illuminate\Pagination\LengthAwarePaginator;

interface PaymentMethodRepositoryInterface
{
    /**
     * Lấy danh sách phương thức thanh toán có áp dụng bộ lọc
     *
     * @param array $filters Các bộ lọc
     * @param array $with Các quan hệ cần eager loading
     * @param string $sortField Trường cần sắp xếp
     * @param string $sortDirection Hướng sắp xếp ('asc' hoặc 'desc')
     * @param int $perPage Số lượng kết quả mỗi trang
     * @param bool $withTrashed Có lấy các bản ghi đã xóa mềm không
     * @return LengthAwarePaginator
     */
    public function getAllWithFilters(
        array $filters, 
        array $with = [], 
        string $sortField = 'created_at', 
        string $sortDirection = 'desc', 
        int $perPage = 15,
        bool $withTrashed = false
    ): LengthAwarePaginator;
    
    /**
     * Lấy phương thức thanh toán theo ID
     *
     * @param int $id
     * @return PaymentMethod|null
     */
    public function getById(int $id);
    
    /**
     * Tạo phương thức thanh toán mới
     *
     * @param array $data
     * @return PaymentMethod
     */
    public function create(array $data): PaymentMethod;
    
    /**
     * Cập nhật phương thức thanh toán
     *
     * @param PaymentMethod $paymentMethod
     * @param array $data
     * @return PaymentMethod
     */
    public function update(PaymentMethod $paymentMethod, array $data): PaymentMethod;
    
    /**
     * Xóa phương thức thanh toán
     *
     * @param PaymentMethod $paymentMethod
     * @return bool
     */
    public function delete(PaymentMethod $paymentMethod): bool;

    /**
     * Kiểm tra xem phương thức thanh toán có đang được sử dụng không
     * 
     * @param PaymentMethod $paymentMethod
     * @return bool
     */
    public function isInUse(PaymentMethod $paymentMethod): bool;
} 