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
                'name' => '25 Tạ Quang Bửu',
                'address' => '25 Tạ Quang Bửu, Bách Khoa, Hai Bà Trưng, Hà Nội',
                'description' => 'Nhà trọ gần Đại học Bách Khoa, đầy đủ tiện nghi, phù hợp cho sinh viên và người đi làm.',
            ],
            [
                'name' => '48A Thái Hà',
                'address' => '48A Thái Hà, Trung Liệt, Đống Đa, Hà Nội',
                'description' => 'Căn hộ cao cấp khu vực trung tâm, gần nhiều trung tâm thương mại và tiện ích.',
            ],
            [
                'name' => 'Seasons Avenue',
                'address' => 'KĐT Mỗ Lao, Mỗ Lao, Hà Đông, Hà Nội',
                'description' => 'Căn hộ hiện đại tại khu đô thị mới, đầy đủ tiện ích nội khu.',
            ],
            [
                'name' => '105 Doãn Kế Thiện',
                'address' => '105 Doãn Kế Thiện, Mai Dịch, Cầu Giấy, Hà Nội',
                'description' => 'Khu trọ yên tĩnh, gần ĐHQG Hà Nội, phù hợp cho sinh viên và người đi làm.',
            ],
            [
                'name' => 'CC mini Xuân Thủy',
                'address' => '128 Xuân Thủy, Dịch Vọng Hậu, Cầu Giấy, Hà Nội',
                'description' => 'Chung cư mini mới xây, đầy đủ nội thất cơ bản, gần khu vực đại học.',
            ],
            [
                'name' => 'Khu trọ NKT',
                'address' => '68 NKT, Quan Hoa, Cầu Giấy, Hà Nội',
                'description' => 'Khu trọ rộng rãi, có chỗ để xe, wifi miễn phí, camera an ninh 24/7.',
            ],
            [
                'name' => 'The Garden',
                'address' => 'KĐT The Garden, Từ Liêm, Hà Nội',
                'description' => 'Căn hộ cao cấp trong khu đô thị khép kín, đầy đủ tiện ích.',
            ],
            [
                'name' => 'CC mini Vinhomes',
                'address' => 'KĐT Vinhomes Smart City, Nam Từ Liêm, Hà Nội',
                'description' => 'Chung cư mini trong khu đô thị hiện đại, tiện nghi cao cấp.',
            ],
            [
                'name' => 'Khu trọ ĐLT',
                'address' => '75 Đê La Thành, Ô Chợ Dừa, Đống Đa, Hà Nội',
                'description' => 'Khu trọ trung tâm, gần nhiều cơ quan, trường học, giá cả hợp lý.',
            ],
            [
                'name' => '11 Láng Hạ',
                'address' => '11 Láng Hạ, Ba Đình, Hà Nội',
                'description' => 'Căn hộ mini khu trung tâm, gần nhiều văn phòng, đầy đủ tiện nghi.',
            ],
            [
                'name' => '235 Đình Thôn',
                'address' => '235 Hoàng Quốc Việt, Cổ Nhuế, Bắc Từ Liêm, Hà Nội',
                'description' => 'Khu nhà trọ an ninh, yên tĩnh, gần nhiều trường đại học lớn.',
            ],
            [
                'name' => 'Times City',
                'address' => 'KĐT Times City, Hai Bà Trưng, Hà Nội',
                'description' => 'Căn hộ cao cấp trong khu đô thị hiện đại, đầy đủ tiện ích nội khu.',
            ],
            [
                'name' => 'Ngọc Hồi',
                'address' => '156 Ngọc Hồi, Thanh Trì, Hà Nội',
                'description' => 'Khu trọ mới xây, giá rẻ, phù hợp công nhân và người thu nhập thấp.',
            ],
            [
                'name' => 'CC mini Tam Trinh',
                'address' => '86 Tam Trinh, Hoàng Mai, Hà Nội',
                'description' => 'Chung cư mini giá rẻ, diện tích đa dạng, phù hợp nhiều đối tượng.',
            ],
            [
                'name' => '45 Mai Dịch',
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
