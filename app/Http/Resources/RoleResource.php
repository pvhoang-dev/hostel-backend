<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            // 'users' => UserResource::collection($this->whenLoaded('users')),
            'created_at' => $this->created_at->format('h:m:s d/m/Y'),
            'updated_at' => $this->updated_at->format('h:m:s d/m/Y'),
        ];
    }
}
