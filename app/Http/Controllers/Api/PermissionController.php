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
     * @return \Illuminate\Http\Response
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::all();
        return $this->sendResponse(PermissionResource::collection($permissions), 'Permissions retrieved successfully.');
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
            'code.unique' => 'Permission code already exists.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $permission = Permission::create($input);

        return $this->sendResponse(new PermissionResource($permission), 'Permission created successfully.');
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
