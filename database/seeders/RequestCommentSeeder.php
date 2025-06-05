<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\RequestComment;
use App\Models\Request as ModelsRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RequestCommentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lấy các yêu cầu đã tạo
        $requests = ModelsRequest::all();

        // Danh sách bình luận từ tenant
        $tenantComments = [
            'Cảm ơn bạn đã xử lý nhanh chóng.',
            'Vấn đề vẫn chưa được giải quyết triệt để.',
            'Khi nào có thể hoàn thành công việc này?',
            'Tôi cần bổ sung thêm thông tin: vấn đề xảy ra vào buổi tối.',
            'Tôi sẽ có mặt tại phòng vào lúc 7h tối hôm nay.',
            'Vấn đề đã nghiêm trọng hơn, cần xử lý gấp.',
            'Đã nhận được thông báo, cảm ơn.',
            'Tôi đồng ý với phương án sửa chữa.',
            'Chi phí quá cao, có thể giảm được không?',
            'Tôi có thể thanh toán vào cuối tháng được không?'
        ];

        // Danh sách bình luận từ manager
        $managerComments = [
            'Chúng tôi sẽ cử nhân viên kỹ thuật đến kiểm tra.',
            'Vấn đề đã được ghi nhận và sẽ xử lý trong 24h tới.',
            'Đã hoàn thành sửa chữa, mời kiểm tra lại.',
            'Chi phí dự kiến sửa chữa là 500.000đ.',
            'Cần thêm thông tin về thời gian bạn có thể ở nhà.',
            'Đã chuyển thông tin đến bộ phận kỹ thuật.',
            'Sẽ có nhân viên đến vào 9h sáng mai.',
            'Xin cung cấp thêm hình ảnh nếu có thể.',
            'Đã hoàn thành xong công việc, vui lòng xác nhận.',
            'Thanh toán có thể thực hiện qua chuyển khoản hoặc tiền mặt.'
        ];

        // Thêm bình luận vào các yêu cầu đã tạo
        foreach ($requests as $request) {
            // Số lượng bình luận ngẫu nhiên (2-10 bình luận) - Tăng số lượng comment
            $numberOfComments = rand(2, 5);

            $sender = $request->sender;
            $recipient = $request->recipient;

            // Tạo cuộc hội thoại giữa sender và recipient
            $lastCommentBy = null; // Theo dõi người bình luận cuối cùng để tránh bình luận liên tiếp

            for ($i = 0; $i < $numberOfComments; $i++) {
                DB::beginTransaction();
                try {
                    // Chọn người bình luận tiếp theo, ưu tiên tạo cuộc đối thoại luân phiên
                    if ($i == 0) {
                        // Bình luận đầu tiên có thể là từ người gửi request
                        $user = $sender;
                        $lastCommentBy = $sender->id;
                    } else {
                        // Bình luận tiếp theo sẽ là từ người còn lại (để tạo đối thoại)
                        $user = $lastCommentBy == $sender->id ? $recipient : $sender;
                        $lastCommentBy = $user->id;
                    }

                    // Chọn danh sách comment phù hợp dựa trên vai trò người dùng
                    $commentTexts = ($user->role->code == 'tenant') ? $tenantComments : $managerComments;

                    // Chọn ngẫu nhiên nội dung bình luận
                    $content = $commentTexts[array_rand($commentTexts)];

                    // Tạo bình luận với thời gian tăng dần
                    $comment = new RequestComment();
                    $comment->request_id = $request->id;
                    $comment->user_id = $user->id;
                    $comment->content = $content;
                    // Tạo thời gian bình luận tăng dần từ thời điểm tạo request
                    $comment->created_at = $request->created_at->addMinutes(10 * $i + rand(1, 30));
                    $comment->updated_at = $comment->created_at;
                    $comment->save();

                    // Tạo thông báo cho người nhận không phải người bình luận
                    $notificationUser = ($user->id == $sender->id) ? $recipient : $sender;

                    $notification = new Notification();
                    $notification->user_id = $notificationUser->id;
                    $notification->type = 'request';
                    $notification->content = "{$user->name} đã bình luận vào yêu cầu #{$request->id}";
                    $notification->url = "/requests/{$request->id}";
                    $notification->is_read = (rand(0, 1) == 1); // 50% đọc, 50% chưa đọc
                    $notification->save();

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    echo "Lỗi khi tạo bình luận cho yêu cầu {$request->id}: " . $e->getMessage() . "\n";
                }
            }
        }
    }
}
