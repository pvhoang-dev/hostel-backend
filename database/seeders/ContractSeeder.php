<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Room;
use App\Models\User;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContractSeeder extends Seeder
{
    public function run(): void
    {
        // Lấy tất cả tenant và các phòng available
        $tenants = User::whereHas('role', function ($query) {
            $query->where('code', 'tenant');
        })->get();

        $availableRooms = Room::where('status', 'available')->get();

        // Quản lý danh sách tenant đã được chỉ định phòng
        $assignedTenants = [];

        // Mỗi phòng sẽ có 1-3 tenant (tùy thuộc vào sức chứa của phòng)
        foreach ($availableRooms as $room) {
            // Random phòng sẽ không tạo hợp đồng
            if (rand(0, 2) == 0) {
                continue;
            }

            // Xác định số lượng tenant cho phòng này (tối đa theo sức chứa, hoặc theo số tenant còn lại)
            $maxTenantsForRoom = min($room->capacity, 3); // Tối đa 3 người/phòng
            $remainingTenants = $tenants->count() - count($assignedTenants);

            // Nếu không còn tenant nào thì thoát vòng lặp
            if ($remainingTenants <= 0) {
                break;
            }

            // Số lượng tenant thực tế sẽ được gán cho phòng này
            $tenantCount = min($remainingTenants, rand(1, $maxTenantsForRoom));

            // Lấy ngẫu nhiên các tenant chưa được chỉ định phòng
            $selectedTenants = [];

            // Mảng chỉ số của tenant còn trống
            $availableTenantIndices = array_diff(range(0, $tenants->count() - 1), $assignedTenants);

            // Nếu còn đủ tenant chưa được chỉ định
            if (count($availableTenantIndices) >= $tenantCount) {
                // Chọn ngẫu nhiên tenant cho phòng
                $randomKeys = array_rand(array_flip($availableTenantIndices), $tenantCount);

                if (!is_array($randomKeys)) {
                    $randomKeys = [$randomKeys];
                }

                foreach ($randomKeys as $index) {
                    $selectedTenants[] = $tenants[$index]->id;
                    $assignedTenants[] = $index; // Đánh dấu tenant đã được chỉ định
                }

                // Tính ngày bắt đầu và kết thúc hợp đồng
                $startDateOptions = [
                    Carbon::now()->subMonths(rand(1, 6)),
                    Carbon::now()->subMonths(rand(1, 3)),
                    Carbon::now()->subWeeks(rand(1, 3)),
                    Carbon::now()->subDays(rand(1, 15))
                ];
                $startDate = $startDateOptions[array_rand($startDateOptions)];

                // Thời hạn hợp đồng (6, 12 hoặc 24 tháng)
                $contractTerms = [6, 12]; // Trọng số cho 12 tháng cao hơn
                $termMonths = $contractTerms[array_rand($contractTerms)];
                $endDate = Carbon::parse($startDate)->addMonths($termMonths);
                $randomRenew = rand(0, 1);

                // Lấy manager là người tạo
                $manager = User::whereHas('role', function ($query) {
                    $query->where('code', 'admin');
                })->first();

                try {
                    DB::beginTransaction();

                    // Giá phòng đã được giảm 3 số 0 trong RoomSeeder
                    $contract = Contract::create([
                        'room_id' => $room->id,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'monthly_price' => $room->base_price,
                        'deposit_amount' => $room->base_price * 2,
                        'notice_period' => 30, // 30 ngày thông báo trước khi hết hạn
                        'deposit_status' => 'held',
                        'status' => 'active',
                        'auto_renew' => (bool)$randomRenew,
                        'time_renew' => (bool)$randomRenew ? 6 : null,
                        'created_by' => $manager->id,
                    ]);

                    // Gán các tenant cho hợp đồng
                    $contract->users()->sync($selectedTenants);

                    // Cập nhật trạng thái phòng
                    $room->update(['status' => 'used']);

                    // Tạo hóa đơn tiền cọc và tiền phòng tháng đầu tiên
                    $invoiceMonth = Carbon::parse($startDate)->month;
                    $invoiceYear = Carbon::parse($startDate)->year;

                    // 1. Tạo hóa đơn với trạng thái đã thanh toán
                    $invoice = new Invoice();
                    $invoice->room_id = $room->id;
                    $invoice->invoice_type = 'custom';
                    $invoice->month = $invoiceMonth;
                    $invoice->year = $invoiceYear;
                    $invoice->description = "Hóa đơn thanh toán đặt cọc và tiền phòng tháng đầu";
                    $invoice->created_by = $manager->id;
                    $invoice->updated_by = $manager->id;
                    $invoice->payment_status = 'completed';
                    $invoice->payment_date = $startDate; // Thanh toán vào ngày ký hợp đồng
                    $invoice->payment_method_id = 1;
                    $invoice->transaction_code = 'CONTRACT-' . $contract->id . '-' . uniqid();
                    $invoice->total_amount = 0; // Sẽ cập nhật sau
                    $invoice->save();

                    $totalAmount = 0;

                    // Item 1: Tiền đặt cọc
                    $depositItem = new InvoiceItem();
                    $depositItem->invoice_id = $invoice->id;
                    $depositItem->source_type = 'manual';
                    $depositItem->amount = $contract->deposit_amount;
                    $depositItem->description = "Tiền đặt cọc";
                    $depositItem->save();
                    $totalAmount += $contract->deposit_amount;

                    // Item 2: Tiền phòng tháng đầu
                    $roomFeeItem = new InvoiceItem();
                    $roomFeeItem->invoice_id = $invoice->id;
                    $roomFeeItem->source_type = 'manual';
                    $roomFeeItem->amount = $contract->monthly_price;
                    $roomFeeItem->description = "Tiền phòng tháng " . $invoiceMonth . "/" . $invoiceYear;
                    $roomFeeItem->save();
                    $totalAmount += $contract->monthly_price;

                    // Cập nhật tổng tiền
                    $invoice->total_amount = $totalAmount;
                    $invoice->save();

                    // Tạo thông báo cho tenant về hóa đơn
                    foreach ($selectedTenants as $tenantId) {
                        $notification = new Notification();
                        $notification->user_id = $tenantId;
                        $notification->type = 'invoice';
                        $notification->content = "Hóa đơn tiền cọc và tiền phòng tháng đầu đã được thanh toán.";
                        $notification->url = "/invoices/{$invoice->id}";
                        $notification->is_read = true; // Đã đọc vì là lịch sử
                        $notification->save();
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    echo "Lỗi khi tạo hợp đồng và hóa đơn cho phòng {$room->room_number}: " . $e->getMessage() . "\n";
                }
            }
        }
    }
}
