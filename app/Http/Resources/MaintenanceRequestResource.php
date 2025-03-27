<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;

class MaintenanceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'room_id'      => $this->room_id,
            'description'  => $this->description,
            'status'       => $this->status,
            'user'         => new UserResource($this->whenLoaded('user')),
            'created_by'   => new UserResource($this->whenLoaded('creator')),
            'updated_by'   => new UserResource($this->whenLoaded('updater')),
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
