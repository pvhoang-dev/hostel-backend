<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class House extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'manager_id',
        'status',
        'description',
        'created_by',
        'updated_by'
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function settings()
    {
        return $this->hasMany(HouseSetting::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($house) {
            if (!$house->isForceDeleting()) {
                foreach ($house->rooms as $room) {
                    $room->delete();
                }
                foreach ($house->settings as $setting) {
                    $setting->delete();
                }
            }
        });
    }
}
