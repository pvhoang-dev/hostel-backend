<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'room_id',
        'user_id',
        'request_type',
        'description',
        'status',
        'created_by',
        'updated_by'
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        return $this->hasMany(MaintenanceComment::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($request) {
            if (!$request->isForceDeleting()) {
                foreach ($request->comments as $comment) {
                    $comment->delete();
                }
            }
        });
    }
}
