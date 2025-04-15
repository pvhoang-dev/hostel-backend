<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\RequestCommentResource;
use App\Models\House;
use App\Models\Request;
use App\Models\RequestComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RequestCommentController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(HttpRequest $httpRequest): JsonResponse
    {
        $user = Auth::user();
        $query = RequestComment::query();

        // Filter by request_id (required)
        if (!$httpRequest->has('request_id')) {
            return $this->sendError('Validation Error.', ['request_id' => 'Request ID is required']);
        }

        $request = Request::with('room.house')->find($httpRequest->request_id);
        if (!$request) {
            return $this->sendError('Request not found.');
        }

        // Authorization check
        if (!$this->canAccessRequest($user, $request)) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to view comments for this request'], 403);
        }

        $query->where('request_id', $httpRequest->request_id);

        // Include relationships
        $with = [];
        if ($httpRequest->has('include')) {
            $includes = explode(',', $httpRequest->include);
            if (in_array('user', $includes)) $with[] = 'user';
        }

        $comments = $query->with($with)->orderBy('created_at', 'desc')->get();

        return $this->sendResponse(
            RequestCommentResource::collection($comments),
            'Comments retrieved successfully.'
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
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $request = Request::with('room.house')->find($input['request_id']);

        // Authorization check
        if (!$this->canAccessRequest($user, $request)) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to comment on this request'], 403);
        }

        // Set the user_id to current user
        $input['user_id'] = $user->id;

        $comment = RequestComment::create($input);

        return $this->sendResponse(
            new RequestCommentResource($comment->load('user')),
            'Comment created successfully.'
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
            return $this->sendError('Comment not found.');
        }

        // Authorization check
        if (!$this->canAccessRequest($user, $comment->request)) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to view this comment'], 403);
        }

        return $this->sendResponse(
            new RequestCommentResource($comment),
            'Comment retrieved successfully.'
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
            return $this->sendError('Comment not found.');
        }

        // Authorization check - users can only edit their own comments
        if ($comment->user_id !== $user->id && $user->role->code !== 'admin') {
            return $this->sendError('Unauthorized', ['error' => 'You can only edit your own comments'], 403);
        }

        $validator = Validator::make($input, [
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $comment->update([
            'content' => $input['content']
        ]);

        return $this->sendResponse(
            new RequestCommentResource($comment->load('user')),
            'Comment updated successfully.'
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
            return $this->sendError('Comment not found.');
        }

        // Users can delete their own comments
        if ($comment->user_id === $user->id) {
            $comment->delete();
            return $this->sendResponse([], 'Comment deleted successfully.');
        }

        // Admins can delete any comment
        if ($user->role->code === 'admin') {
            $comment->delete();
            return $this->sendResponse([], 'Comment deleted successfully.');
        }

        // Managers can delete comments on requests from their houses
        if ($user->role->code === 'manager') {
            if ($user->id === $comment->request->room->house->manager_id) {
                $comment->delete();
                return $this->sendResponse([], 'Comment deleted successfully.');
            }
        }

        return $this->sendError('Unauthorized', ['error' => 'You do not have permission to delete this comment'], 403);
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
