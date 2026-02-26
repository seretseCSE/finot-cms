<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
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

        // Define permissions with naming convention: {resource}.{action}
        $permissions = [
            // System permissions
            'system.settings',
            'system.backups',
            'system.error_logs',
            'system.maintenance',
            
            // User management
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.export',
            'users.assign_roles',
            'users.manage_departments',
            
            // Member management
            'members.view',
            'members.create',
            'members.update',
            'members.delete',
            'members.export',
            'members.assign_groups',
            'members.view_attendance',
            'members.manage_groups',
            
            // Group management
            'groups.view',
            'groups.create',
            'groups.update',
            'groups.delete',
            'groups.assign_members',
            
            // Finance permissions
            'contributions.view',
            'contributions.record',
            'contributions.update',
            'contributions.delete',
            'contributions.export',
            'contributions.reports',
            
            // Inventory permissions
            'inventory.view',
            'inventory.create',
            'inventory.update',
            'inventory.delete',
            'inventory.movements',
            'inventory.analytics',
            'inventory.reports',
            
            // Education permissions
            'academic_years.view',
            'academic_years.create',
            'academic_years.update',
            'academic_years.delete',
            'classes.view',
            'classes.create',
            'classes.update',
            'classes.delete',
            'subjects.view',
            'subjects.create',
            'subjects.update',
            'subjects.delete',
            'enrollments.view',
            'enrollments.create',
            'enrollments.update',
            'enrollments.delete',
            'promotions.view',
            'promotions.create',
            'promotions.update',
            'promotions.delete',
            'teachers.view',
            'teachers.create',
            'teachers.update',
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
            'songs.update',
            'songs.delete',
            'rehearsals.view',
            'rehearsals.create',
            'rehearsals.update',
            'rehearsals.delete',
            'rehearsals.attendance',
            'media.visibility',
            
            // Media permissions
            'media.view',
            'media.create',
            'media.update',
            'media.delete',
            'media.categories',
            
            // Blog permissions
            'blog.view',
            'blog.create',
            'blog.update',
            'blog.delete',
            'blog.publish',
            
            // Announcements permissions
            'announcements.view',
            'announcements.create',
            'announcements.update',
            'announcements.delete',
            'announcements.publish',
            
            // FAQ permissions
            'faq.view',
            'faq.create',
            'faq.update',
            'faq.delete',
            
            // Charity permissions
            'beneficiaries.view',
            'beneficiaries.create',
            'beneficiaries.update',
            'beneficiaries.delete',
            'aid.distribution',
            'charity.reports',
            
            // Tour permissions
            'tours.view',
            'tours.create',
            'tours.update',
            'tours.delete',
            'tours.registration',
            'tours.attendance',
            'tours.reports',
            
            // Document management
            'documents.view',
            'documents.create',
            'documents.update',
            'documents.delete',
            'documents.upload',
            'documents.download',
            
            // Department resources (scoped by department)
            'department_resources.view',
            'department_resources.create',
            'department_resources.update',
            'department_resources.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Define permission sets for inheritance
        $permissionSets = [
            'hr_head_permissions' => [
                'members.view', 'members.create', 'members.update', 'members.delete', 'members.export',
                'members.assign_groups', 'members.view_attendance', 'members.manage_groups',
                'groups.view', 'groups.create', 'groups.update', 'groups.delete', 'groups.assign_members',
                'users.view', 'users.update', 'users.assign_roles',
            ],
            
            'finance_head_permissions' => [
                'contributions.view', 'contributions.record', 'contributions.update', 'contributions.delete', 'contributions.export', 'contributions.reports',
                'beneficiaries.view', 'charity.reports',
                'tours.reports',
                'members.view', 'members.export',
            ],
            
            'inventory_staff_permissions' => [
                'inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete',
                'inventory.movements', 'inventory.analytics', 'inventory.reports',
            ],
            
            'education_head_permissions' => [
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
            
            'education_monitor_permissions' => [
                'attendance.view', 'attendance.create', 'attendance.mark', 'attendance.lock', 'attendance.unlock', 'attendance.sync_conflicts',
                'members.view',
            ],
            
            'worship_monitor_permissions' => [
                'songs.view', 'songs.create', 'songs.update', 'songs.delete',
                'rehearsals.view', 'rehearsals.create', 'rehearsals.update', 'rehearsals.delete', 'rehearsals.attendance',
                'media.visibility',
            ],
            
            'av_head_permissions' => [
                'media.view', 'media.create', 'media.update', 'media.delete', 'media.categories',
                'blog.view', 'blog.create', 'blog.update', 'blog.delete', 'blog.publish',
                'announcements.view', 'announcements.create', 'announcements.update', 'announcements.delete', 'announcements.publish',
                'faq.view', 'faq.create', 'faq.update', 'faq.delete',
                'documents.view', 'documents.upload', 'documents.download',
                'members.view', 'members.export',
            ],
            
            'charity_head_permissions' => [
                'beneficiaries.view', 'beneficiaries.create', 'beneficiaries.update', 'beneficiaries.delete',
                'aid.distribution', 'charity.reports',
                'contributions.view', 'contributions.record', 'contributions.update', 'contributions.delete',
                'tours.reports',
                'members.view', 'members.export',
            ],
            
            'tour_head_permissions' => [
                'tours.view', 'tours.create', 'tours.update', 'tours.delete',
                'tours.registration', 'tours.attendance', 'tours.reports',
                'members.view', 'members.export',
            ],
        ];

        // Create roles with inheritance
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
                'permissions' => $this->getAdminPermissions(),
            ],
            
            [
                'name' => 'hr_head',
                'label' => 'HR Head',
                'description' => 'Member CRUD, group CRUD, group assignment, member export – scoped to all members',
                'permissions' => $permissionSets['hr_head_permissions'],
            ],
            
            [
                'name' => 'finance_head',
                'label' => 'Finance Head',
                'description' => 'Contribution amounts, donations CRUD, financial reports, export – dept scoped',
                'permissions' => $permissionSets['finance_head_permissions'],
            ],
            
            [
                'name' => 'nibret_hisab_head',
                'label' => 'Nibret Hisab Head',
                'description' => 'All Finance Head permissions + inventory CRUD + inventory reports',
                'permissions' => array_merge(
                    $permissionSets['finance_head_permissions'],
                    $permissionSets['inventory_staff_permissions']
                ),
            ],
            
            [
                'name' => 'inventory_staff',
                'label' => 'Inventory Staff',
                'description' => 'Inventory CRUD, movements, analytics – dept scoped',
                'permissions' => $permissionSets['inventory_staff_permissions'],
            ],
            
            [
                'name' => 'education_head',
                'label' => 'Education Head',
                'description' => 'Academic year CRUD, class/subject CRUD, enrollment, promotion, teacher management, unlock attendance, view teacher reports',
                'permissions' => $permissionSets['education_head_permissions'],
            ],
            
            [
                'name' => 'education_monitor',
                'label' => 'Education Monitor',
                'description' => 'Attendance session create/mark, lock sessions, view sync conflicts',
                'permissions' => $permissionSets['education_monitor_permissions'],
            ],
            
            [
                'name' => 'worship_monitor',
                'label' => 'Worship Monitor',
                'description' => 'Song CRUD, rehearsal schedule, rehearsal attendance, media visibility (own)',
                'permissions' => $permissionSets['worship_monitor_permissions'],
            ],
            
            [
                'name' => 'mezmur_head',
                'label' => 'Mezmur Head',
                'description' => 'All Worship Monitor permissions + manage song/rehearsal',
                'permissions' => array_merge(
                    $permissionSets['worship_monitor_permissions'],
                    ['teacher_reports.view']
                ),
            ],
            
            [
                'name' => 'av_head',
                'label' => 'AV Head',
                'description' => 'Media CRUD, blog posts, announcements, FAQ, media categories',
                'permissions' => $permissionSets['av_head_permissions'],
            ],
            
            [
                'name' => 'charity_head',
                'label' => 'Charity Head',
                'description' => 'Beneficiary CRUD, aid distribution, contribution recording (members only), view reports',
                'permissions' => $permissionSets['charity_head_permissions'],
            ],
            
            [
                'name' => 'tour_head',
                'label' => 'Tour Head',
                'description' => 'Tour CRUD, registration management, attendance, tour reports',
                'permissions' => $permissionSets['tour_head_permissions'],
            ],
            
            [
                'name' => 'internal_relations_head',
                'label' => 'Internal Relations Head',
                'description' => 'Member group CRUD (all departments), media delete, document management',
                'permissions' => $this->getInternalRelationsHeadPermissions(),
            ],
            
            [
                'name' => 'department_secretary',
                'label' => 'Department Secretary',
                'description' => 'Create/update only (NO delete) for their department resources',
                'permissions' => ['department_resources.view', 'department_resources.create', 'department_resources.update'],
            ],
            
            [
                'name' => 'staff',
                'label' => 'Staff',
                'description' => 'Read-only access to own department resources',
                'permissions' => ['department_resources.view'],
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
            foreach ($roleData['permissions'] as $permissionName) {
                if ($permissionName === '*') {
                    // Wildcard permission - give all permissions
                    $allPermissions = Permission::all();
                    foreach ($allPermissions as $permission) {
                        $role->givePermissionTo($permission);
                    }
                } else {
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

    /**
     * Get admin permissions (all operational permissions except system settings)
     */
    private function getAdminPermissions(): array
    {
        return [
            // User management
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.export',
            'users.assign_roles', 'users.manage_departments',
            
            // Member management (all departments)
            'members.view', 'members.create', 'members.update', 'members.delete', 'members.export',
            'members.assign_groups', 'members.view_attendance', 'members.manage_groups',
            
            // Group management (all departments)
            'groups.view', 'groups.create', 'groups.update', 'groups.delete', 'groups.assign_members',
            
            // Finance (except advanced inventory)
            'contributions.view', 'contributions.record', 'contributions.update', 'contributions.delete',
            'contributions.export', 'contributions.reports',
            
            // Education (except teacher management)
            'academic_years.view', 'academic_years.create', 'academic_years.update', 'academic_years.delete',
            'classes.view', 'classes.create', 'classes.update', 'classes.delete',
            'subjects.view', 'subjects.create', 'subjects.update', 'subjects.delete',
            'enrollments.view', 'enrollments.create', 'enrollments.update', 'enrollments.delete',
            'promotions.view', 'promotions.create', 'promotions.update', 'promotions.delete',
            'attendance.view', 'teacher_reports.view',
            
            // Worship
            'songs.view', 'rehearsals.view', 'rehearsals.attendance',
            
            // Media
            'media.view', 'blog.view', 'announcements.view', 'faq.view',
            
            // Charity
            'beneficiaries.view', 'charity.reports',
            
            // Tours
            'tours.view', 'tours.reports',
            
            // Documents
            'documents.view', 'documents.upload', 'documents.download',
            
            // Department resources
            'department_resources.view',
        ];
    }

    /**
     * Get Internal Relations Head permissions (inherits HR Head + AV Head permissions)
     */
    private function getInternalRelationsHeadPermissions(): array
    {
        return array_merge(
            [
                // HR Head permissions
                'members.view', 'members.create', 'members.update', 'members.delete', 'members.export',
                'members.assign_groups', 'members.view_attendance', 'members.manage_groups',
                'groups.view', 'groups.create', 'groups.update', 'groups.delete', 'groups.assign_members',
                'users.view', 'users.update', 'users.assign_roles',
                
                // AV Head permissions (minus some)
                'media.delete',
                'documents.view', 'documents.create', 'documents.update', 'documents.delete', 'documents.upload', 'documents.download',
                
                // Department resources
                'department_resources.view', 'department_resources.create', 'department_resources.update', 'department_resources.delete',
            ]
        );
    }
}
