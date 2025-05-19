<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contract;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NotifyContractsExpiring extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:expiring-notify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Thông báo cho người thuê và quản lý về các hợp đồng sắp hết hạn ở các mốc 30, 15 và 7 ngày';

    /**
     * @var NotificationService
     */
    protected $notificationService;

    /**
     * Các mốc thời gian để gửi thông báo (số ngày trước khi hết hạn)
     */
    protected $notificationDays = [30, 15, 7];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Bắt đầu kiểm tra hợp đồng sắp hết hạn...');
        
        try {
            // Lấy ngày hiện tại
            $now = Carbon::now();
            
            // Xử lý với từng mốc thời gian
            foreach ($this->notificationDays as $days) {
                // Tính ngày hết hạn cần kiểm tra
                $targetDate = $now->copy()->addDays($days)->format('Y-m-d');
                
                $this->info("Kiểm tra hợp đồng hết hạn vào ngày: {$targetDate} ({$days} ngày nữa)");
                
                // Lấy tất cả hợp đồng active mà sẽ hết hạn đúng theo mốc thời gian
                $expiringContracts = Contract::where('status', 'active')
                    ->whereDate('end_date', $targetDate)
                    ->with(['room.house.manager', 'users'])
                    ->get();
                
                $this->info("Tìm thấy {$expiringContracts->count()} hợp đồng sẽ hết hạn sau {$days} ngày.");
                
                foreach ($expiringContracts as $contract) {
                    $this->sendNotificationsForContract($contract, $days);
                }
            }
            
            $this->info('Hoàn thành việc gửi thông báo hợp đồng sắp hết hạn.');
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Có lỗi xảy ra: {$e->getMessage()}");
            Log::error("Error in NotifyContractsExpiring command: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString()
            ]);
            
            return 1;
        }
    }
    
    /**
     * Gửi thông báo cho một hợp đồng cụ thể
     * 
     * @param Contract $contract Hợp đồng cần gửi thông báo
     * @param int $daysLeft Số ngày còn lại trước khi hết hạn
     */
    protected function sendNotificationsForContract($contract, $daysLeft)
    {
        // Kiểm tra xem hợp đồng có tự động gia hạn không
        $autoRenew = $contract->auto_renew ?? false;
        
        // Xác định thời gian thông báo (1 tháng, 15 ngày, 1 tuần)
        $timeFrame = $this->getTimeFrame($daysLeft);
        
        // Thông báo cho người thuê
        foreach ($contract->users as $tenant) {
            // Nội dung thông báo dựa trên việc có tự động gia hạn không
            if ($autoRenew) {
                $content = "Hợp đồng thuê phòng {$contract->room->room_number} của bạn sẽ tự động gia hạn sau {$timeFrame} ({$contract->end_date}). Nếu bạn không muốn gia hạn, vui lòng liên hệ quản lý.";
            } else {
                $content = "Hợp đồng thuê phòng {$contract->room->room_number} của bạn sẽ hết hạn sau {$timeFrame} ({$contract->end_date}). Vui lòng liên hệ quản lý để gia hạn.";
            }
            
            $this->notificationService->create(
                $tenant->id,
                'contract_expiring',
                $content,
                "/contracts/{$contract->id}"
            );
            
            $this->info("Đã gửi thông báo cho tenant: {$tenant->name} (ID: {$tenant->id})");
        }
        
        // Thông báo cho quản lý nhà
        if ($contract->room && $contract->room->house && $contract->room->house->manager) {
            $manager = $contract->room->house->manager;
            
            // Lấy tên tenant trong hợp đồng
            $tenantNames = $contract->users->pluck('name')->implode(', ');
            
            // Nội dung thông báo cho manager
            if ($autoRenew) {
                $content = "Hợp đồng phòng {$contract->room->room_number} của {$tenantNames} sẽ tự động gia hạn sau {$timeFrame} ({$contract->end_date}).";
            } else {
                $content = "Hợp đồng phòng {$contract->room->room_number} của {$tenantNames} sẽ hết hạn sau {$timeFrame} ({$contract->end_date}). Cần liên hệ để gia hạn.";
            }
            
            $this->notificationService->create(
                $manager->id,
                'contract_expiring',
                $content,
                "/contracts/{$contract->id}"
            );
            
            $this->info("Đã gửi thông báo cho manager: {$manager->name} (ID: {$manager->id})");
        }
    }
    
    /**
     * Chuyển đổi số ngày thành văn bản mô tả thời gian
     * 
     * @param int $days Số ngày
     * @return string Văn bản mô tả thời gian
     */
    protected function getTimeFrame($days)
    {
        switch ($days) {
            case 30:
                return '1 tháng';
            case 15:
                return '15 ngày';
            case 7:
                return '1 tuần';
            default:
                return "{$days} ngày";
        }
    }
}