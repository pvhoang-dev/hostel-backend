<?php

namespace App\Services;

use App\Http\Resources\RequestCommentResource;
use App\Models\Request;
use App\Repositories\Interfaces\RequestCommentRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RequestCommentService
{
    protected $requestCommentRepository;
    protected $notificationService;

    public function __construct(
        RequestCommentRepositoryInterface $requestCommentRepository,
        NotificationService $notificationService
    ) {
        $this->requestCommentRepository = $requestCommentRepository;
        $this->notificationService = $notificationService;
    }

    /**
     * Get all comments for a request with filters
     *
     * @param \Illuminate\Http\Request $httpRequest
     * @return array
     * @throws \Exception
     */
    public function getAllComments($httpRequest)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        // Validate request_id (required)
        if (!$httpRequest->has('request_id')) {
            throw ValidationException::withMessages(['request_id' => 'Request ID là bắt buộc']);
        }

        $request = Request::with('room.house')->find($httpRequest->request_id);
        if (!$request) {
            throw new \Exception('Yêu cầu không tồn tại.');
        }

        // Authorization check
        if (!$this->requestCommentRepository->canAccessRequest($user, $request)) {
            throw new \Exception('Bạn không có quyền xem bình luận cho yêu cầu này');
        }

        // Build filters
        $filters = [
            'request_id' => $httpRequest->request_id,
            'user_id' => $httpRequest->user_id ?? null,
            'content' => $httpRequest->content ?? null,
            'created_from' => $httpRequest->created_from ?? null,
            'created_to' => $httpRequest->created_to ?? null,
            'updated_from' => $httpRequest->updated_from ?? null,
            'updated_to' => $httpRequest->updated_to ?? null
        ];

        // Include relationships
        $with = [];
        if ($httpRequest->has('include')) {
            $includes = explode(',', $httpRequest->include);
            if (in_array('user', $includes)) $with[] = 'user';
            if (in_array('request', $includes)) $with[] = 'request';
        }

        // Sorting
        $sortField = $httpRequest->get('sort_by', 'created_at');
        $sortDirection = $httpRequest->get('sort_dir', 'desc');
        $perPage = $httpRequest->get('per_page', 15);

        $comments = $this->requestCommentRepository->getAllWithFilters(
            $filters,
            $with,
            $sortField,
            $sortDirection,
            $perPage
        );

        return RequestCommentResource::collection($comments)->response()->getData(true);
    }

    /**
     * Create a new comment
     *
     * @param \Illuminate\Http\Request $httpRequest
     * @return mixed
     * @throws \Exception
     */
    public function createComment($httpRequest)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

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
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $request = Request::with('room.house')->find($input['request_id']);

        // Authorization check
        if (!$this->requestCommentRepository->canAccessRequest($user, $request)) {
            throw new \Exception('Bạn không có quyền bình luận cho yêu cầu này');
        }

        // Set the user_id to current user
        $input['user_id'] = $user->id;

        $comment = $this->requestCommentRepository->create($input);

        // Send notifications to relevant parties
        $this->sendCommentNotifications($user, $request, $comment);

        return $comment->load('user');
    }

    /**
     * Get a comment by ID
     *
     * @param string $id
     * @return mixed
     * @throws \Exception
     */
    public function getCommentById($id)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $comment = $this->requestCommentRepository->getById($id, ['user', 'request.room.house']);
        if (!$comment) {
            throw new \Exception('Bình luận không tồn tại.');
        }

        // Authorization check
        if (!$this->requestCommentRepository->canAccessRequest($user, $comment->request)) {
            throw new \Exception('Bạn không có quyền xem bình luận này');
        }

        return $comment;
    }

    /**
     * Update a comment
     *
     * @param \Illuminate\Http\Request $httpRequest
     * @param string $id
     * @return mixed
     * @throws \Exception
     */
    public function updateComment($httpRequest, $id)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $comment = $this->requestCommentRepository->getById($id, ['request.room.house']);
        if (!$comment) {
            throw new \Exception('Bình luận không tồn tại.');
        }

        // Authorization check - users can only edit their own comments
        if ($comment->user_id !== $user->id && $user->role->code !== 'admin') {
            throw new \Exception('Bạn chỉ có thể chỉnh sửa bình luận của chính mình');
        }

        $input = $httpRequest->all();
        $validator = Validator::make($input, [
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $updatedComment = $this->requestCommentRepository->update($id, [
            'content' => $input['content']
        ]);

        return $updatedComment->load('user');
    }

    /**
     * Delete a comment
     *
     * @param string $id
     * @return bool
     * @throws \Exception
     */
    public function deleteComment($id)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $comment = $this->requestCommentRepository->getById($id, ['request.room.house']);
        if (!$comment) {
            throw new \Exception('Bình luận không tồn tại.');
        }

        // Users can delete their own comments
        if ($comment->user_id === $user->id) {
            return $this->requestCommentRepository->delete($id);
        }

        // Admins can delete any comment
        if ($user->role->code === 'admin') {
            return $this->requestCommentRepository->delete($id);
        }

        // Managers can delete comments on requests from their houses
        if ($user->role->code === 'manager') {
            if ($user->id === $comment->request->room->house->manager_id) {
                return $this->requestCommentRepository->delete($id);
            }
        }

        throw new \Exception('Bạn không có quyền xóa bình luận này');
    }

    /**
     * Send notifications about a new comment
     *
     * @param \App\Models\User $user
     * @param \App\Models\Request $request
     * @param \App\Models\RequestComment $comment
     * @return void
     */
    private function sendCommentNotifications($user, $request, $comment)
    {
        try {
            // Determine who needs to be notified (people involved with the request except the commenter)
            $notificationRecipients = [];

            // Add request sender (if not the commenter)
            if ($request->sender_id && $request->sender_id !== $user->id) {
                $notificationRecipients[] = $request->sender_id;
            }

            // Add request recipient (if not the commenter)
            if ($request->recipient_id && $request->recipient_id !== $user->id) {
                $notificationRecipients[] = $request->recipient_id;
            }

            // Send notification to all relevant parties
            foreach ($notificationRecipients as $recipientId) {
                $this->notificationService->create(
                    $recipientId,
                    'request', // Using request type for better integration with existing notification system
                    "{$user->name} đã thêm một bình luận vào yêu cầu #{$request->id}",
                    "/requests/{$request->id}"
                );
            }
        } catch (\Exception $e) {
            // Log error but don't interrupt processing
            \Illuminate\Support\Facades\Log::error('Error sending comment notifications: ' . $e->getMessage());
        }
    }
}