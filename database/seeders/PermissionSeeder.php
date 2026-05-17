<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Resources grouped by module with their custom actions.
     * Format: 'resource_name' => ['extra_action_1', 'extra_action_2']
     */
    protected array $resources = [
        // Members & Groups
        'members' => [
            'export', 'assign_groups', 'view_attendance', 'manage_groups',
            'change_status', 'parent_manage', 'groups_bulk_assign', 'groups_remove',
            'timeline_view', 'search'
        ],
        'member_groups' => ['assign_members'],
        'group_assignments' => [],
        'parents' => [],

        // Users & Sessions
        'users' => [
            'export', 'assign_roles', 'manage_departments', 'lock_unlock',
            'password_reset', 'activate_deactivate', 'audit_logs_view', 'emergency_override'
        ],
        'user_sessions' => [],

        // Finance
        'contributions' => ['export', 'reports', 'define_amount'],
        'contribution_amounts' => [],
        'donations' => ['record'],
        'financial_transactions' => [],
        'bank_accounts' => [],

        // Inventory
        'inventory_items' => ['movements', 'analytics', 'reports'],
        'inventory_movements' => [],
        'stock_movements' => [],
        'loss_records' => [],

        // Education
        'academic_years' => ['activate', 'deactivate'],
        'classes' => [],
        'subjects' => [],
        'enrollments' => [],
        'promotions' => [],
        'students' => ['enroll', 'remove', 'promote', 'bulk_promote'],
        'teachers' => [
            'manage', 'create_external', 'assign_member', 'assign_class_subject',
            'update_assignments', 'remove_assignment', 'assignment_history',
            'attendance_rate', 'substitute_assign'
        ],
        'teacher_assignments' => [],
        'teacher_attendances' => [],
        'school_classes' => [],
        'student_enrollments' => [],

        // Attendance
        'attendance_sessions' => ['sync_conflicts'],
        'attendance_records' => [
            'mark', 'lock', 'unlock', 'unlock_session', 'record_offline'
        ],

        // Worship & Songs
        'songs' => [],
        'song_categories' => [],
        'song_subcategories' => [],
        'rehearsals' => ['attendance', 'schedule'],
        'rehearsal_attendances' => [],

        // Media & Content
        'media_items' => ['visibility'],
        'media_categories' => [],
        'media_subcategories' => [],
        'blog_posts' => ['publish'],
        'announcements' => ['publish'],
        'faqs' => ['manage'],
        'documents' => ['upload', 'download', 'search'],

        // Charity
        'beneficiaries' => [],
        'aid_distributions' => [],
        'charity' => ['beneficiaries_manage', 'aid_distribution', 'reports_view', 'reports'],

        // Tours
        'tours' => [
            'registrations_view', 'register_passengers', 'confirm_registration',
            'registration', 'create_attendance', 'attendance', 'call_button', 'reports'
        ],
        'tour_attendances' => [],
        'tour_passengers' => [],

        // Events & Fundraising
        'events' => [],
        'event_registrations' => [],
        'fundraising' => ['create', 'update_total', 'delete'],

        // System
        'departments' => ['manage', 'assign_roles'],
        'audit_logs' => ['view', 'export'],
        'error_logs' => [],
        'backups' => ['create', 'restore'],

        // Library
        'library_resources' => ['upload'],
        'library_categories' => [],
        'library_subcategories' => [],

        // Other
        'contact_messages' => [],
        'custom_options' => ['manage', 'merge', 'reorder'],
        'pages' => [],
        'help_docs' => ['documentation'],
        'data' => ['search_timeline', 'search_tours', 'search_inventory', 'search_archives'],

        // Mobile & PWA
        'pwa' => ['install'],
        'mobile' => ['attendance_offline', 'cached_content', 'download_content'],

        // System admin resources
        'predefined_reports' => [],
        'duplicate_records' => [],
        'temporary_filters' => [],
    ];

    /**
     * Special permissions that don't fit the resource pattern
     */
    protected array $specialPermissions = [
        'system.settings',
        'system.backups',
        'system.error_logs',
        'system.maintenance',
        'system.global_oversight',
        'system.health_monitor',
        'system.manual_export',
        'auth.login',
        'auth.logout',
        'auth.phone_only',
        'dashboard.view',
        'profile.update',
        'sessions.manage',
        'ethiopian_dates.view',
        'ethiopian_dates.picker',
        'ethiopian_dates.pagume',
        'reports.view',
        'reports.apply_filters',
        'reports.export',
        'reports.contributions_view',
        'reports.donations_view',
        'reports.outstanding_view',
        'reports.financial_generate',
        'reports.financial_export',
        'reports.teacher_reports',
        'content.schedule_publication',
        'help.documentation',

        // Page access permissions
        'page.report.tour',
        'page.custom-options',
        'page.settings.global-church',
        'page.financial.statements',
        'page.financial.statement',
        'page.financial.audit-trail',
        'page.financial.analytics',
        'page.system.audit-logs-export',
        'page.report.teacher-attendance',
        'page.report.student-progress',
        'page.report.class-performance',
        'page.report.attendance-summary',
        'page.report.donation',
        'page.report.contribution',
        'page.report.contribution-matrix',
        'page.report.charity',
        'page.report.beneficiary',
        'page.settings.auto-purge',
        'page.attendance.teacher',
        'page.attendance.student',
        'page.search.archives',
        'page.system.health',
        'page.addon-marketplace',
        'page.user-manual',

        // Timeline view permissions (category-specific)
        'members.timeline.all',
        'members.timeline.education',
        'members.timeline.finance',
    ];

    /**
     * Role definitions using permission patterns
     * Format: 'role_name' => ['label' => '', 'description' => '', 'permissions' => []]
     */
    protected array $roles = [
        'superadmin' => [
            'label' => 'Super Admin',
            'description' => 'Full system access including system settings, backups',
            'permissions' => ['*'],
        ],
        'admin' => [
            'label' => 'Admin',
            'description' => 'All operational permissions except system settings',
            'permissions' => [
                // User management
                'users.*', 'user_sessions.*',
                // Members & Groups
                'members.*', 'member_groups.*', 'group_assignments.*', 'parents.*',
                // Finance
                'contributions.*', 'donations.*', 'financial_transactions.*', 'bank_accounts.*',
                // Reports
                'reports.*',
                // Education (limited)
                'academic_years.*', 'classes.*', 'subjects.*', 'enrollments.*', 'promotions.*',
                'students.enroll', 'students.remove', 'students.promote', 'students.bulk_promote',
                'attendance_sessions.view', 'library_resources.upload', 'library_categories.*',
                'library_subcategories.*',
                // Worship
                'songs.*', 'rehearsals.*', 'song_categories.*', 'song_subcategories.*',
                // Media
                'media_items.*', 'media_categories.*', 'media_subcategories.*',
                'blog_posts.*', 'announcements.*', 'faqs.*', 'documents.*',
                'content.schedule_publication',
                // Charity
                'beneficiaries.*', 'aid_distributions.*', 'charity.*',
                // Tours
                'tours.*', 'tour_attendances.*', 'tour_passengers.*',
                // Events
                'events.*', 'event_registrations.*',
                'fundraising.create', 'fundraising.update_total', 'fundraising.delete',
                // System
                'departments.manage', 'departments.assign_roles',
                'contact_messages.*',
                'custom_options.*',
                'pages.*',
                // Page access
                'page.report.tour', 'page.custom-options',
                'page.financial.statements', 'page.financial.statement',
                'page.financial.audit-trail', 'page.financial.analytics',
                'page.report.teacher-attendance', 'page.report.student-progress',
                'page.report.class-performance', 'page.report.attendance-summary',
                'page.report.donation', 'page.report.contribution', 'page.report.contribution-matrix',
                'page.report.charity', 'page.report.beneficiary',
                'page.attendance.teacher', 'page.attendance.student',
                'page.search.archives', 'page.user-manual',
                // Core
                'dashboard.view', 'profile.update', 'sessions.manage',
                'ethiopian_dates.*', 'help.documentation',
            ],
        ],
        'hr_head' => [
            'label' => 'HR Head',
            'description' => 'Member & group management for all departments',
            'permissions' => [
                'members.*', 'group_assignments.*',
                'member_groups.view', 'member_groups.create', 'member_groups.update',
                'parents.view', 'parents.create', 'parents.update', 'parents.delete',
                'documents.*',
                'dashboard.view', 'profile.update', 'sessions.manage',
                'ethiopian_dates.*', 'reports.*',
                'help.documentation',
                'page.attendance.teacher',
            ],
        ],
        'finance_head' => [
            'label' => 'Finance Head',
            'description' => 'Financial management and reporting',
            'permissions' => [
                'contributions.*', 'contribution_amounts.*', 'donations.*',
                'financial_transactions.*', 'bank_accounts.*',
                'documents.*',
                'reports.*',
                'charity.reports',
                'tours.reports',
                'fundraising.update_total',
                'members.view', 'members.export',
                'members.timeline.finance',
                'dashboard.view', 'profile.update', 'sessions.manage',
                'ethiopian_dates.*',
                'help.documentation',
                'page.financial.statements', 'page.financial.statement',
                'page.financial.audit-trail', 'page.financial.analytics',
                'page.report.donation', 'page.report.contribution',
                'page.report.contribution-matrix',
            ],
        ],
        'nibret_hisab_head' => [
            'label' => 'Nibret Hisab Head',
            'description' => 'Finance + Inventory management',
            'permissions' => [
                'contributions.*', 'contribution_amounts.*', 'donations.*',
                'financial_transactions.*', 'bank_accounts.*',
                'inventory_items.*', 'inventory_movements.*', 'stock_movements.*', 'loss_records.*',
                'documents.*',
                'reports.*',
                'beneficiaries.view', 'charity.reports',
                'tours.reports',
                'fundraising.update_total',
                'members.view', 'members.export',
                'dashboard.view', 'profile.update', 'sessions.manage',
                'ethiopian_dates.*',
                'help.documentation',
                'page.financial.statements', 'page.financial.statement',
                'page.financial.audit-trail', 'page.financial.analytics',
                'page.report.donation', 'page.report.contribution',
                'page.report.contribution-matrix',
            ],
        ],
        'inventory_staff' => [
            'label' => 'Inventory Staff',
            'description' => 'Inventory management and tracking',
            'permissions' => [
                'inventory_items.*', 'inventory_movements.*', 'stock_movements.*', 'loss_records.*',
                'documents.*',
                'dashboard.view', 'profile.update', 'sessions.manage',
                'ethiopian_dates.*', 'reports.*',
                'help.documentation',
            ],
        ],
        'education_head' => [
            'label' => 'Education Head',
            'description' => 'Complete education management',
            'permissions' => [
                'academic_years.*', 'classes.*', 'subjects.*', 'enrollments.*', 'promotions.*',
                'students.*', 'teachers.*', 'teacher_assignments.*', 'teacher_attendances.*',
                'school_classes.*', 'student_enrollments.*',
                'attendance_sessions.*', 'attendance_records.*',
                'documents.*',
                'library_resources.upload', 'library_categories.*', 'library_subcategories.*',
                'reports.teacher_reports',
                'members.view', 'members.export',
                'members.timeline.education',
                'dashboard.view', 'profile.update', 'sessions.manage',
                'ethiopian_dates.*', 'reports.*',
                'help.documentation',
                'page.report.teacher-attendance', 'page.report.student-progress',
                'page.report.class-performance', 'page.report.attendance-summary',
                'page.attendance.teacher', 'page.attendance.student',
            ],
        ],
        'education_monitor' => [
            'label' => 'Education Monitor',
            'description' => 'Attendance tracking and monitoring',
            'permissions' => [
                'attendance_sessions.*', 'attendance_records.*',
                'documents.*',
                'teachers.substitute_assign',
                'academic_years.view', 'classes.view', 'subjects.view', 'enrollments.view',
                'teachers.view', 'school_classes.view', 'student_enrollments.view',
                'members.view',
                'dashboard.view', 'profile.update', 'sessions.manage',
                'ethiopian_dates.*', 'reports.*',
                'help.documentation',
                'page.attendance.student',
                'page.report.attendance-summary',
                'page.report.teacher-attendance',
            ],
        ],
        'worship_monitor' => [
            'label' => 'Worship Monitor',
            'description' => 'Song and rehearsal management',
            'permissions' => [
                'songs.*', 'song_categories.*', 'song_subcategories.*',
                'rehearsals.*', 'rehearsal_attendances.*',
                'documents.*',
                'media_items.visibility',
                'attendance_records.record_offline',
                'dashboard.view', 'profile.update', 'sessions.manage',
                'ethiopian_dates.*', 'reports.*',
                'help.documentation',
            ],
        ],
        'mezmur_head' => [
            'label' => 'Mezmur Head',
            'description' => 'Head of worship with full song/rehearsal control',
            'permissions' => [
                'songs.*', 'song_categories.*', 'song_subcategories.*',
                'rehearsals.*', 'rehearsal_attendances.*',
                'media_items.visibility',
                'attendance_records.record_offline',
                'reports.teacher_reports',
                'documents.*', 'departments.view',
                'members.view',
                'dashboard.view', 'profile.update', 'sessions.manage',
                'ethiopian_dates.*', 'reports.*',
                'help.documentation',
            ],
        ],
        'av_head' => [
            'label' => 'AV Head',
            'description' => 'Media, content, and communications management',
            'permissions' => [
                'media_items.*', 'media_categories.*', 'media_subcategories.*',
                'blog_posts.*', 'announcements.*', 'faqs.*',
                'documents.*',
                'content.schedule_publication',
                'members.view', 'members.export',
                'dashboard.view', 'profile.update', 'sessions.manage',
                'ethiopian_dates.*', 'reports.*',
                'help.documentation',
            ],
        ],
        'charity_head' => [
            'label' => 'Charity Head',
            'description' => 'Beneficiary and aid distribution management',
            'permissions' => [
                'beneficiaries.*', 'aid_distributions.*', 'charity.*',
                'documents.*',
                'contributions.view', 'contributions.create',
                'donations.*',
                'tours.reports',
                'members.view', 'members.export',
                'dashboard.view', 'profile.update', 'sessions.manage',
                'ethiopian_dates.*', 'reports.*',
                'help.documentation',
                'page.report.donation', 'page.report.charity', 'page.report.beneficiary',
            ],
        ],
        'tour_head' => [
            'label' => 'Tour Head',
            'description' => 'Tour and registration management',
            'permissions' => [
                'tours.*', 'tour_attendances.*', 'tour_passengers.*',
                'documents.*',
                'members.view', 'members.export',
                'dashboard.view', 'profile.update', 'sessions.manage',
                'ethiopian_dates.*', 'reports.*',
                'help.documentation',
                'page.report.tour',
            ],
        ],
        'internal_relations_head' => [
            'label' => 'Internal Relations Head',
            'description' => 'Member relations and document management',
            'permissions' => [
                'members.*', 'group_assignments.*',
                'member_groups.view', 'member_groups.create', 'member_groups.update',
                'parents.view', 'parents.create', 'parents.update', 'parents.delete',
                'media_items.delete',
                'documents.*',
                'contact_messages.view',
                'departments.view', 'departments.update',
                'dashboard.view', 'profile.update', 'sessions.manage',
                'ethiopian_dates.*', 'reports.*',
                'help.documentation',
                'page.report.attendance-summary',
                'page.report.teacher-attendance',
            ],
        ],
        'department_secretary' => [
            'label' => 'Department Secretary',
            'description' => 'Department-level resource management (no delete)',
            'permissions' => [
                'department_resources.view', 'department_resources.create', 'department_resources.update',
                'documents.*',
                'members.view', 'members.create', 'members.update',
                'members.export',
                'events.view', 'events.create', 'events.update',
                'contributions.view', 'contributions.create', 'contributions.update',
                'inventory_items.view', 'inventory_items.create', 'inventory_items.update',
                'dashboard.view', 'profile.update', 'sessions.manage',
                'ethiopian_dates.*', 'reports.*',
                'help.documentation',
            ],
        ],
        'staff' => [
            'label' => 'Staff',
            'description' => 'Read-only access to department resources',
            'permissions' => [
                'department_resources.view',
                'members.view', 'events.view', 'contributions.view',
                'inventory_items.view', 'beneficiaries.view', 'documents.*',
                'dashboard.view', 'profile.update', 'sessions.manage',
                'ethiopian_dates.*', 'reports.*',
                'help.documentation',
            ],
        ],
    ];

    public function run(): void
    {
        // Clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Generate all permissions
        $allPermissions = $this->generateAllPermissions();

        // Create permissions in database
        $this->createPermissions($allPermissions);

        // Create roles and assign permissions
        $this->createRoles();

        $this->command->info('Permission seeding completed successfully.');
    }

    /**
     * Generate all permissions from resources, special permissions, and patterns.
     */
    private function generateAllPermissions(): array
    {
        $permissions = [];

        // Generate resource-based permissions
        foreach ($this->resources as $resource => $customActions) {
            // Base CRUD + extended actions (matching BaseResource checks)
            $actions = ['view', 'create', 'update', 'delete', 'restore', 'force_delete', 'delete_any', 'force_delete_any', 'reorder', 'replicate', 'restore_any'];
            // Add custom actions
            $actions = array_merge($actions, $customActions);

            foreach ($actions as $action) {
                $permissions[] = "{$resource}.{$action}";
            }
        }

        // Add special permissions
        $permissions = array_merge($permissions, $this->specialPermissions);

        // Handle resources not defined but referenced in roles (like department_resources)
        $additionalPermissions = [
            'department_resources.view', 'department_resources.create',
            'department_resources.update', 'department_resources.delete',
        ];
        $permissions = array_merge($permissions, $additionalPermissions);

        return array_unique($permissions);
    }

    /**
     * Create permissions in database.
     */
    private function createPermissions(array $allPermissions): void
    {
        $created = 0;
        foreach ($allPermissions as $permission) {
            $result = Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
            if ($result->wasRecentlyCreated) {
                $created++;
            }
        }

        // Remove stale permissions
        Permission::whereNotIn('name', $allPermissions)->delete();

        $this->command->info("Total permissions: " . count($allPermissions) . " ($created new)");
    }

    /**
     * Create roles with their assigned permissions.
     */
    private function createRoles(): void
    {
        foreach ($this->roles as $roleName => $roleData) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                [
                    'label' => $roleData['label'],
                    'description' => $roleData['description'],
                ]
            );

            if (in_array('*', $roleData['permissions'])) {
                $role->syncPermissions(Permission::all());
                $this->command->info("Role [{$roleName}] => ALL permissions");
                continue;
            }

            $rolePermissions = $this->resolvePermissions($roleData['permissions']);
            $role->syncPermissions($rolePermissions);

            $this->command->info("Role [{$roleName}] => {$rolePermissions->count()} permissions");
        }
    }

    /**
     * Resolve permission patterns to actual permission objects.
     */
    private function resolvePermissions(array $patterns): \Illuminate\Support\Collection
    {
        $resolved = collect();

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                $prefix = str_replace('.*', '', $pattern);
                $matched = Permission::where('name', 'like', "{$prefix}.%")->get();
                $resolved = $resolved->merge($matched);
            } else {
                $permission = Permission::where('name', $pattern)->first();
                if ($permission) {
                    $resolved->push($permission);
                }
            }
        }

        return $resolved->unique('id');
    }
}
