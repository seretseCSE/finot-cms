<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing roles and permissions
        DB::table('role_has_permissions')->delete();
        DB::table('model_has_permissions')->delete();
        DB::table('model_has_roles')->delete();
        DB::table('roles')->delete();
        DB::table('permissions')->delete();

        // Create permissions
        $permissions = [
            // System permissions
            'system.settings',
            'system.backups',
            'system.error_logs',
            'system.maintenance',

            // User management
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.export',
            'users.assign_roles',
            'users.manage_departments',

            // Member management
            'members.view',
            'members.create',
            'members.edit',
            'members.delete',
            'members.export',
            'members.assign_groups',
            'members.view_attendance',
            'members.manage_groups',

            // Group management
            'groups.view',
            'groups.create',
            'groups.edit',
            'groups.delete',
            'groups.assign_members',

            // Finance permissions
            'contributions.view',
            'contributions.create',
            'contributions.edit',
            'contributions.delete',
            'contributions.export',
            'contributions.reports',

            // Inventory permissions
            'inventory.view',
            'inventory.create',
            'inventory.edit',
            'inventory.delete',
            'inventory.movements',
            'inventory.analytics',
            'inventory.reports',

            // Education permissions
            'academic_years.view',
            'academic_years.create',
            'academic_years.edit',
            'academic_years.delete',
            'classes.view',
            'classes.create',
            'classes.edit',
            'classes.delete',
            'subjects.view',
            'subjects.create',
            'subjects.edit',
            'subjects.delete',
            'enrollments.view',
            'enrollments.create',
            'enrollments.edit',
            'enrollments.delete',
            'promotions.view',
            'promotions.create',
            'promotions.edit',
            'promotions.delete',
            'teachers.view',
            'teachers.create',
            'teachers.edit',
            'teachers.delete',
            'teachers.manage',
            'attendance.view',
            'attendance.create',
            'attendance.mark',
            'attendance.lock',
            'attendance.unlock',
            'attendance.sync_conflicts',
            'teacher_reports.view',

            // Worship permissions
            'songs.view',
            'songs.create',
            'songs.edit',
            'songs.delete',
            'rehearsals.view',
            'rehearsals.create',
            'rehearsals.edit',
            'rehearsals.delete',
            'rehearsals.attendance',
            'media.visibility',

            // Media permissions
            'media.view',
            'media.create',
            'media.edit',
            'media.delete',
            'media.categories',

            // Blog permissions
            'blog.view',
            'blog.create',
            'blog.edit',
            'blog.delete',
            'blog.publish',

            // Announcements permissions
            'announcements.view',
            'announcements.create',
            'announcements.edit',
            'announcements.delete',
            'announcements.publish',

            // FAQ permissions
            'faq.view',
            'faq.create',
            'faq.edit',
            'faq.delete',

            // Charity permissions
            'beneficiaries.view',
            'beneficiaries.create',
            'beneficiaries.edit',
            'beneficiaries.delete',
            'aid.distribution',
            'charity.reports',

            // Tour permissions
            'tours.view',
            'tours.create',
            'tours.edit',
            'tours.delete',
            'tours.registration',
            'tours.attendance',
            'tours.reports',

            // Document management
            'documents.view',
            'documents.create',
            'documents.edit',
            'documents.delete',
            'documents.upload',
            'documents.download',

            // Department resources
            'department_resources.view',
            'department_resources.create',
            'department_resources.edit',
            'department_resources.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles
        $roles = [
            [
                'name' => 'superadmin',
                'label' => 'Super Admin',
                'description' => 'Full system access including system settings, backups',
                'permissions' => ['*'], // Wildcard permission
            ],
            [
                'name' => 'admin',
                'label' => 'Admin',
                'description' => 'All operational permissions across all departments EXCEPT system settings, backups, error logs',
                'permissions' => [
                    // User management
                    'users.view',
                    'users.create',
                    'users.edit',
                    'users.delete',
                    'users.export',
                    'users.assign_roles',
                    'users.manage_departments',

                    // Member management (all departments)
                    'members.view',
                    'members.create',
                    'members.edit',
                    'members.delete',
                    'members.export',
                    'members.assign_groups',
                    'members.view_attendance',
                    'members.manage_groups',

                    // Group management (all departments)
                    'groups.view',
                    'groups.create',
                    'groups.edit',
                    'groups.delete',
                    'groups.assign_members',

                    // Finance (except advanced inventory)
                    'contributions.view',
                    'contributions.create',
                    'contributions.edit',
                    'contributions.delete',
                    'contributions.export',
                    'contributions.reports',

                    // Education (except teacher management)
                    'academic_years.view',
                    'academic_years.create',
                    'academic_years.edit',
                    'academic_years.delete',
                    'classes.view',
                    'classes.create',
                    'classes.edit',
                    'classes.delete',
                    'subjects.view',
                    'subjects.create',
                    'subjects.edit',
                    'subjects.delete',
                    'enrollments.view',
                    'enrollments.create',
                    'enrollments.edit',
                    'enrollments.delete',
                    'promotions.view',
                    'promotions.create',
                    'promotions.edit',
                    'promotions.delete',
                    'attendance.view',
                    'teacher_reports.view',

                    // Worship
                    'songs.view',
                    'rehearsals.view',
                    'rehearsals.attendance',

                    // Media
                    'media.view',
                    'blog.view',
                    'announcements.view',
                    'faq.view',

                    // Charity
                    'beneficiaries.view',
                    'charity.reports',

                    // Tours
                    'tours.view',
                    'tours.reports',

                    // Documents
                    'documents.view',
                    'documents.upload',
                    'documents.download',

                    // Department resources
                    'department_resources.view',
                ],
            ],
            [
                'name' => 'hr_head',
                'label' => 'HR Head',
                'description' => 'Member CRUD, group CRUD, group assignment, member export – scoped to all members',
                'permissions' => [
                    'members.view',
                    'members.create',
                    'members.edit',
                    'members.delete',
                    'members.export',
                    'members.assign_groups',
                    'members.view_attendance',
                    'members.manage_groups',

                    'groups.view',
                    'groups.create',
                    'groups.edit',
                    'groups.delete',
                    'groups.assign_members',

                    'users.view',
                    'users.edit',
                    'users.assign_roles',
                ],
            ],
            [
                'name' => 'finance_head',
                'label' => 'Finance Head',
                'description' => 'Contribution amounts, donations CRUD, financial reports, export – dept scoped',
                'permissions' => [
                    'contributions.view',
                    'contributions.create',
                    'contributions.edit',
                    'contributions.delete',
                    'contributions.export',
                    'contributions.reports',

                    'beneficiaries.view',
                    'charity.reports',

                    'tours.reports',

                    'members.view',
                    'members.export',
                ],
            ],
            [
                'name' => 'nibret_hisab_head',
                'label' => 'Nibret Hisab Head',
                'description' => 'All Finance Head permissions + inventory CRUD + inventory reports',
                'permissions' => [
                    // Finance Head permissions
                    'contributions.view',
                    'contributions.create',
                    'contributions.edit',
                    'contributions.delete',
                    'contributions.export',
                    'contributions.reports',
                    'beneficiaries.view',
                    'charity.reports',

                    // Inventory permissions
                    'inventory.view',
                    'inventory.create',
                    'inventory.edit',
                    'inventory.delete',
                    'inventory.movements',
                    'inventory.analytics',
                    'inventory.reports',

                    'tours.reports',

                    'members.view',
                    'members.export',
                ],
            ],
            [
                'name' => 'inventory_staff',
                'label' => 'Inventory Staff',
                'description' => 'Inventory CRUD, movements, analytics – dept scoped',
                'permissions' => [
                    'inventory.view',
                    'inventory.create',
                    'inventory.edit',
                    'inventory.delete',
                    'inventory.movements',
                    'inventory.analytics',
                    'inventory.reports',
                ],
            ],
            [
                'name' => 'education_head',
                'label' => 'Education Head',
                'description' => 'Academic year CRUD, class/subject CRUD, enrollment, promotion, teacher management, unlock attendance, view teacher reports',
                'permissions' => [
                    'academic_years.view',
                    'academic_years.create',
                    'academic_years.edit',
                    'academic_years.delete',
                    'classes.view',
                    'classes.create',
                    'classes.edit',
                    'classes.delete',
                    'subjects.view',
                    'subjects.create',
                    'subjects.edit',
                    'subjects.delete',
                    'enrollments.view',
                    'enrollments.create',
                    'enrollments.edit',
                    'enrollments.delete',
                    'promotions.view',
                    'promotions.create',
                    'promotions.edit',
                    'promotions.delete',
                    'teachers.view',
                    'teachers.create',
                    'teachers.edit',
                    'teachers.delete',
                    'teachers.manage',
                    'attendance.view',
                    'attendance.create',
                    'attendance.mark',
                    'attendance.lock',
                    'attendance.unlock',
                    'attendance.sync_conflicts',
                    'teacher_reports.view',

                    'members.view',
                    'members.export',
                ],
            ],
            [
                'name' => 'education_monitor',
                'label' => 'Education Monitor',
                'description' => 'Attendance session create/mark, lock sessions, view sync conflicts',
                'permissions' => [
                    'attendance.view',
                    'attendance.create',
                    'attendance.mark',
                    'attendance.lock',
                    'attendance.unlock',
                    'attendance.sync_conflicts',

                    'members.view',
                ],
            ],
            [
                'name' => 'worship_monitor',
                'label' => 'Worship Monitor',
                'description' => 'Song CRUD, rehearsal schedule, rehearsal attendance, media visibility (own)',
                'permissions' => [
                    'songs.view',
                    'songs.create',
                    'songs.edit',
                    'songs.delete',
                    'rehearsals.view',
                    'rehearsals.create',
                    'rehearsals.edit',
                    'rehearsals.delete',
                    'rehearsals.attendance',
                    'media.visibility',
                ],
            ],
            [
                'name' => 'mezmur_head',
                'label' => 'Mezmur Head',
                'description' => 'All Worship Monitor permissions + manage song/rehearsal',
                'permissions' => [
                    // Worship Monitor permissions
                    'songs.view',
                    'songs.create',
                    'songs.edit',
                    'songs.delete',
                    'rehearsals.view',
                    'rehearsals.create',
                    'rehearsals.edit',
                    'rehearsals.delete',
                    'rehearsals.attendance',
                    'media.visibility',

                    'teacher_reports.view',

                    'members.view',
                    'members.export',
                ],
            ],
            [
                'name' => 'av_head',
                'label' => 'AV Head',
                'description' => 'Media CRUD, blog posts, announcements, FAQ, media categories',
                'permissions' => [
                    'media.view',
                    'media.create',
                    'media.edit',
                    'media.delete',
                    'media.categories',

                    'blog.view',
                    'blog.create',
                    'blog.edit',
                    'blog.delete',
                    'blog.publish',

                    'announcements.view',
                    'announcements.create',
                    'announcements.edit',
                    'announcements.delete',
                    'announcements.publish',

                    'faq.view',
                    'faq.create',
                    'faq.edit',
                    'faq.delete',

                    'documents.view',
                    'documents.upload',
                    'documents.download',

                    'members.view',
                    'members.export',
                ],
            ],
            [
                'name' => 'charity_head',
                'label' => 'Charity Head',
                'description' => 'Beneficiary CRUD, aid distribution, contribution recording (members only), view reports',
                'permissions' => [
                    'beneficiaries.view',
                    'beneficiaries.create',
                    'beneficiaries.edit',
                    'beneficiaries.delete',
                    'aid.distribution',
                    'charity.reports',

                    'contributions.view',
                    'contributions.create',
                    'contributions.edit',
                    'contributions.delete',

                    'tours.reports',

                    'members.view',
                    'members.export',
                ],
            ],
            [
                'name' => 'tour_head',
                'label' => 'Tour Head',
                'description' => 'Tour CRUD, registration management, attendance, tour reports',
                'permissions' => [
                    'tours.view',
                    'tours.create',
                    'tours.edit',
                    'tours.delete',
                    'tours.registration',
                    'tours.attendance',
                    'tours.reports',

                    'members.view',
                    'members.export',
                ],
            ],
            [
                'name' => 'internal_relations_head',
                'label' => 'Internal Relations Head',
                'description' => 'Member group CRUD (all departments), media delete, document management',
                'permissions' => [
                    'members.view',
                    'members.create',
                    'members.edit',
                    'members.delete',
                    'members.export',
                    'members.assign_groups',
                    'members.manage_groups',

                    'media.delete',
                    'documents.view',
                    'documents.create',
                    'documents.edit',
                    'documents.delete',
                    'documents.upload',
                    'documents.download',

                    'department_resources.view',
                    'department_resources.create',
                    'department_resources.edit',
                    'department_resources.delete',
                ],
            ],
            [
                'name' => 'department_secretary',
                'label' => 'Department Secretary',
                'description' => 'Create/update only (NO delete) for their department resources',
                'permissions' => [
                    'department_resources.view',
                    'department_resources.create',
                    'department_resources.edit',
                ],
            ],
            [
                'name' => 'staff',
                'label' => 'Staff',
                'description' => 'Read-only access to own department resources',
                'permissions' => [
                    'department_resources.view',
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            $role = Role::create([
                'name' => $roleData['name'],
                'label' => $roleData['label'],
                'description' => $roleData['description'],
                'guard_name' => 'web',
            ]);

            // Assign permissions to role
            if (isset($roleData['permissions'][0]) && $roleData['permissions'][0] === '*') {
                // Superadmin: give ALL permissions
                $role->givePermissionTo(Permission::all());
            } else {
                foreach ($roleData['permissions'] as $permissionName) {
                    $permission = Permission::where('name', $permissionName)->first();
                    if ($permission) {
                        $role->givePermissionTo($permission);
                    }
                }
            }
        }

        $this->command->info('Created 16 roles with permissions successfully.');
        $this->command->info('Created ' . count($permissions) . ' permissions successfully.');
    }
}
