<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\HouseResource;
use App\Models\House;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Services\NotificationService;
class HouseController extends BaseController
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of the houses.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn cần đăng nhập để thực hiện thao tác này'], 401);
        }

        // Khởi tạo query
        $query = House::query();

        // Phân quyền dựa trên vai trò
        if ($user->role->code === 'manager') {
            // Manager chỉ thấy nhà họ quản lý
            $query->where('manager_id', $user->id);
        } elseif ($user->role->code === 'tenant') {
            // Tenant chỉ thấy nhà họ đang thuê thông qua hợp đồng active
            $housesOfTenant = House::whereHas('rooms', function($q) use ($user) {
                $q->whereHas('contracts', function($q2) use ($user) {
                    $q2->where('status', 'active')
                      ->whereHas('users', function($q3) use ($user) {
                          $q3->where('users.id', $user->id);
                      });
                });
            });
            
            // Lấy các house_id mà tenant có hợp đồng active
            $houseIds = $housesOfTenant->pluck('id')->toArray();
            
            if (empty($houseIds)) {
                // Nếu không có nhà nào, trả về kết quả rỗng
                return $this->sendResponse(
                    ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 15]],
                    'Không tìm thấy nhà trọ cho bạn.'
                );
            }
            
            $query->whereIn('id', $houseIds);
        }
        // Admin có thể xem tất cả nhà

        // Filter by name
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by manager_id (admin only)
        if ($user->role->code === 'admin' && $request->has('manager_id')) {
            $query->where('manager_id', $request->manager_id);
        }

        // Filter by address
        if ($request->has('address')) {
            $query->where('address', 'like', '%' . $request->address . '%');
        }

        // Filter by created/updated date ranges
        if ($request->has('created_from')) {
            $query->where('created_at', '>=', $request->created_from);
        }

        if ($request->has('created_to')) {
            $query->where('created_at', '<=', $request->created_to);
        }

        if ($request->has('updated_from')) {
            $query->where('updated_at', '>=', $request->updated_from);
        }

        if ($request->has('updated_to')) {
            $query->where('updated_at', '<=', $request->updated_to);
        }

        // Include relationships
        $with = ['manager'];

        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('updater', $includes)) {
                $with[] = 'updater';
            }
            if (in_array('rooms', $includes)) {
                $with[] = 'rooms';
            }
        }

        $query->with($with);

        // Sorting
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_dir', 'desc');
        $allowedSortFields = ['id', 'name', 'created_at', 'updated_at', 'status', 'address'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $houses = $query->paginate($perPage);

        return $this->sendResponse(
            HouseResource::collection($houses)->response()->getData(true),
            'Lấy danh sách nhà trọ thành công.'
        );
    }

    /**
     * Store a newly created house in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn cần đăng nhập để thực hiện thao tác này'], 401);
        }

        // Already checks for admin only
        if ($currentUser->role->code !== 'admin') {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Chỉ quản trị viên mới có thể tạo nhà trọ mới'], 403);
        }

        $input = $request->all();
        $validator = Validator::make($input, [
            'name' => 'required|string|max:100',
            'address' => 'required|string|max:255',
            'manager_id' => 'nullable|exists:users,id',
            'status' => 'sometimes|string',
            'description' => 'nullable|string',
        ], [
            'name.required' => 'Tên nhà trọ là bắt buộc.',
            'name.string' => 'Tên nhà trọ phải là chuỗi.',
            'name.max' => 'Tên nhà trọ không được vượt quá 100 ký tự.',
            'address.required' => 'Địa chỉ nhà trọ là bắt buộc.',
            'address.string' => 'Địa chỉ nhà trọ phải là chuỗi.',
            'address.max' => 'Địa chỉ nhà trọ không được vượt quá 255 ký tự.',
            'manager_id.exists' => 'Quản lý không tồn tại.',
            'status.string' => 'Trạng thái phải là chuỗi.',
            'description.string' => 'Mô tả phải là chuỗi.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Lỗi dữ liệu.', $validator->errors(), 422);
        }

        if (empty($input['manager_id'])) {
            $input['manager_id'] = $currentUser->id;
        }

        $input['created_by'] = $currentUser->id;
        $input['updated_by'] = $currentUser->id;

        $house = House::create($input);
        
        if ($house->manager_id !== $currentUser->id) {
            $this->notificationService->create(
                $house->manager_id,
                'house',
                "Nhà trọ {$house->name} đã được tạo. Bạn là quản lý của nhà trọ này.",
                "/houses/{$house->id}",
                false
            );
        }

        return $this->sendResponse(new HouseResource($house), 'Nhà trọ được tạo thành công.');
    }

    /**
     * Display the specified house.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn cần đăng nhập để thực hiện thao tác này'], 401);
        }

        $house = House::with(['manager', 'updater'])->find($id);

        if (is_null($house)) {
            return $this->sendError('Không tìm thấy nhà trọ.');
        }

        // Kiểm tra quyền truy cập
        if ($user->role->code === 'tenant') {
            // Tenant chỉ có thể xem nhà của mình thông qua hợp đồng active
            $hasAccess = House::where('id', $id)
                ->whereHas('rooms', function($q) use ($user) {
                    $q->whereHas('contracts', function($q2) use ($user) {
                        $q2->where('status', 'active')
                          ->whereHas('users', function($q3) use ($user) {
                              $q3->where('users.id', $user->id);
                          });
                    });
                })
                ->exists();
                
            if (!$hasAccess) {
                return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền xem thông tin nhà trọ này'], 403);
            }
        } elseif ($user->role->code === 'manager' && $house->manager_id !== $user->id) {
            // Manager chỉ được xem nhà họ quản lý
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền xem thông tin nhà trọ này'], 403);
        }

        return $this->sendResponse(new HouseResource($house), 'Lấy thông tin nhà trọ thành công.');
    }

    /**
     * Update the specified house in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn cần đăng nhập để thực hiện thao tác này'], 401);
        }

        // Check if user is admin or manager
        if (!in_array($currentUser->role->code, ['admin', 'manager'])) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Chỉ quản trị viên hoặc quản lý mới có quyền cập nhật nhà trọ'], 403);
        }

        $house = House::find($id);

        if (is_null($house)) {
            return $this->sendError('Không tìm thấy nhà trọ.');
        }

        // Check permissions
        $isAdmin = $currentUser->role->code === 'admin';
        $isManager = $currentUser->role->code === 'manager' && $house->manager_id === $currentUser->id;

        if (!$isAdmin && !$isManager) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn không có quyền cập nhật nhà trọ này'], 403);
        }

        // Determine which fields can be updated based on role
        $fieldsAllowed = $isAdmin
            ? ['name', 'address', 'manager_id', 'status', 'description']
            : ['name', 'address', 'status', 'description'];

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'address' => 'sometimes|required|string|max:255',
            'manager_id' => $isAdmin ? 'sometimes|nullable|exists:users,id' : '',
            'status' => 'sometimes|string',
            'description' => 'sometimes|nullable|string',
        ], [
            'name.required' => 'Tên nhà trọ là bắt buộc.',
            'name.string' => 'Tên nhà trọ phải là chuỗi.',
            'name.max' => 'Tên nhà trọ không được vượt quá 100 ký tự.',
            'address.required' => 'Địa chỉ nhà trọ là bắt buộc.',
            'address.string' => 'Địa chỉ nhà trọ phải là chuỗi.',
            'address.max' => 'Địa chỉ nhà trọ không được vượt quá 255 ký tự.',
            'manager_id.exists' => 'Quản lý không tồn tại.',
            'status.string' => 'Trạng thái phải là chuỗi.',
            'description.string' => 'Mô tả phải là chuỗi.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Lỗi dữ liệu.', $validator->errors(), 422);
        }

        // Filter request data based on allowed fields
        $input = array_intersect_key($request->all(), array_flip($fieldsAllowed));

        if ($isAdmin && isset($input['manager_id']) && empty($input['manager_id'])) {
            $input['manager_id'] = $currentUser->id;
        }

        // Add updated_by
        $input['updated_by'] = $currentUser->id;

        $house->update($input);
        $house->load(['manager', 'updater']);

        if ($house->manager_id !== $currentUser->id) {
            $this->notificationService->create(
                $house->manager_id,
                'house',
                "Nhà trọ {$house->name} đã được cập nhật. Bạn là quản lý của nhà trọ này.",
                "/houses/{$house->id}",
                false
            );
        }

        return $this->sendResponse(new HouseResource($house), 'Cập nhật nhà trọ thành công.');
    }

    /**
     * Remove the specified house from storage.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Bạn cần đăng nhập để thực hiện thao tác này'], 401);
        }

        // Already checks for admin only
        if ($currentUser->role->code !== 'admin') {
            return $this->sendError('Lỗi xác thực.', ['error' => 'Chỉ quản trị viên mới có thể xóa nhà trọ'], 403);
        }

        $house = House::find($id);

        if ($house->manager_id !== $currentUser->id) {
            $this->notificationService->create(
                $house->manager_id,
                'house',
                "Nhà trọ {$house->name} đã bị xóa.",
                "/houses/{$house->id}",
                false
            );
        }

        if (is_null($house)) {
            return $this->sendError('Không tìm thấy nhà trọ.');
        }

        $house->delete();

        return $this->sendResponse([], 'Xóa nhà trọ thành công.');
    }
}
