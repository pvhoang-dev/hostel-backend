<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ContractResource;
use App\Http\Resources\UserResource;
use App\Services\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContractController extends BaseController
{
    protected $contractService;

    public function __construct(ContractService $contractService)
    {
        $this->contractService = $contractService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $contracts = $this->contractService->getAllContracts($request);
            return $this->sendResponse(
                ContractResource::collection($contracts)->response()->getData(true),
                'Danh sách hợp đồng đã được lấy thành công.'
            );
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError($e->getMessage(), [], $code);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $contract = $this->contractService->createContract($request);
            return $this->sendResponse(
                new ContractResource($contract),
                'Hợp đồng đã được tạo thành công.'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError('Lỗi khi tạo hợp đồng.', ['error' => $e->getMessage()], $code);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $contract = $this->contractService->getContractById($id);
            return $this->sendResponse(
                new ContractResource($contract),
                'Hợp đồng đã được lấy thành công.'
            );
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError($e->getMessage(), [], $code);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $contract = $this->contractService->updateContract($request, $id);
            return $this->sendResponse(
                new ContractResource($contract),
                'Hợp đồng đã được cập nhật thành công.'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError('Lỗi khi cập nhật hợp đồng.', ['error' => $e->getMessage()], $code);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->contractService->deleteContract($id);
            return $this->sendResponse([], 'Hợp đồng đã được xóa thành công.');
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError($e->getMessage(), [], $code);
        }
    }

    /**
     * Get available tenants for a room
     */
    public function getAvailableTenants(Request $request): JsonResponse
    {
        try {
            $tenants = $this->contractService->getAvailableTenants($request);
            return $this->sendResponse(
                UserResource::collection($tenants),
                'Danh sách người thuê đã được lấy thành công.'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            $code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            return $this->sendError($e->getMessage(), [], $code);
        }
    }
}
