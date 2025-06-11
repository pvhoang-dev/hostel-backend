<?php

namespace App\Http\Controllers\Api;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Class AuthController
 *
 * @package App\Http\Controllers\Api
 */
class AuthController extends BaseController
{
    protected $authService;
    
    /**
     * Constructor
     * 
     * @param AuthService $authService
     */
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }
    
    /**
     * Login API
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $result = $this->authService->login($request);
        
        if ($result) {
            return $this->sendResponse($result, 'Đăng nhập thành công.');
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
        $this->authService->logout($request);
        return $this->sendResponse([], 'Đăng xuất thành công.');
    }
}
