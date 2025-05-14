<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Models\House;
use App\Models\Room;
use App\Models\RoomService;
use App\Models\ServiceUsage;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MonthlyServiceController extends BaseController
{
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
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
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
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to access this resource'], 403);
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
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $room = Room::with('house')->findOrFail($roomId);

        // Check authorization
        if ($user->role->code === 'manager') {
            $managedHouseIds = House::where('manager_id', $user->id)->pluck('id')->toArray();
            if (!in_array($room->house_id, $managedHouseIds)) {
                return $this->sendError('Unauthorized', ['error' => 'You can only access rooms in houses you manage'], 403);
            }
        } elseif ($user->role->code !== 'admin') {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to access this resource'], 403);
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
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
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
                return $this->sendError('Unauthorized', ['error' => 'You can only update services for rooms in houses you manage'], 403);
            }
        } elseif ($user->role->code !== 'admin') {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to update services'], 403);
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
                \Illuminate\Support\Facades\Log::info('Processing unchecked services:', [
                    'unchecked_count' => count($request->unchecked_services),
                    'unchecked_services' => $request->unchecked_services
                ]);
                
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
                                    
                                \Illuminate\Support\Facades\Log::info("Deleted invoice items for unchecked service:", [
                                    'service_usage_id' => $deletedServiceUsage->id,
                                    'deleted_count' => $deletedInvoiceItems
                                ]);
                            }
                            
                            // Xóa service_usage
                            $deletedServiceUsage->delete();
                            
                            \Illuminate\Support\Facades\Log::info("Deleted service usage for unchecked service:", [
                                'room_service_id' => $roomServiceId,
                                'service_usage_id' => $deletedServiceUsage->id
                            ]);
                        } else {
                            \Illuminate\Support\Facades\Log::info("No service usage found to delete:", [
                                'room_service_id' => $roomServiceId
                            ]);
                        }
                    }
                }
            }

            foreach ($services as $serviceData) {
                $roomServiceId = $serviceData['room_service_id'];
                $roomService = RoomService::with('service')->findOrFail($roomServiceId);

                // Validate that this room service belongs to the room
                if ($roomService->room_id != $roomId) {
                    throw new \Exception("Room service does not belong to the specified room");
                }

                // Check if meters are valid for metered services
                if ($roomService->service->is_metered) {
                    if (
                        isset($serviceData['start_meter']) && isset($serviceData['end_meter']) &&
                        $serviceData['start_meter'] > $serviceData['end_meter']
                    ) {
                        throw new \Exception("End meter must be greater than or equal to start meter for service: {$roomService->service->name}");
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
            $transaction = null;

            // Xử lý hóa đơn - nếu đã tồn tại và chọn cập nhật hoặc chưa tồn tại
            if ((!$existingInvoice) || ($existingInvoice && $updateInvoice)) {
                \Illuminate\Support\Facades\Log::info('Monthly service processing invoice:', [
                    'room_id' => $roomId,
                    'month' => $month,
                    'year' => $year,
                    'existing_invoice' => $existingInvoice ? $existingInvoice->id : 'none',
                    'update_invoice' => $updateInvoice,
                    'total_amount' => $totalAmount,
                    'saved_services_count' => count($savedServices)
                ]);
                
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
                    
                    \Illuminate\Support\Facades\Log::info("Recalculated invoice total amount:", [
                        'invoice_id' => $invoice->id,
                        'previous_total' => $invoice->total_amount,
                        'new_total' => $recalculatedTotal,
                        'item_count' => InvoiceItem::where('invoice_id', $invoice->id)->count()
                    ]);
                }

                // Log thông tin về hóa đơn và các item sau khi cập nhật
                \Illuminate\Support\Facades\Log::info('Monthly service update - Invoice items after update:', [
                    'invoice_id' => $invoice->id,
                    'item_count' => InvoiceItem::where('invoice_id', $invoice->id)->count(),
                    'manual_items' => InvoiceItem::where('invoice_id', $invoice->id)
                        ->where('source_type', 'manual')
                        ->get()
                        ->toArray(),
                    'service_items' => InvoiceItem::where('invoice_id', $invoice->id)
                        ->where('source_type', 'service_usage')
                        ->get()
                        ->toArray()
                ]);

                // Kiểm tra xem đã có transaction cho hóa đơn này chưa
                $existingTransaction = Transaction::where('invoice_id', $invoice->id)->first();

                if ($existingTransaction) {
                    // Cập nhật transaction hiện có
                    $existingTransaction->amount = $totalAmount;
                    $existingTransaction->save();
                    $transaction = $existingTransaction;
                } else if (!$existingInvoice) {
                    // Chỉ tạo transaction mới nếu là hóa đơn mới
                    $transaction = Transaction::create([
                        'invoice_id' => $invoice->id,
                        'payment_method_id' => 1, // ID của payment method mặc định
                        'amount' => $totalAmount,
                        'transaction_code' => 'TXN-' . Str::random(8) . '-' . time(),
                        'status' => 'pending',
                        'payment_date' => now(),
                    ]);
                }
            }

            DB::commit();

            return $this->sendResponse([
                'saved_services' => $savedServices,
                'invoice' => $invoice ? $invoice->load('items') : null,
                'transaction' => $transaction,
                'count' => count($savedServices),
                'updated_invoice' => $updateInvoice && $existingInvoice !== null
            ], 'Room service usages saved' . ($updateInvoice ? ' and invoice updated' : '') . ' successfully.');
        } catch (\Exception $e) {
            DB::rollback();
            return $this->sendError('Error saving service usages', ['error' => $e->getMessage()]);
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
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to access this resource'], 403);
        }

        return $this->sendResponse([
            'houses' => $houses
        ], 'Available houses retrieved successfully.');
    }
}
