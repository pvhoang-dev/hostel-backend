<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\ContractResource;

class ContractUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'contract' => new ContractResource($this->whenLoaded('contract')),
            'user'     => new UserResource($this->whenLoaded('user')),
        ];
    }
}
