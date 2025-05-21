<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EquipmentResource;
use App\Models\Equipment;
use App\Services\EquipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Class EquipmentController
 *
 * @package App\Http\Controllers\Api
 */
class EquipmentController extends BaseController
{
    protected $equipmentService;

    public function __construct(EquipmentService $equipmentService)
    {
        $this->equipmentService = $equipmentService;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $result = $this->equipmentService->getAllEquipments($request);
            return $this->sendResponse($result, 'Equipment fetched successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $equipment = $this->equipmentService->createEquipment($request);
            return $this->sendResponse(new EquipmentResource($equipment), 'Equipment created successfully', 201);
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $equipment = $this->equipmentService->getEquipmentById($id);
            return $this->sendResponse(new EquipmentResource($equipment), 'Equipment fetched successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Equipment $equipment
     * @return JsonResponse
     */
    public function update(Request $request, Equipment $equipment): JsonResponse
    {
        try {
            $equipment = $this->equipmentService->updateEquipment($request, $equipment);
            return $this->sendResponse(new EquipmentResource($equipment), 'Equipment updated successfully');
        } catch (ValidationException $e) {
            return $this->sendError('Lỗi dữ liệu', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Equipment $equipment
     * @return JsonResponse
     */
    public function destroy(Equipment $equipment): JsonResponse
    {
        try {
            $this->equipmentService->deleteEquipment($equipment);
            return $this->sendResponse(null, 'Equipment deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}