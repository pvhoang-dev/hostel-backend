<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\RoomResource;
use App\Http\Resources\EquipmentResource;

class RoomEquipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'quantity'     => $this->quantity,
            'price'        => $this->price,
            'description'  => $this->description,
            'room'         => new RoomResource($this->whenLoaded('room')),
            'equipment'    => new EquipmentResource($this->whenLoaded('equipment')),
            'created_at'   => $this->created_at->format('h:m:s d/m/Y'),
            'updated_at'   => $this->updated_at->format('h:m:s d/m/Y'),
        ];
    }
}
