<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServicePriceHistory extends Model
{
    protected $table = 'service_price_history';
    protected $fillable = [
        'room_service_id',
        'price',
        'effective_from',
        'effective_to'
    ];

    public function roomService()
    {
        return $this->belongsTo(RoomService::class);
    }
}
