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
        'updated_by'
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($invoice) {
            if (!$invoice->isForceDeleting()) {
                foreach ($invoice->items as $item) {
                    $item->delete();
                }
                foreach ($invoice->transactions as $transaction) {
                    $transaction->delete();
                }
            }
        });
    }
}
