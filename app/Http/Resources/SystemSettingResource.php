<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'key'         => $this->key,
            'value'       => $this->value,
            'description' => $this->description,
            'created_by'  => new UserResource($this->whenLoaded('creator')),
            'updated_by'  => new UserResource($this->whenLoaded('updater')),
            'created_at'  => $this->created_at->format('h:m:s d/m/Y'),
            'updated_at'  => $this->updated_at->format('h:m:s d/m/Y'),
        ];
    }
}
