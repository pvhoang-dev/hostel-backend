<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['code' => 'admin', 'name' => 'Admin'],
            ['code' => 'manager', 'name' => 'House Manager'],
            ['code' => 'tenant', 'name' => 'Tenant'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['code' => $role['code']],
                ['name' => $role['name']]
            );
        }
    }
}
