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
            'equipment_id' => $this->equipment_id,
            'room_id'      => $this->room_id,
            'source'       => $this->source,
            'quantity'     => $this->quantity,
            'price'        => $this->price,
            'custom_name'  => $this->custom_name,
            'description'  => $this->description,
            'room'         => new RoomResource($this->whenLoaded('room')),
            'equipment'    => new EquipmentResource($this->whenLoaded('equipment')),
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
