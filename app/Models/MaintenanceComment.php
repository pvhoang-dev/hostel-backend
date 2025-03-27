<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceComment extends Model
{
    protected $fillable = [
        'maintenance_request_id',
        'user_id',
        'content'
    ];

    public function maintenanceRequest()
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
