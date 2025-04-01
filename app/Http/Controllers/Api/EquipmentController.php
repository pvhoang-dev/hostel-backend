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
         */
        public function index()
        {
            $equipments = Equipment::all();

            return $this->sendResponse(
                EquipmentResource::collection($equipments),
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
