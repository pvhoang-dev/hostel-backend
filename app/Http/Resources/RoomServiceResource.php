<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\RoomResource;
use App\Http\Resources\ServiceResource;

class RoomServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'room_id'        => $this->room_id,
            'service_id'     => $this->service_id,
            'effective_date' => $this->effective_date,
            'custom_price'   => $this->custom_price,
            'status'         => $this->status,
            'room'           => new RoomResource($this->whenLoaded('room')),
            'service'        => new ServiceResource($this->whenLoaded('service')),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
