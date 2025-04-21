<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\RoomResource;

class RoomPriceHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'price'          => $this->price,
            'effective_from' => $this->effective_from,
            'effective_to'   => $this->effective_to,
            'room'           => new RoomResource($this->whenLoaded('room')),
            'created_at'     => $this->created_at->format('h:m:s d/m/Y'),
            'updated_at'     => $this->updated_at->format('h:m:s d/m/Y'),
        ];
    }
}
