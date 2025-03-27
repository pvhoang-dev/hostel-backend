<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ContractResource;
use App\Http\Resources\RoomServiceResource;

class RecurringInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'contract'          => new ContractResource($this->whenLoaded('contract')),
            'type'              => $this->type,
            'room_service'      => new RoomServiceResource($this->whenLoaded('roomService')),
            'amount'            => $this->amount,
            'price_source_type' => $this->price_source_type,
            'price_source_id'   => $this->price_source_id,
            'frequency'         => $this->frequency,
            'next_run_date'     => $this->next_run_date,
            'end_date'          => $this->end_date,
            'status'            => $this->status,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
