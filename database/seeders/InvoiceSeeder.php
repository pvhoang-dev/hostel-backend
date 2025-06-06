<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\User;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentMethod;
use App\Models\Notification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoiceSeeder extends Seeder
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
            $query->where('code', 'admin');
        })->first();

        // Tạo các hóa đơn tùy chỉnh cho tháng 1, 2, 3 năm 2025
        $months = [4, 5, 6];
        $year = 2025;

        // Các loại hóa đơn tùy chỉnh
        $customInvoiceTypes = [
            'Tiền đặt cọc' => [1000, 2000],
            'Sửa chữa đồ đạc' => [500, 2000],
            'Phí dọn dẹp' => [1000, 3000],
            'Phí giữ xe' => [500, 1500],
            'Phí internet tốc độ cao' => [1000, 3000],
        ];

        foreach ($rooms as $room) {
            foreach ($months as $month) {
                // Quyết định xem có tạo hóa đơn tùy chỉnh cho tháng này không
                if (rand(0, 2) > 1) { // 2/3 xác suất tạo hóa đơn
                    DB::beginTransaction();
                    try {
                        // Lấy danh sách các key (tên loại hóa đơn)
                        $invoiceTypes = array_keys($customInvoiceTypes);

                        // Chọn ngẫu nhiên 1 hoặc 2 loại hóa đơn
                        $numToSelect = min(rand(1, 2), count($invoiceTypes));
                        $selectedTypes = [];

                        // Chọn ngẫu nhiên các loại không trùng lặp
                        $keys = array_rand($invoiceTypes, $numToSelect);
                        if (!is_array($keys)) {
                            $keys = [$keys];
                        }

                        foreach ($keys as $key) {
                            $selectedTypes[] = $invoiceTypes[$key];
                        }

                        // Tạo hóa đơn
                        $invoice = new Invoice();
                        $invoice->room_id = $room->id;
                        $invoice->invoice_type = 'custom';
                        $invoice->month = $month;
                        $invoice->year = $year;
                        $invoice->description = "Hóa đơn phát sinh tháng $month/$year";
                        $invoice->created_by = $manager->id;
                        $invoice->updated_by = $manager->id;

                        // Tháng 4, 5 đã thanh toán, tháng 6 random
                        if ($month == 4 || $month == 5) {
                            $invoice->payment_status = 'completed';
                            $invoice->payment_date = now()->subDays(rand(1, 20));
                            $paymentMethodId = rand(1, 2);
                            $invoice->payment_method_id = $paymentMethodId;
                            $invoice->transaction_code = 'CUST-' . $month . '-' . uniqid();
                        } else {
                            // Tháng 6: ngẫu nhiên 'pending', 'waiting', 'completed'
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
                                $paymentMethodId = rand(1, 2);
                                $invoice->payment_method_id = $paymentMethodId;
                                $invoice->transaction_code = 'CUST-' . $month . '-' . uniqid();
                            }
                        }

                        $invoice->total_amount = 0; // Sẽ cập nhật sau
                        $invoice->save();

                        $totalAmount = 0;

                        // Tạo các invoice items
                        foreach ($selectedTypes as $type) {
                            // Lấy khoảng giá từ loại hóa đơn tùy chỉnh
                            $range = $customInvoiceTypes[$type];
                            $amount = rand($range[0], $range[1]);
                            $totalAmount += $amount;

                            $invoiceItem = new InvoiceItem();
                            $invoiceItem->invoice_id = $invoice->id;
                            $invoiceItem->source_type = 'manual';
                            $invoiceItem->amount = $amount;
                            $invoiceItem->description = $type;
                            $invoiceItem->save();
                        }

                        // Cập nhật tổng tiền
                        $invoice->total_amount = $totalAmount;
                        $invoice->save();

                        // Tạo thông báo cho tenant
                        if ($room->contracts && $room->contracts->isNotEmpty()) {
                            foreach ($room->contracts as $contract) {
                                foreach ($contract->users as $tenant) {
                                    if ($tenant->role->code === 'tenant') {
                                        $notification = new Notification();
                                        $notification->user_id = $tenant->id;
                                        $notification->type = 'invoice';
                                        $notification->content = "Hóa đơn phát sinh tháng $month/$year đã được tạo.";
                                        $notification->url = "/invoices/{$invoice->id}";
                                        $notification->is_read = false;
                                        $notification->save();
                                    }
                                }
                            }
                        }

                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        echo "Lỗi khi tạo hóa đơn tùy chỉnh cho phòng {$room->room_number}: " . $e->getMessage() . "\n";
                    }
                }
            }

            // Tạo một số hóa đơn đã thanh toán đầy đủ
            DB::beginTransaction();
            try {
                $invoice = new Invoice();
                $invoice->room_id = $room->id;
                $invoice->invoice_type = 'custom';
                $invoice->month = 12;
                $invoice->year = 2024;
                $invoice->description = "Hóa đơn tiện ích tháng 12/2024";
                $invoice->created_by = $manager->id;
                $invoice->updated_by = $manager->id;
                $invoice->payment_status = 'completed';
                $invoice->payment_method_id = rand(1, 2);
                $invoice->payment_date = now()->subDays(rand(30, 60));
                $invoice->transaction_code = 'INV-' . uniqid();
                $invoice->total_amount = 0;
                $invoice->save();

                // Thêm một vài mục hóa đơn
                $items = [
                    ['Giặt ủi', rand(50000, 150000)],
                    ['Phí vệ sinh', rand(100000, 200000)],
                    ['Phí internet', rand(100000, 300000)]
                ];

                $totalAmount = 0;
                foreach ($items as $item) {
                    $invoiceItem = new InvoiceItem();
                    $invoiceItem->invoice_id = $invoice->id;
                    $invoiceItem->source_type = 'manual';
                    $invoiceItem->amount = $item[1];
                    $invoiceItem->description = $item[0];
                    $invoiceItem->save();

                    $totalAmount += $item[1];
                }

                $invoice->total_amount = $totalAmount;
                $invoice->save();

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                echo "Lỗi khi tạo hóa đơn đã thanh toán cho phòng {$room->room_number}: " . $e->getMessage() . "\n";
            }
        }
    }
}
