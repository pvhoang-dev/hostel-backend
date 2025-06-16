<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends BaseController
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }
    
    /**
     * Display a listing of the users.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $result = $this->userService->getAllUsers($request);
            return $this->sendResponse($result, 'Lấy danh sách người dùng thành công');
        } catch (\Exception $e) {
            return $this->sendError('Lỗi khi lấy danh sách người dùng', [$e->getMessage()]);
        }
    }
    
    /**
     * Store a newly created user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $this->userService->createUser($request);
            return $this->sendResponse(new UserResource($user), 'Tạo người dùng thành công');
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi khi tạo người dùng', $e->errors(), 422);
        } catch (\Exception $e) {
            $code = 500;
            $message = $e->getMessage();
            
            // Check if error message contains status code
            if (preg_match('/:(\d+)$/', $message, $matches)) {
                $code = intval($matches[1]);
                $message = preg_replace('/:(\d+)$/', '', $message);
            }
            
            return $this->sendError('Lỗi khi tạo người dùng', [$message], $code);
        }
    }
    
    /**
     * Display the specified user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = $this->userService->getUserById($id);
            return $this->sendResponse(new UserResource($user), 'Lấy thông tin người dùng thành công');
        } catch (\Exception $e) {
            $code = 500;
            $message = $e->getMessage();
            
            // Check if error message contains status code
            if (preg_match('/:(\d+)$/', $message, $matches)) {
                $code = intval($matches[1]);
                $message = preg_replace('/:(\d+)$/', '', $message);
            }
            
            return $this->sendError('Lỗi khi lấy thông tin người dùng', [$message], $code);
        }
    }
    
    /**
     * Update a user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = $this->userService->updateUser($request, $id);
            return $this->sendResponse(new UserResource($user), 'Cập nhật người dùng thành công');
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi khi cập nhật người dùng', $e->errors(), 422);
        } catch (\Exception $e) {
            $code = 500;
            $message = $e->getMessage();
            
            // Check if error message contains status code
            if (preg_match('/:(\d+)$/', $message, $matches)) {
                $code = intval($matches[1]);
                $message = preg_replace('/:(\d+)$/', '', $message);
            }
            
            return $this->sendError('Lỗi khi cập nhật người dùng', [$message], $code);
        }
    }
    
    /**
     * Remove a user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->userService->deleteUser($id);
            return $this->sendResponse([], 'Xóa người dùng thành công');
        } catch (\Exception $e) {
            $code = 500;
            $message = $e->getMessage();
            
            // Check if error message contains status code
            if (preg_match('/:(\d+)$/', $message, $matches)) {
                $code = intval($matches[1]);
                $message = preg_replace('/:(\d+)$/', '', $message);
            }
            
            return $this->sendError($message, [$message], $code);
        }
    }
    
    /**
     * Change user password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function changePassword(Request $request, $id): JsonResponse
    {
        try {
            $this->userService->changePassword($request, $id);
            return $this->sendResponse([], 'Thay đổi mật khẩu thành công');
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi khi thay đổi mật khẩu', $e->errors(), 422);
        } catch (\Exception $e) {
            $code = 500;
            $message = $e->getMessage();
            
            // Check if error message contains status code
            if (preg_match('/:(\d+)$/', $message, $matches)) {
                $code = intval($matches[1]);
                $message = preg_replace('/:(\d+)$/', '', $message);
            }
            
            return $this->sendError('Lỗi khi thay đổi mật khẩu', [$message], $code);
        }
    }
    
    /**
     * Get managers for a tenant.
     *
     * @param Request $request
     * @param int $tenantId
     * @return JsonResponse
     */
    public function getManagersForTenant(Request $request, $tenantId): JsonResponse
    {
        try {
            $result = $this->userService->getManagersForTenant($request, $tenantId);
            return $this->sendResponse($result, 'Lấy danh sách quản lý thành công');
        } catch (\Exception $e) {
            $code = 500;
            $message = $e->getMessage();
            
            // Check if error message contains status code
            if (preg_match('/:(\d+)$/', $message, $matches)) {
                $code = intval($matches[1]);
                $message = preg_replace('/:(\d+)$/', '', $message);
            }
            
            return $this->sendError('Lỗi khi lấy danh sách quản lý', [$message], $code);
        }
    }
    
    /**
     * Get tenants for a manager.
     *
     * @param Request $request
     * @param string $managerId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTenantsForManager(Request $request, string $managerId): \Illuminate\Http\JsonResponse
    {
        try {
            $result = $this->userService->getTenantsForManager($request, $managerId);
            return $this->sendResponse($result, 'Lấy danh sách người thuê thành công');
        } catch (\Exception $e) {
            $code = 500;
            $message = $e->getMessage();
            
            // Check if error message contains status code
            if (preg_match('/:(\d+)$/', $message, $matches)) {
                $code = intval($matches[1]);
                $message = preg_replace('/:(\d+)$/', '', $message);
            }
            
            return $this->sendError('Lỗi khi lấy danh sách người thuê', [$message], $code);
        }
    }
}
