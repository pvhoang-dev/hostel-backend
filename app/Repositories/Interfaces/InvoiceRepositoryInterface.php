<?php

namespace App\Repositories\Interfaces;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface InvoiceRepositoryInterface
{
    /**
     * Lấy danh sách hóa đơn với phân trang
     * 
     * @param Request $request
     * @return LengthAwarePaginator
     */
    public function getAllInvoices(Request $request): LengthAwarePaginator;
    
    /**
     * Lấy hóa đơn theo ID
     * 
     * @param string $id
     * @return Invoice|null
     */
    public function getInvoiceById(string $id);
    
    /**
     * Tìm hóa đơn đã tồn tại cho phòng/tháng/năm và loại
     * 
     * @param string $roomId
     * @param int $month
     * @param int $year
     * @param string $invoiceType
     * @return Invoice|null
     */
    public function findExistingInvoice(string $roomId, int $month, int $year, string $invoiceType);
    
    /**
     * Lấy danh sách ID hóa đơn của tenant
     * 
     * @param int $userId ID của người thuê
     * @return array
     */
    public function getTenantInvoiceIds(int $userId);
    
    /**
     * Tạo hóa đơn mới
     * 
     * @param array $data
     * @return Invoice
     */
    public function createInvoice(array $data);
    
    /**
     * Cập nhật hóa đơn
     * 
     * @param string $id
     * @param array $data
     * @return Invoice
     */
    public function updateInvoice(string $id, array $data);
    
    /**
     * Xóa hóa đơn
     * 
     * @param string $id
     */
    public function deleteInvoice(string $id);
    
    /**
     * Tạo invoice items
     * 
     * @param Invoice $invoice
     * @param array $items
     */
    public function createInvoiceItems(Invoice $invoice, array $items);
    
    /**
     * Cập nhật trạng thái thanh toán của hóa đơn
     * 
     * @param string $id
     * @param array $data
     */
    public function updatePaymentStatus(string $id, array $data);
    
    /**
     * Kiểm tra người dùng có quyền truy cập hóa đơn không
     * 
     * @param User $user
     * @param Invoice $invoice
     * @return bool
     */
    public function canAccessInvoice($user, $invoice): bool;
    
    /**
     * Kiểm tra người dùng có quyền quản lý hóa đơn không
     * 
     * @param User $user
     * @param Invoice $invoice
     * @return bool
     */
    public function canManageInvoice($user, $invoice): bool;
    
    /**
     * Tạo thanh toán Payos
     * 
     * @param array $data
     */
    public function createPayosPayment(array $data);
    
    /**
     * Xác thực thanh toán từ Payos
     * 
     * @param array $data
     */
    public function verifyPayosPayment(array $data);
} 