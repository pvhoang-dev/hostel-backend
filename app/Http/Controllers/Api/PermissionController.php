<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Validator;

class PermissionController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Permission::query();

        // Filter by name
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // Filter by code
        if ($request->has('code')) {
            $query->where('code', 'like', '%' . $request->code . '%');
        }

        // Filter by exact code match
        if ($request->has('code_exact')) {
            $query->where('code', $request->code_exact);
        }

        // Filter by description
        if ($request->has('description')) {
            $query->where('description', 'like', '%' . $request->description . '%');
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

        // Sorting
        $sortField = $request->get('sort_by', 'id');
        $sortDirection = $request->get('sort_dir', 'asc');
        $allowedSortFields = ['id', 'name', 'code', 'created_at', 'updated_at'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('id', 'asc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $permissions = $query->paginate($perPage);

        return $this->sendResponse(
            PermissionResource::collection($permissions)->response()->getData(true),
            'Permissions retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'name' => 'required',
            'code' => 'required|unique:permissions,code',
            'description' => 'nullable'
        ], [
            'name.required' => 'Tên quyền hạn là bắt buộc.',
            'code.unique' => 'Mã quyền hạn đã tồn tại.',
            'code.required' => 'Mã quyền hạn là bắt buộc.',
            'description.nullable' => 'Mô tả quyền hạn có thể là null.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Lỗi dữ liệu.', $validator->errors());
        }

        $permission = Permission::create($input);

        return $this->sendResponse(new PermissionResource($permission), 'Quyền hạn đã được tạo thành công.');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id): JsonResponse
    {
        $permission = Permission::find($id);

        if (is_null($permission)) {
            return $this->sendError('Permission not found.');
        }

        return $this->sendResponse(new PermissionResource($permission), 'Permission retrieved successfully.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Permission  $permission
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'name' => 'sometimes|required',
            'code' => 'sometimes|required|unique:permissions,code,' . $permission->id,
            'description' => 'nullable'
        ], [
            'code.unique' => 'Permission code already exists.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        if (isset($input['name'])) {
            $permission->name = $input['name'];
        }
        if (isset($input['code'])) {
            $permission->code = $input['code'];
        }
        if (isset($input['description']) || $input['description'] === null) {
            $permission->description = $input['description'];
        }

        $permission->save();

        return $this->sendResponse(new PermissionResource($permission), 'Permission updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Permission  $permission
     * @return \Illuminate\Http\Response
     */
    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return $this->sendResponse([], 'Permission deleted successfully.');
    }
}
