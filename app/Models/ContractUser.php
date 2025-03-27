<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractUser extends Model
{
    protected $table = 'contract_users';
    public $timestamps = false;
    protected $fillable = ['contract_id', 'user_id'];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
