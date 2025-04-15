<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceUsage extends Model
{
    use SoftDeletes;

    protected $table = 'service_usage';
    protected $fillable = [
        'room_service_id',
        'start_meter',
        'end_meter',
        'usage_value',
        'month',
        'year',
        'price_used',
        'description'
    ];

    public function roomService()
    {
        return $this->belongsTo(RoomService::class);
    }
}
