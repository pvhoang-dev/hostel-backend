<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\RequestResource;

class RequestCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'request'    => new RequestsResource($this->whenLoaded('request')),
            'user'                   => new UserResource($this->whenLoaded('user')),
            'content'                => $this->content,
            'created_at'             => $this->created_at->format('h:m:s d/m/Y'),
            'updated_at'             => $this->updated_at->format('h:m:s d/m/Y'),
        ];
    }
}
