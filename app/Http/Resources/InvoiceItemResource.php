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
            'service_usage_id'   => new ServiceUsageResource($this->whenLoaded('service_usage')),
            'amount'      => $this->amount,
            'description' => $this->description,
            'created_at'  => $this->created_at->format('h:m:s d/m/Y'),
            'updated_at'  => $this->updated_at->format('h:m:s d/m/Y'),
        ];
    }
}
