<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\HouseResource;

class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'room_number' => $this->room_number,
            'capacity'    => $this->capacity,
            'description' => $this->description,
            'status'      => $this->status,
            'base_price'  => $this->base_price,
            'house'       => new HouseResource($this->whenLoaded('house')),
            'currentContract'   => new ContractResource($this->whenLoaded('currentContract')),
            'created_at'  => $this->created_at->format('h:m:s d/m/Y'),
            'updated_at'  => $this->updated_at->format('h:m:s d/m/Y'),
        ];
    }
}
