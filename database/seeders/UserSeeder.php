<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Tạo faker tiếng Việt
        $faker = Faker::create('vi_VN');
        
        // Lấy roles
        $adminRole = Role::where('code', 'admin')->first();
        $managerRole = Role::where('code', 'manager')->first();
        $tenantRole = Role::where('code', 'tenant')->first();

        // Tạo admin (giữ nguyên)
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'password' => Hash::make('123123'),
                'name' => 'Admin',
                'phone_number' => '0989407376',
                'email' => 'admin@example.com',
                'status' => 'active',
                'role_id' => $adminRole->id,
            ]
        );

        // Tạo 10 manager với thông tin giả
        $managerNames = [
            'Nguyễn Văn Minh', 'Trần Thị Hương', 'Lê Quang Đạt', 
            'Phạm Thanh Tùng', 'Đỗ Thị Lan', 'Vũ Quốc Anh',
            'Hoàng Văn Hiếu', 'Ngô Thị Thảo', 'Bùi Minh Tuấn', 'Đặng Thu Hà'
        ];
        
        for ($i = 0; $i < 10; $i++) {
            $name = $managerNames[$i];
            $parts = explode(' ', $name);
            $lastName = end($parts);
            
            User::firstOrCreate(
                ['username' => 'manager' . ($i + 1)],
                [
                    'password' => Hash::make('123123'),
                    'name' => $name,
                    'phone_number' => '09' . $faker->numerify('########'),
                    'email' => strtolower($lastName) . ($i + 1) . '@gmail.com',
                    'hometown' => $faker->city,
                    'identity_card' => $faker->numerify('##########'),
                    'vehicle_plate' => $faker->boolean(70) ? $faker->numerify('##') . $faker->randomLetter . $faker->randomLetter . ' - ' . $faker->numerify('######') : null,
                    'status' => 'active',
                    'role_id' => $managerRole->id,
                ]
            );
        }

        // Tạo 100 tenant với thông tin giả
        $vietnameseSurnames = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Huỳnh', 'Phan', 'Vũ', 'Võ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ', 'Ngô', 'Dương', 'Lý'];
        $vietnameseMiddleNames = ['Văn', 'Thị', 'Hữu', 'Đức', 'Quang', 'Minh', 'Hoàng', 'Công', 'Thành', 'Quốc', 'Anh', 'Thanh', 'Kim', 'Đình', 'Bảo'];
        $vietnameseNames = ['Hùng', 'Dũng', 'Hiếu', 'Thủy', 'Hoa', 'Trang', 'Minh', 'Tuấn', 'Hoàng', 'Phong', 'Lan', 'Hương', 'Mai', 'Anh', 'Dung', 'Trung', 'Phương', 'Quốc', 'Hải', 'Hà', 'Đạt', 'Linh', 'Sơn', 'Long', 'Toàn', 'Vinh', 'Bình'];
        
        // Danh sách các trường đại học và công ty ở Hà Nội để tạo thông tin thực tế cho tenant
        $universities = [
            'Đại học Quốc gia Hà Nội', 'Đại học Bách khoa Hà Nội', 
            'Học viện Ngân hàng', 'Đại học Kinh tế Quốc dân',
            'Đại học Ngoại thương', 'Đại học Sư phạm Hà Nội',
            'Đại học FPT', 'Đại học Công nghiệp Hà Nội',
            'Học viện Kỹ thuật Quân sự', 'Đại học Y Hà Nội'
        ];
        
        $companies = [
            'Công ty TNHH Viettel', 'Công ty CP FPT', 
            'Ngân hàng BIDV', 'Công ty CP Vingroup',
            'Tập đoàn Bưu chính Viễn thông Việt Nam', 'Công ty CP Thế giới Di động',
            'Công ty TNHH Samsung Electronics Vietnam', 'Ngân hàng Techcombank',
            'Công ty CP Masan Group', 'Công ty BPO GMO-Z.com RUNSYSTEM'
        ];
        
        $tenantDescriptions = [
            'Sinh viên năm %d tại ' . $faker->randomElement($universities),
            'Sinh viên ngành %s tại ' . $faker->randomElement($universities),
            'Nhân viên %s tại ' . $faker->randomElement($companies),
            'Kỹ sư %s làm việc tại ' . $faker->randomElement($companies),
            'Chuyên viên %s tại ' . $faker->randomElement($companies)
        ];
        
        $fields = ['CNTT', 'Marketing', 'Kế toán', 'Quản trị', 'Kinh doanh', 'Kỹ thuật', 'Thiết kế', 'Truyền thông', 'Nhân sự', 'Tài chính'];
        
        for ($i = 0; $i < 100; $i++) {
            $surname = $faker->randomElement($vietnameseSurnames);
            $middleName = $faker->randomElement($vietnameseMiddleNames);
            $name = $faker->randomElement($vietnameseNames);
            $fullName = $surname . ' ' . $middleName . ' ' . $name;
            
            // Tạo mô tả ngẫu nhiên
            $descriptionTemplate = $faker->randomElement($tenantDescriptions);
            $description = '';
            
            if (strpos($descriptionTemplate, 'năm %d') !== false) {
                $description = sprintf($descriptionTemplate, rand(1, 5));
            } elseif (strpos($descriptionTemplate, '%s') !== false) {
                $description = sprintf($descriptionTemplate, $faker->randomElement($fields));
            }
            
            User::firstOrCreate(
                ['username' => 'tenant' . ($i + 1)],
                [
                    'password' => Hash::make('123123'),
                    'name' => $fullName,
                    'phone_number' => '09' . $faker->numerify('########'),
                    'email' => 'tenant' . ($i + 1) . '@gmail.com',
                    'hometown' => $faker->city,
                    'identity_card' => $faker->numerify('##########'),
                    'vehicle_plate' => $faker->boolean(80) ? $faker->numerify('##') . $faker->randomLetter . $faker->randomLetter . ' - ' . $faker->numerify('######') : null,
                    'status' => 'active',
                    'role_id' => $tenantRole->id,
                    'notification_preferences' => json_encode(['email' => $faker->boolean(70), 'sms' => $faker->boolean(30)]),
                    'description' => $description
                ]
            );
        }
    }
}
