<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'house_id',
        'room_number',
        'capacity',
        'description',
        'status',
        'base_price',
        'created_by',
        'updated_by'
    ];

    public function house()
    {
        return $this->belongsTo(House::class);
    }

    public function priceHistories()
    {
        return $this->hasMany(RoomPriceHistory::class);
    }

    public function equipments()
    {
        return $this->hasMany(RoomEquipment::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($room) {
            if (!$room->isForceDeleting()) {
                foreach ($room->priceHistories as $history) {
                    $history->delete();
                }
                foreach ($room->equipments as $equipment) {
                    $equipment->delete();
                }
            }
        });
    }
}
