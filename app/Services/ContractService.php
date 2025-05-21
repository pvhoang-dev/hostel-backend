<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Room;
use App\Repositories\Interfaces\ContractRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ContractService
{
    protected $contractRepository;
    protected $notificationService;
    
    public function __construct(
        ContractRepositoryInterface $contractRepository,
        NotificationService $notificationService
    ) {
        $this->contractRepository = $contractRepository;
        $this->notificationService = $notificationService;
    }
    
    /**
     * Kiểm tra quyền quản lý hợp đồng cho phòng
     *
     * @param int $roomId
     * @return bool
     */
    public function isAuthorizedForRoom($roomId): bool
    {
        $user = Auth::user();
        
        if (!$user) {
            return false;
        }

        if ($user->role->code === 'admin') {
            return true;
        }

        if ($user->role->code === 'manager') {
            $room = Room::with('house')->find($roomId);
            if (!$room || !$room->house) return false;

            return $room->house->manager_id == $user->id;
        }

        return false;
    }
    
    /**
     * Lấy danh sách hợp đồng theo các bộ lọc
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getAllContracts(Request $request)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Người dùng không được xác thực', 401);
        }
        
        $filters = [
            'status' => $request->status ?? null,
            'house_id' => $request->house_id ?? null,
            'room_id' => $request->room_id ?? null,
            'start_date_from' => $request->start_date_from ?? null,
            'start_date_to' => $request->start_date_to ?? null,
            'end_date_from' => $request->end_date_from ?? null,
            'end_date_to' => $request->end_date_to ?? null,
            'deposit_status' => $request->deposit_status ?? null,
            'auto_renew' => $request->auto_renew ?? null,
            'user_id' => $request->user_id ?? null,
        ];
        
        // Áp dụng bộ lọc theo vai trò người dùng
        if ($currentUser->role->code === 'tenant') {
            $filters['user_role'] = 'tenant';
            $filters['user_id'] = $currentUser->id;
        } elseif ($currentUser->role->code === 'manager') {
            $filters['user_role'] = 'manager';
            $filters['manager_id'] = $currentUser->id;
        }
        
        // Chuẩn bị các quan hệ cần eager load
        $with = ['room', 'creator', 'updater'];
        
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('users', $includes)) {
                $with[] = 'users';
            }
            if (in_array('room.house', $includes)) {
                $with[] = 'room.house';
            }
        } else {
            $with[] = 'users';
        }
        
        // Lấy thông tin sắp xếp và phân trang
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_dir', 'desc');
        $perPage = $request->get('per_page', 10);
        
        // Lấy danh sách hợp đồng
        $contracts = $this->contractRepository->getAllWithFilters(
            $filters, 
            $with, 
            $sortField, 
            $sortDirection, 
            $perPage
        );
        
        return $contracts;
    }
    
    /**
     * Lấy thông tin hợp đồng theo ID
     *
     * @param int $id
     * @return Contract
     * @throws \Exception
     */
    public function getContractById(int $id)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Người dùng không được xác thực', 401);
        }
        
        $contract = $this->contractRepository->getById($id, ['room.house', 'creator', 'users', 'updater']);
        
        if (is_null($contract)) {
            throw new \Exception('Hợp đồng không tồn tại', 404);
        }
        
        if ($currentUser->role->code === 'tenant') {
            $isUserContract = $contract->users->contains('id', $currentUser->id);
            if (!$isUserContract) {
                throw new \Exception('Bạn chỉ có thể xem hợp đồng của chính mình', 403);
            }
        } elseif (!$this->isAuthorizedForRoom($contract->room_id)) {
            throw new \Exception('Bạn chỉ có thể xem hợp đồng cho phòng mà bạn quản lý', 403);
        }
        
        return $contract;
    }
    
    /**
     * Tạo hợp đồng mới
     *
     * @param Request $request
     * @return Contract
     * @throws \Exception
     */
    public function createContract(Request $request)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Người dùng không được xác thực', 403);
        }
        
        if ($currentUser->role->code === 'tenant') {
            throw new \Exception('Bạn không có quyền tạo hợp đồng', 403);
        }
        
        if (!$this->isAuthorizedForRoom($request->room_id)) {
            throw new \Exception('Bạn chỉ có thể quản lý hợp đồng cho tài sản mà bạn quản lý', 403);
        }
        
        $validator = Validator::make($request->all(), [
            'room_id' => 'required|exists:rooms,id',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'monthly_price' => 'required|integer|min:0',
            'deposit_amount' => 'required|integer|min:0',
            'notice_period' => 'sometimes|integer|min:0',
            'deposit_status' => 'sometimes|in:held,refunded,partial',
            'status' => 'sometimes|in:draft,active,terminated,expired',
            'auto_renew' => 'sometimes|boolean',
        ], [
            'room_id.required' => 'Phòng là bắt buộc',
            'room_id.exists' => 'Phòng không tồn tại',
            'user_ids.required' => 'Người thuê là bắt buộc',
            'user_ids.array' => 'Người thuê phải là mảng',
            'user_ids.*.exists' => 'Một hoặc nhiều người thuê không tồn tại',
            'start_date.required' => 'Ngày bắt đầu là bắt buộc',
            'start_date.date' => 'Ngày bắt đầu không hợp lệ',
            'end_date.required' => 'Ngày kết thúc là bắt buộc',
            'end_date.date' => 'Ngày kết thúc không hợp lệ',
            'end_date.after' => 'Ngày kết thúc phải sau ngày bắt đầu',
            'monthly_price.required' => 'Giá thuê tháng là bắt buộc',
            'monthly_price.integer' => 'Giá thuê tháng phải là số',
            'monthly_price.min' => 'Giá thuê tháng phải lớn hơn 0', 
            'deposit_amount.required' => 'Tiền cọc là bắt buộc',
            'deposit_amount.integer' => 'Tiền cọc phải là số',
            'deposit_amount.min' => 'Tiền cọc phải lớn hơn 0',
            'notice_period.integer' => 'Thời gian thông báo phải là số',
            'notice_period.min' => 'Thời gian thông báo phải lớn hơn 0',
            'deposit_status.in' => 'Trạng thái cọc không hợp lệ',
            'status.in' => 'Trạng thái hợp đồng không hợp lệ',
            'auto_renew.boolean' => 'Tự động gia hạn phải là boolean',
        ]);
        
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
        
        if ($request->has('auto_renew') && $request->auto_renew) {
            $validator2 = Validator::make($request->all(), [
                'time_renew' => 'required|integer|min:1',
            ], [
                'time_renew.integer' => 'Thời gian gia hạn phải là số nguyên',
                'time_renew.min' => 'Thời gian gia hạn phải lớn hơn 0',
            ]);

            if ($validator2->fails()) {
                throw ValidationException::withMessages($validator2->errors()->toArray());
            }
        }

        $input = $request->except('user_ids');
        $input['created_by'] = $currentUser->id;
        $input['load_relations'] = true;
        
        // Nếu auto_renew là true mà không cung cấp time_renew
        if (isset($input['auto_renew']) && $input['auto_renew'] && (!isset($input['time_renew']) || $input['time_renew'] <= 0)) {
            // Tính time_renew từ khoảng cách giữa start_date và end_date
            $startDate = \Carbon\Carbon::parse($input['start_date']);
            $endDate = \Carbon\Carbon::parse($input['end_date']);
            $monthsDiff = $endDate->diffInMonths($startDate);
            
            // Nếu khoảng cách > 0 thì sử dụng, nếu không thì mặc định là 6 tháng
            $input['time_renew'] = $monthsDiff > 0 ? $monthsDiff : 6;
        }
        
        return $this->contractRepository->create($input, $request->user_ids);
    }
    
    /**
     * Cập nhật hợp đồng
     *
     * @param Request $request
     * @param int $id
     * @return Contract
     * @throws \Exception
     */
    public function updateContract(Request $request, int $id)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Người dùng không được xác thực', 401);
        }
        
        if ($currentUser->role->code === 'tenant') {
            throw new \Exception('Bạn không có quyền cập nhật hợp đồng', 403);
        }
        
        $contract = $this->contractRepository->getById($id);
        if (is_null($contract)) {
            throw new \Exception('Hợp đồng không tồn tại', 404);
        }
        
        if (!$this->isAuthorizedForRoom($contract->room_id)) {
            throw new \Exception('Bạn chỉ có thể cập nhật hợp đồng cho phòng mà bạn quản lý', 403);
        }
        
        if ($request->has('room_id') && $request->room_id != $contract->room_id) {
            if (!$this->isAuthorizedForRoom($request->room_id)) {
                throw new \Exception('Bạn chỉ có thể gán hợp đồng cho phòng trong tài sản mà bạn quản lý', 403);
            }
        }
        
        $validator = Validator::make($request->all(), [
            'room_id' => 'sometimes|exists:rooms,id',
            'user_ids' => 'sometimes|array',
            'user_ids.*' => 'exists:users,id',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'monthly_price' => 'sometimes|integer|min:0',
            'deposit_amount' => 'sometimes|integer|min:0',
            'notice_period' => 'sometimes|integer|min:0',
            'deposit_status' => 'sometimes|in:held,refunded,partial',
            'termination_reason' => 'sometimes|string|nullable',
            'status' => 'sometimes|in:draft,active,terminated,expired',
            'auto_renew' => 'sometimes|boolean',
        ], [
            'room_id.exists' => 'Phòng không tồn tại',
            'user_ids.*.exists' => 'Một hoặc nhiều người thuê không tồn tại',
            'start_date.date' => 'Ngày bắt đầu không hợp lệ',
            'end_date.date' => 'Ngày kết thúc không hợp lệ',
            'end_date.after' => 'Ngày kết thúc phải sau ngày bắt đầu',
            'monthly_price.integer' => 'Giá thuê tháng phải là số',
            'monthly_price.min' => 'Giá thuê tháng phải lớn hơn 0',
            'deposit_amount.integer' => 'Tiền cọc phải là số',
            'deposit_amount.min' => 'Tiền cọc phải lớn hơn 0',
            'notice_period.integer' => 'Thời gian thông báo phải là số',
            'notice_period.min' => 'Thời gian thông báo phải lớn hơn 0',
            'termination_reason.string' => 'Lý do huỷ bỏ phải là chuỗi',
            'status.in' => 'Trạng thái hợp đồng không hợp lệ',
            'auto_renew.boolean' => 'Tự động gia hạn phải là boolean',
        ]);
        
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        if ($request->has('auto_renew') && $request->auto_renew) {
            $validator2 = Validator::make($request->all(), [
                'time_renew' => 'required|integer|min:1',
            ], [
                'time_renew.integer' => 'Thời gian gia hạn phải là số nguyên',
                'time_renew.min' => 'Thời gian gia hạn phải lớn hơn 0',
            ]);
            
            if ($validator2->fails()) {
                throw ValidationException::withMessages($validator2->errors()->toArray());
            }
        }
        
        $input = $request->except(['user_ids', 'created_by']);
        $input['updated_by'] = $currentUser->id;
        $input['load_relations'] = true;
        
        // Nếu auto_renew là true mà không cung cấp time_renew
        if (isset($input['auto_renew']) && $input['auto_renew'] && (!isset($input['time_renew']) || $input['time_renew'] <= 0)) {
            // Sử dụng time_renew hiện tại nếu có
            if ($contract->time_renew > 0) {
                $input['time_renew'] = $contract->time_renew;
            } else {
                // Tính time_renew từ khoảng cách giữa start_date và end_date
                $startDate = \Carbon\Carbon::parse($request->input('start_date', $contract->start_date));
                $endDate = \Carbon\Carbon::parse($request->input('end_date', $contract->end_date));
                $monthsDiff = $endDate->diffInMonths($startDate);
                
                // Nếu khoảng cách > 0 thì sử dụng, nếu không thì mặc định là 6 tháng
                $input['time_renew'] = $monthsDiff > 0 ? $monthsDiff : 6;
            }
        }
        
        $userIds = $request->has('user_ids') ? $request->user_ids : null;
        
        return $this->contractRepository->update($contract, $input, $userIds);
    }
    
    /**
     * Xóa hợp đồng
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function deleteContract(int $id)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Người dùng không được xác thực', 401);
        }
        
        if ($currentUser->role->code === 'tenant') {
            throw new \Exception('Bạn không có quyền xóa hợp đồng', 403);
        }
        
        $contract = $this->contractRepository->getById($id, ['room.house']);
        if (is_null($contract)) {
            throw new \Exception('Hợp đồng không tồn tại', 404);
        }
        
        if (!$this->isAuthorizedForRoom($contract->room_id)) {
            throw new \Exception('Bạn chỉ có thể xóa hợp đồng cho phòng mà bạn quản lý', 403);
        }
        
        $result = $this->contractRepository->delete($contract);
        
        if ($result) {
            $this->notificationService->notifyRoomTenants(
                $contract->room_id,
                'contract',
                "Hợp đồng thuê phòng {$contract->room->room_number} tại {$contract->room->house->name} đã được xóa.",
                "/contracts/{$contract->id}",
                false
            );
        }
        
        return $result;
    }
    
    /**
     * Lấy danh sách người thuê có thể thuê phòng
     *
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Collection
     * @throws \Exception
     */
    public function getAvailableTenants(Request $request)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            throw new \Exception('Người dùng không được xác thực', 401);
        }
        
        if ($currentUser->role->code === 'tenant') {
            throw new \Exception('Bạn không có quyền truy cập vào tài nguyên này', 403);
        }
        
        $validator = Validator::make($request->all(), [
            'room_id' => 'required|exists:rooms,id',
        ]);
        
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
        
        // Kiểm tra quyền truy cập phòng
        if (!$this->isAuthorizedForRoom($request->room_id)) {
            throw new \Exception('Bạn chỉ có thể truy cập phòng mà bạn quản lý', 403);
        }
        
        return $this->contractRepository->getAvailableTenants($request->room_id);
    }
} 