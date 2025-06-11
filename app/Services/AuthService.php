<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Interfaces\AuthRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AuthService
{
    protected $authRepository;

    public function __construct(AuthRepositoryInterface $authRepository)
    {
        $this->authRepository = $authRepository;
    }

    /**
     * Đăng nhập người dùng
     *
     * @param Request $request
     * @return array|null
     */
    public function login(Request $request)
    {
        $credentials = [
            'username' => $request->username,
            'password' => $request->password
        ];

        if ($this->authRepository->attempt($credentials)) {
            $user = $this->authRepository->getAuthenticatedUser();
            
            // Tạo token với thời hạn 24 giờ
            $token = $user->createToken('MyApp', ['*'], now()->addHours(24));
            
            return [
                'token' => $token->plainTextToken,
                'name'  => $user->name,
                'user'  => [
                    'id'        => $user->id,
                    'username'  => $user->username,
                    'email'     => $user->email,
                    'role'      => $user->role->code,
                    'name'      => $user->name,
                ]
            ];
        }
        
        return null;
    }

    /**
     * Đăng xuất người dùng
     *
     * @param Request $request
     * @return bool
     */
    public function logout(Request $request): bool
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
            return true;
        }
        return false;
    }
} 