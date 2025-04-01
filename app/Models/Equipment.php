<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use SoftDeletes;
    protected $table = 'equipments';
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
