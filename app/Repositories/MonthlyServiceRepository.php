<?php

namespace App\Repositories;

use App\Models\House;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Room;
use App\Models\RoomService;
use App\Models\ServiceUsage;
use App\Repositories\Interfaces\MonthlyServiceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MonthlyServiceRepository implements MonthlyServiceRepositoryInterface
{
    /**
     * Lấy danh sách phòng cần cập nhật dịch vụ
     */
    public function getRoomsNeedingUpdate(Request $request): array
    {
        $user = Auth::user();
        $month = $request->month;
        $year = $request->year;
        $houseId = $request->house_id;
        $showAll = filter_var($request->show_all, FILTER_VALIDATE_BOOLEAN);

        $query = Room::query()
            ->with(['house', 'services.service', 'currentContract'])
            ->where('status', 'used');

        // Filter by role permissions
        if ($user->role->code === 'manager') {
            $managedHouseIds = House::where('manager_id', $user->id)->pluck('id')->toArray();
            $query->whereIn('house_id', $managedHouseIds);
        }

        // Filter by house if provided
        if ($houseId) {
            $query->where('house_id', $houseId);
        }

        $rooms = $query->get();

        // Trước tiên, lọc ra những phòng có dịch vụ
        $roomsWithServices = $rooms->filter(function ($room) {
            // Kiểm tra xem phòng có dịch vụ hay không
            $hasServicesRoom = RoomService::where('room_id', $room->id)->exists();
            return $hasServicesRoom;
        });

        // Add a flag indicating if the room needs updates 
        $roomsWithNeedUpdateFlag = $roomsWithServices->map(function ($room) use ($month, $year) {
            $needsUpdate = true;

            // Room needs update if any of its services don't have usage records for this month/year
            foreach ($room->services as $roomService) {
                if ($roomService->status === 'active') {
                    $hasUsage = ServiceUsage::where('room_service_id', $roomService->id)
                        ->where('month', $month)
                        ->where('year', $year)
                        ->exists();

                    if ($hasUsage) {
                        $needsUpdate = false;
                        break;
                    }
                }
            }

            $room->needs_update = $needsUpdate;
            return $room;
        });

        // Filter rooms that need updates if not showing all
        $finalRooms = $showAll ? $roomsWithNeedUpdateFlag : $roomsWithNeedUpdateFlag->filter(function ($room) {
            return $room->needs_update;
        });

        return [
            'rooms' => $finalRooms->values(),
            'total_with_services' => $roomsWithServices->count()
        ];
    }

    /**
     * Lấy dịch vụ của phòng theo tháng/năm
     */
    public function getRoomServices(string $roomId, int $month, int $year): array
    {
        $room = Room::with('house')->findOrFail($roomId);

        // Kiểm tra xem đã có hóa đơn cho phòng này trong tháng/năm này hay chưa
        $existingInvoice = Invoice::where('room_id', $roomId)
            ->where('month', $month)
            ->where('year', $year)
            ->where('invoice_type', 'service_usage')
            ->first();

        // Get previous month/year for meter reading comparisons
        $prevMonth = $month - 1;
        $prevYear = $year;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear = $year - 1;
        }

        $roomServices = RoomService::with(['service'])
            ->where('room_id', $roomId)
            ->where('status', 'active')
            ->get();

        $result = [];
        foreach ($roomServices as $roomService) {
            // Get current month's usage if it exists
            $currentUsage = ServiceUsage::where('room_service_id', $roomService->id)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            // Get previous month's usage
            $previousUsage = ServiceUsage::where('room_service_id', $roomService->id)
                ->where('month', $prevMonth)
                ->where('year', $prevYear)
                ->first();

            $startMeter = null;
            if ($roomService->service->is_metered) {
                // If metered service, use previous month's end meter as this month's start
                $startMeter = $previousUsage ? $previousUsage->end_meter : 0;
            }

            $result[] = [
                'room_service_id' => $roomService->id,
                'service_id' => $roomService->service_id,
                'service_name' => $roomService->service->name,
                'unit' => $roomService->service->unit,
                'is_metered' => $roomService->service->is_metered,
                'price' => $roomService->price,
                'is_fixed' => $roomService->is_fixed,
                'start_meter' => $currentUsage ? $currentUsage->start_meter : $startMeter,
                'end_meter' => $currentUsage ? $currentUsage->end_meter : null,
                'usage_value' => $currentUsage ? $currentUsage->usage_value : null,
                'price_used' => $currentUsage ? $currentUsage->price_used : $roomService->price,
                'has_usage' => (bool) $currentUsage,
                'can_edit' => !$currentUsage // Cho phép chỉnh sửa nếu chưa có bản ghi
            ];
        }

        return [
            'room' => $room,
            'services' => $result,
            'month' => $month,
            'year' => $year,
            'has_invoice' => $existingInvoice !== null,
            'invoice_id' => $existingInvoice ? $existingInvoice->id : null
        ];
    }

    /**
     * Lưu thông tin sử dụng dịch vụ hàng tháng
     */
    public function saveRoomServiceUsage(Request $request): array
    {
        $user = Auth::user();
        $roomId = $request->room_id;
        $month = $request->month;
        $year = $request->year;
        $services = $request->services;
        $updateInvoice = $request->has('update_invoice') ? (bool)$request->update_invoice : true;

        // Kiểm tra xem đã có hóa đơn cho phòng này trong tháng này chưa
        $existingInvoice = Invoice::where('room_id', $roomId)
            ->where('month', $month)
            ->where('year', $year)
            ->where('invoice_type', 'service_usage')
            ->first();

        // Begin transaction
        DB::beginTransaction();
        try {
            $savedServices = [];
            $totalAmount = 0;

            // Xử lý các dịch vụ bị bỏ chọn - xóa service_usage tương ứng
            if (isset($request->unchecked_services) && is_array($request->unchecked_services)) {
                foreach ($request->unchecked_services as $roomServiceId) {
                    // Xác minh room_service_id thuộc về phòng này
                    $roomService = RoomService::where('id', $roomServiceId)
                        ->where('room_id', $roomId)
                        ->first();

                    if ($roomService) {
                        // Xóa service usage cho dịch vụ bị bỏ chọn
                        $deletedServiceUsage = ServiceUsage::where('room_service_id', $roomServiceId)
                            ->where('month', $month)
                            ->where('year', $year)
                            ->first();

                        if ($deletedServiceUsage) {
                            // Tìm và xóa invoice_item liên quan nếu có invoice
                            if ($existingInvoice && $updateInvoice) {
                                $deletedInvoiceItems = InvoiceItem::where('invoice_id', $existingInvoice->id)
                                    ->where('source_type', 'service_usage')
                                    ->where('service_usage_id', $deletedServiceUsage->id)
                                    ->delete();
                            }

                            // Xóa service_usage
                            $deletedServiceUsage->delete();
                        }
                    }
                }
            }

            foreach ($services as $serviceData) {
                $roomServiceId = $serviceData['room_service_id'];
                $roomService = RoomService::with('service')->findOrFail($roomServiceId);

                // Validate that this room service belongs to the room
                if ($roomService->room_id != $roomId) {
                    throw new \Exception("Dịch vụ không thuộc phòng đã chọn");
                }

                // Check if meters are valid for metered services
                if ($roomService->service->is_metered) {
                    if (
                        isset($serviceData['start_meter']) && isset($serviceData['end_meter']) &&
                        $serviceData['start_meter'] > $serviceData['end_meter']
                    ) {
                        throw new \Exception("Đồng hồ kết thúc phải lớn hơn hoặc bằng đồng hồ bắt đầu cho dịch vụ: {$roomService->service->name}");
                    }
                }

                // Chỉ lưu các dịch vụ có usage_value > 0
                if ($serviceData['usage_value'] > 0) {
                    // Create or update service usage
                    $serviceUsage = ServiceUsage::updateOrCreate(
                        [
                            'room_service_id' => $roomServiceId,
                            'month' => $month,
                            'year' => $year,
                        ],
                        [
                            'start_meter' => $serviceData['start_meter'] ?? null,
                            'end_meter' => $serviceData['end_meter'] ?? null,
                            'usage_value' => $serviceData['usage_value'],
                            'price_used' => $serviceData['price_used'],
                            'description' => $serviceData['description'] ?? null,
                        ]
                    );

                    // Tính tổng tiền cho hóa đơn
                    $totalAmount += $serviceData['price_used'];

                    $savedServices[] = $serviceUsage;
                } else {
                    // Nếu usage_value = 0, xóa service usage nếu có
                    ServiceUsage::where('room_service_id', $roomServiceId)
                        ->where('month', $month)
                        ->where('year', $year)
                        ->delete();
                }
            }

            $invoice = null;

            // Xử lý hóa đơn - nếu đã tồn tại và chọn cập nhật hoặc chưa tồn tại
            if ((!$existingInvoice) || ($existingInvoice && $updateInvoice)) {
                if ($existingInvoice) {
                    // Cập nhật hóa đơn hiện có
                    $invoice = $existingInvoice;

                    // Xóa chỉ các invoice item liên quan đến service_usage
                    InvoiceItem::where('invoice_id', $invoice->id)
                        ->where('source_type', 'service_usage')
                        ->delete();

                    // Tính lại tổng tiền bao gồm các manual item hiện có
                    $manualItemsTotal = InvoiceItem::where('invoice_id', $invoice->id)
                        ->where('source_type', '!=', 'service_usage')
                        ->sum('amount');

                    $invoice->total_amount = $totalAmount + $manualItemsTotal;
                    $invoice->updated_by = $user->id;
                    $invoice->save();
                } else {
                    // Tạo hóa đơn mới
                    $invoice = Invoice::create([
                        'room_id' => $roomId,
                        'invoice_type' => 'service_usage',
                        'total_amount' => $totalAmount,
                        'month' => $month,
                        'year' => $year,
                        'description' => "Hóa đơn dịch vụ tháng $month/$year",
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                        'payment_method_id' => 1,
                        'payment_status' => 'pending',
                        'transaction_code' => 'INV-' . Str::random(8) . '-' . time(),
                    ]);
                }

                // Tạo các invoice_item tương ứng với các dịch vụ
                foreach ($savedServices as $serviceUsage) {
                    $roomService = RoomService::with('service')->find($serviceUsage->room_service_id);

                    if ($serviceUsage->usage_value > 0) {
                        InvoiceItem::create([
                            'invoice_id' => $invoice->id,
                            'source_type' => 'service_usage',
                            'service_usage_id' => $serviceUsage->id,
                            'amount' => $serviceUsage->price_used,
                            'description' => "Phí dịch vụ {$roomService->service->name} tháng $month/$year",
                        ]);
                    }
                }

                // Cập nhật lại tổng tiền hóa đơn sau khi tất cả các dịch vụ đã được xử lý
                if ($invoice) {
                    // Tính lại tổng tiền từ tất cả các invoice_item
                    $recalculatedTotal = InvoiceItem::where('invoice_id', $invoice->id)->sum('amount');

                    // Cập nhật tổng tiền
                    $invoice->total_amount = $recalculatedTotal;
                    $invoice->save();
                }
            }

            DB::commit();

            return [
                'saved_services' => $savedServices,
                'invoice' => $invoice ? $invoice->load('items') : null,
                'count' => count($savedServices),
                'updated_invoice' => $updateInvoice && $existingInvoice !== null
            ];
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Lấy danh sách nhà trọ khả dụng cho quản lý dịch vụ
     */
    public function getAvailableHouses(): Collection
    {
        $user = Auth::user();

        if ($user->role->code === 'manager') {
            return House::where('manager_id', $user->id)->get();
        } else {
            return House::all();
        }
    }
}
