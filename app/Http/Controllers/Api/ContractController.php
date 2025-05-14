<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ContractResource;
use App\Http\Resources\UserResource;
use App\Models\Contract;
use App\Models\ContractUser;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ContractController extends BaseController
{
    /**
     * Check if user is authorized to manage contracts for a room
     */
    private function isAuthorizedForRoom($roomId): bool
    {
        $user = auth()->user();

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

    public function index(Request $request): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        $query = Contract::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('house_id')) {
            $query->whereHas('room', function ($q) use ($request) {
                $q->where('house_id', $request->house_id);
            });
        }

        if ($request->has('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        if ($request->has('start_date_from')) {
            $query->where('start_date', '>=', $request->start_date_from);
        }

        if ($request->has('start_date_to')) {
            $query->where('start_date', '<=', $request->start_date_to);
        }

        if ($request->has('end_date_from')) {
            $query->where('end_date', '>=', $request->end_date_from);
        }

        if ($request->has('end_date_to')) {
            $query->where('end_date', '<=', $request->end_date_to);
        }

        if ($request->has('deposit_status')) {
            $query->where('deposit_status', $request->deposit_status);
        }

        if ($request->has('auto_renew')) {
            $query->where('auto_renew', $request->auto_renew === 'true');
        }

        if ($currentUser->role->code === 'tenant') {
            $query->whereHas('users', function ($q) use ($currentUser) {
                $q->where('users.id', $currentUser->id);
            });
        } elseif ($currentUser->role->code === 'manager') {
            $query->whereHas('room.house', function ($q) use ($currentUser) {
                $q->where('houses.manager_id', $currentUser->id);
            });
        }

        if ($request->has('user_id')) {
            $query->whereHas('users', function ($q) use ($request) {
                $q->where('users.id', $request->user_id);
            });
        }

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

        $query->with($with);

        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_dir', 'desc');
        $allowedSortFields = ['created_at', 'start_date', 'end_date', 'monthly_price'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        $perPage = $request->get('per_page', 10);
        $contracts = $query->paginate($perPage);

        return $this->sendResponse(
            ContractResource::collection($contracts)->response()->getData(true),
            'Contracts retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 403);
        }

        if ($currentUser->role->code === 'tenant') {
            return $this->sendError('Unauthorized. As a tenant, you cannot create contracts.', [], 403);
        }

        if (!$this->isAuthorizedForRoom($request->room_id)) {
            return $this->sendError('Unauthorized. You can only manage contracts for properties you manage.', [], 403);
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
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        try {
            DB::beginTransaction();

            $input = $request->except('user_ids');
            $input['created_by'] = $currentUser->id;

            $contract = Contract::create($input);

            foreach ($request->user_ids as $userId) {
                ContractUser::create([
                    'contract_id' => $contract->id,
                    'user_id' => $userId
                ]);
            }

            // Nếu hợp đồng có trạng thái active, cập nhật phòng thành used
            if ($contract->status === 'active') {
                Room::where('id', $contract->room_id)->update(['status' => 'used']);
            }

            DB::commit();

            $contract->load(['room', 'users', 'creator']);

            return $this->sendResponse(
                new ContractResource($contract),
                'Contract created successfully.'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Contract creation failed.', ['error' => $e->getMessage()], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        $contract = Contract::with(['room.house', 'creator', 'users', 'updater'])->find($id);

        if (is_null($contract)) {
            return $this->sendError('Contract not found.', [], 404);
        }

        if ($currentUser->role->code === 'tenant') {
            $isUserContract = $contract->users->contains('id', $currentUser->id);
            if (!$isUserContract) {
                return $this->sendError('Unauthorized. You can only view your own contracts.', [], 403);
            }
        } elseif (!$this->isAuthorizedForRoom($contract->room_id)) {
            return $this->sendError('Unauthorized. You can only view contracts for properties you manage.', [], 403);
        }

        return $this->sendResponse(
            new ContractResource($contract),
            'Contract retrieved successfully.'
        );
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        if ($currentUser->role->code === 'tenant') {
            return $this->sendError('Unauthorized. As a tenant, you cannot update contracts.', [], 403);
        }

        $contract = Contract::find($id);
        if (is_null($contract)) {
            return $this->sendError('Contract not found.', [], 404);
        }

        if (!$this->isAuthorizedForRoom($contract->room_id)) {
            return $this->sendError('Unauthorized. You can only manage contracts for properties you manage.', [], 403);
        }

        if ($request->has('room_id') && $request->room_id != $contract->room_id) {
            if (!$this->isAuthorizedForRoom($request->room_id)) {
                return $this->sendError('Unauthorized. You can only assign contracts to rooms in properties you manage.', [], 403);
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
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        try {
            DB::beginTransaction();

            $input = $request->except(['user_ids', 'created_by']);
            $input['updated_by'] = $currentUser->id;

            $oldStatus = $contract->status;
            $contract->update($input);

            if ($request->has('user_ids')) {
                ContractUser::where('contract_id', $contract->id)->forceDelete();

                foreach ($request->user_ids as $userId) {
                    ContractUser::create([
                        'contract_id' => $contract->id,
                        'user_id' => $userId
                    ]);
                }
            }

            // Nếu trạng thái hợp đồng thay đổi thành active, cập nhật phòng thành used
            if ($contract->status === 'active' && $oldStatus !== 'active') {
                Room::where('id', $contract->room_id)->update(['status' => 'used']);
            }
            // Nếu trạng thái hợp đồng thay đổi từ active sang trạng thái khác, cập nhật phòng thành available
            elseif ($oldStatus === 'active' && $contract->status !== 'active') {
                // Kiểm tra nếu không còn hợp đồng active nào khác cho phòng này
                $activeContractsCount = Contract::where('room_id', $contract->room_id)
                    ->where('id', '!=', $contract->id)
                    ->where('status', 'active')
                    ->count();
                
                if ($activeContractsCount === 0) {
                    Room::where('id', $contract->room_id)->update(['status' => 'available']);
                }
            }

            DB::commit();

            $contract->load(['room', 'creator', 'users', 'updater']);

            return $this->sendResponse(
                new ContractResource($contract),
                'Contract updated successfully.'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Contract update failed.', ['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        if ($currentUser->role->code === 'tenant') {
            return $this->sendError('Unauthorized. As a tenant, you cannot delete contracts.', [], 403);
        }

        $contract = Contract::find($id);
        if (is_null($contract)) {
            return $this->sendError('Contract not found.', [], 404);
        }

        if (!$this->isAuthorizedForRoom($contract->room_id)) {
            return $this->sendError('Unauthorized. You can only manage contracts for properties you manage.', [], 403);
        }

        try {
            DB::beginTransaction();
            
            // Lưu trạng thái hợp đồng và room_id trước khi xóa
            $wasActive = $contract->status === 'active';
            $roomId = $contract->room_id;
            
            // Xóa hợp đồng
            $contract->delete();
            
            // Nếu hợp đồng là active, kiểm tra xem còn hợp đồng active nào cho phòng này không
            if ($wasActive) {
                $activeContractsCount = Contract::where('room_id', $roomId)
                    ->where('status', 'active')
                    ->count();
                
                // Nếu không còn hợp đồng active nào, cập nhật phòng thành available
                if ($activeContractsCount === 0) {
                    Room::where('id', $roomId)->update(['status' => 'available']);
                }
            }
            
            DB::commit();
            
            return $this->sendResponse([], 'Contract deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Contract deletion failed.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get available tenants for a room
     */
    public function getAvailableTenants(Request $request): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }
        
        if ($currentUser->role->code === 'tenant') {
            return $this->sendError('Unauthorized. As a tenant, you cannot access this resource.', [], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'room_id' => 'required|exists:rooms,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }
        
        // Kiểm tra quyền truy cập phòng
        if (!$this->isAuthorizedForRoom($request->room_id)) {
            return $this->sendError('Unauthorized. You can only access rooms you manage.', [], 403);
        }
        
        // Tạo query cơ bản
        $query = \App\Models\User::whereHas('role', function($query) {
            $query->where('code', 'tenant');
        });
        
        // Chỉ lấy người không có hợp đồng active
        $query->whereDoesntHave('contracts', function($query) {
            $query->where('status', 'active');
        });
        
        // Lấy danh sách người thuê
        $tenants = $query->with('role')->get();
        
        return $this->sendResponse(
            UserResource::collection($tenants),
            'Available tenants retrieved successfully.'
        );
    }
}
