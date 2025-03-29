<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipmentStorage extends Model
{
    use SoftDeletes;
    protected $table = 'equipment_storage';
    protected $fillable = [
        'house_id',
        'equipment_id',
        'quantity',
        'price',
        'description'
    ];

    public function house()
    {
        return $this->belongsTo(House::class);
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }
}
