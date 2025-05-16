<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('username', 'admin')->first();

        $settings = [
            [
                'key' => '1',
                'value' => 'H-Hostel - Hệ thống quản lý nhà trọ',
                'description' => 'Tên công ty quản lý nhà trọ'
            ],
            [
                'key' => '2',
                'value' => '25 Tạ Quang Bửu, Bách Khoa, Hai Bà Trưng, Hà Nội',
                'description' => 'Địa chỉ văn phòng công ty'
            ],
            [
                'key' => '3',
                'value' => '0985123456',
                'description' => 'Số điện thoại liên hệ'
            ],
            [
                'key' => '4',
                'value' => 'hhostel@example.com',
                'description' => 'Email liên hệ'
            ],
            [
                'key' => '5',
                'value' => 'Thứ 2 - Thứ 6: 8:00 - 17:30, Thứ 7: 8:00 - 12:00',
                'description' => 'Giờ làm việc'
            ],
            [
                'key' => '6',
                'value' => 'Thanh toán tiền thuê nhà đúng hạn, giữ vệ sinh chung, không gây ồn sau 22:00',
                'description' => 'Nội quy chung của nhà trọ'
            ],
            [
                'key' => '7',
                'value' => 'Tiền thuê thanh toán từ ngày 1-10 hàng tháng, phí trễ hạn 50k/ngày',
                'description' => 'Quy định thanh toán'
            ],
            [
                'key' => '8',
                'value' => 'Quản lý: 0985123456, Bảo vệ: 0978123456, Cứu hỏa: 114, Cấp cứu: 115',
                'description' => 'Số điện thoại khẩn cấp'
            ],
            [
                'key' => '9',
                'value' => 'Tạo yêu cầu trên hệ thống, đội bảo trì sẽ liên hệ trong vòng 24 giờ',
                'description' => 'Quy trình bảo trì sửa chữa'
            ],
            [
                'key' => '10',
                'value' => 'Chào mừng bạn đến với H-Hostel! Chúng tôi rất vui khi được đồng hành cùng bạn.',
                'description' => 'Thông điệp chào mừng khách thuê mới'
            ],
            [
                'key' => '11',
                'value' => 'Thông báo hạn thanh toán, cập nhật bảo trì, thông báo chung',
                'description' => 'Cài đặt thông báo hệ thống'
            ],
            [
                'key' => '12',
                'value' => 'Mã số thuế: 0123456789, VAT: 10%, Đã bao gồm thuế VAT',
                'description' => 'Thông tin thuế'
            ]
        ];

        foreach ($settings as $setting) {
            SystemSetting::firstOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'description' => $setting['description'],
                    'created_by' => $admin->id,
                ]
            );
        }
    }
}