<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\Request as ModelsRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RequestSeeder extends Seeder
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

        // Danh sách loại yêu cầu từ tenant đến manager
        $tenantRequestTypes = [
            'repair' => [
                'Sửa chữa vòi nước trong phòng tắm',
                'Sửa chữa bồn cầu bị rò rỉ',
                'Đèn phòng ngủ không hoạt động',
                'Máy lạnh không làm lạnh',
                'Cửa phòng bị hư khóa',
                'Tủ lạnh không hoạt động',
                'Bình nóng lạnh bị hỏng',
                'Quạt trần không quay',
                'Ổ điện bị chập',
                'Rèm cửa bị rách, cần thay thế'
            ],
            'service' => [
                'Yêu cầu thêm dịch vụ giặt ủi',
                'Đăng ký thêm chỗ đậu xe',
                'Yêu cầu vệ sinh phòng',
                'Đăng ký thêm người ở trong phòng',
                'Yêu cầu nâng cấp dịch vụ Internet'
            ],
            'complaint' => [
                'Phòng bên cạnh gây ồn ào sau 10h tối',
                'Khu vực chung quá bẩn',
                'Mùi thuốc lá từ phòng khác',
                'Nước nóng không ổn định',
                'Tiếng ồn từ máy bơm nước'
            ],
            'payment' => [
                'Xin gia hạn thời gian đóng tiền thuê phòng',
                'Yêu cầu xem lại hóa đơn tháng này',
                'Xin đóng tiền trễ hạn',
                'Đề nghị kiểm tra lại chỉ số điện',
                'Thanh toán theo đợt'
            ]
        ];

        // Danh sách loại yêu cầu từ manager đến tenant
        $managerRequestTypes = [
            'notice' => [
                'Thông báo tăng giá thuê từ tháng sau',
                'Thông báo sửa chữa khu vực chung',
                'Thông báo cắt điện bảo trì',
                'Thông báo cắt nước bảo trì',
                'Thông báo thay đổi nội quy nhà trọ'
            ],
            'payment_reminder' => [
                'Nhắc nhở thanh toán tiền thuê phòng',
                'Nhắc nhở thanh toán hóa đơn dịch vụ',
                'Nhắc nhở thanh toán tiền đặt cọc',
                'Thông báo hóa đơn quá hạn',
                'Nhắc thanh toán phí bảo trì'
            ],
            'inspection' => [
                'Thông báo kiểm tra thiết bị phòng',
                'Thông báo kiểm tra an toàn PCCC',
                'Thông báo kiểm tra sửa chữa định kỳ',
                'Đặt lịch đo chỉ số điện nước',
                'Kiểm tra tình trạng vệ sinh phòng'
            ]
        ];

        // Danh sách trạng thái yêu cầu
        $requestStatuses = ['pending', 'completed', 'pending'];

        // Tạo các yêu cầu từ tenant đến manager
        foreach ($tenants as $tenant) {
            // Tìm contract active để lấy phòng và nhà
            $contract = $tenant->contracts()->where('status', 'active')->first();

            // Nếu tenant không có contract active thì bỏ qua
            if (!$contract || !$contract->room || !$contract->room->house) {
                continue;
            }

            $room = $contract->room;
            $house = $room->house;
            $manager = User::find($house->manager_id);

            if (!$manager) {
                continue;
            }

            // Tạo 2-5 yêu cầu từ tenant đến manager
            $numberOfRequests = rand(2, 5);
            for ($i = 0; $i < $numberOfRequests; $i++) {
                DB::beginTransaction();
                try {
                    // Chọn ngẫu nhiên loại yêu cầu
                    $requestTypeKeys = array_keys($tenantRequestTypes);
                    $requestType = $requestTypeKeys[array_rand($requestTypeKeys)];

                    // Chọn ngẫu nhiên nội dung yêu cầu
                    $descriptions = $tenantRequestTypes[$requestType];
                    $description = $descriptions[array_rand($descriptions)];

                    // Chọn ngẫu nhiên trạng thái yêu cầu
                    $status = $requestStatuses[array_rand($requestStatuses)];

                    // Tạo yêu cầu
                    $request = new ModelsRequest();
                    $request->sender_id = $tenant->id;
                    $request->recipient_id = $manager->id;
                    $request->request_type = $requestType;
                    $request->description = $description;
                    $request->status = $status;
                    $request->updated_by = $tenant->id;
                    $request->save();

                    // Tạo thông báo cho manager
                    $notification = new Notification();
                    $notification->user_id = $manager->id;
                    $notification->type = 'new_request';
                    $notification->content = "{$tenant->name} đã tạo một yêu cầu mới cho bạn";
                    $notification->url = "/requests/{$request->id}";
                    $notification->is_read = (rand(0, 1) == 1); // 50% đọc, 50% chưa đọc
                    $notification->save();

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    echo "Lỗi khi tạo yêu cầu từ tenant {$tenant->name} đến manager {$manager->name}: " . $e->getMessage() . "\n";
                }
            }

            // Tạo 1-3 yêu cầu từ manager đến tenant
            $numberOfRequests = rand(1, 3);
            for ($i = 0; $i < $numberOfRequests; $i++) {
                DB::beginTransaction();
                try {
                    // Chọn ngẫu nhiên loại yêu cầu
                    $requestTypeKeys = array_keys($managerRequestTypes);
                    $requestType = $requestTypeKeys[array_rand($requestTypeKeys)];

                    // Chọn ngẫu nhiên nội dung yêu cầu
                    $descriptions = $managerRequestTypes[$requestType];
                    $description = $descriptions[array_rand($descriptions)];

                    // Chọn ngẫu nhiên trạng thái yêu cầu
                    $status = $requestStatuses[array_rand($requestStatuses)];

                    // Tạo yêu cầu
                    $request = new ModelsRequest();
                    $request->sender_id = $manager->id;
                    $request->recipient_id = $tenant->id;
                    $request->request_type = $requestType;
                    $request->description = $description;
                    $request->status = $status;
                    $request->updated_by = $manager->id;
                    $request->save();

                    // Tạo thông báo cho tenant
                    $notification = new Notification();
                    $notification->user_id = $tenant->id;
                    $notification->type = 'new_request';
                    $notification->content = "{$manager->name} đã tạo một yêu cầu mới cho bạn";
                    $notification->url = "/requests/{$request->id}";
                    $notification->is_read = (rand(0, 1) == 1); // 50% đọc, 50% chưa đọc
                    $notification->save();

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    echo "Lỗi khi tạo yêu cầu từ manager {$manager->name} đến tenant {$tenant->name}: " . $e->getMessage() . "\n";
                }
            }
        }
    }
}
