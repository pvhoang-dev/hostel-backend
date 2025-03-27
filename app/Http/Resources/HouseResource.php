<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;

class HouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'address'     => $this->address,
            'status'      => $this->status,
            'description' => $this->description,
            'manager'     => new UserResource($this->whenLoaded('manager')),
            'created_by'  => new UserResource($this->whenLoaded('creator')),
            'updated_by'  => new UserResource($this->whenLoaded('updater')),
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
