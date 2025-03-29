<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemSetting extends Model
{
    use SoftDeletes;
    
    protected $table = 'system_settings';
    protected $fillable = [
        'key',
        'value',
        'description',
        'created_by',
        'updated_by'
    ];
}
