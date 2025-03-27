<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomEquipment extends Model
{
    protected $table = 'room_equipments';
    protected $fillable = [
        'equipment_id',
        'room_id',
        'source',
        'quantity',
        'price',
        'custom_name',
        'description'
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }
}
