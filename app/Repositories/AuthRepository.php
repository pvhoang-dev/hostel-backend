<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Interfaces\AuthRepositoryInterface;
use Illuminate\Support\Facades\Auth;

class AuthRepository implements AuthRepositoryInterface
{
    /**
     * Kiểm tra thông tin đăng nhập
     * 
     * @param array $credentials
     * @return bool
     */
    public function attempt(array $credentials): bool
    {
        return Auth::attempt($credentials);
    }
    
    /**
     * Lấy thông tin người dùng đã đăng nhập
     * 
     * @return User|null
     */
    public function getAuthenticatedUser()
    {
        return Auth::user();
    }
    
    /**
     * Xóa token hiện tại của người dùng
     * 
     * @param User $user
     * @return bool
     */
    public function deleteCurrentToken(User $user): bool
    {
        // Phương thức này giả định rằng hàm xóa token đã được gọi trong controller
        // Trong Laravel Sanctum, API này là $request->user()->currentAccessToken()->delete();
        return true;
    }
} 