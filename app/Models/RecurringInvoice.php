<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'contract_id',
        'type',
        'room_service_id',
        'amount',
        'price_source_type',
        'price_source_id',
        'frequency',
        'next_run_date',
        'end_date',
        'status'
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function roomService()
    {
        return $this->belongsTo(RoomService::class, 'room_service_id');
    }
}
