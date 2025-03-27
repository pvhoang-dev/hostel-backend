<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'source_type',
        'source_id',
        'item_type',
        'amount',
        'description',
        'period'
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
