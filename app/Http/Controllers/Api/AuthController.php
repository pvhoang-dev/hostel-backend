<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Class AuthController
 *
 * @package App\Http\Controllers\Api
 */
class AuthController extends BaseController
{
    /**
     * Login API
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        if (Auth::attempt(['username' => $request->username, 'password' => $request->password])) {
            $user = Auth::user();
            $token = $user->createToken('MyApp', ['*'], now()->addHours(1));
            $success['token'] = $token->plainTextToken;
            $success['name']  = $user->name;
            $success['user']  = [
                'id'        => $user->id,
                'username'  => $user->username,
                'email'     => $user->email,
                'role'      => $user->role->code,
                'name'      => $user->name,
            ];

            return $this->sendResponse($success, 'Đăng nhập thành công.');
        } else {
            return $this->sendError('Không có quyền truy cập.', ['error' => 'Tên đăng nhập hoặc mật khẩu không chính xác']);
        }
    }

    /**
     * Logout API
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->sendResponse([], 'Đăng xuất thành công.');
    }
}
