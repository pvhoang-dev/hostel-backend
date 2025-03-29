<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractUser extends Model
{
    use SoftDeletes;
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
