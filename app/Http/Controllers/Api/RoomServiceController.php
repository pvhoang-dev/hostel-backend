<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\RoomServiceResource;
use App\Services\RoomServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoomServiceController extends BaseController
{
    protected $roomServiceService;

    public function __construct(RoomServiceService $roomServiceService)
    {
        $this->roomServiceService = $roomServiceService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $result = $this->roomServiceService->getAllRoomServices($request);
            return $this->sendResponse(
                $result,
                'Dịch vụ phòng đã được lấy thành công.'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $roomService = $this->roomServiceService->createRoomService($request);
            return $this->sendResponse(
                new RoomServiceResource($roomService),
                'Dịch vụ phòng đã được tạo thành công.'
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
            $roomService = $this->roomServiceService->getRoomServiceById($id);
            return $this->sendResponse(
                new RoomServiceResource($roomService),
                'Dịch vụ phòng đã được lấy thành công.'
            );
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $roomService = $this->roomServiceService->updateRoomService($request, $id);
            return $this->sendResponse(
                new RoomServiceResource($roomService),
                'Dịch vụ phòng đã được cập nhật thành công.'
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
            $this->roomServiceService->deleteRoomService($id);
            return $this->sendResponse([], 'Dịch vụ phòng đã được xóa thành công.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }
}
