<?php

namespace Database\Seeders;

use App\Models\House;
use App\Models\User;
use Illuminate\Database\Seeder;

class HouseSeeder extends Seeder
{
    public function run(): void
    {
        // Lấy admin và tất cả manager
        $admin = User::where('username', 'admin')->first();
        
        $managers = User::whereHas('role', function ($query) {
            $query->where('code', 'manager');
        })->get();
        
        // Danh sách các căn nhà ở Hà Nội với địa chỉ thực tế
        $houses = [
            [
                'name' => 'Nhà trọ 25 Tạ Quang Bửu',
                'address' => '25 Tạ Quang Bửu, Bách Khoa, Hai Bà Trưng, Hà Nội',
                'description' => 'Nhà trọ gần Đại học Bách Khoa, đầy đủ tiện nghi, phù hợp cho sinh viên và người đi làm.',
            ],
            [
                'name' => 'Căn hộ 48A Thái Hà',
                'address' => '48A Thái Hà, Trung Liệt, Đống Đa, Hà Nội',
                'description' => 'Căn hộ cao cấp khu vực trung tâm, gần nhiều trung tâm thương mại và tiện ích.',
            ],
            [
                'name' => 'Căn hộ Seasons Avenue',
                'address' => 'KĐT Mỗ Lao, Mỗ Lao, Hà Đông, Hà Nội',
                'description' => 'Căn hộ hiện đại tại khu đô thị mới, đầy đủ tiện ích nội khu.',
            ],
            [
                'name' => 'Nhà trọ 105 Doãn Kế Thiện',
                'address' => '105 Doãn Kế Thiện, Mai Dịch, Cầu Giấy, Hà Nội',
                'description' => 'Khu trọ yên tĩnh, gần ĐHQG Hà Nội, phù hợp cho sinh viên và người đi làm.',
            ],
            [
                'name' => 'Chung cư mini Xuân Thủy',
                'address' => '128 Xuân Thủy, Dịch Vọng Hậu, Cầu Giấy, Hà Nội',
                'description' => 'Chung cư mini mới xây, đầy đủ nội thất cơ bản, gần khu vực đại học.',
            ],
            [
                'name' => 'Khu trọ Dương Quảng Hàm',
                'address' => '68 Dương Quảng Hàm, Quan Hoa, Cầu Giấy, Hà Nội',
                'description' => 'Khu trọ rộng rãi, có chỗ để xe, wifi miễn phí, camera an ninh 24/7.',
            ],
            [
                'name' => 'Căn hộ The Garden',
                'address' => 'KĐT The Garden, Từ Liêm, Hà Nội',
                'description' => 'Căn hộ cao cấp trong khu đô thị khép kín, đầy đủ tiện ích.',
            ],
            [
                'name' => 'Chung cư mini Vinhomes',
                'address' => 'KĐT Vinhomes Smart City, Nam Từ Liêm, Hà Nội',
                'description' => 'Chung cư mini trong khu đô thị hiện đại, tiện nghi cao cấp.',
            ],
            [
                'name' => 'Khu trọ Đê La Thành',
                'address' => '75 Đê La Thành, Ô Chợ Dừa, Đống Đa, Hà Nội',
                'description' => 'Khu trọ trung tâm, gần nhiều cơ quan, trường học, giá cả hợp lý.',
            ],
            [
                'name' => 'Căn hộ mini Láng Hạ',
                'address' => '22 Láng Hạ, Ba Đình, Hà Nội',
                'description' => 'Căn hộ mini khu trung tâm, gần nhiều văn phòng, đầy đủ tiện nghi.',
            ],
            [
                'name' => 'Nhà trọ 235 Hoàng Quốc Việt',
                'address' => '235 Hoàng Quốc Việt, Cổ Nhuế, Bắc Từ Liêm, Hà Nội',
                'description' => 'Khu nhà trọ an ninh, yên tĩnh, gần nhiều trường đại học lớn.',
            ],
            [
                'name' => 'Căn hộ chung cư Times City',
                'address' => 'KĐT Times City, Hai Bà Trưng, Hà Nội',
                'description' => 'Căn hộ cao cấp trong khu đô thị hiện đại, đầy đủ tiện ích nội khu.',
            ],
            [
                'name' => 'Khu trọ Ngọc Hồi',
                'address' => '156 Ngọc Hồi, Thanh Trì, Hà Nội',
                'description' => 'Khu trọ mới xây, giá rẻ, phù hợp công nhân và người thu nhập thấp.',
            ],
            [
                'name' => 'Chung cư mini Tam Trinh',
                'address' => '86 Tam Trinh, Hoàng Mai, Hà Nội',
                'description' => 'Chung cư mini giá rẻ, diện tích đa dạng, phù hợp nhiều đối tượng.',
            ],
            [
                'name' => 'Nhà trọ 45 Trần Thái Tông',
                'address' => '45 Trần Thái Tông, Dịch Vọng, Cầu Giấy, Hà Nội',
                'description' => 'Nhà trọ sạch sẽ, khép kín, gần khu vực công nghệ Cầu Giấy.',
            ],
        ];

        // Phân bổ nhà cho các manager ngẫu nhiên
        foreach ($houses as $index => $house) {
            // Chọn manager theo công thức luân phiên để đảm bảo mỗi manager quản lý 1-2 nhà
            $manager = $managers[($index % count($managers))];
            
            House::firstOrCreate(
                ['name' => $house['name'], 'address' => $house['address']],
                [
                    'description' => $house['description'],
                    'manager_id' => $manager->id,
                    'status' => 'active',
                    'updated_by' => $admin->id
                ]
            );
        }
    }
}
