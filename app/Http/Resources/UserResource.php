<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'created_at'       => $this->created_at->format('h:m:s d/m/Y'),
            'updated_at'       => $this->updated_at->format('h:m:s d/m/Y'),
            'deleted_at'       => $this->deleted_at ? $this->deleted_at->format('H:i:s d/m/Y') : null,
            
            // Thêm thông tin về phòng
            'room' => $this->whenLoaded('contracts', function () {
                // Lấy hợp đồng đang hoạt động
                $activeContract = $this->contracts->where('status', 'active')->first();
                if ($activeContract && $activeContract->room) {
                    return [
                        'id' => $activeContract->room->id,
                        'room_number' => $activeContract->room->room_number,
                        'house_id' => $activeContract->room->house_id
                    ];
                }
                return null;
            }),
            
            // Thêm thông tin về nhà
            'house' => $this->when($this->resource->relationLoaded('contracts') || $this->resource->relationLoaded('housesManaged'), function () {
                // Nếu là manager, lấy nhà từ housesManaged
                if ($this->role && $this->role->code === 'manager' && $this->resource->relationLoaded('housesManaged')) {
                    $house = $this->housesManaged->first();
                    if ($house) {
                        return [
                            'id' => $house->id,
                            'name' => $house->name,
                            'address' => $house->address
                        ];
                    }
                }
                
                // Nếu là tenant, lấy nhà từ phòng trong hợp đồng đang hoạt động
                if ($this->resource->relationLoaded('contracts')) {
                    $activeContract = $this->contracts->where('status', 'active')->first();
                    if ($activeContract && $activeContract->room && $activeContract->room->house) {
                        return [
                            'id' => $activeContract->room->house->id,
                            'name' => $activeContract->room->house->name,
                            'address' => $activeContract->room->house->address
                        ];
                    }
                }
                
                return null;
            }),
        ];
    }
}
