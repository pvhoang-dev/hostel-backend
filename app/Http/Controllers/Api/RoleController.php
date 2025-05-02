<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Class RoleController
 *
 * @package App\Http\Controllers\Api
 */
class RoleController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Only admin can access roles
        if (!$user || $user->role->code !== 'admin') {
            return $this->sendError('Unauthorized', ['error' => 'Bạn không có quyền thực hiện thao tác này'], 403);
        }

        $query = Role::query();

        // Apply filters
        if ($request->has('code')) {
            $query->where('code', 'like', '%' . $request->code . '%');
        }

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
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

        // Include relationships if needed
        $with = [];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            // Add relationships if needed in the future
        }

        // Sorting
        $sortField = $request->get('sort_by', 'id');
        $sortDirection = $request->get('sort_dir', 'asc');
        $allowedSortFields = ['id', 'code', 'name', 'created_at', 'updated_at'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('id', 'asc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $roles = $query->with($with)->paginate($perPage);

        return $this->sendResponse(
            RoleResource::collection($roles)->response()->getData(true),
            'Roles retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Only admin can create roles
        if (!$user || $user->role->code !== 'admin') {
            return $this->sendError('Unauthorized', ['error' => 'Bạn không có quyền thực hiện thao tác này'], 403);
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
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $role = Role::create($input);

        return $this->sendResponse(
            new RoleResource($role),
            'Vai trò đã được tạo thành công.'
        );
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        // Only admin can view role details
        if (!$user || $user->role->code !== 'admin') {
            return $this->sendError('Unauthorized', ['error' => 'Bạn không có quyền thực hiện thao tác này'], 403);
        }

        $role = Role::find($id);

        if (is_null($role)) {
            return $this->sendError('Vai trò không tồn tại.');
        }

        return $this->sendResponse(
            new RoleResource($role),
            'Role retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Role  $role
     * @return JsonResponse
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $user = Auth::user();

        // Only admin can update roles
        if (!$user || $user->role->code !== 'admin') {
            return $this->sendError('Unauthorized', ['error' => 'Bạn không có quyền thực hiện thao tác này'], 403);
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
            return $this->sendError('Lỗi khi cập nhật vai trò.', $validator->errors());
        }

        if (isset($input['code'])) {
            $role->code = $input['code'];
        }
        if (isset($input['name'])) {
            $role->name = $input['name'];
        }

        $role->save();

        return $this->sendResponse(
            new RoleResource($role),
            'Vai trò đã được cập nhật thành công.'
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Role  $role
     * @return JsonResponse
     */
    public function destroy(Role $role): JsonResponse
    {
        $user = Auth::user();

        // Only admin can delete roles
        if (!$user || $user->role->code !== 'admin') {
            return $this->sendError('Unauthorized', ['error' => 'Bạn không có quyền thực hiện thao tác này'], 403);
        }

        $role->delete();

        return $this->sendResponse([], 'Vai trò đã được xóa thành công.');
    }
}
