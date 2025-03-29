<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use SoftDeletes;
    
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
