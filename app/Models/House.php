<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class House extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'manager_id',
        'status',
        'description',
        'updated_by'
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function storages()
    {
        return $this->hasMany(EquipmentStorage::class);
    }

    public function settings()
    {
        return $this->hasMany(HouseSetting::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($house) {
            if (!$house->isForceDeleting()) {
                // Xóa các phòng trong nhà - sẽ trigger cascade delete cho các bảng liên quan đến phòng
                foreach ($house->rooms as $room) {
                    // Xóa các hợp đồng liên quan đến phòng
                    foreach ($room->contracts as $contract) {
                        $contract->delete();
                    }
                    
                    // Xóa các thiết bị phòng
                    foreach ($room->equipments as $equipment) {
                        $equipment->delete();
                    }
                    
                    // Xóa các dịch vụ phòng
                    foreach ($room->services as $service) {
                        // Xóa các lần sử dụng dịch vụ
                        foreach ($service->usages as $usage) {
                            $usage->delete();
                        }
                        $service->delete();
                    }
                    
                    $room->delete();
                }
                
                // Xóa các cài đặt nhà
                foreach ($house->settings as $setting) {
                    $setting->delete();
                }
                
                // Xóa các thiết bị trong kho
                foreach ($house->storages as $storage) {
                    $storage->delete();
                }
            }
        });
    }
}
