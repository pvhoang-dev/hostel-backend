<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomEquipment extends Model
{
    use SoftDeletes;

    protected $table = 'room_equipments';
    protected $fillable = [
        'equipment_id',
        'room_id',
        'quantity',
        'price',
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
