<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'room_id',
        'start_date',
        'end_date',
        'monthly_price',
        'deposit_amount',
        'payment_terms',
        'notice_period',
        'deposit_status',
        'termination_reason',
        'status',
        'auto_renew',
        'created_by',
        'updated_by'
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function contractUsers()
    {
        return $this->hasMany(ContractUser::class);
    }

    public function recurringInvoices()
    {
        return $this->hasMany(RecurringInvoice::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($contract) {
            if (!$contract->isForceDeleting()) {
                foreach ($contract->contractUsers as $cu) {
                    $cu->delete();
                }
                foreach ($contract->recurringInvoices as $ri) {
                    $ri->delete();
                }
            }
        });
    }
}
