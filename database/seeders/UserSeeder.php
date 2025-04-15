<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Get roles
        $adminRole = Role::where('code', 'admin')->first();
        $managerRole = Role::where('code', 'manager')->first();
        $tenantRole = Role::where('code', 'tenant')->first();

        // Create admin
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'password' => Hash::make('admin123'),
                'name' => 'Admin',
                'phone_number' => '0989407376',
                'email' => 'admin@example.com',
                'status' => 'active',
                'role_id' => $adminRole->id,
            ]
        );

        // Create managers
        $managers = [
            [
                'username' => 'manager1',
                'password' => Hash::make('manager123'),
                'name' => 'House Manager 1',
                'phone_number' => '0901234567',
                'email' => 'manager1@example.com',
            ],
            [
                'username' => 'manager2',
                'password' => Hash::make('manager123'),
                'name' => 'House Manager 2',
                'phone_number' => '0901234568',
                'email' => 'manager2@example.com',
            ],
        ];

        foreach ($managers as $manager) {
            User::firstOrCreate(
                ['username' => $manager['username']],
                [
                    'password' => $manager['password'],
                    'name' => $manager['name'],
                    'phone_number' => $manager['phone_number'],
                    'email' => $manager['email'],
                    'status' => 'active',
                    'role_id' => $managerRole->id,
                ]
            );
        }

        // Create tenants
        $tenants = [
            [
                'username' => 'tenant1',
                'password' => Hash::make('tenant123'),
                'name' => 'Tenant 1',
                'phone_number' => '0912345678',
                'email' => 'tenant1@example.com',
            ],
            [
                'username' => 'tenant2',
                'password' => Hash::make('tenant123'),
                'name' => 'Tenant 2',
                'phone_number' => '0912345679',
                'email' => 'tenant2@example.com',
            ],
            [
                'username' => 'tenant3',
                'password' => Hash::make('tenant123'),
                'name' => 'Tenant 3',
                'phone_number' => '0912345680',
                'email' => 'tenant3@example.com',
            ],
        ];

        foreach ($tenants as $tenant) {
            User::firstOrCreate(
                ['username' => $tenant['username']],
                [
                    'password' => $tenant['password'],
                    'name' => $tenant['name'],
                    'phone_number' => $tenant['phone_number'],
                    'email' => $tenant['email'],
                    'status' => 'active',
                    'role_id' => $tenantRole->id,
                ]
            );
        }
    }
}
