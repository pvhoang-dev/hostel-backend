<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends BaseController
{
    /**
     * Display a listing of the users.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = Auth::user();
        $query = User::query();

        // Check if this is a request to get recipients for creating requests
        $isForRequestRecipients = $request->has('for_requests') && $request->for_requests === 'true';

        // Apply role-based access control
        if ($currentUser->role?->code === 'admin') {
            // Admin có thể thấy tất cả người dùng
            if ($isForRequestRecipients) {
                // Không cần lọc cho admin khi họ tạo request
                // Admin có thể gửi cho bất kỳ ai
            }
            // Không filter gì cả, admin thấy tất cả
        } elseif ($currentUser->role?->code === 'manager') {
            // Nếu đây là yêu cầu lấy danh sách người nhận cho Request
            if ($isForRequestRecipients) {
                // Cho phép manager lấy tất cả admin và tenant từ nhà họ quản lý
                $managedHouseIds = $currentUser->housesManaged()->pluck('id')->toArray();
                
                $query->where(function ($q) use ($currentUser, $managedHouseIds) {
                    // Manager có thể thấy admin
                    $q->whereHas('role', function ($roleQuery) {
                        $roleQuery->where('code', 'admin');
                    })
                    // Hoặc tenant từ nhà họ quản lý
                    ->orWhere(function ($q2) use ($managedHouseIds, $currentUser) {
                        $q2->whereHas('role', function ($roleQuery) {
                            $roleQuery->where('code', 'tenant');
                        })
                        ->whereHas('contracts', function ($contractQuery) use ($managedHouseIds) {
                            $contractQuery->whereHas('room', function ($roomQuery) use ($managedHouseIds) {
                                $roomQuery->whereIn('house_id', $managedHouseIds);
                            });
                        });
                    });
                });
            } else {
                // Logic thông thường cho manager khi không phải lấy danh sách người nhận
                // Manager can see their own profile and tenants from houses they manage
                $managedHouseIds = $currentUser->housesManaged()->pluck('id')->toArray();

                $query->where(function ($q) use ($currentUser, $managedHouseIds) {
                    // Manager can see their own profile
                    $q->where('id', $currentUser->id)
                        // Or tenants from contracts in rooms of houses they manage
                        ->orWhere(function ($q2) use ($managedHouseIds) {
                            $q2->whereHas('role', function ($roleQuery) {
                                $roleQuery->where('code', 'tenant');
                            })
                                ->whereHas('contracts', function ($contractQuery) use ($managedHouseIds) {
                                    $contractQuery->whereHas('room', function ($roomQuery) use ($managedHouseIds) {
                                        $roomQuery->whereIn('house_id', $managedHouseIds);
                                    });
                                });
                        });
                });
            }
        } else {
            // Logic cho tenant
            if ($isForRequestRecipients) {
                // Đơn giản hóa truy vấn để tenant dễ tìm thấy manager
                // Tìm tất cả manager quản lý nhà mà tenant đang có hợp đồng
                $contracts = $currentUser->contracts()->where('status', 'active')->with('room.house')->get();
                $houseIds = [];
                
                // Lấy tất cả house_id mà tenant đang ở
                foreach($contracts as $contract) {
                    if ($contract->room && $contract->room->house) {
                        $houseIds[] = $contract->room->house->id;
                    }
                }
                
                // Lấy tất cả manager quản lý các nhà đó
                $query->whereHas('role', function ($roleQuery) {
                    $roleQuery->where('code', 'manager');
                })
                ->whereHas('housesManaged', function ($houseQuery) use ($houseIds) {
                    $houseQuery->whereIn('id', $houseIds);
                });
            } else {
                // Other users (tenants) can only see their own profile
                $query->where('id', $currentUser->id);
            }
        }

        // Apply filters
        if ($request->has('username')) {
            $query->where('username', 'like', '%' . $request->username . '%');
        }

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->has('email')) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }

        if ($request->has('phone_number')) {
            $query->where('phone_number', 'like', '%' . $request->phone_number . '%');
        }

        if ($request->has('hometown')) {
            $query->where('hometown', 'like', '%' . $request->hometown . '%');
        }

        if ($request->has('identity_card')) {
            $query->where('identity_card', 'like', '%' . $request->identity_card . '%');
        }

        if ($request->has('vehicle_plate')) {
            $query->where('vehicle_plate', 'like', '%' . $request->vehicle_plate . '%');
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        // Date range filters
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

        if ($request->has('role')) {
            $roleCodes = is_array($request->role) ? $request->role : explode(',', $request->role);
            $query->whereHas('role', function ($query) use ($roleCodes) {
                $query->whereIn('code', $roleCodes);
            });
        }

        // Include relationships
        $with = ['role'];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('contracts', $includes)) $with[] = 'contracts';
            if (in_array('managedHouses', $includes)) $with[] = 'housesManaged';
            
            // Thêm mới: Bao gồm phòng và nhà cho request
            if (in_array('house', $includes) || in_array('room', $includes)) {
                // Đối với ứng dụng lấy thông tin cho request form, luôn load đầy đủ dữ liệu
                if ($request->has('for_requests') && $request->for_requests === 'true') {
                    // Load hợp đồng đang active cho tenant
                    $query->with(['contracts' => function($q) {
                        $q->where('status', 'active');
                    }]);
                    
                    // Load thông tin phòng và nhà từ contract
                    $query->with(['contracts.room.house']);
                    
                    // Load thông tin nhà đang quản lý cho manager
                    $query->with(['housesManaged']);
                    
                    // Đảm bảo eager load nested relationship cho cả admin và manager
                    if ($currentUser->role?->code === 'admin' || $currentUser->role?->code === 'manager') {
                        // Nếu đang lấy thông tin để hiển thị trong form request, lấy đầy đủ thông tin
                        $query->where(function($q) {
                            // Lấy tenant với thông tin phòng và nhà
                            $q->whereHas('role', function($roleQ) {
                                $roleQ->where('code', 'tenant');
                            })->with(['contracts' => function($contractQ) {
                                $contractQ->where('status', 'active')
                                          ->with('room.house');
                            }])
                            // Hoặc lấy manager với thông tin nhà
                            ->orWhereHas('role', function($roleQ) {
                                $roleQ->where('code', 'manager');
                            })->with('housesManaged')
                            // Hoặc lấy admin
                            ->orWhereHas('role', function($roleQ) {
                                $roleQ->where('code', 'admin');
                            });
                        });
                    }
                }
            }
        }

        // Lọc người dùng không có hợp đồng active
        if ($request->has('without_active_contract') && $request->without_active_contract === 'true') {
            $query->whereDoesntHave('contracts', function ($query) {
                $query->where('status', 'active');
            });
        }

        // Sorting
        $sortField = $request->get('sort_by', 'id');
        $sortDirection = $request->get('sort_dir', 'asc');
        $allowedSortFields = ['id', 'username', 'name', 'email', 'status', 'role_id', 'created_at', 'updated_at'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('id', 'asc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $users = $query->with($with)->paginate($perPage);

        return $this->sendResponse(
            UserResource::collection($users)->response()->getData(true),
            'Users retrieved successfully.'
        );
    }

    /**
     * Store a newly created user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = Auth::user();
        $input = $request->all();

        // Tenants cannot create users
        if ($currentUser->role?->code === 'tenant') {
            return $this->sendError('Người thuê không có quyền tạo người dùng mới.');
        }

        // Managers can only create tenant users
        if ($currentUser->role?->code === 'manager') {
            $tenantRoleId = Role::where('code', 'tenant')->first()?->id;

            // If role_id is not specified or is not tenant
            if (!isset($input['role_id']) || $input['role_id'] != $tenantRoleId) {
                return $this->sendError('Quản lý chỉ có thể tạo người dùng với vai trò người thuê.');
            }
        }

        $validator = Validator::make($input, [
            'username'                 => 'required|string|unique:users,username',
            'name'                     => 'required|string',
            'email'                    => 'required|email|unique:users,email',
            'password'                 => 'required|string|min:6',
            'phone_number'             => 'required|string|regex:/^0\d{9}$/',
            'hometown'                 => 'nullable|string',
            'identity_card'            => 'nullable|string',
            'vehicle_plate'            => 'nullable|string',
            'status'                   => 'sometimes|string',
            'role_id'                  => 'nullable|exists:roles,id',
            'avatar_url'               => 'nullable|string',
            'notification_preferences' => 'nullable',
        ], [
            'username.required' => 'Bắt buộc điền username.',
            'username.unique'   => 'Username đã tồn tại.',
            'name.required'     => 'Bắt buộc điền tên.',
            'email.required'    => 'Bắt buộc điền email.',
            'email.email'       => 'Email không đúng đinh dạng.',
            'email.unique'      => 'Email đã tồn tại.',
            'phone_number.required' => 'Bắt buộc điền số điện thoại.',
            'phone_number.regex' => 'Nhập đúng định dạng số điện thoại.',
            'role_id.exists'    => 'Không tồn tại role đó.',
            'password.required' => 'Bắt buộc điền mật khẩu.',
            'password.min'      => 'Mật khẩu phải có ít nhất 6 kí tự.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $input['password'] = Hash::make($input['password']);

        $user = User::create($input);

        return $this->sendResponse(new UserResource($user), 'User created successfully.');
    }

    /**
     * Display the specified user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(string $id): JsonResponse
    {
        $currentUser = Auth::user();
        $user = User::with('role')->find($id);

        if (is_null($user)) {
            return $this->sendError('User not found.');
        }

        // Apply role-based access control
        if ($currentUser->role?->code === 'admin') {
            // Admin can see any user
        } elseif ($currentUser->role?->code === 'manager') {
            // Manager can see themselves
            if ($currentUser->id == $user->id) {
                // Allow access
            }
            // Or manager can see tenants from houses they manage
            elseif ($user->role?->code === 'tenant') {
                $managedHouseIds = $currentUser->housesManaged()->pluck('id')->toArray();

                // Check if user is a tenant in a managed house
                $canView = $user->contracts()
                    ->whereHas('room', function ($roomQuery) use ($managedHouseIds) {
                        $roomQuery->whereIn('house_id', $managedHouseIds);
                    })
                    ->exists();

                if (!$canView) {
                    return $this->sendError('Bạn không có quyền xem thông tin người dùng này.', []);
                }
            } else {
                return $this->sendError('Bạn không có quyền xem thông tin người dùng này.', []);
            }
        } else {
            // Tenants can see their own profile
            if ($currentUser->id == $user->id) {
                // Allow access to own profile
            }
            // Tenants can see manager's profile if they manage house where tenant lives (via active contract)
            elseif ($user->role?->code === 'manager') {
                $activeContracts = $currentUser->contracts()->where('status', 'active')->with('room.house')->get();
                
                // Kiểm tra nếu manager này quản lý nhà nào mà tenant đang thuê
                foreach ($activeContracts as $contract) {
                    if ($contract->room && $contract->room->house) {
                        if ($contract->room->house->manager_id == $user->id) {
                            // Manager này quản lý nhà của tenant
                            return $this->sendResponse(new UserResource($user), 'User retrieved successfully.');
                        }
                    }
                }
                
                return $this->sendError('Bạn không có quyền xem thông tin người dùng này.', []);
            } else {
                return $this->sendError('Bạn chỉ có thể xem thông tin tài khoản của mình hoặc quản lý nhà của bạn.', []);
            }
        }

        return $this->sendResponse(new UserResource($user), 'User retrieved successfully.');
    }

    /**
     * Update the specified user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);
        $currentUser = Auth::user();

        if (is_null($user)) {
            return $this->sendError('User not found.');
        }

        $input = $request->all();

        // Prevent users from changing their own role
        if ($currentUser->id == $user->id && isset($input['role_id']) && $input['role_id'] != $user->role_id) {
            return $this->sendError('Bạn không thể thay đổi vai trò của chính mình.');
        }

        // Prevent tenants from updating themselves
        if ($currentUser->id == $user->id && $currentUser->role?->code === 'tenant') {
            return $this->sendError('Người thuê không thể tự cập nhật thông tin của mình.');
        }

        // Check if attempting to change role of an admin user
        if ($user->role?->code === 'admin' && isset($input['role_id']) && $input['role_id'] != $user->role_id) {
            return $this->sendError('Không thể thay đổi vai trò của người dùng admin.');
        }

        // Check if attempting to change manager to tenant while they manage houses
        if (
            $user->role?->code === 'manager' &&
            isset($input['role_id']) &&
            $input['role_id'] != $user->role_id
        ) {

            // Get the role code of the new role_id
            $newRoleCode = \App\Models\Role::find($input['role_id'])?->code;

            // If changing to tenant and manager has houses
            if ($newRoleCode === 'tenant' && $user->housesManaged()->count() > 0) {
                return $this->sendError('Không thể thay đổi vai trò của quản lý thành người thuê khi họ đang quản lý nhà.');
            }
        }

        // Check if attempting to change status of a manager who manages houses from active to inactive
        if (
            $user->role?->code === 'manager' &&
            isset($input['status']) &&
            $input['status'] === 'inactive' &&
            $user->status === 'active' &&
            $user->housesManaged()->count() > 0
        ) {
            return $this->sendError('Không thể thay đổi trạng thái của quản lý thành inactive khi họ đang quản lý nhà.');
        }

        $validator = Validator::make($input, [
            'username'                 => 'sometimes|required|string|unique:users,username,' . $user->id,
            'name'                     => 'sometimes|required|string',
            'email'                    => 'sometimes|required|email|unique:users,email,' . $user->id,
            'phone_number'             => 'sometimes|required|string|regex:/^0\d{9}$/',
            'hometown'                 => 'sometimes|nullable|string',
            'identity_card'            => 'sometimes|nullable|string',
            'vehicle_plate'            => 'sometimes|nullable|string',
            'status'                   => 'sometimes|string',
            'role_id'                  => 'sometimes|nullable|exists:roles,id',
            'avatar_url'               => 'sometimes|nullable|string',
            'notification_preferences' => 'sometimes|nullable',
        ], [
            'username.required' => 'Bắt buộc điền username.',
            'username.unique'   => 'Username đã tồn tại.',
            'name.required'     => 'Bắt buộc điền tên.',
            'email.required'    => 'Bắt buộc điền email.',
            'email.email'       => 'Email không đúng đinh dạng.',
            'email.unique'      => 'Email đã tồn tại.',
            'phone_number.required' => 'Bắt buộc điền số điện thoại.',
            'phone_number.regex' => 'Nhập đúng định dạng số điện thoại.',
            'role_id.exists'    => 'Không tồn tại role đó.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $user->fill($request->only([
            'username',
            'name',
            'email',
            'phone_number',
            'hometown',
            'identity_card',
            'vehicle_plate',
            'status',
            'role_id',
            'avatar_url',
            'notification_preferences',
        ]));

        $user->save();

        return $this->sendResponse(new UserResource($user), 'User updated successfully.');
    }

    /**
     * Remove the specified user from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id): JsonResponse
    {
        $currentUser = Auth::user();
        $user = User::find($id);

        if (is_null($user)) {
            return $this->sendError('Không tìm thấy user.');
        }

        // Prevent users from deleting themselves
        if ($currentUser->id == $user->id) {
            return $this->sendError('Bạn không thể xóa tài khoản của mình.', []);
        }

        // Apply role-based access control
        if ($currentUser->role?->code === 'admin') {
            // Admin can delete any user except themselves (already checked above)
        } elseif ($currentUser->role?->code === 'manager') {
            // Manager can only delete tenants from houses they manage
            $managedHouseIds = $currentUser->housesManaged()->pluck('id')->toArray();

            // Check if user is a tenant and belongs to a managed house
            $canDelete = $user->role?->code === 'tenant' &&
                $user->contracts()
                ->whereHas('room', function ($roomQuery) use ($managedHouseIds) {
                    $roomQuery->whereIn('house_id', $managedHouseIds);
                })
                ->exists();

            if (!$canDelete) {
                return $this->sendError('Bạn không có quyền xóa người dùng này.', []);
            }
        } else {
            // Tenants cannot delete any users
            return $this->sendError('Bạn không có quyền xóa người dùng này.', []);
        }

        $user->delete();

        return $this->sendResponse([], 'Xóa người dùng thành công.');
    }

    /**
     * Change the password of the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function changePassword(Request $request, $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return $this->sendError('Không tìm thấy người dùng.', [], 404);
        }

        $currentUser = Auth::user();
        $isAdmin = $currentUser->role->code === 'admin';

        if ($currentUser->id !== $user->id && !$isAdmin) {
            return $this->sendError('Bạn không có quyền thay đổi mật khẩu của người dùng này.', [], 403);
        }

        $rules = [
            'new_password' => 'required|string|min:6|confirmed',
        ];

        if (!$isAdmin) {
            $rules['current_password'] = 'required|string';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->sendError('Lỗi xác thực.', $validator->errors(), 422);
        }

        if (!$isAdmin && !Hash::check($request->current_password, $user->password)) {
            return $this->sendError('Mật khẩu hiện tại không đúng.', [], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return $this->sendResponse([], 'Mật khẩu đã được cập nhật thành công.');
    }

    /**
     * Get managers for tenant by active contracts
     *
     * @param Request $request
     * @param int $tenantId
     * @return JsonResponse
     */
    public function getManagersForTenant(Request $request, $tenantId): JsonResponse
    {
        $currentUser = Auth::user();
        
        // Chỉ admin mới có quyền gọi API này
        if (!$currentUser || $currentUser->role?->code !== 'admin') {
            return $this->sendError('Không có quyền truy cập', [], 403);
        }
        
        try {
            // Tìm tenant
            $tenant = User::with(['role', 'contracts' => function($query) {
                $query->where('status', 'active')->with('room.house.manager');
            }])->find($tenantId);
            
            if (!$tenant) {
                return $this->sendError('Không tìm thấy khách trọ', [], 404);
            }
            
            if ($tenant->role?->code !== 'tenant') {
                return $this->sendError('Người dùng được chỉ định không phải là khách trọ', [], 400);
            }
            
            $managers = [];
            $managersIds = [];
            
            // Lấy manager từ các hợp đồng active
            if ($tenant->contracts && count($tenant->contracts) > 0) {
                foreach ($tenant->contracts as $contract) {
                    if ($contract->room && $contract->room->house && $contract->room->house->manager) {
                        $manager = $contract->room->house->manager;
                        
                        // Tránh trùng lặp
                        if (!in_array($manager->id, $managersIds)) {
                            $managersIds[] = $manager->id;
                            $managers[] = [
                                'id' => $manager->id,
                                'name' => $manager->name,
                                'role' => $manager->role,
                                'house' => [
                                    'id' => $contract->room->house->id,
                                    'name' => $contract->room->house->name
                                ]
                            ];
                        }
                    }
                }
            }
            
            return $this->sendResponse([
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name
                ],
                'managers' => $managers
            ], 'Lấy danh sách quản lý thành công');
            
        } catch (\Exception $e) {
            return $this->sendError('Lỗi khi lấy danh sách quản lý: ' . $e->getMessage(), [], 500);
        }
    }
}
