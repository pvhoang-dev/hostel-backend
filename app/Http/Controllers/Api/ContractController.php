<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\ContractUser;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ContractController extends Controller
{
    /**
     * Check if user is authorized to manage contracts for a room
     */
    private function isAuthorizedForRoom($roomId): bool
    {
        $user = auth()->user();

        // Admin has full access
        if ($user->role->code === 'admin') {
            return true;
        }

        // For manager, check if they manage the room's house
        if ($user->role->code === 'manager') {
            // Tìm house của phòng
            $room = Room::with('house')->find($roomId);
            if (!$room || !$room->house) return false;

            // Kiểm tra xem user có phải là manager của house này không
            return $room->house->manager_id == $user->id;
        }

        return false;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $query = Contract::query();

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by room_id
        if ($request->has('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        // Filter by date ranges
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

        // Filter by deposit_status
        if ($request->has('deposit_status')) {
            $query->where('deposit_status', $request->deposit_status);
        }

        // Filter by auto_renew
        if ($request->has('auto_renew')) {
            $query->where('auto_renew', $request->auto_renew === 'true');
        }

        // Apply permission-based filtering
        if ($currentUser->role->code === 'admin') {
            // Admin sees all contracts
        } elseif ($currentUser->role->code === 'tenant') {
            // Tenant only sees their contracts
            $query->whereHas('users', function($q) use ($currentUser) {
                $q->where('users.id', $currentUser->id);
            });
        } else {
            // Manager only sees contracts for properties they manage
            $query->whereHas('room.house', function($q) use ($currentUser) {
                $q->where('houses.manager_id', $currentUser->id);
            });
        }

        // Additional user_id filtering if provided
        if ($request->has('user_id')) {
            $query->whereHas('users', function($q) use ($request) {
                $q->where('users.id', $request->user_id);
            });
        }

        // Include relationships based on request
        $with = ['room', 'creator', 'updater'];

        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('users', $includes) && !in_array('users', $with)) {
                $with[] = 'users';
            }
            if (in_array('room.house', $includes)) {
                $with[] = 'room.house';
            }
        } else {
            $with[] = 'users'; // Include users by default
        }

        $query->with($with);

        // Sorting
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

        return response()->json([
            'success' => true,
            'data' => ContractResource::collection($contracts),
            'meta' => [
                'total' => $contracts->total(),
                'per_page' => $contracts->perPage(),
                'current_page' => $contracts->currentPage(),
                'last_page' => $contracts->lastPage(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        // Check if user is tenant
        if ($currentUser->role->code === 'tenant') {
            return response()->json(['success' => false, 'message' => 'Unauthorized. As a tenant, you cannot create contracts.'], 403);
        }

        // Check if user is authorized for this room
        if (!$this->isAuthorizedForRoom($request->room_id)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized. You can only manage contracts for properties you manage.'], 403);
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
            return response()->json(['success' => false, 'message' => 'Validation Error.', 'data' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $input = $request->except('user_ids');
            $input['created_by'] = $currentUser->id;

            $contract = Contract::create($input);

            // Attach users to contract
            foreach ($request->user_ids as $userId) {
                ContractUser::create([
                    'contract_id' => $contract->id,
                    'user_id' => $userId
                ]);
            }

            DB::commit();

            // Load relationships
            $contract->load(['room', 'users', 'creator']);

            return response()->json([
                'success' => true,
                'message' => 'Contract created successfully.',
                'data' => new ContractResource($contract)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Contract creation failed.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $contract = Contract::with(['room', 'creator', 'users', 'updater'])->find($id);

        if (is_null($contract)) {
            return response()->json(['success' => false, 'message' => 'Contract not found.'], 404);
        }

        // Authorization check based on user role
        if ($currentUser->role->code === 'admin') {
            // Admin can view any contract
        } elseif ($currentUser->role->code === 'tenant') {
            // Tenant can only view their own contracts
            $isUserContract = $contract->users->contains('id', $currentUser->id);
            if (!$isUserContract) {
                return response()->json(['success' => false, 'message' => 'Unauthorized. You can only view your own contracts.'], 403);
            }
        } else {
            // Manager can only view contracts for properties they manage
            if (!$this->isAuthorizedForRoom($contract->room_id)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized. You can only view contracts for properties you manage.'], 403);
            }
        }

        return response()->json([
            'success' => true,
            'data' => new ContractResource($contract)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        // Check if user is tenant
        if ($currentUser->role->code === 'tenant') {
            return response()->json(['success' => false, 'message' => 'Unauthorized. As a tenant, you cannot update contracts.'], 403);
        }

        $contract = Contract::find($id);
        if (is_null($contract)) {
            return response()->json(['success' => false, 'message' => 'Contract not found.'], 404);
        }

        // Check if user is authorized for this room
        if (!$this->isAuthorizedForRoom($contract->room_id)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized. You can only manage contracts for properties you manage.'], 403);
        }

        // If updating room_id, check authorization for new room as well
        if ($request->has('room_id') && $request->room_id != $contract->room_id) {
            if (!$this->isAuthorizedForRoom($request->room_id)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized. You can only assign contracts to rooms in properties you manage.'], 403);
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
            return response()->json(['success' => false, 'message' => 'Validation Error.', 'data' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $input = $request->except('user_ids');
            $input['updated_by'] = $currentUser->id;

            $contract->update($input);

            // Update contract users if provided
            if ($request->has('user_ids')) {
                // Delete existing relationships
                ContractUser::where('contract_id', $contract->id)->forceDelete();

                // Create new relationships
                foreach ($request->user_ids as $userId) {
                    ContractUser::create([
                        'contract_id' => $contract->id,
                        'user_id' => $userId
                    ]);
                }
            }

            DB::commit();

            // Load relationships
            $contract->load(['room', 'creator', 'users', 'updater']);

            return response()->json([
                'success' => true,
                'message' => 'Contract updated successfully.',
                'data' => new ContractResource($contract)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Contract update failed.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        // Check if user is tenant
        if ($currentUser->role->code === 'tenant') {
            return response()->json(['success' => false, 'message' => 'Unauthorized. As a tenant, you cannot delete contracts.'], 403);
        }

        $contract = Contract::find($id);
        if (is_null($contract)) {
            return response()->json(['success' => false, 'message' => 'Contract not found.'], 404);
        }

        // Check if user is authorized for this room
        if (!$this->isAuthorizedForRoom($contract->room_id)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized. You can only manage contracts for properties you manage.'], 403);
        }

        $contract->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contract deleted successfully.'
        ]);
    }
}
