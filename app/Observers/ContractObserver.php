<?php

namespace App\Observers;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ContractObserver
{
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
            
            Log::info('Đã tạo hóa đơn ban đầu ID#' . $invoice->id . ' cho hợp đồng ID#' . $contract->id);
        } catch (\Exception $e) {
            Log::error('Lỗi khi tạo hóa đơn ban đầu cho hợp đồng ID#' . $contract->id . ': ' . $e->getMessage());
        }
    }
}