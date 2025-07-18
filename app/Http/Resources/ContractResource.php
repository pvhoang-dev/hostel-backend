<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\RoomResource;
use App\Http\Resources\UserResource;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'room'             => new RoomResource($this->whenLoaded('room')),
            'tenants'          => UserResource::collection($this->whenLoaded('users')),
            'start_date'       => $this->start_date,
            'end_date'         => $this->end_date,
            'monthly_price'    => $this->monthly_price,
            'deposit_amount'   => $this->deposit_amount,
            'notice_period'    => $this->notice_period,
            'deposit_status'   => $this->deposit_status,
            'termination_reason' => $this->termination_reason,
            'status'           => $this->status,
            'auto_renew'       => $this->auto_renew,
            'time_renew'       => $this->time_renew,
            'created_by'       => new UserResource($this->whenLoaded('creator')),
            'updated_by'       => new UserResource($this->whenLoaded('updater')),
            'created_at'       => $this->created_at->format('h:m:s d/m/Y'),
            'updated_at'       => $this->updated_at->format('h:m:s d/m/Y'),
        ];
    }
}
