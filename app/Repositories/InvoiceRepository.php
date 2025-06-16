<?php

namespace App\Repositories;

use App\Models\House;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ServiceUsage;
use App\Repositories\Interfaces\InvoiceRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use PayOS\PayOS;

class InvoiceRepository implements InvoiceRepositoryInterface
{
    /**
     * Lấy danh sách hóa đơn với phân trang
     */
    public function getAllInvoices(Request $request): LengthAwarePaginator
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

        // Apply additional filters
        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        if ($request->has('house_id')) {
            $query->whereHas('room', function ($q) use ($request) {
                $q->where('house_id', $request->house_id);
            });
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
        return $query->with($with)->paginate($perPage);
    }

    /**
     * Lấy hóa đơn theo ID
     */
    public function getInvoiceById(string $id)
    {
        return Invoice::with(['room.house', 'items.service_usage.roomService.service', 'paymentMethod', 'creator', 'updater'])->find($id);
    }

    /**
     * Tìm hóa đơn đã tồn tại cho phòng/tháng/năm và loại
     * 
     * @param string $roomId
     * @param int $month
     * @param int $year
     * @param string $invoiceType
     * @return Invoice|null
     */
    public function findExistingInvoice(string $roomId, int $month, int $year, string $invoiceType)
    {
        return Invoice::where('room_id', $roomId)
            ->where('month', $month)
            ->where('year', $year)
            ->where('invoice_type', $invoiceType)
            ->first();
    }

    /**
     * Lấy danh sách ID hóa đơn của tenant
     * 
     * @param int $userId ID của người thuê
     * @return array
     */
    public function getTenantInvoiceIds(int $userId)
    {
        return Invoice::whereHas('room.contracts.users', function ($query) use ($userId) {
            $query->where('users.id', $userId);
        })->pluck('id')->toArray();
    }

    /**
     * Tạo hóa đơn mới
     */
    public function createInvoice(array $data)
    {
        $userId = Auth::id();
        $totalAmount = array_sum(array_column($data['items'], 'amount'));

        return Invoice::create([
            'room_id' => $data['room_id'],
            'invoice_type' => $data['invoice_type'],
            'total_amount' => $totalAmount,
            'month' => $data['month'],
            'year' => $data['year'],
            'description' => $data['description'] ?? null,
            'created_by' => $userId,
            'updated_by' => $userId,
            'payment_method_id' => $data['payment_method_id'] ?? null,
            'payment_status' => $data['payment_status'] ?? 'pending',
            'payment_date' => $data['payment_date'] ?? null,
            'transaction_code' => $data['transaction_code'] ?? null,
        ]);
    }

    /**
     * Tạo invoice items
     */
    public function createInvoiceItems(Invoice $invoice, array $items)
    {
        foreach ($items as $itemData) {
            $invoice->items()->create([
                'source_type' => $itemData['source_type'],
                'service_usage_id' => $itemData['service_usage_id'] ?? null,
                'amount' => $itemData['amount'],
                'description' => $itemData['description'] ?? null,
            ]);
        }

        return $invoice;
    }

    /**
     * Cập nhật hóa đơn
     */
    public function updateInvoice(string $id, array $data)
    {
        $invoice = Invoice::with(['room.house', 'items'])->find($id);
        if (!$invoice) {
            return null;
        }

        DB::beginTransaction();
        try {
            // Cập nhật thông tin hóa đơn
            if (isset($data['description'])) {
                $invoice->description = $data['description'];
            }
            
            // Cập nhật thông tin thanh toán nếu có
            if (isset($data['payment_method_id'])) {
                $invoice->payment_method_id = $data['payment_method_id'];
            }
            
            if (isset($data['payment_status'])) {
                $invoice->payment_status = $data['payment_status'];
                
                // Nếu trạng thái không phải là completed, đặt ngày thanh toán thành null
                if ($data['payment_status'] !== 'completed') {
                    $invoice->payment_date = null;
                    $invoice->transaction_code = null;
                } else if (isset($data['payment_date'])) {
                    // Nếu trạng thái là completed và có ngày thanh toán, cập nhật ngày
                    $invoice->payment_date = $data['payment_date'];
                }
            } else if (isset($data['payment_date']) && $invoice->payment_status === 'completed') {
                // Chỉ cập nhật ngày thanh toán nếu trạng thái hiện tại là completed
                $invoice->payment_date = $data['payment_date'];
            }
            
            if (isset($data['transaction_code'])) {
                $invoice->transaction_code = $data['transaction_code'];
            }
            
            $invoice->updated_by = Auth::id();
            $invoice->save();
            
            // Trích xuất tất cả ID của các item trong request
            $requestItemIds = [];
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
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
                    $manualItem->delete();
                }
            }
            
            // Nếu có items gửi lên, xử lý từng item
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $index => $itemData) {
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
                        
                        $newItem->save();
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
                        }
                    }
                }
            }
            
            // Xử lý xóa các service_usage_id được chỉ định
            if (isset($data['deleted_service_usage_ids']) && is_array($data['deleted_service_usage_ids']) && !empty($data['deleted_service_usage_ids'])) {
                foreach ($data['deleted_service_usage_ids'] as $index => $usageId) {
                    $normalizedId = is_array($usageId) && isset($usageId['id']) ? $usageId['id'] : $usageId;
                    
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
            
            // Nếu không còn item nào, xóa hóa đơn
            if ($itemCount === 0) {
                $invoice->delete();
                DB::commit();
                return ['deleted' => true];
            }
            
            // Tải lại invoice với tất cả quan hệ
            $refreshedInvoice = Invoice::with(['room.house', 'items.service_usage.roomService.service', 'updater'])->find($invoice->id);
            
            DB::commit();
            return $refreshedInvoice;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Xóa hóa đơn
     */
    public function deleteInvoice(string $id)
    {
        $invoice = Invoice::with(['room.house'])->find($id);
        if (!$invoice) {
            return false;
        }

        try {
            DB::beginTransaction();
            $invoice->delete();
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cập nhật trạng thái thanh toán của hóa đơn
     */
    public function updatePaymentStatus(string $id, array $data)
    {
        $invoice = Invoice::with(['room.house'])->find($id);
        if (!$invoice) {
            return null;
        }

        // Cập nhật thông tin thanh toán
        $invoice->payment_method_id = $data['payment_method_id'];
        $invoice->payment_status = $data['payment_status'];
        $invoice->payment_date = $data['payment_date'];
        
        // Tạo mã giao dịch nếu chưa có
        if (!$invoice->transaction_code && !isset($data['transaction_code'])) {
            $invoice->transaction_code = 'INV-' . substr(uniqid(), -8) . '-' . time();
        } elseif (isset($data['transaction_code'])) {
            $invoice->transaction_code = $data['transaction_code'];
        }

        $invoice->updated_by = Auth::id();
        $invoice->save();
        
        return $invoice->load(['room.house', 'items', 'paymentMethod', 'creator', 'updater']);
    }

    /**
     * Kiểm tra người dùng có quyền truy cập hóa đơn không
     */
    public function canAccessInvoice($user, $invoice): bool
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
     * Kiểm tra người dùng có quyền quản lý hóa đơn không
     */
    public function canManageInvoice($user, $invoice): bool
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
     * Tạo thanh toán Payos
     */
    public function createPayosPayment(array $data)
    {
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

        foreach ($data['invoice_ids'] as $invoiceId) {
            DB::table('invoices')
                ->where('id', $invoiceId)
                ->update([
                    'transaction_code' => $systemOrderCode
                ]);
        }
        
        // URL trả về sau khi thanh toán
        $returnUrl = config('app.frontend_url') . '/invoice-payment?success=true&orderCode=' . $orderCode . '&invoice_ids=' . implode(',', $data['invoice_ids']);
        $cancelUrl = config('app.frontend_url') . '/invoice-payment?cancel=true&orderCode=' . $orderCode;
        
        // Tạo đơn hàng
        $order = [
            'amount' => $data['amount'],
            'description' => 'Thanh toán HĐ',
            'orderCode' => $orderCode,
            'returnUrl' => $returnUrl,
            'cancelUrl' => $cancelUrl
        ];
        
        // Khởi tạo SDK PayOS với cấu hình
        $payos = new PayOS(
            payos_config('client_id'),
            payos_config('api_key'),
            payos_config('checksum_key')
        );
        
        // Tạo payment link từ PayOS
        $paymentLink = $payos->createPaymentLink($order);
        
        if (!$paymentLink) {
            throw new \Exception('Không thể tạo liên kết thanh toán. Vui lòng nhấn lại');
        }
        
        return [
            'checkoutUrl' => $paymentLink['checkoutUrl'],
            'orderCode' => $orderCode,
            'systemOrderCode' => $systemOrderCode,
            'qrCode' => $paymentLink['qrCode'] ?? null
        ];
    }

    /**
     * Xác thực thanh toán từ Payos
     */
    public function verifyPayosPayment(array $data)
    {
        $orderCode = $data['orderCode'] ?? null;
        $cancel = $data['cancel'] ?? false;
        
        // Validate orderCode presence
        if (!$orderCode) {
            return [
                'status' => 'FAILED',
                'message' => 'Thiếu mã đơn hàng (orderCode)',
            ];
        }
        
        $transactionCode = 'INV-' . $orderCode;
        
        // Xử lý khi người dùng hủy thanh toán
        if ($cancel) {
            return [
                'status' => 'CANCELLED',
                'message' => 'Thanh toán đã bị hủy',
                'orderCode' => $orderCode
            ];
        }
        
        // Thay vì tin tưởng tham số từ client, kiểm tra trực tiếp với server Payos
        try {
            // Khởi tạo SDK PayOS với cấu hình
            $payos = new PayOS(
                payos_config('client_id'),
                payos_config('api_key'),
                payos_config('checksum_key')
            );
            
            // Lấy thông tin thanh toán trực tiếp từ Payos
            $paymentInfo = $payos->getPaymentLinkInformation($orderCode);
                        
            // Kiểm tra kết quả trả về từ PayOS có hợp lệ không
            if (!isset($paymentInfo['status'])) {
                return [
                    'status' => 'FAILED',
                    'message' => 'Không thể xác thực thông tin thanh toán từ PayOS',
                    'orderCode' => $orderCode
                ];
            }
            
            // Kiểm tra trạng thái thanh toán thực tế từ Payos
            $isPaid = $paymentInfo['status'] === 'PAID';
            
            // Nếu chưa thanh toán, trả về lỗi
            if (!$isPaid) {
                return [
                    'status' => 'FAILED',
                    'message' => 'Chưa thanh toán hoặc thanh toán không thành công',
                    'orderCode' => $orderCode
                ];
            }
            
            // Tìm tất cả invoices liên quan dựa trên transaction_code
            $invoices = Invoice::where('transaction_code', $transactionCode)
                               ->where('payment_status', '!=', 'completed')
                               ->get();
            
            if ($invoices->isEmpty()) {
                return [
                    'status' => 'SUCCESS',
                    'message' => 'Không tìm thấy hóa đơn cần cập nhật',
                    'orderCode' => $orderCode
                ];
            }
            
            // Bắt đầu transaction để đảm bảo tính nhất quán dữ liệu
            DB::beginTransaction();
            
            // Cập nhật trạng thái các hóa đơn
            $updatedInvoiceIds = [];
            $roomId = null;

            foreach ($invoices as $invoice) {
                // Lưu lại room_id để thông báo
                $roomId = $invoice->room_id;
                
                // Cập nhật thông tin
                $invoice->payment_status = 'completed';
                $invoice->payment_date = now();
                $invoice->save();
                
                $updatedInvoiceIds[] = $invoice->id;
            }
            
            DB::commit();

            return [
                'status' => 'SUCCESS',
                'message' => 'Thanh toán thành công',
                'invoices' => $updatedInvoiceIds,
                'orderCode' => $orderCode,
                'transactionCode' => $transactionCode,
                'new_completed_invoices' => $updatedInvoiceIds,
                'room_id' => $roomId
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PayOS payment verification error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'orderCode' => $orderCode
            ]);
            
            return [
                'status' => 'FAILED',
                'message' => 'Lỗi khi xác thực thanh toán: ' . $e->getMessage(),
                'orderCode' => $orderCode
            ];
        }
    }
} 