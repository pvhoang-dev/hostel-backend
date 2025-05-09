<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Models\House;
use App\Models\Room;
use App\Models\RoomService;
use App\Models\Service;
use App\Models\ServiceUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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

        // Add a flag indicating if the room needs updates 
        $roomsWithNeedUpdateFlag = $rooms->map(function ($room) use ($month, $year) {
            $needsUpdate = false;

            // Room needs update if any of its services don't have usage records for this month/year
            foreach ($room->services as $roomService) {
                if ($roomService->status === 'active') {
                    $hasUsage = ServiceUsage::where('room_service_id', $roomService->id)
                        ->where('month', $month)
                        ->where('year', $year)
                        ->exists();

                    if (!$hasUsage) {
                        $needsUpdate = true;
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
            'total_rooms' => $rooms->count()
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
            'year' => $year
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
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $roomId = $request->room_id;
        $month = $request->month;
        $year = $request->year;
        $services = $request->services;

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

        // Begin transaction
        DB::beginTransaction();
        try {
            $savedServices = [];

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

                $savedServices[] = $serviceUsage;
            }

            DB::commit();
            return $this->sendResponse([
                'saved_services' => $savedServices,
                'count' => count($savedServices)
            ], 'Room service usages saved successfully.');
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
