<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'content',
        'url',
        'is_read'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
