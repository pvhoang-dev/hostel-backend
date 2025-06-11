<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    use HasFactory;

    /**
     * Tên bảng liên kết với model
     *
     * @var string
     */
    protected $table = 'configs';

    /**
     * Các trường có thể gán hàng loạt
     *
     * @var array
     */
    protected $fillable = [
        'key',
        'value',
        'description',
        'group',
        'status'
    ];

    /**
     * Lấy giá trị của cấu hình theo key
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getValueByKey(string $key, $default = null)
    {
        $config = self::where('key', $key)
            ->where('status', 'active')
            ->first();

        return $config ? $config->value : $default;
    }

    /**
     * Lấy tất cả cấu hình theo nhóm
     *
     * @param string $group
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getByGroup(string $group)
    {
        return self::where('group', $group)
            ->where('status', 'active')
            ->get();
    }

    /**
     * Lấy tất cả cấu hình theo nhóm dưới dạng mảng key-value
     *
     * @param string $group
     * @return array
     */
    public static function getByGroupAsArray(string $group)
    {
        $configs = self::where('group', $group)
            ->where('status', 'active')
            ->get();

        $result = [];
        foreach ($configs as $config) {
            $result[$config->key] = $config->value;
        }

        return $result;
    }

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        // Xóa cache khi cấu hình được cập nhật hoặc xóa
        static::saved(function ($config) {
            if ($config->group === 'payos' && function_exists('clear_payos_config_cache')) {
                clear_payos_config_cache();
            }
        });
        
        static::deleted(function ($config) {
            if ($config->group === 'payos' && function_exists('clear_payos_config_cache')) {
                clear_payos_config_cache();
            }
        });
    }
} 