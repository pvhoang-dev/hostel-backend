<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Validator;

class RoleController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): JsonResponse
    {
        $roles = Role::all();

        return $this->sendResponse(RoleResource::collection($roles), 'Roles retrieved successfully.');
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
            'code' => 'required|unique:roles,code',
            'name' => 'required'
        ], [
            'code.unique'   => 'Code already exists.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $role = Role::create($input);

        return $this->sendResponse(new RoleResource($role), 'Role created successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id): JsonResponse
    {
        $role = Role::find($id);

        if (is_null($role)) {
            return $this->sendError('Role not found.');
        }

        return $this->sendResponse(new RoleResource($role), 'Role retrieved successfully.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'code' => 'sometimes|required|unique:roles,code,' . $role->id,
            'name' => 'sometimes|required'
        ], [
            'code.unique'   => 'Code already exists.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        if (isset($input['code'])) {
            $role->code = $input['code'];
        }
        if (isset($input['name'])) {
            $role->name = $input['name'];
        }

        $role->save();

        return $this->sendResponse(new RoleResource($role), 'Role updated successfully.');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Role $role): JsonResponse
    {
        $role->delete();

        return $this->sendResponse([], 'Role deleted successfully.');
    }
}
