<?php

namespace App\Http\Resources;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'description'  => $this->description,
            'status'       => $this->status,
            'comments'     => RequestCommentResource::collection($this->whenLoaded('comments')),
            'room'         => new RoomResource($this->whenLoaded('room')),
            'sender'       => new UserResource($this->whenLoaded('sender')),
            'recipient'    => new UserResource($this->whenLoaded('recipient')),
            'updated_by'   => new UserResource($this->whenLoaded('updater')),
            'created_at'   => $this->created_at->format('h:m:s d/m/Y'),
            'updated_at'   => $this->updated_at->format('h:m:s d/m/Y'),
        ];
    }
}
