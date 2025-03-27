<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\MaintenanceRequestResource;

class MaintenanceCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'maintenance_request'    => new MaintenanceRequestResource($this->whenLoaded('maintenanceRequest')),
            'user'                   => new UserResource($this->whenLoaded('user')),
            'content'                => $this->content,
            'created_at'             => $this->created_at,
            'updated_at'             => $this->updated_at,
        ];
    }
}
