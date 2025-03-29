<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicePriceHistory extends Model
{
    use SoftDeletes;
    
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
