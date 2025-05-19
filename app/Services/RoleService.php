<?php

namespace App\Services;

use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Repositories\Interfaces\RoleRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RoleService
{
    protected $roleRepository;

    public function __construct(RoleRepositoryInterface $roleRepository)
    {
        $this->roleRepository = $roleRepository;
    }

    /**
     * Lấy danh sách vai trò
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getAllRoles(Request $request)
    {
        $user = Auth::user();

        // Only admin can access roles
        if (!$user || $user->role->code !== 'admin') {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        $filters = [
            'code' => $request->code ?? null,
            'name' => $request->name ?? null,
            'created_from' => $request->created_from ?? null,
            'created_to' => $request->created_to ?? null,
            'updated_from' => $request->updated_from ?? null,
            'updated_to' => $request->updated_to ?? null,
        ];

        $with = [];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            // Add relationships if needed in the future
        }

        $sortField = $request->get('sort_by', 'id');
        $sortDirection = $request->get('sort_dir', 'asc');
        $perPage = $request->get('per_page', 15);

        $roles = $this->roleRepository->getAllWithFilters($filters, $with, $sortField, $sortDirection, $perPage);

        $result = RoleResource::collection($roles);
        return $result->response()->getData(true);
    }

    /**
     * Tạo vai trò mới
     *
     * @param Request $request
     * @return Role
     * @throws \Exception
     */
    public function createRole(Request $request)
    {
        $user = Auth::user();

        // Only admin can create roles
        if (!$user || $user->role->code !== 'admin') {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        $input = $request->all();

        $validator = Validator::make($input, [
            'code' => 'required|unique:roles,code',
            'name' => 'required'
        ], [
            'code.required' => 'Mã code là bắt buộc.',
            'code.unique'   => 'Mã code đã tồn tại.',
            'name.required' => 'Tên vai trò là bắt buộc.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $this->roleRepository->create($input);
    }

    /**
     * Lấy thông tin chi tiết vai trò
     *
     * @param int $id
     * @return Role
     * @throws \Exception
     */
    public function getRoleById(int $id)
    {
        $user = Auth::user();

        // Only admin can view role details
        if (!$user || $user->role->code !== 'admin') {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        $role = $this->roleRepository->getById($id);

        if (is_null($role)) {
            throw new \Exception('Vai trò không tồn tại.', 404);
        }

        return $role;
    }

    /**
     * Cập nhật vai trò
     *
     * @param Request $request
     * @param Role $role
     * @return Role
     * @throws \Exception
     */
    public function updateRole(Request $request, Role $role)
    {
        $user = Auth::user();

        // Only admin can update roles
        if (!$user || $user->role->code !== 'admin') {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        $input = $request->all();

        $validator = Validator::make($input, [
            'code' => 'sometimes|required|unique:roles,code,' . $role->id,
            'name' => 'sometimes|required'
        ], [
            'code.required' => 'Mã code là bắt buộc.',
            'code.unique'   => 'Mã code đã tồn tại.',
            'name.required' => 'Tên vai trò là bắt buộc.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $this->roleRepository->update($role, $input);
    }

    /**
     * Xóa vai trò
     *
     * @param Role $role
     * @return bool
     * @throws \Exception
     */
    public function deleteRole(Role $role)
    {
        $user = Auth::user();

        // Only admin can delete roles
        if (!$user || $user->role->code !== 'admin') {
            throw new \Exception('Bạn không có quyền thực hiện thao tác này', 403);
        }

        return $this->roleRepository->delete($role);
    }
} 