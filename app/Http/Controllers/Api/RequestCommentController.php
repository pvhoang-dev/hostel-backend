<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\RequestCommentResource;
use App\Models\Request;
use App\Models\RequestComment;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RequestCommentController extends BaseController
{
    protected $notificationService;
    
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(HttpRequest $httpRequest): JsonResponse
    {
        $user = Auth::user();
        $query = RequestComment::query();

        // Filter by request_id (required)
        if (!$httpRequest->has('request_id')) {
            return $this->sendError('Lỗi dữ liệu.', ['request_id' => 'Request ID là bắt buộc']);
        }

        $request = Request::with('room.house')->find($httpRequest->request_id);
        if (!$request) {
            return $this->sendError('Yêu cầu không tồn tại.');
        }

        // Authorization check
        if (!$this->canAccessRequest($user, $request)) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền xem bình luận cho yêu cầu này'], 403);
        }

        $query->where('request_id', $httpRequest->request_id);

        // Filter by user_id
        if ($httpRequest->has('user_id')) {
            $query->where('user_id', $httpRequest->user_id);
        }

        // Filter by content (partial match)
        if ($httpRequest->has('content')) {
            $query->where('content', 'like', '%' . $httpRequest->content . '%');
        }

        // Filter by date ranges
        if ($httpRequest->has('created_from')) {
            $query->where('created_at', '>=', $httpRequest->created_from);
        }

        if ($httpRequest->has('created_to')) {
            $query->where('created_at', '<=', $httpRequest->created_to);
        }

        if ($httpRequest->has('updated_from')) {
            $query->where('updated_at', '>=', $httpRequest->updated_from);
        }

        if ($httpRequest->has('updated_to')) {
            $query->where('updated_at', '<=', $httpRequest->updated_to);
        }

        // Include relationships
        $with = [];
        if ($httpRequest->has('include')) {
            $includes = explode(',', $httpRequest->include);
            if (in_array('user', $includes)) $with[] = 'user';
            if (in_array('request', $includes)) $with[] = 'request';
        }

        if (!empty($with)) {
            $query->with($with);
        }

        // Sorting
        $sortField = $httpRequest->get('sort_by', 'created_at');
        $sortDirection = $httpRequest->get('sort_dir', 'desc');
        $allowedSortFields = ['id', 'user_id', 'created_at', 'updated_at'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = $httpRequest->get('per_page', 15);
        $comments = $query->paginate($perPage);

        return $this->sendResponse(
            RequestCommentResource::collection($comments)->response()->getData(true),
            'Bình luận đã được lấy thành công.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(HttpRequest $httpRequest): JsonResponse
    {
        $user = Auth::user();
        $input = $httpRequest->all();

        $validator = Validator::make($input, [
            'request_id' => 'required|exists:requests,id',
            'content' => 'required|string',
        ], [
            'request_id.required' => 'Yêu cầu là bắt buộc.',
            'request_id.exists' => 'Yêu cầu không tồn tại.',
            'content.required' => 'Nội dung là bắt buộc.',
            'content.string' => 'Nội dung phải là một chuỗi.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Lỗi dữ liệu.', $validator->errors());
        }

        $request = Request::with('room.house')->find($input['request_id']);

        // Authorization check
        if (!$this->canAccessRequest($user, $request)) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền bình luận cho yêu cầu này'], 403);
        }

        // Set the user_id to current user
        $input['user_id'] = $user->id;

        $comment = RequestComment::create($input);
        
        // Gửi thông báo cho những người liên quan khi có bình luận mới
        try {
            // Xác định người cần được thông báo (những người liên quan đến yêu cầu ngoại trừ người comment)
            $notificationRecipients = [];
            
            // Thêm người gửi yêu cầu (nếu không phải người comment)
            if ($request->sender_id && $request->sender_id !== $user->id) {
                $notificationRecipients[] = $request->sender_id;
            }
            
            // Thêm người nhận yêu cầu (nếu không phải người comment)
            if ($request->recipient_id && $request->recipient_id !== $user->id) {
                $notificationRecipients[] = $request->recipient_id;
            }
            
            // Gửi thông báo cho tất cả người liên quan
            foreach ($notificationRecipients as $recipientId) {
                $this->notificationService->create(
                    $recipientId,
                    'new_comment',
                    "{$user->name} đã thêm một bình luận vào yêu cầu #{$request->id}",
                    "/requests/{$request->id}"
                );
            }
        } catch (\Exception $e) {
            // Ghi log lỗi nhưng không dừng xử lý
        }

        return $this->sendResponse(
            new RequestCommentResource($comment->load('user')),
            'Bình luận đã được tạo thành công.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();
        $comment = RequestComment::with('user', 'request.room.house')->find($id);

        if (is_null($comment)) {
            return $this->sendError('Bình luận không tồn tại.');
        }

        // Authorization check
        if (!$this->canAccessRequest($user, $comment->request)) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền xem bình luận này'], 403);
        }

        return $this->sendResponse(
            new RequestCommentResource($comment),
            'Bình luận đã được lấy thành công.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(HttpRequest $httpRequest, string $id): JsonResponse
    {
        $user = Auth::user();
        $input = $httpRequest->all();
        $comment = RequestComment::with('request.room.house')->find($id);

        if (is_null($comment)) {
            return $this->sendError('Bình luận không tồn tại.');
        }

        // Authorization check - users can only edit their own comments
        if ($comment->user_id !== $user->id && $user->role->code !== 'admin') {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn chỉ có thể chỉnh sửa bình luận của chính mình'], 403);
        }

        $validator = Validator::make($input, [
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Lỗi dữ liệu.', $validator->errors());
        }

        $comment->update([
            'content' => $input['content']
        ]);

        return $this->sendResponse(
            new RequestCommentResource($comment->load('user')),
            'Bình luận đã được cập nhật thành công.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();
        $comment = RequestComment::with('request.room.house')->find($id);

        if (is_null($comment)) {
            return $this->sendError('Bình luận không tồn tại.');
        }

        // Users can delete their own comments
        if ($comment->user_id === $user->id) {
            $comment->delete();
            return $this->sendResponse([], 'Bình luận đã được xóa thành công.');
        }

        // Admins can delete any comment
        if ($user->role->code === 'admin') {
            $comment->delete();
            return $this->sendResponse([], 'Bình luận đã được xóa thành công.');
        }

        // Managers can delete comments on requests from their houses
        if ($user->role->code === 'manager') {
            if ($user->id === $comment->request->room->house->manager_id) {
                $comment->delete();
                return $this->sendResponse([], 'Bình luận đã được xóa thành công.');
            }
        }

        return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền xóa bình luận này'], 403);
    }

    /**
     * Check if user can access a request
     */
    private function canAccessRequest($user, $request): bool
    {
        // Admins can access all requests
        if ($user->role->code === 'admin') {
            return true;
        }

        // Tenants can only access requests they sent or received
        if ($user->role->code === 'tenant') {
            return $user->id === $request->sender_id || $user->id === $request->recipient_id;
        }

        // Managers can access requests they sent/received or from their houses
        if ($user->role->code === 'manager') {
            if ($user->id === $request->sender_id || $user->id === $request->recipient_id) {
                return true;
            }

            return $user->id === $request->room->house->manager_id;
        }

        return false;
    }
}
