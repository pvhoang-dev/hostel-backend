<?php

use App\Models\Config;
use Illuminate\Support\Facades\Cache;

if (!function_exists('payos_config')) {
    /**
     * Lấy cấu hình PayOS từ database (sử dụng cache)
     *
     * @param string|null $key Key của cấu hình (không cần tiền tố payos_)
     * @param mixed $default Giá trị mặc định nếu không tìm thấy
     * @return mixed
     */
    function payos_config($key = null, $default = null)
    {
        $cacheKey = 'payos_config';
        
        // Nếu không truyền key, trả về toàn bộ cấu hình
        if ($key === null) {
            // Sử dụng cache cho toàn bộ cấu hình
            return Cache::remember($cacheKey, 86400, function () {
                return Config::where('group', 'payos')
                    ->where('status', 'active')
                    ->pluck('value', 'key')
                    ->toArray();
            });
        }
        
        // Thêm tiền tố payos_ vào key
        $fullKey = 'payos_' . $key;
        
        // Sử dụng cache cho từng key riêng biệt
        return Cache::remember("$cacheKey.$key", 86400, function () use ($fullKey, $default) {
            // Lấy cấu hình từ database
            $config = Config::where('key', $fullKey)
                ->where('status', 'active')
                ->first();
                
            return $config ? $config->value : $default;
        });
    }
}

if (!function_exists('clear_payos_config_cache')) {
    /**
     * Xóa cache cấu hình PayOS
     *
     * @return void
     */
    function clear_payos_config_cache()
    {
        Cache::forget('payos_config');
        
        // Xóa cache cho từng key đã biết
        $keys = ['client_id', 'api_key', 'checksum_key'];
        foreach ($keys as $key) {
            Cache::forget("payos_config.$key");
        }
    }
} 