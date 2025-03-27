<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    protected $fillable = ['name'];

    public function storages()
    {
        return $this->hasMany(EquipmentStorage::class);
    }

    public function roomEquipments()
    {
        return $this->hasMany(RoomEquipment::class);
    }
}
