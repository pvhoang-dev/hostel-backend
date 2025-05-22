<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\RequestCommentResource;
use App\Services\RequestCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Validation\ValidationException;

class RequestCommentController extends BaseController
{
    protected $requestCommentService;

    public function __construct(RequestCommentService $requestCommentService)
    {
        $this->requestCommentService = $requestCommentService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(HttpRequest $httpRequest): JsonResponse
    {
        try {
            $result = $this->requestCommentService->getAllComments($httpRequest);
            return $this->sendResponse($result, 'Bình luận đã được lấy thành công.');
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
            $comment = $this->requestCommentService->createComment($httpRequest);
            return $this->sendResponse(
                new RequestCommentResource($comment),
                'Bình luận đã được tạo thành công.'
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
            $comment = $this->requestCommentService->getCommentById($id);
            return $this->sendResponse(
                new RequestCommentResource($comment),
                'Bình luận đã được lấy thành công.'
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
            $comment = $this->requestCommentService->updateComment($httpRequest, $id);
            return $this->sendResponse(
                new RequestCommentResource($comment),
                'Bình luận đã được cập nhật thành công.'
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
            $this->requestCommentService->deleteComment($id);
            return $this->sendResponse([], 'Bình luận đã được xóa thành công.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }
}
