<?php

namespace Database\Seeders;

use App\Models\House;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lấy danh sách tenant
        $tenants = User::whereHas('role', function ($query) {
            $query->where('code', 'tenant');
        })->get();

        // Lấy danh sách manager
        $managers = User::whereHas('role', function ($query) {
            $query->where('code', 'manager');
        })->get();

        // Danh sách các loại thông báo và nội dung cho tenant
        $tenantNotificationTypes = [
            'reminder' => [
                'Nhắc nhở: Hạn đóng tiền phòng là ngày 5 hàng tháng',
                'Nhắc nhở: Thời hạn hợp đồng sẽ hết trong 30 ngày',
                'Nhắc nhở: Hạn đóng tiền dịch vụ là cuối tháng',
                'Nhắc nhở: Đã đến hạn thanh toán hóa đơn tháng này',
                'Nhắc nhở: Kiểm tra đồng hồ điện nước vào ngày mai'
            ],
            'announcement' => [
                'Thông báo: Sẽ có bảo trì hệ thống điện vào Chủ Nhật tới',
                'Thông báo: Nhà trọ sẽ tổ chức vệ sinh chung vào tuần sau',
                'Thông báo: Cập nhật nội quy mới về việc đỗ xe',
                'Thông báo: Sẽ tiến hành kiểm tra PCCC vào tuần sau',
                'Thông báo: Sẽ nâng cấp hệ thống internet vào tháng sau'
            ],
            'system' => [
                'Thông báo cập nhật: Thêm tính năng mới trên ứng dụng',
                'Thông báo hệ thống: Bảo trì hệ thống từ 23h-24h hôm nay',
                'Thông báo an ninh: Vui lòng cập nhật mật khẩu của bạn',
                'Thông báo kỹ thuật: Đã khắc phục lỗi báo cáo sự cố',
                'Thông báo: Phiên bản mới của ứng dụng đã sẵn sàng'
            ]
        ];

        // Danh sách các loại thông báo và nội dung cho manager
        $managerNotificationTypes = [
            'tenant_activity' => [
                'Có người thuê mới đăng ký xem phòng',
                'Người thuê phòng 101 đã yêu cầu gia hạn hợp đồng',
                'Có người thuê đã trễ hạn thanh toán 7 ngày',
                'Người thuê đã báo cáo vấn đề về cơ sở vật chất',
                'Có người thuê dự kiến dọn ra vào cuối tháng'
            ],
            'admin_notice' => [
                'Admin đã phê duyệt yêu cầu tăng giá thuê',
                'Admin đã gửi báo cáo doanh thu quý cho bạn',
                'Admin yêu cầu cập nhật thông tin nhà trọ của bạn',
                'Admin đã gửi kế hoạch marketing mới',
                'Admin cần bạn xác nhận thông tin danh sách phòng trống'
            ],
            'system' => [
                'Cập nhật: Thêm tính năng quản lý tiện ích mới',
                'Bảo trì: Hệ thống báo cáo sẽ ngừng hoạt động từ 22h-24h',
                'Thông báo: Cập nhật biểu mẫu hợp đồng mới',
                'Cảnh báo: Sắp hết dung lượng lưu trữ',
                'Thông báo: Đã kích hoạt tính năng theo dõi thanh toán tự động'
            ]
        ];

        // Tạo thông báo cho tenants
        foreach ($tenants as $tenant) {
            // Tìm thông tin nhà của tenant này từ contract
            $contract = $tenant->contracts()->where('status', 'active')->first();

            // Nếu tenant không có contract active thì bỏ qua
            if (!$contract || !$contract->room || !$contract->room->house) {
                continue;
            }

            $room = $contract->room;
            $house = $room->house;

            // Tạo 3-7 thông báo ngẫu nhiên
            $notificationCount = rand(3, 7);
            for ($i = 0; $i < $notificationCount; $i++) {
                // Chọn ngẫu nhiên loại thông báo
                $notificationTypeKeys = array_keys($tenantNotificationTypes);
                $notificationType = $notificationTypeKeys[array_rand($notificationTypeKeys)];

                // Chọn ngẫu nhiên nội dung thông báo
                $notifications = $tenantNotificationTypes[$notificationType];
                $content = $notifications[array_rand($notifications)];

                // Link tùy theo loại thông báo
                $link = match ($notificationType) {
                    'reminder' => '/invoices',
                    'announcement' => '/dashboard',
                    'system' => '/settings',
                    default => '/dashboard'
                };

                // Tạo thông báo
                $notification = new Notification();
                $notification->user_id = $tenant->id;
                $notification->type = $notificationType;
                $notification->content = $content;
                $notification->url = $link;
                $notification->is_read = (rand(0, 2) < 1);
                $notification->save();
            }
        }

        // Tạo thông báo cho managers
        foreach ($managers as $manager) {
            // Lấy danh sách nhà quản lý bởi manager này
            $houses = House::where('manager_id', $manager->id)->get();

            if ($houses->isEmpty()) {
                continue;
            }

            // Tạo 5-10 thông báo ngẫu nhiên
            $notificationCount = rand(5, 10);
            for ($i = 0; $i < $notificationCount; $i++) {
                // Chọn ngẫu nhiên loại thông báo
                $notificationTypeKeys = array_keys($managerNotificationTypes);
                $notificationType = $notificationTypeKeys[array_rand($notificationTypeKeys)];

                // Chọn ngẫu nhiên nội dung thông báo
                $notifications = $managerNotificationTypes[$notificationType];
                $content = $notifications[array_rand($notifications)];

                // Link tùy theo loại thông báo
                $link = match ($notificationType) {
                    'tenant_activity' => '/tenants',
                    'admin_notice' => '/dashboard',
                    'system' => '/settings',
                    default => '/dashboard'
                };

                // Tạo thông báo
                $notification = new Notification();
                $notification->user_id = $manager->id;
                $notification->type = $notificationType;
                $notification->content = $content;
                $notification->url = $link;
                $notification->is_read = (rand(0, 2) < 1);
                $notification->save();
            }
        }
    }
}
