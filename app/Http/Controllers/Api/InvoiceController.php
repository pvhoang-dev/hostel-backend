<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\InvoiceResource;
use App\Services\InvoiceService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvoiceController extends BaseController
{
    protected $invoiceService;
    protected $notificationService;

    public function __construct(
        InvoiceService $invoiceService,
        NotificationService $notificationService
    ) {
        $this->invoiceService = $invoiceService;
        $this->notificationService = $notificationService;
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
            $result = $this->invoiceService->getAllInvoices($request);
            return $this->sendResponse($result, 'Invoices retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving invoices.', [$e->getMessage()]);
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
            $invoice = $this->invoiceService->createInvoice($request);
            return $this->sendResponse(
                new InvoiceResource($invoice),
                'Hóa đơn đã được tạo thành công.'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            $code = 500;
            $message = $e->getMessage();
            
            // Check if error message contains status code
            if (preg_match('/:(\d+)$/', $message, $matches)) {
                $code = intval($matches[1]);
                $message = preg_replace('/:(\d+)$/', '', $message);
            }
            
            return $this->sendError('Lỗi tạo hóa đơn.', [$message], $code);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $invoice = $this->invoiceService->getInvoiceById($id);
            return $this->sendResponse(
                new InvoiceResource($invoice),
                'Hóa đơn đã được lấy thành công.'
            );
        } catch (\Exception $e) {
            $code = 500;
            $message = $e->getMessage();
            
            // Check if error message contains status code
            if (preg_match('/:(\d+)$/', $message, $matches)) {
                $code = intval($matches[1]);
                $message = preg_replace('/:(\d+)$/', '', $message);
            }
            
            return $this->sendError('Lỗi khi lấy thông tin hóa đơn.', [$message], $code);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $result = $this->invoiceService->updateInvoice($request, $id);
            
            if (isset($result['deleted']) && $result['deleted'] === true) {
                return $this->sendResponse(
                    [], 
                    'Invoice đã được xóa do không còn mục nào'
                );
            }
            
            return $this->sendResponse(
                new InvoiceResource($result),
                'Invoice updated successfully.'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            $code = 500;
            $message = $e->getMessage();
            
            // Check if error message contains status code
            if (preg_match('/:(\d+)$/', $message, $matches)) {
                $code = intval($matches[1]);
                $message = preg_replace('/:(\d+)$/', '', $message);
            }
            
            return $this->sendError('Lỗi cập nhật hóa đơn.', [$message], $code);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->invoiceService->deleteInvoice($id);
            return $this->sendResponse([], 'Hóa đơn và giao dịch liên quan đã được xóa thành công.');
        } catch (\Exception $e) {
            $code = 500;
            $message = $e->getMessage();
            
            // Check if error message contains status code
            if (preg_match('/:(\d+)$/', $message, $matches)) {
                $code = intval($matches[1]);
                $message = preg_replace('/:(\d+)$/', '', $message);
            }
            
            return $this->sendError('Lỗi xóa hóa đơn.', [$message], $code);
        }
    }

    /**
     * Cập nhật trạng thái thanh toán của hóa đơn
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function updatePaymentStatus(Request $request, string $id): JsonResponse
    {
        try {
            $invoice = $this->invoiceService->updatePaymentStatus($request, $id);
            return $this->sendResponse(
                new InvoiceResource($invoice),
                'Trạng thái thanh toán hóa đơn đã được cập nhật thành công.'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            $code = 500;
            $message = $e->getMessage();
            
            // Check if error message contains status code
            if (preg_match('/:(\d+)$/', $message, $matches)) {
                $code = intval($matches[1]);
                $message = preg_replace('/:(\d+)$/', '', $message);
            }
            
            return $this->sendError('Lỗi cập nhật trạng thái thanh toán.', [$message], $code);
        }
    }

    /**
     * Tạo thanh toán qua cổng Payos
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createPayosPayment(Request $request): JsonResponse
    {
        try {
            $result = $this->invoiceService->createPayosPayment($request);
            return $this->sendResponse(
                $result,
                'Đã tạo liên kết thanh toán thành công'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            $code = 500;
            $message = $e->getMessage();
            
            // Check if error message contains status code
            if (preg_match('/:(\d+)$/', $message, $matches)) {
                $code = intval($matches[1]);
                $message = preg_replace('/:(\d+)$/', '', $message);
            }
            
            return $this->sendError('Không thể tạo liên kết thanh toán.', [$message], $code);
        }
    }

    /**
     * Xác thực trạng thái thanh toán từ Payos
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyPayosPayment(Request $request): JsonResponse
    {
        try {
            $result = $this->invoiceService->verifyPayosPayment($request);
            return $this->sendResponse(
                $result,
                $result['status'] === 'SUCCESS' ? 'Thanh toán thành công' : ($result['status'] === 'CANCELLED' ? 'Thanh toán đã bị hủy' : 'Không thể xác thực thanh toán')
            );
        } catch (\Exception $e) {
            $code = 500;
            $message = $e->getMessage();
            
            // Check if error message contains status code
            if (preg_match('/:(\d+)$/', $message, $matches)) {
                $code = intval($matches[1]);
                $message = preg_replace('/:(\d+)$/', '', $message);
            }
            
            return $this->sendError('Lỗi khi xác thực thanh toán.', [$message], $code);
        }
    }

    /**
     * Xử lý thanh toán tiền mặt từ tenant
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateCashPayment(Request $request): JsonResponse
    {
        try {
            $result = $this->invoiceService->updateCashPayment($request);
            return $this->sendResponse(
                $result,
                'Yêu cầu thanh toán tiền mặt đã được ghi nhận và thông báo đã được gửi cho quản lý nhà'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            $code = 500;
            $message = $e->getMessage();
            
            // Check if error message contains status code
            if (preg_match('/:(\d+)$/', $message, $matches)) {
                $code = intval($matches[1]);
                $message = preg_replace('/:(\d+)$/', '', $message);
            }
            
            return $this->sendError('Không thể xử lý thanh toán tiền mặt.', [$message], $code);
        }
    }
}
