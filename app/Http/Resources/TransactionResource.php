<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentMethodResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'invoice'           => new InvoiceResource($this->whenLoaded('invoice')),
            'payment_method'    => new PaymentMethodResource($this->whenLoaded('paymentMethod')),
            'amount'            => $this->amount,
            'transaction_code'  => $this->transaction_code,
            'status'            => $this->status,
            'payment_date'      => $this->payment_date,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
