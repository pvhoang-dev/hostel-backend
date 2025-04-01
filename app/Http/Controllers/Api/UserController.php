<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends BaseController
{
    /**
     * Display a listing of the users.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): JsonResponse
    {
        $users = User::with('role')->get();

        return $this->sendResponse(UserResource::collection($users), 'Users retrieved successfully.');
    }

    /**
     * Store a newly created user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'username'                 => 'required|string|unique:users,username',
            'name'                     => 'required|string',
            'email'                    => 'nullable|email|unique:users,email',
            'password'                 => 'required|string|min:6',
            'phone_number'             => 'nullable|string',
            'hometown'                 => 'nullable|string',
            'identity_card'            => 'nullable|string',
            'vehicle_plate'            => 'nullable|string',
            'status' => 'sometimes|string',
            'role_id'                  => 'nullable|exists:roles,id',
            'avatar_url'               => 'nullable|string',
            'notification_preferences' => 'nullable',
        ], [
            'username.required' => 'Username is required.',
            'username.unique'   => 'Username already exists.',
            'name.required'     => 'Name is required.',
            'email.email'       => 'Email is invalid.',
            'email.unique'      => 'Email already exists.',
            'role_id.exists'    => 'Selected role does not exist.',
            'password.required' => 'Password is required.',
            'password.min'      => 'Password must be at least 6 characters.',
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
        $user = User::with('role')->find($id);

        if (is_null($user)) {
            return $this->sendError('User not found.');
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

        if (is_null($user)) {
            return $this->sendError('User not found.');
        }

        $input = $request->all();

        $validator = Validator::make($input, [
            'username'                 => 'sometimes|required|string|unique:users,username,'.$user->id,
            'name'                     => 'sometimes|required|string',
            'email'                    => 'sometimes|nullable|email|unique:users,email,'.$user->id,
            'phone_number'             => 'sometimes|nullable|string',
            'hometown'                 => 'sometimes|nullable|string',
            'identity_card'            => 'sometimes|nullable|string',
            'vehicle_plate'            => 'sometimes|nullable|string',
            'status'                   => 'sometimes|string',
            'role_id'                  => 'sometimes|nullable|exists:roles,id',
            'avatar_url'               => 'sometimes|nullable|string',
            'notification_preferences' => 'sometimes|nullable',
        ], [
            'username.required' => 'Username is required when provided.',
            'username.unique'   => 'Username already exists.',
            'name.required'     => 'Name is required when provided.',
            'email.email'       => 'Email is invalid.',
            'email.unique'      => 'Email already exists.',
            'role_id.exists'    => 'Selected role does not exist.',
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
        $user = User::find($id);

        if (is_null($user)) {
            return $this->sendError('User not found.');
        }

        $user->delete();

        return $this->sendResponse([], 'User deleted successfully.');
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
            return $this->sendError('User not found.', [], 404);
        }

        $currentUser = auth()->user();
        $isAdmin = $currentUser->role->code === 'admin';

        if ($currentUser->id !== $user->id && !$isAdmin) {
            return $this->sendError('You are not allowed to change this password.', [], 403);
        }

        $rules = [
            'new_password' => 'required|string|min:6|confirmed',
        ];

        if (!$isAdmin) {
            $rules['current_password'] = 'required|string';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        if (!$isAdmin && !Hash::check($request->current_password, $user->password)) {
            return $this->sendError('Current password is incorrect.', [], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return $this->sendResponse([], 'Password updated successfully.');
    }
}
