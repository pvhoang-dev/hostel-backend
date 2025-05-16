<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'room_id',
        'invoice_type',
        'total_amount',
        'month',
        'year',
        'description',
        'created_by',
        'updated_by',
        'payment_method_id',
        'transaction_code',
        'payment_status',
        'payment_date'
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getStatusAttribute()
    {
        return $this->payment_status;
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($invoice) {
            if (!$invoice->isForceDeleting()) {
                foreach ($invoice->items as $item) {
                    $item->delete();
                }
            }
        });
    }
}
