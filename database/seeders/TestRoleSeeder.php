<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class TestRoleSeeder extends Seeder
{
    /**
     * Roles required by the test suite.
     */
    protected array $roles = [
        'superadmin',
        'admin',
        'hr_head',
        'finance_head',
        'nibret_hisab_head',
        'inventory_staff',
        'education_head',
        'education_monitor',
        'worship_monitor',
        'mezmur_head',
        'av_head',
        'charity_head',
        'tour_head',
        'internal_relations_head',
        'department_secretary',
        'staff',
    ];

    public function run(): void
    {
        foreach ($this->roles as $role) {
            Role::firstOrCreate(
                ['name' => $role, 'guard_name' => 'web'],
                ['label' => ucwords(str_replace('_', ' ', $role))]
            );
        }
    }
}
