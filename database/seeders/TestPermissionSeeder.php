<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TestPermissionSeeder extends Seeder
{
    /**
     * Legacy and test-specific permissions that don't map to the
     * application's resource-based permission naming but are referenced
     * throughout the test suite.
     */
    protected array $testPermissions = [
        // Commonly referenced legacy .manage permissions
        'users.manage',
        'members.manage',
        'groups.manage',
        'contributions.manage',
        'donations.manage',
        'education.manage',
        'tours.manage',
        'charity.manage',
        'inventory.manage',
        'media.manage',
        'blog.manage',
        'announcements.manage',
        'faq.manage',
        'worship.manage',
        'worship.songs.manage',
        'worship.rehearsals.manage',
        'documents.manage',
        'settings.manage',
        'teachers.manage',
        'backup.manage',

        // Legacy singular backup alias
        'backup.restore',

        // Legacy group aliases
        'groups.view',
        'groups.create',
        'groups.update',
        'groups.delete',
        'groups.assign',
        'groups.bulk_assign',

        // Legacy audit aliases
        'audit.export',
        'audit.view',

        // Legacy finance aliases
        'finance.reports',
        'financial.reports.view',
        'financial.statements.generate',
        'financial.reports.export',
        'contributions.record',
        'contributions.view_reports',
        'contributions.outstanding.view',
        'donations.view_reports',

        // Legacy education aliases
        'education.academic_year.create',
        'education.academic_year.activate',
        'education.academic_year.deactivate',
        'education.class.manage',
        'education.subject.manage',
        'education.enrollment.manage',
        'education.promote',
        'education.bulk_promote',
        'education.attendance.unlock',
        'education.attendance.create',
        'education.attendance.record',
        'education.attendance.lock',
        'academic_year.create',
        'academic_year.activate',
        'academic_year.deactivate',
        'class.create',
        'class.update',
        'class.delete',
        'subject.create',
        'subject.update',
        'subject.delete',
        'enrollment.create',
        'enrollment.remove',
        'enrollment.promote',
        'enrollment.bulk_promote',

        // Legacy tour aliases
        'tours.attendance.manage',
        'tours.reports.view',
        'tours.registrations.view',
        'tours.passengers.register_internal',
        'tours.registrations.confirm',
        'tours.attendance.session.create',
        'tours.attendance.record',
        'tours.call_button.use',

        // Legacy charity aliases
        'charity.beneficiaries.manage',
        'charity.aid.distribute',
        'charity.reports.view',
        'aid.distribute',
        'aid.reports.view',

        // Legacy inventory aliases
        'inventory.items.manage',
        'inventory.movements.manage',
        'inventory.reports.view',
        'inventory.delete',
        'inventory.create',
        'inventory.update',
        'inventory.dispose',
        'inventory.movements.record',
        'inventory.analytics.view',

        // Legacy media aliases
        'media.upload',
        'media.delete',
        'media.update',
        'media.categories.manage',
        'media.visibility.change',

        // Legacy blog aliases
        'blog.create',
        'blog.update',
        'blog.delete',
        'blog.publish',

        // Legacy announcement aliases
        'announcements.schedule',
        'announcements.urgent.mark',

        // Legacy song aliases
        'songs.upload',
        'songs.categories.manage',
        'rehearsals.attendance.record',

        // Legacy teacher aliases
        'teachers.assign',
        'teachers.attendance.view',
        'teachers.substitute.assign',

        // Legacy attendance aliases
        'attendance.view',
        'attendance.mark',
        'attendance.session.create',
        'attendance.session.lock',
        'attendance.session.unlock',
        'attendance.student.record',
        'attendance.teacher.record',
        'attendance.sync_conflicts.view',

        // Legacy member aliases
        'members.timeline.view',

        // Legacy library aliases
        'library.upload',

        // Legacy system aliases
        'system.oversight',

        // Test-specific arbitrary permissions
        'custom.permission',
        'test.permission',
        'test.permission1',
        'test.permission2',
        'direct.permission',
        'role.permission',
        'permission1',
        'permission2',
    ];

    /**
     * Map of legacy permissions to the roles that should receive them.
     */
    protected array $rolePermissionMap = [
        'superadmin' => ['*'], // Will get all permissions
        'admin' => [
            'users.manage', 'groups.manage', 'contributions.manage', 'donations.manage',
            'education.manage', 'tours.manage', 'charity.manage', 'inventory.manage',
            'media.manage', 'blog.manage', 'announcements.manage', 'faq.manage',
            'worship.manage', 'documents.manage', 'settings.manage', 'teachers.manage',
            'backup.manage', 'audit.export', 'audit.view',
            'finance.reports', 'system.oversight',
        ],
        'hr_head' => [
            'groups.manage', 'groups.assign', 'groups.bulk_assign',
            'members.timeline.view', 'members.manage',
        ],
        'finance_head' => [
            'contributions.manage', 'donations.manage', 'finance.reports',
            'contributions.record', 'contributions.view_reports',
            'donations.view_reports', 'financial.reports.view',
            'financial.statements.generate', 'financial.reports.export',
            'contributions.outstanding.view',
        ],
        'nibret_hisab_head' => [
            'contributions.manage', 'donations.manage', 'finance.reports',
            'inventory.manage', 'inventory.items.manage', 'inventory.movements.manage',
            'inventory.reports.view', 'inventory.delete', 'inventory.create',
            'inventory.update', 'inventory.dispose', 'inventory.movements.record',
            'inventory.analytics.view',
        ],
        'inventory_staff' => [
            'inventory.manage', 'inventory.items.manage', 'inventory.movements.manage',
            'inventory.reports.view', 'inventory.delete', 'inventory.create',
            'inventory.update', 'inventory.dispose', 'inventory.movements.record',
            'inventory.analytics.view',
        ],
        'education_head' => [
            'education.manage', 'teachers.manage', 'teachers.assign',
            'education.academic_year.create', 'education.academic_year.activate',
            'education.academic_year.deactivate', 'education.class.manage',
            'education.subject.manage', 'education.enrollment.manage',
            'education.promote', 'education.bulk_promote',
            'education.attendance.unlock', 'education.attendance.create',
            'education.attendance.record', 'education.attendance.lock',
            'academic_year.create', 'academic_year.activate', 'academic_year.deactivate',
            'class.create', 'class.update', 'class.delete',
            'subject.create', 'subject.update', 'subject.delete',
            'enrollment.create', 'enrollment.remove', 'enrollment.promote', 'enrollment.bulk_promote',
        ],
        'education_monitor' => [
            'attendance.view', 'attendance.mark',
            'attendance.session.create', 'attendance.session.lock', 'attendance.session.unlock',
            'attendance.student.record', 'attendance.teacher.record',
            'attendance.sync_conflicts.view',
        ],
        'worship_monitor' => [
            'worship.manage', 'worship.songs.manage', 'worship.rehearsals.manage',
            'songs.upload', 'songs.categories.manage', 'rehearsals.attendance.record',
        ],
        'mezmur_head' => [
            'worship.manage', 'worship.songs.manage', 'worship.rehearsals.manage',
            'songs.upload', 'songs.categories.manage', 'rehearsals.attendance.record',
            'media.manage', 'media.upload', 'media.delete',
        ],
        'av_head' => [
            'media.manage', 'media.upload', 'media.delete', 'media.update',
            'media.categories.manage', 'media.visibility.change',
            'blog.manage', 'announcements.manage', 'faq.manage',
            'blog.create', 'blog.update', 'blog.delete', 'blog.publish',
            'announcements.schedule', 'announcements.urgent.mark',
        ],
        'charity_head' => [
            'charity.manage', 'charity.beneficiaries.manage', 'charity.aid.distribute',
            'charity.reports.view', 'aid.distribute', 'aid.reports.view',
        ],
        'tour_head' => [
            'tours.manage', 'tours.attendance.manage', 'tours.reports.view',
            'tours.registrations.view', 'tours.passengers.register_internal',
            'tours.registrations.confirm', 'tours.attendance.session.create',
            'tours.attendance.record', 'tours.call_button.use',
        ],
        'internal_relations_head' => [
            'groups.manage', 'groups.assign', 'groups.bulk_assign',
            'documents.manage', 'members.manage',
        ],
        'department_secretary' => [
            'groups.assign', 'members.timeline.view',
        ],
        'staff' => [
            'attendance.view',
        ],
    ];

    public function run(): void
    {
        foreach ($this->testPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Re-sync superadmin with all permissions (including new test ones)
        $superadmin = Role::where('name', 'superadmin')->first();
        if ($superadmin) {
            $superadmin->syncPermissions(Permission::all());
        }

        // Assign legacy permissions to other roles
        foreach ($this->rolePermissionMap as $roleName => $permissions) {
            if ($roleName === 'superadmin') {
                continue;
            }

            $role = Role::where('name', $roleName)->first();
            if (! $role) {
                continue;
            }

            $permissionModels = collect();
            foreach ($permissions as $permName) {
                $perm = Permission::where('name', $permName)->first();
                if ($perm) {
                    $permissionModels->push($perm);
                }
            }

            // Sync without removing existing permissions
            $role->permissions()->syncWithoutDetaching($permissionModels->pluck('id')->toArray());
        }
    }
}
