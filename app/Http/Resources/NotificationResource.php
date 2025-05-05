<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'content'    => $this->content,
            'url'        => $this->url,
            'is_read'    => $this->is_read,
            'user'       => new UserResource($this->user),
            'created_at' => $this->created_at->format('h:m:s d/m/Y'),
            'updated_at' => $this->updated_at->format('h:m:s d/m/Y'),
        ];
    }
}
