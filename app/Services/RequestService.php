<?php

namespace App\Services;

use App\Http\Resources\RequestResource;
use App\Models\User;
use App\Repositories\Interfaces\RequestRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RequestService
{
    protected $requestRepository;
    protected $notificationService;

    public function __construct(
        RequestRepositoryInterface $requestRepository,
        NotificationService $notificationService
    ) {
        $this->requestRepository = $requestRepository;
        $this->notificationService = $notificationService;
    }

    /**
     * Get all requests with filters
     *
     * @param \Illuminate\Http\Request $httpRequest
     * @return array
     * @throws \Exception
     */
    public function getAllRequests($httpRequest)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        // Build filters
        $filters = [
            'user' => $user,
            'sender_id' => $httpRequest->sender_id ?? null,
            'recipient_id' => $httpRequest->recipient_id ?? null,
            'status' => $httpRequest->status ?? null,
            'request_type' => $httpRequest->request_type ?? null,
            'description' => $httpRequest->description ?? null,
            'created_from' => $httpRequest->created_from ?? null,
            'created_to' => $httpRequest->created_to ?? null,
            'updated_from' => $httpRequest->updated_from ?? null,
            'updated_to' => $httpRequest->updated_to ?? null
        ];

        // Add role-specific filters
        if ($user->role->code === 'tenant' && $httpRequest->has('include_room_house') && $httpRequest->include_room_house === 'true') {
            $filters['include_room_house'] = true;
        }

        if ($user->role->code === 'manager' && $httpRequest->has('include_house') && $httpRequest->include_house === 'true') {
            $filters['include_house'] = true;
        }

        // Include relationships
        $with = [];
        if ($httpRequest->has('include')) {
            $includes = explode(',', $httpRequest->include);
            if (in_array('room', $includes)) $with[] = 'room';
            if (in_array('sender', $includes)) $with[] = 'sender';
            if (in_array('recipient', $includes)) $with[] = 'recipient';
            if (in_array('comments', $includes)) $with[] = 'comments.user';
            if (in_array('updater', $includes)) $with[] = 'updater';
        }

        // Sorting
        $sortField = $httpRequest->get('sort_by', 'created_at');
        $sortDirection = $httpRequest->get('sort_dir', 'desc');
        $perPage = $httpRequest->get('per_page', 15);

        $requests = $this->requestRepository->getAllWithFilters(
            $filters,
            $with,
            $sortField,
            $sortDirection,
            $perPage
        );

        return RequestResource::collection($requests)->response()->getData(true);
    }

    /**
     * Create a new request
     *
     * @param \Illuminate\Http\Request $httpRequest
     * @return mixed
     * @throws \Exception
     */
    public function createRequest($httpRequest)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $input = $httpRequest->all();
        $validator = Validator::make($input, [
            'sender_id' => 'required|exists:users,id',
            'recipient_id' => 'required|exists:users,id',
            'request_type' => 'required|string|max:50',
            'description' => 'required|string',
            'status' => 'sometimes|string|max:20',
        ], [
            'sender_id.required' => 'ID người gửi là bắt buộc.',
            'sender_id.exists' => 'Người gửi không tồn tại.',
            'recipient_id.required' => 'ID người nhận là bắt buộc.',
            'recipient_id.exists' => 'Người nhận không tồn tại.',
            'request_type.required' => 'Loại yêu cầu là bắt buộc.',
            'request_type.string' => 'Loại yêu cầu phải là một chuỗi.',
            'request_type.max' => 'Loại yêu cầu không được vượt quá 50 ký tự.',
            'description.required' => 'Nội dung là bắt buộc.',
            'description.string' => 'Nội dung phải là một chuỗi.',
            'status.string' => 'Trạng thái phải là một chuỗi.',
            'status.max' => 'Trạng thái không được vượt quá 20 ký tự.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Users can only create requests where they are the sender
        if ($user->id !== $input['sender_id']) {
            throw new \Exception('Bạn chỉ có thể tạo yêu cầu là người gửi');
        }

        // Check role-based routing permissions
        $recipient = User::find($input['recipient_id']);
        if (!$recipient) {
            throw ValidationException::withMessages(['recipient_id' => 'Người nhận không tồn tại']);
        }

        // Enforce role-based request routing
        if ($user->role->code === 'tenant') {
            // Tenant chỉ có thể gửi cho manager quản lý nhà của họ
            if ($recipient->role->code !== 'manager') {
                throw new \Exception('Tenants can only send requests to managers');
            }
        } elseif ($user->role->code === 'manager') {
            // Manager có thể gửi cho admin hoặc tenant thuộc nhà họ quản lý
            if (!in_array($recipient->role->code, ['admin', 'tenant'])) {
                throw new \Exception('Managers can only send requests to admins or tenants');
            }
        }
        // Admin có thể gửi cho bất kỳ ai, không cần kiểm tra thêm

        // If no status is provided, set it to 'pending'
        if (!isset($input['status'])) {
            $input['status'] = 'pending';
        }

        // Set updated_by to current user
        $input['updated_by'] = $user->id;

        $request = $this->requestRepository->create($input);

        // Tự động tạo thông báo cho người nhận khi yêu cầu được tạo
        try {
            $this->notificationService->create(
                $recipient->id,
                'new_request',
                $user->name . ' đã tạo một yêu cầu mới cho bạn',
                '/requests/' . $request->id
            );
        } catch (\Exception $e) {
            // Ghi log lỗi nhưng không dừng xử lý
            \Illuminate\Support\Facades\Log::error('Error sending request notification: ' . $e->getMessage());
        }

        return $request->load(['sender', 'recipient', 'updater']);
    }

    /**
     * Get a request by ID
     *
     * @param string $id
     * @return mixed
     * @throws \Exception
     */
    public function getRequestById($id)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $request = $this->requestRepository->getById($id, ['sender.role', 'recipient.role', 'comments.user', 'updater']);
        if (!$request) {
            throw new \Exception('Yêu cầu không tồn tại.');
        }

        // Authorization check
        if (!$this->requestRepository->canAccessRequest($user, $request)) {
            throw new \Exception('Bạn không có quyền xem yêu cầu này');
        }

        return $request;
    }

    /**
     * Update a request
     *
     * @param \Illuminate\Http\Request $httpRequest
     * @param string $id
     * @return mixed
     * @throws \Exception
     */
    public function updateRequest($httpRequest, $id)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $request = $this->requestRepository->getById($id);
        if (!$request) {
            throw new \Exception('Yêu cầu không tồn tại.');
        }

        // Authorization check
        if (!$this->requestRepository->canAccessRequest($user, $request)) {
            throw new \Exception('Bạn không có quyền cập nhật yêu cầu này');
        }

        // Lưu trữ thông tin cũ để so sánh sau khi cập nhật
        $oldStatus = $request->status;
        $oldRecipientId = $request->recipient_id;
        $oldSenderId = $request->sender_id;

        $input = $httpRequest->all();

        // Apply role-specific restrictions
        if ($user->role->code === 'tenant') {
            // Tenants can't change sender_id or recipient_id
            if (isset($input['sender_id']) || isset($input['recipient_id'])) {
                throw new \Exception('Tenants cannot change sender or recipient');
            }

            // Tenants can only update requests they sent
            if ($request->sender_id !== $user->id) {
                throw new \Exception('Bạn chỉ có thể cập nhật yêu cầu mà bạn gửi');
            }

            // Tenants can only update description, not status
            if (isset($input['status'])) {
                throw new \Exception('Tenants cannot change request status');
            }
        } elseif ($user->role->code === 'manager') {
            // Managers can update recipient_id only to admin users
            if (isset($input['recipient_id'])) {
                $recipient = User::find($input['recipient_id']);
                if (!$recipient || $recipient->role->code !== 'admin') {
                    throw new \Exception('Managers can only change recipient to admin users');
                }
            }
        }

        $validator = Validator::make($input, [
            'sender_id' => 'sometimes|exists:users,id',
            'recipient_id' => 'sometimes|exists:users,id',
            'request_type' => 'sometimes|string|max:50',
            'description' => 'sometimes|string',
            'status' => 'sometimes|string|max:20',
        ], [
            'sender_id.exists' => 'Người gửi không tồn tại.',
            'recipient_id.exists' => 'Người nhận không tồn tại.',
            'request_type.string' => 'Loại yêu cầu phải là một chuỗi.',
            'request_type.max' => 'Loại yêu cầu không được vượt quá 50 ký tự.',
            'description.string' => 'Nội dung phải là một chuỗi.',
            'status.string' => 'Trạng thái phải là một chuỗi.',
            'status.max' => 'Trạng thái không được vượt quá 20 ký tự.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Set updated_by to current user
        $input['updated_by'] = $user->id;

        $updatedRequest = $this->requestRepository->update($id, $input);

        // Send notifications about status changes
        if (isset($input['status']) && $input['status'] !== $oldStatus) {
            $this->sendStatusChangeNotifications($user, $updatedRequest, $input['status']);
        }

        // Send notifications about recipient changes
        if (isset($input['recipient_id']) && $input['recipient_id'] != $oldRecipientId) {
            $this->sendRecipientChangeNotifications($user, $updatedRequest, $input['recipient_id']);
        }

        // Send notifications about sender changes
        if (isset($input['sender_id']) && $input['sender_id'] != $oldSenderId) {
            $this->sendSenderChangeNotifications($user, $updatedRequest, $input['sender_id']);
        }

        return $updatedRequest->load(['sender', 'recipient', 'updater']);
    }

    /**
     * Delete a request
     *
     * @param string $id
     * @return bool
     * @throws \Exception
     */
    public function deleteRequest($id)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Lỗi xác thực.');
        }

        $request = $this->requestRepository->getById($id);
        if (!$request) {
            throw new \Exception('Yêu cầu không tồn tại.');
        }

        // Authorization check - only admins and managers can delete requests
        if ($user->role->code === 'tenant') {
            throw new \Exception('Tenants cannot delete requests');
        }

        // Manager có thể xóa request họ gửi hoặc nhận, nhưng không được xóa nếu người gửi là admin
        if ($user->role->code === 'manager') {
            // Kiểm tra nếu không phải là người gửi hoặc người nhận
            if ($request->sender_id !== $user->id && $request->recipient_id !== $user->id) {
                throw new \Exception('Bạn chỉ có thể xóa yêu cầu mà bạn gửi hoặc nhận lại từ khách trọ');
            }

            // Kiểm tra nếu người gửi là admin
            $sender = User::find($request->sender_id);
            if ($sender && $sender->role->code === 'admin') {
                throw new \Exception('Bạn không thể xóa yêu cầu được gửi từ quản trị viên');
            }
        }

        return $this->requestRepository->delete($id);
    }

    /**
     * Send notifications about status changes
     *
     * @param User $user
     * @param \App\Models\Request $request
     * @param string $newStatus
     * @return void
     */
    private function sendStatusChangeNotifications(User $user, $request, $newStatus)
    {
        try {
            // Xác định các đối tượng cần thông báo
            $notificationRecipients = [];

            // Tạo nội dung thông báo
            $statusText = match ($newStatus) {
                'pending' => 'đang chờ',
                'in_progress' => 'đang xử lý',
                'completed' => 'đã hoàn thành',
                'rejected' => 'đã bị từ chối',
                default => $newStatus
            };

            $notificationContent = "Yêu cầu #{$request->id} đã được cập nhật trạng thái thành {$statusText} bởi {$user->name}";

            // Thông báo cho người gửi (nếu không phải người cập nhật)
            if ($request->sender_id && $request->sender_id !== $user->id) {
                $notificationRecipients[] = $request->sender_id;
            }

            // Thông báo cho người nhận (nếu không phải người cập nhật)
            if ($request->recipient_id && $request->recipient_id !== $user->id) {
                $notificationRecipients[] = $request->recipient_id;
            }

            // Gửi thông báo
            foreach ($notificationRecipients as $recipientId) {
                $this->notificationService->create(
                    $recipientId,
                    'request_updated',
                    $notificationContent,
                    "/requests/{$request->id}"
                );
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error sending status change notifications: ' . $e->getMessage());
        }
    }

    /**
     * Send notifications about recipient changes
     *
     * @param User $user
     * @param \App\Models\Request $request
     * @param string $newRecipientId
     * @return void
     */
    private function sendRecipientChangeNotifications(User $user, $request, $newRecipientId)
    {
        try {
            // Thông báo cho người nhận mới
            $this->notificationService->create(
                $newRecipientId,
                'request_transferred',
                "{$user->name} đã chuyển yêu cầu #{$request->id} cho bạn",
                "/requests/{$request->id}"
            );

            // Thông báo cho người gửi (nếu không phải người cập nhật)
            if ($request->sender_id && $request->sender_id !== $user->id) {
                $this->notificationService->create(
                    $request->sender_id,
                    'request_transferred',
                    "{$user->name} đã chuyển yêu cầu #{$request->id} cho người khác",
                    "/requests/{$request->id}"
                );
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error sending recipient change notifications: ' . $e->getMessage());
        }
    }

    /**
     * Send notifications about sender changes
     *
     * @param User $user
     * @param \App\Models\Request $request
     * @param string $newSenderId
     * @return void
     */
    private function sendSenderChangeNotifications(User $user, $request, $newSenderId)
    {
        try {
            // Thông báo cho người gửi mới
            $this->notificationService->create(
                $newSenderId,
                'request_sender_changed',
                "{$user->name} đã thay đổi người gửi của yêu cầu #{$request->id} thành bạn",
                "/requests/{$request->id}"
            );

            // Thông báo cho người nhận (nếu không phải người cập nhật)
            if ($request->recipient_id && $request->recipient_id !== $user->id) {
                $this->notificationService->create(
                    $request->recipient_id,
                    'request_sender_changed',
                    "{$user->name} đã thay đổi người gửi của yêu cầu #{$request->id}",
                    "/requests/{$request->id}"
                );
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error sending sender change notifications: ' . $e->getMessage());
        }
    }
}
