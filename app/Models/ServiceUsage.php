<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceUsage extends Model
{
    protected $table = 'service_usage';
    protected $fillable = [
        'room_service_id',
        'start_meter',
        'end_meter',
        'usage_value',
        'period',
        'price_used',
        'notes'
    ];

    public function roomService()
    {
        return $this->belongsTo(RoomService::class);
    }
}
