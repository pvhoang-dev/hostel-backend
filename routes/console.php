<?php

use Illuminate\Support\Facades\Schedule;

// Schedule cho hợp đồng hết hạn - chạy vào 00:00 hàng ngày
Schedule::command('contracts:update-expired')->daily()
    ->description('Cập nhật hợp đồng đã hết hạn và phòng tương ứng')
    ->appendOutputTo(storage_path('logs/contract-expiry-notification.log'));

Schedule::command('contracts:expiring-notify')->daily()
    ->description('Thông báo hợp đồng sắp hết hạn')
    ->appendOutputTo(storage_path('logs/contract-expiry-notification.log'));