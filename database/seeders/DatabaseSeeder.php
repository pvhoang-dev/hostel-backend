<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            HouseSeeder::class,
            RoomSeeder::class,
            EquipmentSeeder::class,
            RoomEquipmentSeeder::class,
            ServiceSeeder::class,
            RoomServiceSeeder::class,
            SystemSettingSeeder::class,
            HouseSettingSeeder::class, 
            StorageSeeder::class,
            ContractSeeder::class,
            PaymentMethodSeeder::class,
            ServiceUsageSeeder::class,
            InvoiceSeeder::class,
            RequestSeeder::class,
            RequestCommentSeeder::class,
            NotificationSeeder::class
        ]);
    }
}