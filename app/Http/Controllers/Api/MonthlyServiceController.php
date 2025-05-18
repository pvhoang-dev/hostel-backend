<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Models\House;
use App\Models\Room;
use App\Models\RoomService;
use App\Models\ServiceUsage;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Services\NotificationService;

class MonthlyServiceController extends BaseController
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get rooms that need service usage updates for a specific month/year
     */
    public function getRoomsNeedingUpdate(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'house_id' => 'sometimes|nullable|exists:houses,id',
            'show_all' => 'sometimes|nullable|in:true,false,0,1',
        ], [
            'month.required' => 'Tháng là bắt buộc.',
            'month.integer' => 'Tháng phải là số.',
            'month.min' => 'Tháng phải lớn hơn 0.',
            'month.max' => 'Tháng phải nhỏ hơn 13.',
            'year.required' => 'Năm là bắt buộc.',
            'year.integer' => 'Năm phải là số.',
            'year.min' => 'Năm phải lớn hơn 2000.',
            'year.max' => 'Năm phải nhỏ hơn 2100.',
            'house_id.exists' => 'Nhà trọ không tồn tại.',
            'show_all.in' => 'show_all phải là true hoặc false.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Lỗi dữ liệu.', $validator->errors());
        }

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
        } elseif ($user->role->code !== 'admin') {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền truy cập vào tài nguyên này'], 403);
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

        return $this->sendResponse([
            'rooms' => $finalRooms->values(),
            'count' => $finalRooms->count(),
            'total_rooms' => $roomsWithServices->count()
        ], 'Rooms retrieved successfully.');
    }

    /**
     * Get services for a room with their latest usage
     */
    public function getRoomServices(Request $request, $roomId): JsonResponse
    {
        $user = Auth::user();

        $validator = Validator::make([
            'room_id' => $roomId,
            'month' => $request->month,
            'year' => $request->year
        ], [
            'room_id' => 'required|exists:rooms,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Lỗi dữ liệu.', $validator->errors());
        }

        $room = Room::with('house')->findOrFail($roomId);

        // Check authorization
        if ($user->role->code === 'manager') {
            $managedHouseIds = House::where('manager_id', $user->id)->pluck('id')->toArray();
            if (!in_array($room->house_id, $managedHouseIds)) {
                return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn chỉ có thể truy cập phòng trong nhà trọ mà bạn quản lý'], 403);
            }
        } elseif ($user->role->code !== 'admin') {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền truy cập vào tài nguyên này'], 403);
        }

        $month = $request->month;
        $year = $request->year;

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

        return $this->sendResponse([
            'room' => $room,
            'services' => $result,
            'month' => $month,
            'year' => $year,
            'has_invoice' => $existingInvoice !== null, // Thêm thông tin về việc có hóa đơn tồn tại hay không
            'invoice_id' => $existingInvoice ? $existingInvoice->id : null // Thêm ID của hóa đơn nếu có
        ], 'Room services retrieved successfully.');
    }

    /**
     * Save monthly service usage for a room
     */
    public function saveRoomServiceUsage(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'room_id' => 'required|exists:rooms,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'services' => 'required|array',
            'services.*.room_service_id' => 'required|exists:room_services,id',
            'services.*.start_meter' => 'sometimes|nullable|numeric|min:0',
            'services.*.end_meter' => 'sometimes|nullable|numeric|min:0',
            'services.*.usage_value' => 'required|numeric|min:0',
            'services.*.price_used' => 'required|integer|min:0',
            'update_invoice' => 'sometimes|boolean', // Thêm tham số update_invoice
            'unchecked_services' => 'sometimes|array', // Thêm tham số unchecked_services
            'unchecked_services.*' => 'sometimes|exists:room_services,id', // Các ID hợp lệ
        ], [
            'room_id.required' => 'Phòng là bắt buộc.',
            'room_id.exists' => 'Phòng không tồn tại.',
            'month.required' => 'Tháng là bắt buộc.',
            'month.integer' => 'Tháng phải là số.',
            'month.min' => 'Tháng phải lớn hơn 0.',
            'month.max' => 'Tháng phải nhỏ hơn 13.',
            'year.required' => 'Năm là bắt buộc.',
            'year.integer' => 'Năm phải là số.',
            'year.min' => 'Năm phải lớn hơn 2000.',
            'year.max' => 'Năm phải nhỏ hơn 2100.',
            'services.required' => 'Dịch vụ là bắt buộc.',
            'services.array' => 'Dịch vụ phải là mảng.',
            'services.*.room_service_id.required' => 'ID dịch vụ là bắt buộc.',
            'services.*.room_service_id.exists' => 'ID dịch vụ không tồn tại.',
            'services.*.start_meter.numeric' => 'Giá trị đồng hồ bắt đầu phải là số.',
            'services.*.end_meter.numeric' => 'Giá trị đồng hồ kết thúc phải là số.',
            'services.*.usage_value.required' => 'Giá trị sử dụng là bắt buộc.',
            'services.*.usage_value.numeric' => 'Giá trị sử dụng phải là số.',
            'services.*.usage_value.min' => 'Giá trị sử dụng phải lớn hơn 0.',
            'services.*.price_used.required' => 'Giá trị sử dụng phải là số.',
            'services.*.price_used.integer' => 'Giá trị sử dụng phải là số.',
            'services.*.price_used.min' => 'Giá trị sử dụng phải lớn hơn 0.',
            'update_invoice.boolean' => 'Cập nhật hóa đơn phải là true hoặc false.',
            'unchecked_services.array' => 'Dịch vụ không được chọn phải là mảng.',
            'unchecked_services.*.exists' => 'ID dịch vụ không tồn tại.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Lỗi dữ liệu.', $validator->errors());
        }

        $roomId = $request->room_id;
        $month = $request->month;
        $year = $request->year;
        $services = $request->services;
        $updateInvoice = $request->has('update_invoice') ? (bool)$request->update_invoice : true; // Mặc định là true

        $room = Room::with('house')->findOrFail($roomId);

        // Check authorization
        if ($user->role->code === 'manager') {
            $managedHouseIds = House::where('manager_id', $user->id)->pluck('id')->toArray();
            if (!in_array($room->house_id, $managedHouseIds)) {
                return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn chỉ có thể cập nhật dịch vụ cho phòng trong nhà trọ mà bạn quản lý'], 403);
            }
        } elseif ($user->role->code !== 'admin') {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền cập nhật dịch vụ'], 403);
        }

        // Kiểm tra xem đã có hóa đơn cho phòng này trong tháng này chưa
        $existingInvoice = Invoice::where('room_id', $roomId)
            ->where('month', $month)
            ->where('year', $year)
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

                    $this->notificationService->notifyRoomTenants(
                        $roomId,
                        'invoice',
                        "Hóa đơn dịch vụ tháng $month/$year đã được tạo.",
                        "/invoices/{$invoice->id}",
                    );
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

            return $this->sendResponse([
                'saved_services' => $savedServices,
                'invoice' => $invoice ? $invoice->load('items') : null,
                'count' => count($savedServices),
                'updated_invoice' => $updateInvoice && $existingInvoice !== null
            ], 'Dịch vụ đã được lưu' . ($updateInvoice ? ' và hóa đơn đã được cập nhật' : '') . ' thành công.');
        } catch (\Exception $e) {
            DB::rollback();
            return $this->sendError('Lỗi lưu dịch vụ', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get available houses for service management
     */
    public function getAvailableHouses(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role->code === 'manager') {
            $houses = House::where('manager_id', $user->id)->get();
        } elseif ($user->role->code === 'admin') {
            $houses = House::all();
        } else {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền truy cập vào tài nguyên này'], 403);
        }

        return $this->sendResponse([
            'houses' => $houses
        ], 'Nhà trọ khả dụng đã được lấy thành công.');
    }
}
