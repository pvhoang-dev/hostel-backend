<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HouseSetting extends Model
{
    protected $table = 'house_settings';
    protected $fillable = [
        'house_id',
        'key',
        'value',
        'description',
        'created_by',
        'updated_by'
    ];

    public function house()
    {
        return $this->belongsTo(House::class);
    }
}
