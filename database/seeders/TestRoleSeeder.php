<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TestRoleSeeder extends Seeder
{
    /**
     * Run the database seeds for testing.
     * Creates minimal roles needed for tests.
     */
    public function run(): void
    {
        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create basic permissions
        $permissions = [
            'system.settings',
            'users.view', 'users.create', 'users.update', 'users.delete',
            'members.view', 'members.create', 'members.update', 'members.delete', 'members.export',
            'groups.view', 'groups.create', 'groups.update', 'groups.delete',
            'contributions.view', 'contributions.record', 'contributions.reports',
            'academic_years.view', 'academic_years.create', 'academic_years.update', 'academic_years.delete',
            'classes.view', 'classes.create', 'classes.update', 'classes.delete',
            'subjects.view', 'subjects.create', 'subjects.update', 'subjects.delete',
            'enrollments.view', 'enrollments.create', 'enrollments.update', 'enrollments.delete',
            'promotions.view', 'promotions.create', 'promotions.update', 'promotions.delete',
            'teachers.view', 'teachers.create', 'teachers.update', 'teachers.delete', 'teachers.manage',
            'attendance.view', 'attendance.create', 'attendance.mark', 'attendance.lock', 'attendance.unlock', 'attendance.sync_conflicts',
            'teacher_reports.view',
            'department_resources.view', 'department_resources.create', 'department_resources.update', 'department_resources.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles with permissions
        $roles = [
            'superadmin' => ['*'],
            'admin' => $permissions,
            'education_head' => [
                'academic_years.view', 'academic_years.create', 'academic_years.update', 'academic_years.delete',
                'classes.view', 'classes.create', 'classes.update', 'classes.delete',
                'subjects.view', 'subjects.create', 'subjects.update', 'subjects.delete',
                'enrollments.view', 'enrollments.create', 'enrollments.update', 'enrollments.delete',
                'promotions.view', 'promotions.create', 'promotions.update', 'promotions.delete',
                'teachers.view', 'teachers.create', 'teachers.update', 'teachers.delete', 'teachers.manage',
                'attendance.view', 'attendance.create', 'attendance.mark', 'attendance.lock', 'attendance.unlock', 'attendance.sync_conflicts',
                'teacher_reports.view',
                'members.view', 'members.export',
            ],
            'education_monitor' => [
                'attendance.view', 'attendance.create', 'attendance.mark', 'attendance.lock', 'attendance.unlock', 'attendance.sync_conflicts',
                'members.view',
            ],
            'finance_head' => [
                'contributions.view', 'contributions.record', 'contributions.reports',
                'members.view', 'members.export',
            ],
            'hr_head' => [
                'members.view', 'members.create', 'members.update', 'members.delete', 'members.export',
                'groups.view', 'groups.create', 'groups.update', 'groups.delete',
                'users.view', 'users.update',
            ],
            'staff' => [
                'department_resources.view',
            ],
        ];

        $roleLabels = [
            'superadmin' => 'Super Admin',
            'admin' => 'Admin',
            'education_head' => 'Education Head',
            'education_monitor' => 'Education Monitor',
            'finance_head' => 'Finance Head',
            'hr_head' => 'HR Head',
            'staff' => 'Staff',
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['label' => $roleLabels[$roleName] ?? $roleName]
            );

            if (in_array('*', $rolePermissions)) {
                // Give all permissions for superadmin
                $role->syncPermissions(Permission::all());
            } else {
                $role->syncPermissions($rolePermissions);
            }
        }
    }
}
