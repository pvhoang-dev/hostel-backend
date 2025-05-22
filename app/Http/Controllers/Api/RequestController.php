<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\RequestResource;
use App\Services\RequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Validation\ValidationException;

class RequestController extends BaseController
{
    protected $requestService;

    public function __construct(RequestService $requestService)
    {
        $this->requestService = $requestService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(HttpRequest $httpRequest): JsonResponse
    {
        try {
            $result = $this->requestService->getAllRequests($httpRequest);
            return $this->sendResponse($result, 'Requests retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(HttpRequest $httpRequest): JsonResponse
    {
        try {
            $request = $this->requestService->createRequest($httpRequest);
            return $this->sendResponse(
                new RequestResource($request),
                'Tạo yêu cầu thành công.'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $request = $this->requestService->getRequestById($id);
            return $this->sendResponse(
                new RequestResource($request),
                'Yêu cầu đã được lấy thành công.'
            );
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(HttpRequest $httpRequest, string $id): JsonResponse
    {
        try {
            $request = $this->requestService->updateRequest($httpRequest, $id);
            return $this->sendResponse(
                new RequestResource($request),
                'Yêu cầu đã được cập nhật thành công.'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->requestService->deleteRequest($id);
            return $this->sendResponse([], 'Yêu cầu đã được xóa thành công.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }
}
