<?php

namespace App\Observers;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Room;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ContractObserver
{
    protected $notificationService;
    
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the Contract "created" event.
     */
    public function created(Contract $contract): void
    {
        // Nếu hợp đồng có trạng thái active, cập nhật trạng thái phòng thành used
        if ($contract->status === 'active') {
            Room::where('id', $contract->room_id)->update(['status' => 'used']);
            
            // Tạo hóa đơn mới cho hợp đồng
            $this->createInitialInvoice($contract);
            
            // Gửi thông báo cho người thuê về hợp đồng mới
            $this->notifyContractCreated($contract);
        }
    }

    /**
     * Handle the Contract "updated" event.
     */
    public function updated(Contract $contract): void
    {
        // Nếu trạng thái hợp đồng thay đổi thành active
        if ($contract->status === 'active' && $contract->getOriginal('status') !== 'active') {
            Room::where('id', $contract->room_id)->update(['status' => 'used']);
        }
        // Nếu trạng thái hợp đồng đổi từ active sang trạng thái khác
        else if ($contract->getOriginal('status') === 'active' && $contract->status !== 'active') {
            // Kiểm tra nếu không còn hợp đồng active nào cho phòng này
            $activeContractsCount = Contract::where('room_id', $contract->room_id)
                ->where('id', '!=', $contract->id)
                ->where('status', 'active')
                ->count();
            
            if ($activeContractsCount === 0) {
                Room::where('id', $contract->room_id)->update(['status' => 'available']);
            }
        }
    }

    /**
     * Handle the Contract "deleted" event.
     */
    public function deleted(Contract $contract): void
    {
        // Nếu hợp đồng là active
        if ($contract->status === 'active') {
            // Kiểm tra xem còn hợp đồng active nào cho phòng này không
            $activeContractsCount = Contract::where('room_id', $contract->room_id)
                ->where('id', '!=', $contract->id)
                ->where('status', 'active')
                ->count();
            
            // Nếu không còn hợp đồng active nào, cập nhật phòng thành available
            if ($activeContractsCount === 0) {
                Room::where('id', $contract->room_id)->update(['status' => 'available']);
            }
        }
    }
    
    /**
     * Tạo hóa đơn ban đầu cho hợp đồng mới
     */
    private function createInitialInvoice(Contract $contract): void
    {
        try {
            $invoice = Invoice::create([
                'room_id' => $contract->room_id,
                'invoice_type' => 'custom',
                'total_amount' => $contract->monthly_price + $contract->deposit_amount,
                'month' => Carbon::now()->month,
                'year' => Carbon::now()->year,
                'description' => 'Hóa đơn ban đầu cho hợp đồng mới #' . $contract->id,
                'created_by' => $contract->created_by,
                'payment_status' => 'pending',
                'payment_method_id' => 1,
            ]);
            
            // Tạo item cho tiền cọc
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'amount' => $contract->deposit_amount,
                'description' => 'Tiền đặt cọc cho hợp đồng #' . $contract->id,
            ]);
            
            // Tạo item cho tiền thuê tháng đầu
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'amount' => $contract->monthly_price,
                'description' => 'Tiền thuê tháng đầu tiên (' . 
                    Carbon::parse($contract->start_date)->format('d/m/Y') . ' - ' . 
                    Carbon::parse($contract->start_date)->addMonth()->subDay()->format('d/m/Y') . ')',
            ]);

            // Tạo thông báo cho người thuê về hóa đơn mới
            $this->notifyTenantAboutInvoice($invoice);
            
            Log::info('Đã tạo hóa đơn ban đầu ID#' . $invoice->id . ' cho hợp đồng ID#' . $contract->id);
        } catch (\Exception $e) {
            Log::error('Lỗi khi tạo hóa đơn ban đầu cho hợp đồng ID#' . $contract->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Gửi thông báo cho người thuê về hợp đồng mới
     */
    private function notifyContractCreated(Contract $contract): void
    {
        try {
            // Lấy thông tin phòng và nhà
            $room = Room::with('house')->find($contract->room_id);
            if (!$room || !$room->house) {
                Log::warning("Không tìm thấy thông tin phòng hoặc nhà cho hợp đồng ID: {$contract->id}");
                return;
            }
            
            // Nội dung thông báo cho người thuê
            $tenantContent = "Hợp đồng thuê phòng {$room->room_number} tại {$room->house->name} đã được tạo.";
            
            // Sử dụng notifyRoomTenants để gửi thông báo cho tất cả người thuê trong phòng
            $this->notificationService->notifyRoomTenants(
                $contract->room_id,
                'contract',
                $tenantContent,
                "/contracts/{$contract->id}",
                false
            );
                
            // Gửi thông báo cho manager của nhà
            if ($room->house->manager_id) {
                $managerContent = "Hợp đồng mới đã được tạo cho phòng {$room->room_number} tại {$room->house->name}.";
                
                $this->notificationService->create(
                    $room->house->manager_id,
                    'contract',
                    $managerContent,
                    "/contracts/{$contract->id}",
                    false
                );
            }
            
        } catch (\Exception $e) {
            Log::error("Lỗi khi gửi thông báo hợp đồng mới ID: {$contract->id}: " . $e->getMessage());
        }
    }

    /**
     * Gửi thông báo cho người thuê về hóa đơn mới
     */
    private function notifyTenantAboutInvoice(Invoice $invoice): void
    {
        // Lấy thông tin phòng và nhà
        $room = Room::with('house')->find($invoice->room_id);
        if (!$room || !$room->house) {
            Log::warning("Không tìm thấy thông tin phòng hoặc nhà cho hóa đơn ID: {$invoice->id}");
            return;
        }

        // Nội dung thông báo cho người thuê
        $tenantContent = "Hóa đơn mới đã được tạo cho phòng {$room->room_number} tại {$room->house->name}.";

        // Sử dụng notifyRoomTenants để gửi thông báo cho tất cả người thuê trong phòng
        $this->notificationService->notifyRoomTenants(
            $invoice->room_id,
            'invoice',
            $tenantContent,
            "/invoices/{$invoice->id}",
            false
        );
    }
}