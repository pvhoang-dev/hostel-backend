<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserService
{
    protected $userRepository;
    protected $notificationService;

    public function __construct(
        UserRepositoryInterface $userRepository,
        NotificationService $notificationService
    ) {
        $this->userRepository = $userRepository;
        $this->notificationService = $notificationService;
    }

    public function getAllUsers($request)
    {
        $currentUser = Auth::user();
        
        $filters = [
            'current_user' => $currentUser,
            'username' => $request->username ?? null,
            'name' => $request->name ?? null,
            'email' => $request->email ?? null,
            'phone_number' => $request->phone_number ?? null,
            'hometown' => $request->hometown ?? null,
            'identity_card' => $request->identity_card ?? null,
            'vehicle_plate' => $request->vehicle_plate ?? null,
            'status' => $request->status ?? null,
            'role_id' => $request->role_id ?? null,
            'created_from' => $request->created_from ?? null,
            'created_to' => $request->created_to ?? null,
            'updated_from' => $request->updated_from ?? null,
            'updated_to' => $request->updated_to ?? null,
            'for_requests' => $request->has('for_requests') && $request->for_requests === 'true',
            'without_active_contract' => $request->has('without_active_contract') && $request->without_active_contract === 'true',
        ];
        
        if ($request->has('role')) {
            $filters['role'] = is_array($request->role) ? $request->role : explode(',', $request->role);
        }
        
        $with = ['role'];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('contracts', $includes)) $with[] = 'contracts';
            if (in_array('managedHouses', $includes)) $with[] = 'housesManaged';
        }
        
        $sortField = $request->get('sort_by', 'id');
        $sortDirection = $request->get('sort_dir', 'asc');
        $perPage = $request->get('per_page', 15);
        
        $users = $this->userRepository->getAllWithFilters($filters, $with, $sortField, $sortDirection, $perPage);
        
        $result = UserResource::collection($users);
        
        // Truyền thông tin phân trang về frontend
        return $result->response()->getData(true);
    }

    public function createUser($request)
    {
        $currentUser = Auth::user();
        $input = $request->all();

        // Tenants cannot create users
        if ($currentUser->role?->code === 'tenant') {
            throw new \Exception('Người thuê không có quyền tạo người dùng mới.');
        }

        // Managers can only create tenant users
        if ($currentUser->role?->code === 'manager') {
            $tenantRoleId = $this->userRepository->getRoleByCode('tenant')?->id;

            // If role_id is not specified or is not tenant
            if (!isset($input['role_id']) || $input['role_id'] != $tenantRoleId) {
                throw new \Exception('Quản lý chỉ có thể tạo người dùng với vai trò người thuê.');
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
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $input['password'] = Hash::make($input['password']);

        $user = $this->userRepository->create($input);

        return $user;
    }

    public function getUserById($id)
    {
        $currentUser = Auth::user();
        $user = $this->userRepository->getById($id, ['role']);

        if (is_null($user)) {
            throw new \Exception('User not found.');
        }

        // Kiểm tra quyền xem thông tin người dùng
        if (!$this->userRepository->canViewUser($currentUser, $user)) {
            throw new \Exception('Bạn không có quyền xem thông tin người dùng này.');
        }

        return $user;
    }

    public function updateUser($request, $id)
    {
        $currentUser = Auth::user();
        $user = $this->userRepository->getById($id);

        if (is_null($user)) {
            throw new \Exception('User not found.');
        }

        $input = $request->all();

        // Prevent users from changing their own role
        if ($currentUser->id == $user->id && isset($input['role_id']) && $input['role_id'] != $user->role_id) {
            throw new \Exception('Bạn không thể thay đổi vai trò của chính mình.');
        }

        // Prevent tenants from updating themselves
        if ($currentUser->id == $user->id && $currentUser->role?->code === 'tenant') {
            throw new \Exception('Người thuê không thể tự cập nhật thông tin của mình.');
        }

        // Check if attempting to change role of an admin user
        if ($user->role?->code === 'admin' && isset($input['role_id']) && $input['role_id'] != $user->role_id) {
            throw new \Exception('Không thể thay đổi vai trò của người dùng admin.');
        }

        // Check if attempting to change manager to tenant while they manage houses
        if (
            $user->role?->code === 'manager' &&
            isset($input['role_id']) &&
            $input['role_id'] != $user->role_id
        ) {
            // Get the role code of the new role_id
            $newRoleCode = $this->userRepository->getRoleByCode('tenant')?->code;

            // If changing to tenant and manager has houses
            if ($newRoleCode === 'tenant' && count($user->housesManaged) > 0) {
                throw new \Exception('Không thể thay đổi vai trò của quản lý thành người thuê khi họ đang quản lý nhà.');
            }
        }

        // Check if attempting to change status of a manager who manages houses from active to inactive
        if (
            $user->role?->code === 'manager' &&
            isset($input['status']) &&
            $input['status'] === 'inactive' &&
            $user->status === 'active' &&
            count($user->housesManaged) > 0
        ) {
            throw new \Exception('Không thể thay đổi trạng thái của quản lý thành inactive khi họ đang quản lý nhà.');
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
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $updateData = $request->only([
            'username',
            'name',
            'email',
            'phone_number',
            'hometown',
            'identity_card',
            'vehicle_plate',
            'status',
            'role_id',
        ]);

        $updatedUser = $this->userRepository->update($user->id, $updateData);

        return $updatedUser;
    }

    public function deleteUser($id)
    {
        $currentUser = Auth::user();
        $user = $this->userRepository->getById($id);

        if (is_null($user)) {
            throw new \Exception('Không tìm thấy user.');
        }

        // Kiểm tra quyền xóa người dùng
        if (!$this->userRepository->canDeleteUser($currentUser, $user)) {
            throw new \Exception('Bạn không có quyền xóa người dùng này.');
        }

        return $this->userRepository->delete($id);
    }

    public function changePassword($request, $id)
    {
        $user = $this->userRepository->getById($id);

        if (!$user) {
            throw new \Exception('Không tìm thấy người dùng.:404');
        }

        $currentUser = Auth::user();
        $isAdmin = $currentUser->role->code === 'admin';

        if ($currentUser->id !== $user->id && !$isAdmin) {
            throw new \Exception('Bạn không có quyền thay đổi mật khẩu của người dùng này.:403');
        }

        $rules = [
            'new_password' => 'required|string|min:6|confirmed',
        ];

        if (!$isAdmin) {
            $rules['current_password'] = 'required|string';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        if (!$isAdmin && !Hash::check($request->current_password, $user->password)) {
            throw new \Exception('Mật khẩu hiện tại không đúng.:400');
        }

        $this->userRepository->update($id, [
            'password' => Hash::make($request->new_password)
        ]);

        $this->notificationService->create(
            $user->id,
            'password_changed',
            $currentUser->id === $user->id 
                ? "Mật khẩu của bạn đã được cập nhật thành công" 
                : "Mật khẩu của bạn đã được cập nhật bởi " . $currentUser->name,
            "/users/{$user->id}"
        );

        return true;
    }

    public function getManagersForTenant($request, $tenantId)
    {
        $currentUser = Auth::user();
        
        // Chỉ admin mới có quyền gọi API này
        if (!$currentUser || $currentUser->role?->code !== 'admin') {
            throw new \Exception('Không có quyền truy cập.:403');
        }
        
        $result = $this->userRepository->getManagersForTenant($tenantId);
        
        if (!$result) {
            throw new \Exception('Không tìm thấy khách trọ.:404');
        }
        
        return $result;
    }
    
    public function getTenantsForManager($request, $managerId)
    {
        $currentUser = Auth::user();
        
        // Chỉ admin và manager được gọi API này
        if (!$currentUser || !in_array($currentUser->role?->code, ['admin', 'manager'])) {
            throw new \Exception('Không có quyền truy cập.:403');
        }
        
        // Manager chỉ có thể xem danh sách tenant của chính mình
        if ($currentUser->role?->code === 'manager' && $currentUser->id != $managerId) {
            throw new \Exception('Không có quyền truy cập.:403');
        }
        
        $result = $this->userRepository->getTenantsForManager($managerId);
        
        if (!$result) {
            throw new \Exception('Không tìm thấy manager.:404');
        }
        
        return $result;
    }
} 