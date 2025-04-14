<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        $notifications = Notification::where('user_id', $currentUser->id)->get();

        return $this->sendResponse(
            NotificationResource::collection($notifications),
            'Notifications retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        $input = $request->all();
        $validator = Validator::make($input, [
            'user_id' => 'required|exists:users,id',
            'type' => 'required|string|max:50',
            'content' => 'required|string',
            'url' => 'nullable|string',
            'is_read' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $isTenant = $currentUser->role->code === 'tenant';
        if ($isTenant) {
            return $this->sendError('Unauthorized. As a tenant, you can not create notifications.', [], 403);
        }

        $notification = Notification::create($input);

        return $this->sendResponse(
            new NotificationResource($notification),
            'Notification created successfully.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        $notification = Notification::find($id);

        if (is_null($notification)) {
            return $this->sendError('Notification not found.');
        }

        // Only allow users to view their own notifications or admins to view any
        $isAdmin = $currentUser->role->code === 'admin';
        if (!$isAdmin && $notification->user_id !== $currentUser->id) {
            return $this->sendError('Unauthorized. You can only view your own notifications.', [], 403);
        }

        // Mark notification as read if the user is viewing their own notification
        if ($currentUser->id === $notification->user_id && !$notification->is_read) {
            $notification->is_read = true;
            $notification->save();
        }

        return $this->sendResponse(
            new NotificationResource($notification),
            'Notification retrieved successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        $notification = Notification::find($id);

        if (is_null($notification)) {
            return $this->sendError('Notification not found.');
        }

        // Only allow users to delete their own notifications or admins to delete any
        $isAdmin = $currentUser->role->code === 'admin';
        if (!$isAdmin && $notification->user_id !== $currentUser->id) {
            return $this->sendError('Unauthorized. You can only delete your own notifications.', [], 403);
        }

        $notification->delete();

        return $this->sendResponse([], 'Notification deleted successfully.');
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->sendError('Unauthorized.', [], 401);
        }

        Notification::where('user_id', $currentUser->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return $this->sendResponse([], 'All notifications marked as read.');
    }
}
