<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\RoleResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'username'         => $this->username,
            'name'             => $this->name,
            'email'            => $this->email,
            'phone_number'     => $this->phone_number,
            'hometown'         => $this->hometown,
            'identity_card'    => $this->identity_card,
            'vehicle_plate'    => $this->vehicle_plate,
            'status'           => $this->status,
            'role' => $this->whenLoaded('role', function () {
                return [
                    'id'   => $this->role->id,
                    'name' => $this->role->name,
                    'code' => $this->role->code,
                ];
            }),
            'avatar_url'       => $this->avatar_url,
            'notification_preferences' => $this->notification_preferences,
            'created_at'       => $this->created_at->format('h:m:s d/m/Y'),
            'updated_at'       => $this->updated_at->format('h:m:s d/m/Y'),
            'deleted_at'       => $this->deleted_at ? $this->deleted_at->format('H:i:s d/m/Y') : null,
        ];
    }
}
