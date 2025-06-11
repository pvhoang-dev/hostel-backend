<?php

namespace App\Repositories;

use App\Models\PaymentMethod;
use App\Repositories\Interfaces\PaymentMethodRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    protected $model;

    public function __construct(PaymentMethod $model)
    {
        $this->model = $model;
    }

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
    ): LengthAwarePaginator {
        $query = $withTrashed ? $this->model->withTrashed() : $this->model->query();

        // Áp dụng bộ lọc theo tên
        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        // Áp dụng bộ lọc theo trạng thái
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Áp dụng bộ lọc theo ID
        if (isset($filters['id'])) {
            $query->where('id', $filters['id']);
        }

        // Bộ lọc khoảng thời gian tạo
        if (isset($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }

        if (isset($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to']);
        }

        // Bộ lọc khoảng thời gian cập nhật
        if (isset($filters['updated_from'])) {
            $query->where('updated_at', '>=', $filters['updated_from']);
        }

        if (isset($filters['updated_to'])) {
            $query->where('updated_at', '<=', $filters['updated_to']);
        }

        // Bộ lọc theo trạng thái đã xóa
        if (isset($filters['deleted']) && $withTrashed) {
            if ($filters['deleted'] === '1') {
                $query->whereNotNull('deleted_at');
            } elseif ($filters['deleted'] === '0') {
                $query->whereNull('deleted_at');
            }
        }

        // Sắp xếp
        $allowedSortFields = ['id', 'name', 'status', 'created_at', 'updated_at', 'deleted_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->with($with)->paginate($perPage);
    }

    /**
     * Lấy phương thức thanh toán theo ID
     *
     * @param int $id
     * @return PaymentMethod|null
     */
    public function getById(int $id)
    {
        return $this->model->find($id);
    }

    /**
     * Tạo phương thức thanh toán mới
     *
     * @param array $data
     * @return PaymentMethod
     */
    public function create(array $data): PaymentMethod
    {
        return $this->model->create($data);
    }

    /**
     * Cập nhật phương thức thanh toán
     *
     * @param PaymentMethod $paymentMethod
     * @param array $data
     * @return PaymentMethod
     */
    public function update(PaymentMethod $paymentMethod, array $data): PaymentMethod
    {
        $paymentMethod->fill($data);
        $paymentMethod->save();
        return $paymentMethod;
    }

    /**
     * Xóa phương thức thanh toán
     *
     * @param PaymentMethod $paymentMethod
     * @return bool
     */
    public function delete(PaymentMethod $paymentMethod): bool
    {
        return $paymentMethod->delete();
    }

    /**
     * Kiểm tra xem phương thức thanh toán có đang được sử dụng không
     * 
     * @param PaymentMethod $paymentMethod
     * @return bool
     */
    public function isInUse(PaymentMethod $paymentMethod): bool
    {
        return $paymentMethod->transactions()->count() > 0;
    }
} 