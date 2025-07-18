<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use SoftDeletes;
    
    protected $fillable = ['name', 'status'];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
