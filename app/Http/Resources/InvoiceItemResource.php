<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\InvoiceResource;

class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'invoice'     => new InvoiceResource($this->whenLoaded('invoice')),
            'source_type' => $this->source_type,
            'source_id'   => $this->source_id,
            'item_type'   => $this->item_type,
            'amount'      => $this->amount,
            'description' => $this->description,
            'period'      => $this->period,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
