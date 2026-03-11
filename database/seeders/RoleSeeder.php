<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Seed the RBAC roles for Forever-Loved.
     */
    public function run(): void
    {
        $roles = [
            'super-admin',
            'admin',
            'user',
            'contributor',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
