<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomService extends Model
{
    use SoftDeletes;

    protected $table = 'room_services';
    protected $fillable = [
        'room_id',
        'service_id',
        'status',
        'price',
        'is_fixed',
        'description'
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function usages()
    {
        return $this->hasMany(ServiceUsage::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($roomService) {
            if (!$roomService->isForceDeleting()) {
                foreach ($roomService->usages as $usage) {
                    $usage->delete();
                }
            }
        });
    }
}
