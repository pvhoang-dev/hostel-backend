<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateExpiredContracts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:update-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cập nhật hợp đồng đã hết hạn và phòng tương ứng';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Bắt đầu cập nhật hợp đồng hết hạn...');
        
        try {
            // Tìm tất cả hợp đồng active đã hết hạn
            $expiredContracts = Contract::where('status', 'active')
                ->where('end_date', '<', Carbon::now()->toDateString())
                ->get();
            
            $count = 0;
            $renewedCount = 0;
            
            DB::beginTransaction();
            
            foreach ($expiredContracts as $contract) {
                // Nếu hợp đồng có auto_renew = true, gia hạn thêm thời gian
                if ($contract->auto_renew) {
                    // Tính thời gian gia hạn dựa trên khoảng cách giữa start_date và end_date
                    $startDate = Carbon::parse($contract->start_date);
                    $endDate = Carbon::parse($contract->end_date);
                    
                    // Nếu có cột time_renew thì sử dụng, nếu không thì tính khoảng cách start_date và end_date
                    if (isset($contract->time_renew) && $contract->time_renew > 0) {
                        // Sử dụng time_renew nếu có (đơn vị tháng)
                        $renewMonths = $contract->time_renew;
                    } else {
                        // Tính khoảng cách tháng giữa start_date và end_date
                        $renewMonths = $endDate->diffInMonths($startDate);
                        
                        // Nếu khoảng cách quá nhỏ hoặc bằng 0, mặc định gia hạn 6 tháng
                        if ($renewMonths <= 0) {
                            $renewMonths = 6;
                            $contract->update(
                                ['time_renew' => $renewMonths]
                            );
                        }
                    }
                    
                    // Tính ngày hết hạn mới = ngày hết hạn cũ + khoảng thời gian gia hạn
                    $newEndDate = $endDate->addMonths($renewMonths);
                    
                    // Cập nhật ngày hết hạn mới cho hợp đồng
                    $contract->update([
                        'end_date' => $newEndDate->toDateString(),
                        'updated_by' => 1 // Hệ thống tự động cập nhật, có thể thay bằng ID của admin
                    ]);
                    
                    $message = "Gia hạn hợp đồng #{$contract->id} thêm {$renewMonths} tháng, ngày hết hạn mới: {$newEndDate->toDateString()}";
                    $this->info($message);
                    Log::info($message);
                    $renewedCount++;
                } 
                // Nếu không có auto_renew thì cập nhật trạng thái thành expired
                else {
                    // Cập nhật trạng thái hợp đồng
                    $contract->update([
                        'status' => 'expired',
                        'termination_reason' => 'Hợp đồng hết hạn'
                    ]);
                    
                    // Kiểm tra nếu không còn hợp đồng active nào cho phòng này
                    $hasActiveContract = Contract::where('room_id', $contract->room_id)
                        ->where('id', '!=', $contract->id)
                        ->where('status', 'active')
                        ->exists();
                    
                    if (!$hasActiveContract) {
                        // Cập nhật trạng thái phòng thành available
                        $room = Room::find($contract->room_id);
                        if ($room) {
                            $room->update(['status' => 'available']);
                        }
                    }
                    
                    $message = "Hợp đồng #{$contract->id} đã hết hạn và đã được cập nhật trạng thái thành expired";
                    $this->info($message);
                    Log::info($message);
                    $count++;
                }
            }
            
            DB::commit();
            
            $message = "Đã cập nhật {$count} hợp đồng hết hạn và {$renewedCount} hợp đồng được gia hạn tự động.";
            $this->info($message);
            Log::info($message);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi khi cập nhật hợp đồng hết hạn: ' . $e->getMessage());
            $this->error('Đã xảy ra lỗi: ' . $e->getMessage());
        }
        
        return 0;
    }
} 