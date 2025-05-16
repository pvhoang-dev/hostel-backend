<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\RoomResource;
use App\Models\House;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class RoomController extends BaseController
{
    /**
     * Display a listing of the rooms.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Room::query();

        // Apply role-based filters
        if ($user) {
            if ($user->role->code === 'manager') {
                // Managers can only see rooms in houses they manage
                $managedHouseIds = House::where('manager_id', $user->id)->pluck('id');
                $query->whereIn('house_id', $managedHouseIds);
            } elseif ($user->role->code === 'tenant') {
                // Tenants can only see rooms they are currently renting through active contracts
                $activeContractRoomIds = $user->contracts()
                    ->where('status', 'active')
                    ->pluck('room_id')
                    ->toArray();
                
                if (empty($activeContractRoomIds)) {
                    // If tenant doesn't have any active contracts, return empty result
                    return $this->sendResponse(
                        ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 15]],
                        'Không tìm thấy phòng cho khách hàng này.'
                    );
                }
                
                $query->whereIn('id', $activeContractRoomIds);
            }
        }
        // Admins can see all rooms (no filter)

        // Apply additional filters
        if ($request->has('house_id')) {
            $query->where('house_id', $request->house_id);
        }

        if ($request->has('room_number')) {
            $query->where('room_number', 'like', '%' . $request->room_number . '%');
        }

        if ($request->has('capacity')) {
            $query->where('capacity', $request->capacity);
        }

        if ($request->has('min_capacity')) {
            $query->where('capacity', '>=', $request->min_capacity);
        }

        if ($request->has('max_capacity')) {
            $query->where('capacity', '<=', $request->max_capacity);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('min_price')) {
            $query->where('base_price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('base_price', '<=', $request->max_price);
        }

        // Filter by date ranges
        if ($request->has('created_from')) {
            $query->where('created_at', '>=', $request->created_from);
        }

        if ($request->has('created_to')) {
            $query->where('created_at', '<=', $request->created_to);
        }

        if ($request->has('updated_from')) {
            $query->where('updated_at', '>=', $request->updated_from);
        }

        if ($request->has('updated_to')) {
            $query->where('updated_at', '<=', $request->updated_to);
        }

        // Include relationships
        $with = ['house'];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('services', $includes)) $with[] = 'services';
            if (in_array('contracts', $includes)) $with[] = 'contracts';
            if (in_array('currentContract', $includes)) $with[] = 'currentContract';
            if (in_array('creator', $includes)) $with[] = 'creator';
            if (in_array('updater', $includes)) $with[] = 'updater';
        }

        // Sorting
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_dir', 'desc');
        $allowedSortFields = ['id', 'house_id', 'room_number', 'capacity', 'base_price', 'status', 'created_at', 'updated_at'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $rooms = $query->with($with)->paginate($perPage);

        return $this->sendResponse(
            RoomResource::collection($rooms)->response()->getData(true),
            'Phòng đã được lấy thành công.'
        );
    }

    /**
     * Store a newly created room in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return $this->sendError('Lỗi xác thực.', [], 401);
        }

        $input = $request->all();
        $validator = Validator::make($input, [
            'house_id' => 'required|exists:houses,id',
            'room_number' => [
                'required',
                'string',
                'max:10',
                Rule::unique('rooms')->where(function ($query) use ($request) {
                    return $query->where('house_id', $request->house_id);
                }),
            ],
            'capacity' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'status' => 'sometimes|string|max:10',
            'base_price' => 'required|integer|min:0',
        ], [
            'house_id.required' => 'Mã nhà không được để trống.',
            'house_id.exists' => 'Mã nhà không tồn tại.',
            'room_number.required' => 'Mã phòng không được để trống.',
            'room_number.string' => 'Mã phòng phải là một chuỗi.',
            'room_number.max' => 'Mã phòng không được vượt quá 10 ký tự.',
            'room_number.unique' => 'Mã phòng đã tồn tại.',
            'capacity.required' => 'Số lượng khách không được để trống.',
            'capacity.integer' => 'Số lượng khách phải là một số nguyên.',
            'capacity.min' => 'Số lượng khách phải lớn hơn 0.',
            'base_price.required' => 'Giá cơ bản không được để trống.',
            'base_price.integer' => 'Giá cơ bản phải là một số nguyên.',
            'base_price.min' => 'Giá cơ bản phải lớn hơn 0.',
            'status.string' => 'Trạng thái phải là một chuỗi.',
            'status.max' => 'Trạng thái không được vượt quá 10 ký tự.',
            'description.string' => 'Mô tả phải là một chuỗi.',
            'description.max' => 'Mô tả không được vượt quá 255 ký tự.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Lỗi dữ liệu.', $validator->errors(), 422);
        }

        $input['created_by'] = $currentUser->id;
        $input['updated_by'] = $currentUser->id;

        $room = Room::create($input);
        $room->load('house');

        return $this->sendResponse(new RoomResource($room), 'Phòng đã được tạo thành công.');
    }

    /**
     * Display the specified room.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();
        $room = Room::with(['house'])->find($id);

        if (is_null($room)) {
            return $this->sendError('Phòng không tồn tại.');
        }

        // Kiểm tra quyền truy cập
        if ($user && $user->role->code === 'tenant') {
            // Tenant chỉ có thể xem phòng của họ thông qua hợp đồng active
            $hasActiveContract = $user->contracts()
                ->where('status', 'active')
                ->where('room_id', $id)
                ->exists();
                
            if (!$hasActiveContract) {
                return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn chỉ có thể xem phòng của mình.'], 403);
            }
        }

        return $this->sendResponse(new RoomResource($room), 'Phòng đã được lấy thành công.');
    }

    /**
     * Update the specified room in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $room = Room::find($id);

        if (is_null($room)) {
            return $this->sendError('Phòng không tồn tại.');
        }

        $currentUser = Auth::user();
        if (!$currentUser) {
            return $this->sendError('Lỗi xác thực.', [], 401);
        }

        // Check if user is admin or the house manager
        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $room->house->manager_id === $currentUser->id;

        if (!$isAdmin && !$isManager) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Chỉ quản trị viên hoặc quản lý nhà mới có thể cập nhật phòng.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'house_id' => 'sometimes|exists:houses,id',
            'room_number' => [
                'sometimes',
                'required',
                'string',
                'max:10',
                Rule::unique('rooms')->where(function ($query) use ($request, $room) {
                    return $query->where('house_id', $request->input('house_id', $room->house_id));
                })->ignore($id),
            ],
            'capacity' => 'sometimes|required|integer|min:1',
            'description' => 'nullable|string',
            'status' => 'sometimes|string|max:10',
            'base_price' => 'sometimes|required|integer|min:0',
        ], [
            'house_id.exists' => 'Mã nhà không tồn tại.',
            'room_number.required' => 'Mã phòng không được để trống.',
            'room_number.string' => 'Mã phòng phải là một chuỗi.',
            'room_number.max' => 'Mã phòng không được vượt quá 10 ký tự.',
            'room_number.unique' => 'Mã phòng đã tồn tại.',
            'capacity.required' => 'Số lượng khách không được để trống.',
            'capacity.integer' => 'Số lượng khách phải là một số nguyên.',
            'capacity.min' => 'Số lượng khách phải lớn hơn 0.',
            'base_price.required' => 'Giá cơ bản không được để trống.',
            'base_price.integer' => 'Giá cơ bản phải là một số nguyên.',
            'base_price.min' => 'Giá cơ bản phải lớn hơn 0.',
            'status.string' => 'Trạng thái phải là một chuỗi.',
            'status.max' => 'Trạng thái không được vượt quá 10 ký tự.',
            'description.string' => 'Mô tả phải là một chuỗi.',
            'description.max' => 'Mô tả không được vượt quá 255 ký tự.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Lỗi dữ liệu.', $validator->errors(), 422);
        }

        // If not admin, restrict house_id from being changed
        if (!$isAdmin && isset($request->house_id) && $request->house_id != $room->house_id) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Chỉ quản trị viên mới có thể thay đổi nhà.'], 403);
        }

        $input = $request->all();
        $input['updated_by'] = $currentUser->id;

        $room->update($input);
        $room->load('house');

        return $this->sendResponse(new RoomResource($room), 'Phòng đã được cập nhật thành công.');
    }

    /**
     * Remove the specified room from storage.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $room = Room::find($id);

        if (is_null($room)) {
            return $this->sendError('Phòng không tồn tại.');
        }

        $currentUser = Auth::user();
        if (!$currentUser) {
            return $this->sendError('Lỗi xác thực.', [], 401);
        }

        // Only admin or house manager can delete rooms
        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $room->house->manager_id === $currentUser->id;

        if (!$isAdmin && !$isManager) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Chỉ quản trị viên hoặc quản lý nhà mới có thể xóa phòng.'], 403);
        }

        $room->delete();

        return $this->sendResponse([], 'Phòng đã được xóa thành công.');
    }
}
