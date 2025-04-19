<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\HouseResource;
use App\Http\Resources\EquipmentResource;

class EquipmentStorageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'quantity'     => $this->quantity,
            'price'        => $this->price,
            'description'  => $this->description,
            'house'        => new HouseResource($this->whenLoaded('house')),
            'equipment'    => new EquipmentResource($this->whenLoaded('equipment')),
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
