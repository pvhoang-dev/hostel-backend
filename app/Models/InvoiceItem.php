<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceItem extends Model
{
    use SoftDeletes;
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
