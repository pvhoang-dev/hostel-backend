<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\RoomServiceResource;

class ServicePriceHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'price'           => $this->price,
            'effective_from'  => $this->effective_from,
            'effective_to'    => $this->effective_to,
            'room_service'    => new RoomServiceResource($this->whenLoaded('roomService')),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
