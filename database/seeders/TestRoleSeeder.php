<?php

namespace Database\Seeders;

use App\Enums\Roles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class TestRoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Roles::ALL_ROLES as $role) {
            Role::firstOrCreate(
                ['name' => $role, 'guard_name' => 'web'],
                ['label' => ucwords(str_replace('_', ' ', $role))]
            );
        }
    }
}
