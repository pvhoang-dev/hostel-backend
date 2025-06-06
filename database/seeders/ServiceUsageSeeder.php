<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomService;
use App\Models\ServiceUsage;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceUsageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lấy danh sách phòng có người thuê (có hợp đồng active)
        $rooms = Room::whereHas('contracts', function ($query) {
            $query->where('status', 'active');
        })->get();

        // Lấy một manager bất kỳ để đặt là người tạo
        $manager = User::whereHas('role', function ($query) {
            $query->where('code', 'manager');
        })->first();

        // Tạo dữ liệu dịch vụ cho tháng 4, 5, 6 năm 2025
        $months = [4, 5, 6];
        $year = 2025;

        foreach ($rooms as $room) {
            // Lấy dịch vụ của phòng
            $roomServices = RoomService::where('room_id', $room->id)->get();

            // Nếu phòng không có dịch vụ nào, bỏ qua
            if ($roomServices->isEmpty()) {
                continue;
            }

            foreach ($months as $month) {
                // Nếu là tháng 6, random bỏ qua một số phòng (30% xác suất bỏ qua)
                if ($month == 6 && rand(1, 10) <= 4) {
                    continue; // Bỏ qua tháng này cho phòng hiện tại
                }

                DB::beginTransaction();
                try {
                    // Tạo invoice cho tháng này
                    $invoice = new Invoice();
                    $invoice->room_id = $room->id;
                    $invoice->invoice_type = 'service_usage';
                    $invoice->month = $month;
                    $invoice->year = $year;
                    $invoice->description = "Hóa đơn dịch vụ tháng $month/$year";
                    $invoice->created_by = $manager->id;
                    $invoice->updated_by = $manager->id;

                    // Tháng 4, 5 đã thanh toán, tháng 6 random
                    if ($month == 4 || $month == 5) {
                        $invoice->payment_status = 'completed';
                        $invoice->payment_date = now()->subDays(rand(1, 15));

                        // Thêm thông tin thanh toán
                        $paymentMethods = PaymentMethod::all();
                        if ($paymentMethods->count() > 0) {
                            $paymentMethodId = rand(1, 2);
                            $invoice->payment_method_id = $paymentMethodId;
                            $invoice->transaction_code = 'SRV-' . $month . '-' . uniqid();
                        }
                    } else {
                        // Tháng 6: ngẫu nhiên 'pending', 'waiting' hoặc 'completed'
                        $statuses = ['pending', 'waiting', 'completed'];
                        $status = $statuses[array_rand($statuses)];
                        $invoice->payment_status = $status;
                        if ($status === 'waiting') {
                            $invoice->payment_method_id = 2;
                        } else {
                            $invoice->payment_method_id = 1;
                        }

                        // Nếu đã thanh toán, thêm thông tin thanh toán
                        if ($status === 'completed') {
                            $invoice->payment_date = now()->subDays(rand(1, 5));

                            $paymentMethods = PaymentMethod::all();
                            if ($paymentMethods->count() > 0) {
                                $invoice->payment_method_id = rand(1, 2);
                                $invoice->transaction_code = 'SRV-' . $month . '-' . uniqid();
                            }
                        }
                    }

                    $invoice->total_amount = 0; // Sẽ cập nhật sau khi tạo các invoice items
                    $invoice->save();

                    $totalAmount = 0;

                    // Tạo service usage và invoice items tương ứng cho từng dịch vụ
                    foreach ($roomServices as $roomService) {
                        // Nếu là tiền phòng thì usage_value = 1
                        $usageValue = ($roomService->service->name === 'Tiền phòng')
                            ? 1
                            : rand(1, 15); // Giá trị sử dụng ngẫu nhiên

                        $startMeter = null;
                        $endMeter = null;

                        // Nếu là dịch vụ tính theo đồng hồ (điện, nước)
                        if (in_array($roomService->service->name, ['Điện', 'Nước'])) {
                            // Tìm service usage trước đó
                            $previousUsage = ServiceUsage::where('room_service_id', $roomService->id)
                                ->where(function ($query) use ($month, $year) {
                                    if ($month == 1) {
                                        $query->where('month', 12)->where('year', $year - 1);
                                    } else {
                                        $query->where('month', $month - 1)->where('year', $year);
                                    }
                                })->first();

                            if ($previousUsage) {
                                $startMeter = $previousUsage->end_meter;
                                $endMeter = $startMeter + $usageValue;
                            } else {
                                $startMeter = rand(100, 1000);
                                $endMeter = $startMeter + $usageValue;
                            }
                        }

                        $amount = $usageValue * $roomService->price;
                        $totalAmount += $amount;

                        $serviceUsage = new ServiceUsage();
                        $serviceUsage->room_service_id = $roomService->id;
                        $serviceUsage->month = $month;
                        $serviceUsage->year = $year;
                        $serviceUsage->usage_value = $usageValue;
                        $serviceUsage->start_meter = $startMeter;
                        $serviceUsage->end_meter = $endMeter;
                        $serviceUsage->price_used = $amount;
                        $serviceUsage->save();

                        // Tạo invoice item tương ứng
                        $invoiceItem = new InvoiceItem();
                        $invoiceItem->invoice_id = $invoice->id;
                        $invoiceItem->source_type = 'service_usage';
                        $invoiceItem->service_usage_id = $serviceUsage->id;
                        $invoiceItem->amount = $amount;
                        $invoiceItem->description = "Sử dụng {$roomService->service->name}: $usageValue {$roomService->service->unit}";
                        $invoiceItem->save();
                    }

                    // Cập nhật tổng tiền của invoice
                    $invoice->total_amount = $totalAmount;
                    $invoice->save();

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollback();
                    echo "Lỗi khi tạo dữ liệu cho phòng {$room->room_number}: " . $e->getMessage() . "\n";
                }
            }
        }
    }
}
