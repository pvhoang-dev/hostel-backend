<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\Services\PaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends BaseController
{
    protected $paymentMethodService;

    public function __construct(PaymentMethodService $paymentMethodService)
    {
        $this->paymentMethodService = $paymentMethodService;
    }

    /**
     * Display a listing of the resource.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $result = $this->paymentMethodService->getAllPaymentMethods($request);
            return $this->sendResponse($result, 'Lấy danh sách phương thức thanh toán thành công');
        } catch (\Exception $e) {
            return $this->sendError(
                $e->getMessage(), 
                [], 
                $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $paymentMethod = $this->paymentMethodService->createPaymentMethod($request);
            return $this->sendResponse(
                new PaymentMethodResource($paymentMethod), 
                'Phương thức thanh toán được tạo thành công'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Lỗi khi tạo phương thức thanh toán', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError(
                $e->getMessage(), 
                [], 
                $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Display the specified resource.
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $paymentMethod = $this->paymentMethodService->getPaymentMethodById($id);
            return $this->sendResponse(
                new PaymentMethodResource($paymentMethod), 
                'Lấy thông tin phương thức thanh toán thành công'
            );
        } catch (\Exception $e) {
            return $this->sendError(
                $e->getMessage(), 
                [], 
                $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Update the specified resource in storage.
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $paymentMethod = $this->paymentMethodService->updatePaymentMethod($request, $id);
            return $this->sendResponse(
                new PaymentMethodResource($paymentMethod), 
                'Phương thức thanh toán được cập nhật thành công'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Lỗi khi cập nhật phương thức thanh toán', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError(
                $e->getMessage(), 
                [], 
                $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->paymentMethodService->deletePaymentMethod($id);
            if ($result) {
                return $this->sendResponse([], 'Phương thức thanh toán được xóa thành công');
            }
            return $this->sendError('Không thể xóa phương thức thanh toán', [], 500);
        } catch (\Exception $e) {
            return $this->sendError(
                $e->getMessage(), 
                [], 
                $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }
}
