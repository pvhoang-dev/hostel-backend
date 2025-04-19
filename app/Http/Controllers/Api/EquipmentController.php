<?php

    namespace App\Http\Controllers\Api;

    use App\Http\Resources\EquipmentResource;
    use App\Models\Equipment;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Validator;

    /**
     * Class EquipmentController
     *
     * @package App\Http\Controllers\Api
     */
    class EquipmentController extends BaseController
    {
        /**
         * Display a listing of the resource.
         *
         * @param Request $request
         * @return JsonResponse
         */
        public function index(Request $request): JsonResponse
        {
            $query = Equipment::query();

            // Filter by name
            if ($request->has('name')) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }

            // Filter by room_id - find equipment linked to specific rooms
            if ($request->has('room_id')) {
                $query->whereHas('roomEquipments', function ($q) use ($request) {
                    $q->where('room_id', $request->room_id);
                });
            }

            // Filter by storage_id - find equipment linked to specific storage
            if ($request->has('storage_id')) {
                $query->whereHas('storages', function ($q) use ($request) {
                    $q->where('storage_id', $request->storage_id);
                });
            }

            // Include relationships
            $with = [];
            if ($request->has('include')) {
                $includes = explode(',', $request->include);
                if (in_array('storages', $includes)) $with[] = 'storages';
                if (in_array('roomEquipments', $includes)) $with[] = 'roomEquipments';
            }

            if (!empty($with)) {
                $query->with($with);
            }

            // Sorting
            $sortField = $request->get('sort_by', 'name');
            $sortDirection = $request->get('sort_dir', 'asc');
            $allowedSortFields = ['id', 'name', 'created_at', 'updated_at'];

            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
            } else {
                $query->orderBy('name', 'asc');
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $equipments = $query->paginate($perPage);

            return $this->sendResponse(
                EquipmentResource::collection($equipments)->response()->getData(true),
                'Equipments retrieved successfully'
            );
        }

        /**
         * Store a newly created resource in storage.
         *
         * @param  \Illuminate\Http\Request  $request
         * @return \Illuminate\Http\Response
         */
        public function store(Request $request)
        {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100|unique:equipments,name'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation Error.', $validator->errors(), 422);
            }

            $equipment = Equipment::create($request->all());

            return $this->sendResponse(
                new EquipmentResource($equipment),
                'Equipment created successfully'
            );
        }

        /**
         * Display the specified resource.
         *
         * @param  int  $id
         * @return \Illuminate\Http\Response
         */
        public function show(int $id)
        {
            $equipment = Equipment::find($id);

            if (is_null($equipment)) {
                return $this->sendError('Equipment not found');
            }

            return $this->sendResponse(
                new EquipmentResource($equipment),
                'Equipment retrieved successfully'
            );
        }

        /**
         * Update the specified resource in storage.
         *
         * @param  \Illuminate\Http\Request  $request
         * @param  \App\Models\Equipment  $equipment
         * @return \Illuminate\Http\JsonResponse
         */
        public function update(Request $request, Equipment $equipment): JsonResponse
        {
            $input = $request->all();

            $validator = Validator::make($input, [
                'name' => 'sometimes|required|string|max:100|unique:equipments,name,' . $equipment->id
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation Error.', $validator->errors(), 422);
            }

            if (isset($input['name'])) {
                $equipment->name = $input['name'];
            }

            $equipment->save();

            return $this->sendResponse(
                new EquipmentResource($equipment),
                'Equipment updated successfully.'
            );
        }

        /**
         * Remove the specified resource from storage.
         */
        public function destroy(string $id)
        {
            $equipment = Equipment::find($id);
            if (is_null($equipment)) {
                return $this->sendError('Equipment not found');
            }

            // Check if there are related records
            if ($equipment->storages()->count() > 0 || $equipment->roomEquipments()->count() > 0) {
                return $this->sendError(
                    'Cannot delete this equipment as it has associated storage or room records',
                    [],
                    422
                );
            }

            $equipment->delete();

            return $this->sendResponse([], 'Equipment deleted successfully');
        }
    }
