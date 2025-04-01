<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'created_at'    => $this->created_at->format('H:i:s d/m/Y'),
            'updated_at'    => $this->updated_at->format('H:i:s d/m/Y'),
            'deleted_at'    => $this->deleted_at ? $this->deleted_at->format('H:i:s d/m/Y') : null,
        ];
    }
}
