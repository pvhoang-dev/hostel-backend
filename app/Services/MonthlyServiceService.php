<?php

namespace App\Services;

use App\Models\House;
use App\Models\Room;
use App\Repositories\Interfaces\MonthlyServiceRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MonthlyServiceService
{
    protected $monthlyServiceRepository;
    protected $notificationService;

    public function __construct(
        MonthlyServiceRepositoryInterface $monthlyServiceRepository,
        NotificationService $notificationService
    ) {
        $this->monthlyServiceRepository = $monthlyServiceRepository;
        $this->notificationService = $notificationService;
    }

    /**
     * Lấy danh sách phòng cần cập nhật dịch vụ
     */
    public function getRoomsNeedingUpdate(Request $request)
    {
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
            return ['success' => false, 'errors' => $validator->errors()];
        }

        $user = Auth::user();
        // Check user permission
        if ($user->role->code !== 'admin' && $user->role->code !== 'manager') {
            return ['success' => false, 'errors' => 'Bạn không có quyền truy cập vào tài nguyên này'];
        }

        // Get rooms from repository
        $result = $this->monthlyServiceRepository->getRoomsNeedingUpdate($request);
        $rooms = $result['rooms'];

        return [
            'success' => true, 
            'data' => [
                'rooms' => $rooms,
                'count' => $rooms->count(),
                'total_rooms' => $result['total_with_services']
            ]
        ];
    }

    /**
     * Lấy dịch vụ của phòng theo tháng/năm
     */
    public function getRoomServices(Request $request, $roomId)
    {
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
            return ['success' => false, 'errors' => $validator->errors()];
        }

        $user = Auth::user();
        $room = Room::with('house')->findOrFail($roomId);

        // Check authorization
        if ($user->role->code === 'manager') {
            $managedHouseIds = House::where('manager_id', $user->id)->pluck('id')->toArray();
            if (!in_array($room->house_id, $managedHouseIds)) {
                return ['success' => false, 'errors' => 'Bạn chỉ có thể truy cập phòng trong nhà trọ mà bạn quản lý'];
            }
        } elseif ($user->role->code !== 'admin') {
            return ['success' => false, 'errors' => 'Bạn không có quyền truy cập vào tài nguyên này'];
        }

        $services = $this->monthlyServiceRepository->getRoomServices($roomId, $request->month, $request->year);

        return [
            'success' => true,
            'data' => $services
        ];
    }

    /**
     * Lưu thông tin sử dụng dịch vụ hàng tháng
     */
    public function saveRoomServiceUsage(Request $request)
    {
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
            'update_invoice' => 'sometimes|boolean',
            'unchecked_services' => 'sometimes|array',
            'unchecked_services.*' => 'sometimes|exists:room_services,id',
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
            return ['success' => false, 'errors' => $validator->errors()];
        }

        $user = Auth::user();
        $room = Room::with('house')->findOrFail($request->room_id);

        // Check authorization
        if ($user->role->code === 'manager') {
            $managedHouseIds = House::where('manager_id', $user->id)->pluck('id')->toArray();
            if (!in_array($room->house_id, $managedHouseIds)) {
                return ['success' => false, 'errors' => 'Bạn chỉ có thể cập nhật dịch vụ cho phòng trong nhà trọ mà bạn quản lý'];
            }
        } elseif ($user->role->code !== 'admin') {
            return ['success' => false, 'errors' => 'Bạn không có quyền cập nhật dịch vụ'];
        }

        try {
            $result = $this->monthlyServiceRepository->saveRoomServiceUsage($request);
            
            // Send notification if invoice was created
            if (isset($result['invoice']) && $result['invoice'] && !isset($result['updated_invoice'])) {
                $invoice = $result['invoice'];
                $this->notificationService->notifyRoomTenants(
                    $request->room_id,
                    'invoice',
                    "Hóa đơn dịch vụ tháng {$request->month}/{$request->year} đã được tạo.",
                    "/invoices/{$invoice->id}",
                );
            }
            
            return [
                'success' => true,
                'data' => $result,
                'message' => 'Dịch vụ đã được lưu' . ($request->has('update_invoice') && (bool)$request->update_invoice ? ' và hóa đơn đã được cập nhật' : '') . ' thành công.'
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => $e->getMessage()];
        }
    }

    /**
     * Lấy danh sách nhà trọ khả dụng cho quản lý dịch vụ
     */
    public function getAvailableHouses()
    {
        $user = Auth::user();

        if ($user->role->code !== 'admin' && $user->role->code !== 'manager') {
            return ['success' => false, 'errors' => 'Bạn không có quyền truy cập vào tài nguyên này'];
        }

        $houses = $this->monthlyServiceRepository->getAvailableHouses();

        return [
            'success' => true,
            'data' => [
                'houses' => $houses
            ]
        ];
    }
} 