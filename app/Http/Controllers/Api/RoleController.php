<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Class RoleController
 *
 * @package App\Http\Controllers\Api
 */
class RoleController extends BaseController
{
    protected $roleService;
    
    /**
     * Constructor
     * 
     * @param RoleService $roleService
     */
    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }
    
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $result = $this->roleService->getAllRoles($request);
            return $this->sendResponse($result, 'Lấy danh sách vai trò thành công.');
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError('Lỗi khi lấy danh sách vai trò', ['error' => $e->getMessage()], $code);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $role = $this->roleService->createRole($request);
            return $this->sendResponse(new RoleResource($role), 'Vai trò đã được tạo thành công.');
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi khi tạo vai trò', $e->errors(), 422);
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError('Lỗi khi tạo vai trò', ['error' => $e->getMessage()], $code);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $role = $this->roleService->getRoleById($id);
            return $this->sendResponse(new RoleResource($role), 'Lấy thông tin vai trò thành công.');
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError('Lỗi khi lấy thông tin vai trò', ['error' => $e->getMessage()], $code);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Role $role
     * @return JsonResponse
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        try {
            $updatedRole = $this->roleService->updateRole($request, $role);
            return $this->sendResponse(new RoleResource($updatedRole), 'Vai trò đã được cập nhật thành công.');
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi khi cập nhật vai trò', $e->errors(), 422);
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError('Lỗi khi cập nhật vai trò', ['error' => $e->getMessage()], $code);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Role $role
     * @return JsonResponse
     */
    public function destroy(Role $role): JsonResponse
    {
        try {
            $this->roleService->deleteRole($role);
            return $this->sendResponse([], 'Vai trò đã được xóa thành công.');
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError('Lỗi khi xóa vai trò', ['error' => $e->getMessage()], $code);
        }
    }
}
