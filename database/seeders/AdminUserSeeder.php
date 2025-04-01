<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(
            ['code' => 'admin'],
            ['name' => 'Admin']
        );

        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'password'                 => Hash::make('admin123'),
                'name'                     => 'Admin',
                'phone_number'             => '0989407376',
                'email'                    => 'admin@gmail.com',
                'status'                   => 'active',
                'role_id'                  => $adminRole->id,
                'avatar_url'               => null,
                'notification_preferences' => null,
            ]
        );
    }
}
