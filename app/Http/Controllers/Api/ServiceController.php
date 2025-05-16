<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Class ServiceController
 *
 * @package App\Http\Controllers\Api
 */
class ServiceController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Service::query();

        // Apply filters
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->has('unit')) {
            $query->where('unit', 'like', '%' . $request->unit . '%');
        }

        if ($request->has('is_metered') && in_array($request->is_metered, ['0', '1'])) {
            $query->where('is_metered', $request->is_metered);
        }

        // Price range filters
        if ($request->has('min_price')) {
            $query->where('default_price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('default_price', '<=', $request->max_price);
        }

        // Date range filters
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
        $with = [];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('roomServices', $includes)) $with[] = 'roomServices';
        }

        // Sorting
        $sortField = $request->get('sort_by', 'name');
        $sortDirection = $request->get('sort_dir', 'asc');
        $allowedSortFields = ['id', 'name', 'default_price', 'unit', 'is_metered', 'created_at', 'updated_at'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('name', 'asc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $services = $query->with($with)->paginate($perPage);

        return $this->sendResponse(
            ServiceResource::collection($services)->response()->getData(true),
            'Dịch vụ đã được lấy thành công.'
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:services,name',
            'default_price' => 'required|integer',
            'unit' => 'required|string|max:20',
            'is_metered' => 'sometimes|boolean'
        ], [
            'name.required' => 'Tên dịch vụ là bắt buộc.',
            'name.unique' => 'Tên dịch vụ đã tồn tại.',
            'default_price.required' => 'Giá mặc định là bắt buộc.',
            'default_price.integer' => 'Giá mặc định phải là một số nguyên.',
            'unit.required' => 'Đơn vị là bắt buộc.',
            'unit.max' => 'Đơn vị không được vượt quá 20 ký tự.',
            'is_metered.boolean' => 'Trạng thái đo lường phải là true hoặc false.'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Tạo dịch vụ không thành công.', $validator->errors(), 422);
        }

        $service = Service::create($request->all());

        return $this->sendResponse(
            new ServiceResource($service),
            'Dịch vụ được tạo thành công.'
        );
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $service = Service::find($id);

        if (is_null($service)) {
            return $this->sendError('Không tìm thấy dịch vụ.');
        }

        return $this->sendResponse(
            new ServiceResource($service),
            'Dịch vụ đã được lấy thành công.'
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Service  $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Service $service): JsonResponse
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'name' => 'sometimes|required|string|max:50|unique:services,name,' . $service->id,
            'default_price' => 'sometimes|required|integer',
            'unit' => 'sometimes|required|string|max:20',
            'is_metered' => 'sometimes|boolean'
        ], [
            'name.required' => 'Tên dịch vụ là bắt buộc.',
            'name.unique' => 'Tên dịch vụ đã tồn tại.',
            'default_price.required' => 'Giá mặc định là bắt buộc.',
            'default_price.integer' => 'Giá mặc định phải là một số nguyên.',
            'unit.required' => 'Đơn vị là bắt buộc.',
            'unit.max' => 'Đơn vị không được vượt quá 20 ký tự.',
            'is_metered.boolean' => 'Trạng thái đo lường phải là true hoặc false.'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Cập nhật dịch vụ không thành công.', $validator->errors(), 422);
        }

        $service->fill($request->only([
            'name',
            'default_price',
            'unit',
            'is_metered'
        ]));

        $service->save();

        return $this->sendResponse(
            new ServiceResource($service),
            'Dịch vụ cập nhật thành công.'
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $service = Service::find($id);

        if (is_null($service)) {
            return $this->sendError('Không tìm thấy dịch vụ.');
        }

        // Check if there are related room service records
        if ($service->roomServices()->count() > 0) {
            return $this->sendError(
                'Không thể xóa dịch vụ này vì nó đang được sử dụng trong các phòng.',
                [],
                422
            );
        }

        $service->delete();

        return $this->sendResponse([], 'Dịch vụ đã được xóa thành công.');
    }
}
