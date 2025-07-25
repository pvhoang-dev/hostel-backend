<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\RoomResource;
use App\Http\Resources\InvoiceItemResource;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\UserResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'room'           => new RoomResource($this->whenLoaded('room')),
            'invoice_type'   => $this->invoice_type,
            'total_amount'   => $this->total_amount,
            'month'          => $this->month,
            'year'           => $this->year,
            'description'    => $this->description,
            'creator'        => new UserResource($this->whenLoaded('creator')),
            'updater'        => new UserResource($this->whenLoaded('updater')),
            'items'          => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payment_method' => new PaymentMethodResource($this->whenLoaded('paymentMethod')),
            'transaction_code' => $this->transaction_code,
            'payment_status' => $this->payment_status,
            'payment_date' => $this->payment_date,
            'created_at'     => $this->created_at->format('d/m/Y'),
            'updated_at'     => $this->updated_at->format('d/m/Y'),
        ];
    }
}
