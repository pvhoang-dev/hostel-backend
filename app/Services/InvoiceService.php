<?php

namespace App\Services;

use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Room;
use App\Models\ServiceUsage;
use App\Repositories\Interfaces\InvoiceRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PayOS\PayOS;

class InvoiceService
{
    protected $invoiceRepository;
    protected $notificationService;

    public function __construct(
        InvoiceRepositoryInterface $invoiceRepository,
        NotificationService $notificationService
    ) {
        $this->invoiceRepository = $invoiceRepository;
        $this->notificationService = $notificationService;
    }

    /**
     * Lấy danh sách hóa đơn với các bộ lọc
     */
    public function getAllInvoices($request)
    {
        $invoices = $this->invoiceRepository->getAllInvoices($request);
        return InvoiceResource::collection($invoices)->response()->getData(true);
    }

    /**
     * Tạo hóa đơn mới
     */
    public function createInvoice($request)
    {
        $user = Auth::user();
        $input = $request->all();

        $validator = Validator::make($input, [
            'room_id' => 'required|exists:rooms,id',
            'invoice_type' => 'required|in:custom,service_usage',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'description' => 'sometimes|nullable|string',
            'items' => 'required|array|min:1',
            'items.*.source_type' => 'required|in:manual,service_usage',
            'items.*.service_usage_id' => 'required_if:items.*.source_type,service_usage|exists:service_usage,id|nullable',
            'items.*.amount' => 'required|integer|min:0',
            'items.*.description' => 'sometimes|nullable|string',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'payment_status' => 'nullable|in:pending,completed,failed,refunded',
            'payment_date' => isset($input['payment_status']) && $input['payment_status'] === 'completed' ? 'required|date' : 'nullable|date',
            'transaction_code' => 'nullable|string|max:255|unique:invoices,transaction_code',
        ], [
            'room_id.required' => 'Phòng là bắt buộc',
            'room_id.exists' => 'Phòng không tồn tại',
            'invoice_type.required' => 'Loại hóa đơn là bắt buộc',
            'invoice_type.in' => 'Loại hóa đơn không hợp lệ',
            'month.required' => 'Tháng là bắt buộc',
            'month.integer' => 'Tháng phải là số nguyên',
            'year.required' => 'Năm là bắt buộc',
            'year.integer' => 'Năm phải là số nguyên',
            'year.min' => 'Năm không được nhỏ hơn 2000',
            'year.max' => 'Năm không được lớn hơn 2100',
            'items.required' => 'Chi tiết hóa đơn là bắt buộc',
            'items.array' => 'Chi tiết hóa đơn phải là mảng',
            'items.min' => 'Hóa đơn phải có ít nhất một chi tiết',
            'items.*.source_type.required' => 'Loại nguồn là bắt buộc',
            'items.*.source_type.in' => 'Loại nguồn không hợp lệ',
            'items.*.service_usage_id.required_if' => 'ID sử dụng dịch vụ là bắt buộc khi loại nguồn là service_usage',
            'items.*.service_usage_id.exists' => 'ID sử dụng dịch vụ không tồn tại',
            'items.*.amount.required' => 'Số tiền là bắt buộc',
            'items.*.amount.integer' => 'Số tiền phải là số nguyên',
            'payment_method_id.exists' => 'Phương thức thanh toán không tồn tại',
            'payment_status.in' => 'Trạng thái thanh toán không hợp lệ',
            'payment_date.required' => 'Ngày thanh toán là bắt buộc khi trạng thái là đã thanh toán',
            'payment_date.date' => 'Ngày thanh toán không hợp lệ',
            'transaction_code.unique' => 'Mã giao dịch đã tồn tại',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Check authorization
        $room = Room::with('house')->find($input['room_id']);
        if (!$room) {
            throw new \Exception('Phòng không tồn tại.');
        }

        // Only managers of the house or admins can create invoices
        if ($user->role->code === 'tenant') {
            throw new \Exception('Không được phép tạo hóa đơn.');
        } elseif ($user->role->code === 'manager' && $room->house->manager_id !== $user->id) {
            throw new \Exception('Không được phép tạo hóa đơn cho phòng này.');
        }

        // Chỉ kiểm tra nếu đang tạo hóa đơn loại service_usage
        if ($input['invoice_type'] === 'service_usage') {
            // Kiểm tra xem đã có hóa đơn service_usage nào cho phòng/tháng/năm này chưa
            $existingInvoice = $this->invoiceRepository->findExistingInvoice(
                $input['room_id'],
                $input['month'],
                $input['year'],
                'service_usage'
            );
            
            if ($existingInvoice) {
                throw new \Exception('Đã tồn tại hóa đơn cho phòng này trong tháng '.$input['month'].'/'.$input['year'].'.');
            }
        }

        // Validate service_usage_id if provided
        if ($input['invoice_type'] === 'service_usage') {
            foreach ($input['items'] as $item) {
                if ($item['source_type'] === 'service_usage' && isset($item['service_usage_id'])) {
                    $serviceUsage = ServiceUsage::with('roomService')->find($item['service_usage_id']);

                    if (!$serviceUsage) {
                        throw new \Exception('Service usage không tồn tại.');
                    }

                    // Check if service usage belongs to a room service in the specified room
                    if ($serviceUsage->roomService->room_id !== $room->id) {
                        throw new \Exception('Service usage phải thuộc phòng đã chọn.');
                    }

                    // Check if service usage is for the specified month/year
                    if ($serviceUsage->month != $input['month'] || $serviceUsage->year != $input['year']) {
                        throw new \Exception('Service usage tháng/năm phải khớp với hóa đơn tháng/năm.');
                    }
                }
            }
        }

        try {
            DB::beginTransaction();

            // Create invoice
            $invoice = $this->invoiceRepository->createInvoice($input);

            // Create invoice items
            $this->invoiceRepository->createInvoiceItems($invoice, $input['items']);

            $this->notificationService->notifyRoomTenants(
                $room->id,
                'invoice',
                $input['invoice_type'] === 'service_usage' 
                ? "Hóa đơn dịch vụ cho phòng {$room->room_number} {$invoice->month}/{$invoice->year} đã được tạo." 
                : "Có một hoá đơn mới được tạo cho phòng {$room->room_number}.",
                "/invoices/{$invoice->id}",
                false
            );

            DB::commit();

            return $invoice->load(['room', 'items', 'creator']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Lấy thông tin hóa đơn theo ID
     */
    public function getInvoiceById($id)
    {
        $user = Auth::user();
        $invoice = $this->invoiceRepository->getInvoiceById($id);

        if (is_null($invoice)) {
            throw new \Exception('Hóa đơn không tồn tại.');
        }

        // Authorization check
        if (!$this->invoiceRepository->canAccessInvoice($user, $invoice)) {
            throw new \Exception('Không được phép xem hóa đơn này.');
        }

        return $invoice;
    }

    /**
     * Cập nhật thông tin hóa đơn
     */
    public function updateInvoice($request, $id)
    {
        $user = Auth::user();
        $input = $request->all();
        
        $invoice = $this->invoiceRepository->getInvoiceById($id);

        if (is_null($invoice)) {
            throw new \Exception('Hóa đơn không tồn tại.');
        }

        // Authorization check
        if (!$this->invoiceRepository->canManageInvoice($user, $invoice)) {
            throw new \Exception('Không được phép cập nhật hóa đơn này.');
        }

        try {
            $updatedInvoice = $this->invoiceRepository->updateInvoice($id, $input);
            
            if (isset($updatedInvoice['deleted']) && $updatedInvoice['deleted'] === true) {
                return ['deleted' => true];
            }
            
            return $updatedInvoice;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Xóa hóa đơn
     */
    public function deleteInvoice($id)
    {
        $user = Auth::user();
        $invoice = $this->invoiceRepository->getInvoiceById($id);

        if (is_null($invoice)) {
            throw new \Exception('Hóa đơn không tồn tại.');
        }

        // Authorization check
        if (!$this->invoiceRepository->canManageInvoice($user, $invoice)) {
            throw new \Exception('Bạn không có quyền xóa hóa đơn này.', 403);
        }

        try {
            return $this->invoiceRepository->deleteInvoice($id);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Cập nhật trạng thái thanh toán của hóa đơn
     */
    public function updatePaymentStatus($request, $id)
    {
        $user = Auth::user();
        $invoice = $this->invoiceRepository->getInvoiceById($id);
        
        if (is_null($invoice)) {
            throw new \Exception('Hóa đơn không tồn tại.');
        }
        
        // Kiểm tra quyền hạn
        if (!$this->invoiceRepository->canManageInvoice($user, $invoice)) {
            throw new \Exception('Bạn không có quyền cập nhật hóa đơn này.', 403);
        }
        
        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|exists:payment_methods,id',
            'payment_status' => 'required|in:pending,completed,failed,refunded',
            'payment_date' => $request->payment_status === 'completed' ? 'required|date' : 'nullable|date',
            'transaction_code' => 'sometimes|string|max:255|unique:invoices,transaction_code,' . $invoice->id,
        ], [
            'payment_method_id.required' => 'Phương thức thanh toán là bắt buộc',
            'payment_method_id.exists' => 'Phương thức thanh toán không tồn tại',
            'payment_status.required' => 'Trạng thái thanh toán là bắt buộc',
            'payment_status.in' => 'Trạng thái thanh toán không hợp lệ',
            'payment_date.required' => 'Ngày thanh toán là bắt buộc khi trạng thái là đã thanh toán',
            'payment_date.date' => 'Ngày thanh toán không hợp lệ',
            'transaction_code.unique' => 'Mã giao dịch đã tồn tại',
        ]);
        
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
        
        try {
            $updatedInvoice = $this->invoiceRepository->updatePaymentStatus($id, $request->all());
            
            if ($request->payment_status === 'completed') {
                $this->notificationService->notifyRoomTenants(
                    $invoice->room_id,
                    'invoice',
                    "Hóa đơn cho phòng {$invoice->room->room_number} {$invoice->month}/{$invoice->year} đã được thanh toán.",
                    "/invoices/{$invoice->id}",
                );
            }
            
            return $updatedInvoice;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Tạo thanh toán qua cổng Payos
     */
    public function createPayosPayment($request)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.', 401);
        }

        $validator = Validator::make($request->all(), [
            'invoice_ids' => 'required|array',
            'invoice_ids.*' => 'exists:invoices,id',
        ], [
            'invoice_ids.required' => 'Danh sách hóa đơn là bắt buộc',
            'invoice_ids.array' => 'Danh sách hóa đơn phải là mảng',
            'invoice_ids.*.exists' => 'Một hoặc nhiều hóa đơn không tồn tại',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $invoiceIds = $request->invoice_ids;

        // Kiểm tra quyền truy cập
        if ($user->role->code === 'tenant') {
            // Tenant chỉ được thanh toán hóa đơn của chính họ
            $tenantInvoiceIds = $this->invoiceRepository->getTenantInvoiceIds($user->id);

            // Kiểm tra xem tất cả các invoice_ids có thuộc về tenant không
            foreach ($invoiceIds as $invoiceId) {
                if (!in_array($invoiceId, $tenantInvoiceIds)) {
                    throw new \Exception('Bạn chỉ có thể thanh toán hóa đơn của chính mình.', 403);
                }
            }
        }

        try {
            // Lấy thông tin chi tiết của tất cả hóa đơn để tính toán số tiền chính xác
            $invoices = Invoice::whereIn('id', $invoiceIds)
                ->where('payment_status', '!=', 'completed')
                ->get();
            
            if ($invoices->isEmpty()) {
                throw new \Exception('Không tìm thấy hóa đơn cần thanh toán hoặc hóa đơn đã được thanh toán.', 400);
            }
            
            // Tính tổng tiền từ tất cả hóa đơn
            $totalAmount = $invoices->sum('total_amount');
            
            if ($totalAmount <= 0) {
                throw new \Exception('Tổng số tiền thanh toán phải lớn hơn 0.', 400);
            }

            $invoiceIds = $invoices->pluck('id')->toArray();
            
            // Gọi repository để tạo thanh toán
            return $this->invoiceRepository->createPayosPayment([
                'invoice_ids' => $invoiceIds,
                'amount' => $totalAmount,
                'description' => 'Thanh toán HD'
            ]);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Xác thực trạng thái thanh toán từ Payos
     */
    public function verifyPayosPayment($request)
    {
        try {
            $result = $this->invoiceRepository->verifyPayosPayment($request->all());
            
            // Gửi thông báo nếu có hóa đơn mới được hoàn thành
            if ($result['status'] === 'SUCCESS' && 
                !empty($result['new_completed_invoices']) && 
                isset($result['room_id'])) {
                
                if (count($result['new_completed_invoices']) > 1) {
                    $this->notificationService->notifyRoomTenants(
                        $result['room_id'],
                        'invoice',
                        "Các hóa đơn #" . implode(', ', $result['new_completed_invoices']) . " đã được thanh toán.",
                        "/invoices/{$result['new_completed_invoices'][0]}",
                    );
                } else {
                    $this->notificationService->notifyRoomTenants(
                        $result['room_id'],
                        'invoice',
                        "Hóa đơn #{$result['new_completed_invoices'][0]} đã được thanh toán.",
                        "/invoices/{$result['new_completed_invoices'][0]}",
                    );
                }

                $room = Room::with('house')->find($result['room_id']);
                if ($room && $room->house && $room->house->manager_id) {
                    $this->notificationService->create(
                        $room->house->manager_id,
                        'invoice',
                        "Khách trọ đã thanh toán thành công " . count($result['new_completed_invoices']) . " hóa đơn qua Payos.",
                        "/invoices?room_id={$result['room_id']}&payment_status=completed",
                    );
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Xử lý thanh toán tiền mặt
     * 
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function updateCashPayment($request)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.', 401);
        }

        $validator = Validator::make($request->all(), [
            'invoice_ids' => 'required|array',
            'invoice_ids.*' => 'exists:invoices,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'description' => 'nullable|string',
        ], [
            'invoice_ids.required' => 'Danh sách hóa đơn là bắt buộc',
            'invoice_ids.array' => 'Danh sách hóa đơn phải là mảng',
            'invoice_ids.*.exists' => 'Một hoặc nhiều hóa đơn không tồn tại',
            'payment_method_id.required' => 'Phương thức thanh toán là bắt buộc',
            'payment_method_id.exists' => 'Phương thức thanh toán không tồn tại',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $invoiceIds = $request->invoice_ids;
        $paymentMethodId = $request->payment_method_id;
        $description = $request->description ?? 'Thanh toán tiền mặt';

        // Kiểm tra xem thanh toán có phải là tiền mặt không
        if ($paymentMethodId != 2) { // ID 2 là tiền mặt
            throw new \Exception('Phương thức thanh toán không hợp lệ, chỉ chấp nhận tiền mặt.', 400);
        }

        // Kiểm tra quyền truy cập
        if ($user->role->code === 'tenant') {
            // Tenant chỉ được thanh toán hóa đơn của chính họ
            $tenantInvoiceIds = $this->invoiceRepository->getTenantInvoiceIds($user->id);

            // Kiểm tra xem tất cả các invoice_ids có thuộc về tenant không
            $invalidInvoices = array_diff($invoiceIds, $tenantInvoiceIds);
            if (!empty($invalidInvoices)) {
                throw new \Exception('Bạn chỉ có thể thanh toán hóa đơn của chính mình.', 403);
            }
            
            // Kiểm tra trạng thái của các hóa đơn
            $invoices = Invoice::whereIn('id', $invoiceIds)->get();
            foreach ($invoices as $invoice) {
                if ($invoice->payment_status === 'completed') {
                    throw new \Exception('Hóa đơn #' . $invoice->id . ' đã được thanh toán.', 400);
                }
                
                if ($invoice->payment_status === 'waiting') {
                    throw new \Exception('Hóa đơn #' . $invoice->id . ' đang chờ xác nhận thanh toán.', 400);
                }
            }
        } else {
            throw new \Exception('Chỉ tenant mới có thể yêu cầu thanh toán tiền mặt.', 403);
        }

        try {
            DB::beginTransaction();
            
            $updatedInvoices = [];
            $roomId = null;
            $houseId = null;
            $managerId = null;
            $totalAmount = 0;
            
            // Cập nhật từng hóa đơn
            foreach ($invoiceIds as $invoiceId) {
                $invoice = $this->invoiceRepository->getInvoiceById($invoiceId);
                if (!$invoice) continue;
                
                // Lưu thông tin phòng và nhà để thông báo cho manager
                if (!$roomId) $roomId = $invoice->room_id;
                if (!$houseId && isset($invoice->room->house_id)) $houseId = $invoice->room->house_id;
                if (!$managerId && isset($invoice->room->house->manager_id)) $managerId = $invoice->room->house->manager_id;
                
                // Cập nhật payment_method_id và trạng thái thành waiting
                $invoice->payment_method_id = $paymentMethodId;
                $invoice->transaction_code = 'CASH-' . $invoiceId . '-' . time();
                $invoice->payment_status = 'waiting';
                
                // Thêm mô tả thanh toán
                $invoice->description = $this->appendPaymentDescription($invoice->description, $description);
                
                $invoice->save();
                
                $updatedInvoices[] = $invoice;
                $totalAmount += $invoice->total_amount;
            }
            
            // Gửi thông báo cho manager nếu có
            if ($managerId) {
                $notificationMessage = "Tenant " . $user->name . " đã yêu cầu thanh toán tiền mặt cho " . 
                                      count($updatedInvoices) . " hóa đơn, tổng số tiền " . 
                                      number_format($totalAmount) . " VND. Vui lòng xác nhận khi nhận được tiền.";
                
                $this->notificationService->create(
                    $managerId,
                    'invoice_cash_payment',
                    $notificationMessage,
                    "/invoices?payment_status=waiting&payment_method_id=" . $paymentMethodId,
                    false
                );
            }
            
            // Thông báo cho tenant
            $notificationMessage = "Yêu cầu thanh toán tiền mặt của bạn đã được gửi đến quản lý. Vui lòng chờ xác nhận.";
            $this->notificationService->create(
                $user->id,
                'invoice_cash_payment',
                $notificationMessage,
                "/tenant-payments",
                false
            );
            
            DB::commit();
            
            return [
                'success' => true,
                'invoices' => $updatedInvoices,
                'total_amount' => $totalAmount,
                'notification_sent' => $managerId !== null
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Thêm mô tả thanh toán vào mô tả hóa đơn
     * 
     * @param string|null $currentDescription
     * @param string $paymentDescription
     * @return string
     */
    private function appendPaymentDescription($currentDescription, $paymentDescription)
    {
        $currentDescription = trim($currentDescription ?? '');
        
        // Kiểm tra xem description có chứa dấu gạch ngang không
        if (strpos($currentDescription, ' - ') !== false) {
            // Nếu có, lấy phần trước dấu gạch ngang đầu tiên
            $currentDescription = substr($currentDescription, 0, strpos($currentDescription, ' - '));
        }
        
        // Nếu description rỗng, trả về payment description luôn
        if (empty($currentDescription)) {
            return $paymentDescription;
        }
        
        // Thêm description mới vào cuối
        return $currentDescription . ' - ' . $paymentDescription;
    }

    /**
     * Xử lý webhook từ Payos
     * 
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function handlePayosWebhook($request)
    {
        try {
            $webhookData = $request->all();
            
            // Ghi log dữ liệu webhook để debug
            Log::info('Payos webhook received', ['data' => $webhookData]);
            
            // Khởi tạo SDK PayOS
            $payos = new PayOS(
                payos_config('client_id'),
                payos_config('api_key'),
                payos_config('checksum_key')
            );
            
            // Kiểm tra xem webhook có đủ dữ liệu cần thiết không
            if (!isset($webhookData['signature']) || !isset($webhookData['data'])) {
                Log::error('Payos webhook missing required fields', ['data' => $webhookData]);
                return ['success' => false, 'message' => 'Missing required webhook fields'];
            }
            
            // Xác thực dữ liệu webhook và lấy dữ liệu đã xác thực
            try {
                // Phương thức này xác thực chữ ký webhook bằng checksum_key
                $verifiedData = $payos->verifyPaymentWebhookData($webhookData);

                // Kiểm tra trạng thái thanh toán
                if (!isset($verifiedData['code']) || $verifiedData['code'] !== '00') {
                    return ['success' => false, 'message' => 'Payment not completed'];
                }
                
                // Lấy orderCode để tìm hóa đơn liên quan
                if (!isset($verifiedData['orderCode'])) {
                    Log::error('Payos webhook missing orderCode', ['data' => $verifiedData]);
                    return ['success' => false, 'message' => 'Missing orderCode'];
                }
                
                $orderCode = $verifiedData['orderCode'];
                $transactionCode = 'INV-' . $orderCode;
                
                // Tìm tất cả invoices liên quan dựa trên transaction_code
                $invoices = Invoice::where('transaction_code', $transactionCode)
                                  ->where('payment_status', '!=', 'completed')
                                  ->get();
                
                if ($invoices->isEmpty()) {
                    Log::info('No invoices found');
                    return ['success' => true, 'message' => 'Không tìm thấy hóa đơn cần cập nhật'];
                }
                
                // Cập nhật trạng thái các hóa đơn
                $updatedInvoiceIds = [];
                $roomId = null;
                $userId = null;
                
                DB::beginTransaction();
                
                foreach ($invoices as $invoice) {
                    // Lưu lại room_id để thông báo
                    $roomId = $invoice->room_id;
                    
                    // Lưu userId của tenant (giả định từ contract)
                    if (!$userId && $invoice->room && $invoice->room->contracts) {
                        $tenant = $invoice->room->contracts()
                            ->where('status', 'active')
                            ->first()
                            ->users()
                            ->where('role_id', function ($query) {
                                $query->select('id')->from('roles')->where('code', 'tenant');
                            })
                            ->first();
                            
                        if ($tenant) {
                            $userId = $tenant->id;
                        }
                    }
                    
                    // Cập nhật thông tin
                    $invoice->payment_status = 'completed';
                    $invoice->payment_date = now();
                    $invoice->save();
                    
                    $updatedInvoiceIds[] = $invoice->id;
                }
                
                // Gửi thông báo cho khách trọ nếu có userId
                if ($roomId) {
                    if (count($updatedInvoiceIds) > 1) {
                        $this->notificationService->notifyRoomTenants(
                            $roomId,
                            'invoice',
                            "Các hóa đơn #" . implode(', ', $updatedInvoiceIds) . " đã được thanh toán thành công qua Payos.",
                            "/invoices/{$updatedInvoiceIds[0]}",
                            false
                        );
                    } else {
                        $this->notificationService->notifyRoomTenants(
                            $roomId,
                            'invoice',
                            "Hóa đơn #{$updatedInvoiceIds[0]} đã được thanh toán thành công qua Payos.",
                            "/invoices/{$updatedInvoiceIds[0]}",
                            false
                        );
                    }
                }
                
                // Thông báo cho manager của house
                if ($roomId) {
                    $room = Room::with('house')->find($roomId);
                    if ($room && $room->house && $room->house->manager_id) {
                        $this->notificationService->create(
                            $room->house->manager_id,
                            'invoice',
                            "Khách trọ đã thanh toán thành công " . count($updatedInvoiceIds) . " hóa đơn qua Payos.",
                            "/invoices?room_id={$roomId}&payment_status=completed",
                            false
                        );
                    }
                }
                
                DB::commit();
                
                return [
                    'success' => true,
                    'message' => 'Payment verified and invoices updated',
                    'invoices_updated' => $updatedInvoiceIds,
                    'room_id' => $roomId
                ];
                
            } catch (\Exception $e) {
                Log::error('Payos webhook verification failed: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                    'data' => $webhookData
                ]);
                DB::rollBack();
                return ['success' => false, 'message' => 'Invalid webhook data: ' . $e->getMessage()];
            }
        } catch (\Exception $e) {
            Log::error('Payos webhook processing error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
} 