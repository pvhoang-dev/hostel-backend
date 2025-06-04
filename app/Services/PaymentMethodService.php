<?php

namespace App\Services;

use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\Repositories\Interfaces\PaymentMethodRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PaymentMethodService
{
    protected $paymentMethodRepository;

    public function __construct(PaymentMethodRepositoryInterface $paymentMethodRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    /**
     * Lấy danh sách phương thức thanh toán
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getAllPaymentMethods(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Người dùng không được xác thực', 401);
        }

        $isAdmin = $user->role->code === 'admin';
        
        // Xử lý các bộ lọc từ request
        $filters = [
            'name' => $request->name ?? null,
            'status' => $request->status ?? null,
            'id' => $request->id ?? null,
            'created_from' => $request->created_from ?? null,
            'created_to' => $request->created_to ?? null,
            'updated_from' => $request->updated_from ?? null,
            'updated_to' => $request->updated_to ?? null,
            'deleted' => $request->deleted ?? null,
        ];

        // Xác định các mối quan hệ cần eager loading
        $with = [];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('transactions', $includes) && $isAdmin) {
                $with[] = 'transactions';
            }
        }

        // Xác định thông tin sắp xếp và phân trang
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_dir', 'desc');
        $perPage = $request->get('per_page', 15);

        // Nếu không phải admin, chỉ hiển thị phương thức thanh toán đang active
        if (!$isAdmin) {
            $filters['status'] = 'active';
            $sortField = 'name';
            $sortDirection = 'asc';
        }

        $paymentMethods = $this->paymentMethodRepository->getAllWithFilters(
            $filters, 
            $with, 
            $sortField, 
            $sortDirection, 
            $perPage, 
            $isAdmin
        );

        // Chuyển đổi kết quả thành resource và trả về
        return PaymentMethodResource::collection($paymentMethods)->response()->getData(true);
    }

    /**
     * Tạo phương thức thanh toán mới
     *
     * @param Request $request
     * @return PaymentMethod
     * @throws \Exception
     */
    public function createPaymentMethod(Request $request)
    {
        $user = Auth::user();
        
        // Chỉ admin mới có thể tạo phương thức thanh toán
        if (!$user || $user->role->code !== 'admin') {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        $input = $request->all();
        $validator = Validator::make($input, [
            'name' => 'required|string|max:255|unique:payment_methods,name',
            'status' => 'required|string|in:active,inactive',
        ], [
            'name.required' => 'Tên phương thức thanh toán là bắt buộc.',
            'name.string' => 'Tên phương thức thanh toán phải là một chuỗi.',
            'name.max' => 'Tên phương thức thanh toán không được vượt quá 255 ký tự.',
            'name.unique' => 'Tên phương thức thanh toán đã tồn tại.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'status.string' => 'Trạng thái phải là một chuỗi.',
            'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận "active" hoặc "inactive".',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $this->paymentMethodRepository->create($input);
    }

    /**
     * Lấy thông tin chi tiết phương thức thanh toán
     *
     * @param int $id
     * @return PaymentMethod
     * @throws \Exception
     */
    public function getPaymentMethodById(int $id)
    {
        $user = Auth::user();
        
        // Chỉ admin và manager có quyền xem chi tiết phương thức thanh toán
        if (!$user || !in_array($user->role->code, ['admin', 'manager'])) {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        $paymentMethod = $this->paymentMethodRepository->getById($id);
        
        if (is_null($paymentMethod)) {
            throw new \Exception('Không tìm thấy phương thức thanh toán.', 404);
        }

        // Đối với người dùng không phải admin, chỉ được xem phương thức thanh toán có trạng thái active
        if ($user->role->code !== 'admin' && $paymentMethod->status !== 'active') {
            throw new \Exception('Bạn không có quyền xem phương thức thanh toán này', 403);
        }

        return $paymentMethod;
    }

    /**
     * Cập nhật phương thức thanh toán
     *
     * @param Request $request
     * @param int $id
     * @return PaymentMethod
     * @throws \Exception
     */
    public function updatePaymentMethod(Request $request, int $id)
    {
        $user = Auth::user();
        
        // Chỉ admin mới có thể cập nhật phương thức thanh toán
        if (!$user || $user->role->code !== 'admin') {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        if (in_array($id, [1, 2])) {
            throw new \Exception('Không thể cập nhật phương thức thanh toán mặc định của hệ thống.', 422);
        }

        $paymentMethod = $this->paymentMethodRepository->getById($id);
        
        if (is_null($paymentMethod)) {
            throw new \Exception('Không tìm thấy phương thức thanh toán.', 404);
        }

        $input = $request->all();
        $validator = Validator::make($input, [
            'name' => 'sometimes|required|string|max:255|unique:payment_methods,name,' . $id,
            'status' => 'sometimes|required|string|in:active,inactive',
        ], [
            'name.required' => 'Tên phương thức thanh toán là bắt buộc.',
            'name.string' => 'Tên phương thức thanh toán phải là một chuỗi.',
            'name.max' => 'Tên phương thức thanh toán không được vượt quá 255 ký tự.',
            'name.unique' => 'Tên phương thức thanh toán đã tồn tại.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'status.string' => 'Trạng thái phải là một chuỗi.',
            'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận "active" hoặc "inactive".',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Chỉ cập nhật các trường được gửi lên
        $data = $request->only(['name', 'status']);
        
        return $this->paymentMethodRepository->update($paymentMethod, $data);
    }

    /**
     * Xóa phương thức thanh toán
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function deletePaymentMethod(int $id)
    {
        $user = Auth::user();
        
        // Chỉ admin mới có thể xóa phương thức thanh toán
        if (!$user || $user->role->code !== 'admin') {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        // Kiểm tra nếu là payment method mặc định
        if (in_array($id, [1, 2])) {
            throw new \Exception('Không thể xóa phương thức thanh toán mặc định của hệ thống.', 422);
        }

        $paymentMethod = $this->paymentMethodRepository->getById($id);
        
        if (is_null($paymentMethod)) {
            throw new \Exception('Phương thức thanh toán không tồn tại.', 404);
        }

        // Kiểm tra xem phương thức thanh toán có đang được sử dụng không
        if ($this->paymentMethodRepository->isInUse($paymentMethod)) {
            throw new \Exception('Không thể xóa vì đang có giao dịch sử dụng phương thức thanh toán này.', 422);
        }

        return $this->paymentMethodRepository->delete($paymentMethod);
    }
} 