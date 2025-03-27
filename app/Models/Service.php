<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'name',
        'default_price',
        'unit',
        'is_metered'
    ];

    public function roomServices()
    {
        return $this->hasMany(RoomService::class);
    }
}
