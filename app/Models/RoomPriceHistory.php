<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomPriceHistory extends Model
{
    use SoftDeletes;
    
    protected $table = 'room_price_history';
    protected $fillable = [
        'room_id',
        'price',
        'effective_from',
        'effective_to'
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
