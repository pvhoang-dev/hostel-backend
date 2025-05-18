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

    public function equipments()
    {
        return $this->hasMany(RoomEquipment::class);
    }

    public function services()
    {
        return $this->hasMany(RoomService::class);
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function currentContract()
    {
        return $this->hasOne(Contract::class)
            ->where('status', 'active')
            ->latest('start_date');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($room) {
            if (!$room->isForceDeleting()) {
                foreach ($room->equipments as $equipment) {
                    $equipment->delete();
                }
                foreach ($room->services as $service) {
                    $service->delete();
                }
            }
        });
    }
}
