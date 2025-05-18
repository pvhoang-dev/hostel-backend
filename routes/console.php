<?php

use Illuminate\Support\Facades\Schedule;

// Schedule cho hợp đồng hết hạn - chạy vào 00:00 hàng ngày
Schedule::command('contracts:update-expired')->daily()
    ->description('Cập nhật hợp đồng đã hết hạn và phòng tương ứng');