<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\InvoiceResource;
use App\Models\House;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Room;
use App\Models\ServiceUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PayOS\PayOS;

class InvoiceController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Invoice::query();

        // Apply role-based filters
        if ($user->role->code === 'tenant') {
            // Tenants can only see invoices for rooms they occupy
            $query->whereHas('room.contracts.users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        } elseif ($user->role->code === 'manager') {
            // Managers can see invoices for houses they manage
            $managedHouseIds = House::where('manager_id', $user->id)->pluck('id');
            $query->whereHas('room', function ($q) use ($managedHouseIds) {
                $q->whereIn('house_id', $managedHouseIds);
            });
        }
        // Admins can see all invoices, so no filter needed

        // Apply additional filters
        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        if ($request->has('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        if ($request->has('invoice_type')) {
            $query->where('invoice_type', $request->invoice_type);
        }

        if ($request->has('month')) {
            $query->where('month', $request->month);
        }

        if ($request->has('year')) {
            $query->where('year', $request->year);
        }

        // Filter by user IDs
        if ($request->has('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        if ($request->has('updated_by')) {
            $query->where('updated_by', $request->updated_by);
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

        if ($request->has('min_amount')) {
            $query->where('total_amount', '>=', $request->min_amount);
        }

        if ($request->has('max_amount')) {
            $query->where('total_amount', '<=', $request->max_amount);
        }

        if ($request->has('payment_method_id')) {
            $query->where('payment_method_id', $request->payment_method_id);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Include relationships
        $with = [];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('room.house', $includes)) $with[] = 'room.house';
            if (in_array('items', $includes)) $with[] = 'items';
            if (in_array('creator', $includes)) $with[] = 'creator';
            if (in_array('updater', $includes)) $with[] = 'updater';
            if (in_array('paymentMethod', $includes)) $with[] = 'paymentMethod';
        }

        // Sorting
        $sortField = $request->get('sort_by', 'year');
        $sortDirection = $request->get('sort_dir', 'desc');
        $allowedSortFields = ['id', 'room_id', 'invoice_type', 'total_amount', 'month', 'year', 'created_at', 'updated_at', 'created_by', 'updated_by', 'payment_method_id', 'payment_date'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('year', 'desc')
                ->orderBy('month', 'desc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $invoices = $query->with($with)->paginate($perPage);

        return $this->sendResponse(
            InvoiceResource::collection($invoices)->response()->getData(true),
            'Invoices retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
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
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        // Check authorization
        $room = Room::with('house')->find($input['room_id']);
        if (!$room) {
            return $this->sendError('Room not found.');
        }

        // Only managers of the house or admins can create invoices
        if ($user->role->code === 'tenant') {
            return $this->sendError('Unauthorized', ['error' => 'Tenants cannot create invoices'], 403);
        } elseif ($user->role->code === 'manager' && $room->house->manager_id !== $user->id) {
            return $this->sendError('Unauthorized', ['error' => 'You can only create invoices for rooms in houses you manage'], 403);
        }

        // Chỉ kiểm tra nếu đang tạo hóa đơn loại service_usage
        if ($input['invoice_type'] === 'service_usage') {
            // Kiểm tra xem đã có hóa đơn service_usage nào cho phòng/tháng/năm này chưa
            $existingInvoice = Invoice::where('room_id', $input['room_id'])
                ->where('month', $input['month'])
                ->where('year', $input['year'])
                ->where('invoice_type', 'service_usage')
                ->first();
            
            if ($existingInvoice) {
                return $this->sendError('Validation Error.', [
                    'invoice' => 'Đã tồn tại hóa đơn cho phòng này trong tháng '.$input['month'].'/'.$input['year'].'.'
                ]);
            }
        }

        // Validate service_usage_id if provided
        if ($input['invoice_type'] === 'service_usage') {
            foreach ($input['items'] as $item) {
                if ($item['source_type'] === 'service_usage' && isset($item['service_usage_id'])) {
                    $serviceUsage = ServiceUsage::with('roomService')->find($item['service_usage_id']);

                    if (!$serviceUsage) {
                        return $this->sendError('Validation Error.', ['items' => 'Service usage not found']);
                    }

                    // Check if service usage belongs to a room service in the specified room
                    if ($serviceUsage->roomService->room_id !== $room->id) {
                        return $this->sendError(
                            'Validation Error.',
                            ['items' => 'Service usage must belong to the specified room']
                        );
                    }

                    // Check if service usage is for the specified month/year
                    if ($serviceUsage->month != $input['month'] || $serviceUsage->year != $input['year']) {
                        return $this->sendError(
                            'Validation Error.',
                            ['items' => 'Service usage month/year must match invoice month/year']
                        );
                    }
                }
            }
        }

        // Calculate total amount
        $totalAmount = array_sum(array_column($input['items'], 'amount'));

        try {
            DB::beginTransaction();

            // Create invoice
            $invoice = Invoice::create([
                'room_id' => $input['room_id'],
                'invoice_type' => $input['invoice_type'],
                'total_amount' => $totalAmount,
                'month' => $input['month'],
                'year' => $input['year'],
                'description' => $input['description'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'payment_method_id' => $input['payment_method_id'] ?? null,
                'payment_status' => $input['payment_status'] ?? 'pending',
                'payment_date' => $input['payment_date'] ?? null,
                'transaction_code' => $input['transaction_code'] ?? null,
            ]);

            // Create invoice items
            foreach ($input['items'] as $itemData) {
                $invoice->items()->create([
                    'source_type' => $itemData['source_type'],
                    'service_usage_id' => $itemData['service_usage_id'] ?? null,
                    'amount' => $itemData['amount'],
                    'description' => $itemData['description'] ?? null,
                ]);
            }

            DB::commit();

            return $this->sendResponse(
                new InvoiceResource($invoice->load(['room', 'items', 'creator'])),
                'Invoice created successfully.'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Creation Error.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();
        $invoice = Invoice::with(['room.house', 'items.service_usage.roomService.service', 'paymentMethod', 'creator', 'updater'])->find($id);

        if (is_null($invoice)) {
            return $this->sendError('Invoice not found.');
        }

        // Authorization check
        if (!$this->canAccessInvoice($user, $invoice)) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to view this invoice'], 403);
        }

        return $this->sendResponse(
            new InvoiceResource($invoice),
            'Invoice retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $input = $request->all();
        
        // Log toàn bộ dữ liệu đầu vào để debug
        \Illuminate\Support\Facades\Log::info('Update invoice input data:', ['input' => $input, 'id' => $id]);
        
        $invoice = Invoice::with(['room.house', 'items'])->find($id);

        if (is_null($invoice)) {
            return $this->sendError('Invoice not found.');
        }

        // Authorization check
        if (!$this->canManageInvoice($user, $invoice)) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to update this invoice'], 403);
        }

        try {
            // Bắt đầu transaction
            DB::beginTransaction();
            
            // Cập nhật thông tin hóa đơn
            if (isset($input['description'])) {
                $invoice->description = $input['description'];
            }
            
            // Cập nhật thông tin thanh toán nếu có
            if (isset($input['payment_method_id'])) {
                $invoice->payment_method_id = $input['payment_method_id'];
            }
            
            if (isset($input['payment_status'])) {
                $invoice->payment_status = $input['payment_status'];
                
                // Nếu trạng thái không phải là completed, đặt ngày thanh toán thành null
                if ($input['payment_status'] !== 'completed') {
                    $invoice->payment_date = null;
                } else if (isset($input['payment_date'])) {
                    // Nếu trạng thái là completed và có ngày thanh toán, cập nhật ngày
                    $invoice->payment_date = $input['payment_date'];
                }
            } else if (isset($input['payment_date']) && $invoice->payment_status === 'completed') {
                // Chỉ cập nhật ngày thanh toán nếu trạng thái hiện tại là completed
                $invoice->payment_date = $input['payment_date'];
            }
            
            if (isset($input['transaction_code'])) {
                $invoice->transaction_code = $input['transaction_code'];
            }
            
            $invoice->updated_by = $user->id;
            $invoice->save();
            
            // Trích xuất tất cả ID của các item trong request
            $requestItemIds = [];
            if (isset($input['items']) && is_array($input['items'])) {
                foreach ($input['items'] as $item) {
                    if (isset($item['id']) && !empty($item['id'])) {
                        $requestItemIds[] = (int)$item['id'];
                    }
                }
            }
            
            // Lấy tất cả item manual hiện có của invoice
            $existingManualItems = InvoiceItem::where('invoice_id', $invoice->id)
                ->where('source_type', 'manual')
                ->get();
            
            // Xóa các item manual không nằm trong danh sách gửi lên
            foreach ($existingManualItems as $manualItem) {
                if (!in_array($manualItem->id, $requestItemIds)) {
                    \Illuminate\Support\Facades\Log::info('Deleting manual item not in request:', ['id' => $manualItem->id]);
                    $manualItem->delete();
                }
            }
            
            // Nếu có items gửi lên, xử lý từng item
            if (isset($input['items']) && is_array($input['items'])) {
                foreach ($input['items'] as $index => $itemData) {
                    // Log thông tin chi tiết về từng item
                    \Illuminate\Support\Facades\Log::info('Processing item:', ['index' => $index, 'item' => $itemData]);
                    
                    // Kiểm tra nếu là item mới (không có ID hoặc ID rỗng)
                    if (!isset($itemData['id']) || empty($itemData['id'])) {
                        $newItem = new InvoiceItem();
                        $newItem->invoice_id = $invoice->id;
                        $newItem->source_type = $itemData['source_type'] ?? 'manual';
                        $newItem->amount = $itemData['amount'] ?? 0;
                        $newItem->description = $itemData['description'] ?? '';
                        $newItem->service_usage_id = isset($itemData['service_usage_id']) && !empty($itemData['service_usage_id']) 
                            ? (is_array($itemData['service_usage_id']) 
                                ? (isset($itemData['service_usage_id']['id']) ? $itemData['service_usage_id']['id'] : null) 
                                : $itemData['service_usage_id']) 
                            : null;
                        
                        $saveResult = $newItem->save();
                        \Illuminate\Support\Facades\Log::info('New item save result:', [
                            'success' => $saveResult, 
                            'item' => $newItem->toArray()
                        ]);
                    } else {
                        // Cập nhật item hiện có
                        $existingItem = InvoiceItem::where('id', $itemData['id'])
                            ->where('invoice_id', $invoice->id)
                            ->first();
                            
                        if ($existingItem) {
                            // Chỉ cập nhật các item manual
                            if ($existingItem->source_type === 'manual') {
                                $existingItem->amount = $itemData['amount'] ?? $existingItem->amount;
                                $existingItem->description = $itemData['description'] ?? $existingItem->description;
                                $existingItem->save();
                            }
                            
                            \Illuminate\Support\Facades\Log::info('Updated existing item:', [
                                'id' => $existingItem->id,
                                'type' => $existingItem->source_type
                            ]);
                        }
                    }
                }
            }
            
            // Xử lý xóa các service_usage_id được chỉ định
            if (isset($input['deleted_service_usage_ids']) && is_array($input['deleted_service_usage_ids']) && !empty($input['deleted_service_usage_ids'])) {
                foreach ($input['deleted_service_usage_ids'] as $index => $usageId) {
                    $normalizedId = is_array($usageId) && isset($usageId['id']) ? $usageId['id'] : $usageId;
                    \Illuminate\Support\Facades\Log::info('Deleting service usage:', ['index' => $index, 'id' => $normalizedId]);
                    
                    if (empty($normalizedId)) continue;
                    
                    // Xóa items liên quan đến service_usage_id này
                    InvoiceItem::where('invoice_id', $invoice->id)
                        ->where('service_usage_id', $normalizedId)
                        ->delete();
                        
                    // Xóa service_usage
                    ServiceUsage::where('id', $normalizedId)->delete();
                }
            }
            
            // Tính lại tổng tiền
            $totalAmount = InvoiceItem::where('invoice_id', $invoice->id)->sum('amount');
            $invoice->total_amount = $totalAmount;
            $invoice->save();
            
            // Đếm số lượng items sau khi cập nhật
            $itemCount = InvoiceItem::where('invoice_id', $invoice->id)->count();
            \Illuminate\Support\Facades\Log::info('Final item count:', ['count' => $itemCount]);
            
            // Nếu không còn item nào, xóa hóa đơn
            if ($itemCount === 0) {
                $invoice->delete();
                
                DB::commit();
                
                return $this->sendResponse(
                    [], 
                    'Invoice đã được xóa do không còn mục nào'
                );
            }
            
            // Tải lại invoice với tất cả quan hệ
            $refreshedInvoice = Invoice::with(['room.house', 'items.service_usage.roomService.service', 'updater'])->find($invoice->id);
            \Illuminate\Support\Facades\Log::info('Refreshed invoice item count:', ['count' => $refreshedInvoice->items->count()]);
            
            // Commit transaction
            DB::commit();
            
            return $this->sendResponse(
                new InvoiceResource($refreshedInvoice),
                'Invoice updated successfully.'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Error updating invoice:', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->sendError('Update Error.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();
        $invoice = Invoice::with(['room.house'])->find($id);

        if (is_null($invoice)) {
            return $this->sendError('Invoice not found.');
        }

        // Authorization check
        if (!$this->canManageInvoice($user, $invoice)) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to delete this invoice'], 403);
        }

        try {
            DB::beginTransaction();
            
            // Sau đó xóa hóa đơn
            $invoice->delete();
            
            DB::commit();
            
            return $this->sendResponse([], 'Invoice and related transactions deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Delete Error.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Check if user can access an invoice
     */
    private function canAccessInvoice($user, $invoice): bool
    {
        // Admins can access all invoices
        if ($user->role->code === 'admin') {
            return true;
        }

        // Tenants can only access invoices for rooms they occupy
        if ($user->role->code === 'tenant') {
            return $invoice->room->contracts()
                ->whereHas('users', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                })
                ->exists();
        }

        // Managers can access invoices for houses they manage
        if ($user->role->code === 'manager') {
            return $user->id === $invoice->room->house->manager_id;
        }

        return false;
    }

    /**
     * Check if user can manage an invoice (update/delete)
     */
    private function canManageInvoice($user, $invoice): bool
    {
        // Admins can manage all invoices
        if ($user->role->code === 'admin') {
            return true;
        }

        // Tenants cannot manage invoices
        if ($user->role->code === 'tenant') {
            return false;
        }

        // Managers can manage invoices for houses they manage
        if ($user->role->code === 'manager') {
            return $user->id === $invoice->room->house->manager_id;
        }

        return false;
    }

    /**
     * Cập nhật trạng thái thanh toán của hóa đơn
     */
    public function updatePaymentStatus(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $invoice = Invoice::with(['room.house'])->find($id);
        
        if (is_null($invoice)) {
            return $this->sendError('Invoice not found.');
        }
        
        // Kiểm tra quyền hạn
        if (!$this->canManageInvoice($user, $invoice)) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to update this invoice'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|exists:payment_methods,id',
            'payment_status' => 'required|in:pending,completed,failed,refunded',
            'payment_date' => $request->payment_status === 'completed' ? 'required|date' : 'nullable|date',
            'transaction_code' => 'sometimes|string|max:255|unique:invoices,transaction_code,' . $invoice->id,
        ]);
        
        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }
        
        // Cập nhật thông tin thanh toán
        $invoice->payment_method_id = $request->payment_method_id;
        $invoice->payment_status = $request->payment_status;
        $invoice->payment_date = $request->payment_date;
        
        // Tạo mã giao dịch nếu chưa có
        if (!$invoice->transaction_code && !$request->has('transaction_code')) {
            $invoice->transaction_code = 'INV-' . substr(uniqid(), -8) . '-' . time();
        } elseif ($request->has('transaction_code')) {
            $invoice->transaction_code = $request->transaction_code;
        }
        
        $invoice->updated_by = $user->id;
        $invoice->save();
        
        return $this->sendResponse(
            new InvoiceResource($invoice->load(['room.house', 'items', 'paymentMethod', 'creator', 'updater'])),
            'Invoice payment status updated successfully.'
        );
    }

    /**
     * Tạo thanh toán qua cổng Payos
     */
    public function createPayosPayment(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'invoice_ids' => 'required|array',
            'invoice_ids.*' => 'exists:invoices,id',
            'amount' => 'required|numeric|min:1000',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $invoiceIds = $request->invoice_ids;
        $amount = $request->amount;
        $description = $request->description ?? 'Thanh toán HĐ';

        // Kiểm tra quyền truy cập
        if ($user->role->code === 'tenant') {
            // Tenant chỉ được thanh toán hóa đơn của chính họ
            $tenantInvoiceIds = Invoice::whereHas('room', function ($query) use ($user) {
                $query->whereHas('contracts', function ($q) use ($user) {
                    $q->where('status', 'active')
                      ->whereHas('users', function ($q2) use ($user) {
                          $q2->where('users.id', $user->id);
                      });
                });
            })->pluck('id')->toArray();

            // Kiểm tra xem tất cả các invoice_ids có thuộc về tenant không
            foreach ($invoiceIds as $invoiceId) {
                if (!in_array($invoiceId, $tenantInvoiceIds)) {
                    return $this->sendError('Unauthorized. You can only pay your own invoices.', [], 403);
                }
            }
        }

        try {
            // Tạo mã đơn hàng duy nhất là một số dương
            $orderCodeNum = time() . rand(100, 999);
            
            // Đảm bảo không vượt quá giới hạn số nguyên an toàn của JavaScript
            if ($orderCodeNum > 9007199254740991) {
                $orderCodeNum = substr($orderCodeNum, 0, 15);
            }
            
            // Chuyển thành số nguyên
            $orderCode = (int)$orderCodeNum;
            
            // Lưu lại orderCode gốc để sử dụng trong hệ thống
            $systemOrderCode = 'INV-' . $orderCode;
            
            // URL trả về sau khi thanh toán
            $returnUrl = config('app.frontend_url') . '/invoice-payment?success=true&orderCode=' . $orderCode . '&invoice_ids=' . implode(',', $invoiceIds);
            $cancelUrl = config('app.frontend_url') . '/invoice-payment?cancel=true&orderCode=' . $orderCode;
            
            // Tạo đơn hàng
            $order = [
                'amount' => $amount,
                'description' => $description,
                'orderCode' => $orderCode,
                'returnUrl' => $returnUrl,
                'cancelUrl' => $cancelUrl
            ];
            
            // Khởi tạo SDK PayOS
            $payos = new PayOS(
                config('services.payos.client_id'),
                config('services.payos.api_key'),
                config('services.payos.checksum_key')
            );
            
            // Tạo payment link thật từ PayOS
            $paymentLink = $payos->createPaymentLink($order);
            
            if (!$paymentLink) {
                throw new \Exception('Không thể tạo liên kết thanh toán');
            }
            
            // Trả về thông tin thanh toán
            return $this->sendResponse(
                [
                    'checkoutUrl' => $paymentLink['checkoutUrl'],
                    'orderCode' => $orderCode,
                    'systemOrderCode' => $systemOrderCode,
                    'qrCode' => $paymentLink['qrCode'] ?? null
                ],
                'Đã tạo liên kết thanh toán thành công'
            );
        } catch (\Exception $e) {
            return $this->sendError('Không thể tạo liên kết thanh toán.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Xác thực trạng thái thanh toán từ Payos
     */
    public function verifyPayosPayment(Request $request): JsonResponse
    {
        try {
            // Lấy thông tin từ request
            $orderCode = $request->input('orderCode');
            $success = $request->input('success');
            $success = ($success === 'true' || $success === true);
            $cancel = $request->input('cancel') === 'true' || $request->input('cancel') === true;
            
            // Xử lý invoice_ids có thể là chuỗi hoặc đã là mảng
            $invoiceIds = [];
            if ($request->has('invoice_ids')) {
                $rawInvoiceIds = $request->input('invoice_ids');
                if (is_array($rawInvoiceIds)) {
                    $invoiceIds = $rawInvoiceIds;
                } else {
                    $invoiceIds = explode(',', $rawInvoiceIds);
                }
                $invoiceIds = array_map('trim', $invoiceIds);
                $invoiceIds = array_filter($invoiceIds);
            }
            
            if (!$orderCode) {
                return $this->sendResponse(
                    [
                        'status' => 'FAILED',
                        'message' => 'Thiếu mã đơn hàng'
                    ],
                    'Không thể xác thực thanh toán'
                );
            }
            
            // Chuyển orderCode về dạng số nếu là chuỗi
            if (!is_numeric($orderCode)) {
                $orderCode = preg_replace('/[^0-9]/', '', $orderCode);
            }
            
            // Tạo mã giao dịch hiển thị
            $transactionCode = 'INV-' . $orderCode;
            
            // Xử lý khi người dùng hủy thanh toán
            if ($cancel) {
                return $this->sendResponse(
                    [
                        'status' => 'CANCELLED',
                        'message' => 'Thanh toán đã bị hủy',
                        'orderCode' => $orderCode
                    ],
                    'Thanh toán đã bị hủy'
                );
            }
            
            // Xử lý khi thanh toán thành công
            if ($success) {
                if (empty($invoiceIds)) {
                    return $this->sendResponse(
                        [
                            'status' => 'FAILED',
                            'message' => 'Thiếu thông tin hóa đơn'
                        ],
                        'Không thể xác thực thanh toán'
                    );
                }
                
                // Cập nhật trạng thái các hóa đơn
                foreach ($invoiceIds as $invoiceId) {
                    $invoice = Invoice::find($invoiceId);
                    if ($invoice) {
                        $invoice->payment_status = 'completed';
                        $invoice->payment_date = now();
                        // Tạo transaction_code duy nhất cho từng hóa đơn
                        $invoice->transaction_code = 'INV-' . $orderCode . '-' . $invoiceId;
                        $invoice->save();
                    }
                }
                
                return $this->sendResponse(
                    [
                        'status' => 'SUCCESS',
                        'message' => 'Thanh toán thành công',
                        'invoices' => $invoiceIds,
                        'orderCode' => $orderCode,
                        'transactionCode' => $transactionCode
                    ],
                    'Thanh toán thành công'
                );
            }
            
            return $this->sendResponse(
                [
                    'status' => 'FAILED',
                    'message' => 'Trạng thái thanh toán không hợp lệ'
                ],
                'Không thể xác thực thanh toán'
            );
            
        } catch (\Exception $e) {
            return $this->sendError('Lỗi khi xác thực thanh toán.', ['error' => $e->getMessage()], 500);
        }
    }
}
