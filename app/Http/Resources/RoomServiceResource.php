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
            'price'          => $this->price,
            'status'         => $this->status,
            'is_fixed'       => $this->is_fixed,
            'description'    => $this->description,
            // 'room'           => new RoomResource($this->whenLoaded('room')),
            'service'        => new ServiceResource($this->whenLoaded('service')),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
