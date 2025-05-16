<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PaymentMethodController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(HttpRequest $request): JsonResponse
    {
        $user = Auth::user();

        // Admin can see both active and deleted payment methods
        if ($user->role->code === 'admin') {
            $query = PaymentMethod::withTrashed();
        } else {
            $query = PaymentMethod::query()->where('status', 'active');
        }

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // Apply filters (only for admin)
        if ($user->role->code === 'admin') {
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('id')) {
                $query->where('id', $request->id);
            }

            // Date range filters
            if ($request->has('created_from')) {
                $query->where('created_at', '>=', $request->created_from);
            }

            if ($request->has('created_to')) {
                $query->where('created_at', '<=', $request->created_to);
            }

            if ($request->has('updated_from')) {
                $query->where('updated_at', '>=', $request->updated_from);
            }

            if ($request->has('updated_to')) {
                $query->where('updated_at', '<=', $request->updated_to);
            }

            if ($request->has('deleted')) {
                if ($request->deleted === '1') {
                    $query->whereNotNull('deleted_at');
                } else if ($request->deleted === '0') {
                    $query->whereNull('deleted_at');
                }
            }

            // Include relationships if needed
            $with = [];
            if ($request->has('include')) {
                $includes = explode(',', $request->include);
                if (in_array('transactions', $includes)) $with[] = 'transactions';
            }

            // Sorting
            $sortField = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_dir', 'desc');
            $allowedSortFields = ['id', 'name', 'status', 'created_at', 'updated_at', 'deleted_at'];

            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
            } else {
                $query->orderBy('created_at', 'desc');
            }
        } else {
            // Default ordering for non-admin users
            $query->orderBy('name', 'asc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $paymentMethods = $query->with($with ?? [])->paginate($perPage);

        return $this->sendResponse(
            PaymentMethodResource::collection($paymentMethods)->response()->getData(true),
            'Payment methods retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(HttpRequest $httpRequest): JsonResponse
    {
        $user = Auth::user();

        // Only admins can create payment methods
        if ($user->role->code !== 'admin') {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền thực hiện thao tác này'], 403);
        }

        $input = $httpRequest->all();
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
            return $this->sendError('Lỗi khi tạo phương thức thanh toán.', $validator->errors());
        }

        $paymentMethod = PaymentMethod::create($input);

        return $this->sendResponse(
            new PaymentMethodResource($paymentMethod),
            'Phương thức thanh toán được tạo thành công.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();

        // Only admin and manager can view payment method details
        if (!$user || !in_array($user->role->code, ['admin', 'manager'])) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền thực hiện thao tác này'], 403);
        }

        $paymentMethod = PaymentMethod::find($id);

        if (is_null($paymentMethod)) {
            return $this->sendError('Không tìm thấy phương thức thanh toán.');
        }

        // For non-admin users, only allow viewing active payment methods
        if ($user->role->code !== 'admin' && $paymentMethod->status !== 'active') {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền xem phương thức thanh toán này'], 403);
        }

        return $this->sendResponse(
            new PaymentMethodResource($paymentMethod),
            'Payment method retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(HttpRequest $httpRequest, string $id): JsonResponse
    {
        $user = Auth::user();

        // Only admins can update payment methods
        if ($user->role->code !== 'admin') {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền thực hiện thao tác này'], 403);
        }

        $paymentMethod = PaymentMethod::find($id);

        if (is_null($paymentMethod)) {
            return $this->sendError('Không tìm thấy phương thức thanh toán.');
        }

        $input = $httpRequest->all();
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
            return $this->sendError('Lỗi khi cập nhật phương thức thanh toán.', $validator->errors());
        }

        $paymentMethod->fill($httpRequest->only(['name', 'status']));
        $paymentMethod->save();

        return $this->sendResponse(
            new PaymentMethodResource($paymentMethod),
            'Phương thức thanh toán cập nhật thành công.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();

        // Only admins can delete payment methods
        if ($user->role->code !== 'admin') {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền thực hiện thao tác này'], 403);
        }

        $paymentMethod = PaymentMethod::find($id);

        if (is_null($paymentMethod)) {
            return $this->sendError('Phương thức thanh toán không tồn tại.');
        }

        // Check if there are any transactions using this payment method
        if ($paymentMethod->transactions()->count() > 0) {
            return $this->sendError(
                'Không thể xóa vì đang có giao dịch sử dụng phương thức thanh toán này.',
                [],
                422
            );
        }

        $paymentMethod->delete();

        return $this->sendResponse([], 'Phương thức thanh toán được xóa thành công.');
    }
}
