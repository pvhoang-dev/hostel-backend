<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\RoomEquipmentResource;
use App\Services\RoomEquipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoomEquipmentController extends BaseController
{
    protected $roomEquipmentService;

    public function __construct(RoomEquipmentService $roomEquipmentService)
    {
        $this->roomEquipmentService = $roomEquipmentService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $result = $this->roomEquipmentService->getAllRoomEquipment($request);
            return $this->sendResponse(
                $result,
                'Thiết bị phòng đã được lấy thành công.'
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
            $roomEquipment = $this->roomEquipmentService->createRoomEquipment($request);
            return $this->sendResponse(
                new RoomEquipmentResource($roomEquipment),
                'Thiết bị phòng đã được tạo thành công.'
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
            $roomEquipment = $this->roomEquipmentService->getRoomEquipmentById($id);
            return $this->sendResponse(
                new RoomEquipmentResource($roomEquipment),
                'Thiết bị phòng đã được lấy thành công.'
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
            $roomEquipment = $this->roomEquipmentService->updateRoomEquipment($request, $id);
            return $this->sendResponse(
                new RoomEquipmentResource($roomEquipment),
                'Thiết bị phòng đã được cập nhật thành công.'
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
            $this->roomEquipmentService->deleteRoomEquipment($id);
            return $this->sendResponse([], 'Thiết bị phòng đã được xóa thành công.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }
}
