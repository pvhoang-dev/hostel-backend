<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\RoomServiceResource;

class ServiceUsageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'room_service_id' => $this->room_service_id,
            'start_meter'     => $this->start_meter,
            'end_meter'       => $this->end_meter,
            'usage_value'     => $this->usage_value,
            'period'          => $this->period,
            'price_used'      => $this->price_used,
            'notes'           => $this->notes,
            'room_service'    => new RoomServiceResource($this->whenLoaded('roomService')),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
