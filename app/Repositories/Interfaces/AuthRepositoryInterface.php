<?php

namespace App\Repositories\Interfaces;

use App\Models\User;

interface AuthRepositoryInterface
{
    /**
     * Kiểm tra thông tin đăng nhập
     * 
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function attempt(array $credentials): bool;
    
    /**
     * Lấy thông tin người dùng đã đăng nhập
     * 
     * @return User|null
     */
    public function getAuthenticatedUser();
    
    /**
     * Xóa token hiện tại của người dùng
     * 
     * @param User $user
     * @return bool
     */
    public function deleteCurrentToken(User $user): bool;
} 