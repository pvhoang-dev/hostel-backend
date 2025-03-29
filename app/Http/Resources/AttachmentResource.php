<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;

class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'entity_type' => $this->entity_type,
            'entity_id'   => $this->entity_id,
            'file_url'    => $this->file_url,
            'file_type'   => $this->file_type,
            'description' => $this->description,
            // 'uploader'    => new UserResource($this->whenLoaded('uploader')),
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
