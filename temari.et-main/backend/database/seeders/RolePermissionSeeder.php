<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Permission catalog. Effective authority is still gated per-context by
     * memberships + policies; this defines what each role *can* do in principle.
     */
    private const PERMISSIONS = [
        'platform.access',
        // Platform seed-data catalogs (subjects, banks, grade levels, health
        // conditions, school directory) — Temari.et staff CRUD, never schools.
        'catalogs.manage',
        'schools.view', 'schools.create', 'schools.update', 'schools.delete',
        'branches.view', 'branches.create', 'branches.update', 'branches.delete',
        'branches.view_geo',
        'employees.view', 'employees.create', 'employees.update', 'employees.delete',
        // Payroll: manage = prepare/recompute draft runs; approve = freeze + mark paid.
        'payroll.view', 'payroll.manage', 'payroll.approve',
        // HR operations. employee_attendance = the daily employee register;
        // leave.manage = decide requests + record on behalf of anyone in scope;
        // leave.request_own = employee self-service (own requests + own HR overview);
        // hr.settings.manage = leave-type policy + the holiday calendar;
        // hr.reports.view = the HR analytics endpoints.
        'employee_attendance.view', 'employee_attendance.record',
        'leave.view', 'leave.manage', 'leave.request_own',
        'hr.settings.manage', 'hr.reports.view',
        // Teacher performance appraisal (MoE-format): manage = score/submit
        // + curate the school's criteria template; view = supervisory read;
        // view_own = the evaluated employee reading + acknowledging THEIR
        // own record (ownership-checked per row, like leave.request_own).
        'evaluations.view', 'evaluations.manage', 'evaluations.view_own',
        'academic_years.view', 'academic_years.create', 'academic_years.update', 'academic_years.delete',
        'sections.view', 'sections.create', 'sections.update', 'sections.delete',
        'students.view', 'students.create', 'students.update', 'students.delete',
        'enrollments.create',
        // Year-end promotion board + rollover, and in-platform transfers.
        'promotion.manage', 'transfers.manage',
        // Branch settings hub (academic policy overrides…) — principal,
        // school_admin and the branch's own director.
        'branch_settings.manage',
        'guardians.view', 'guardians.manage',
        // .view / .record / .manage = supervisory (any section/continuous
        // assessment in scope); .view_own / .record_own / .manage_own =
        // teachers, gated to sections/assignments that are actually THEIRS
        // (homeroom or teaching assignment). Teachers hold NO branch-wide
        // reads: their reads flow through the ownership lane like their writes.
        'sections.view_own',
        'attendance.view', 'attendance.record', 'attendance.view_own', 'attendance.record_own',
        // The student attendance analytics endpoints. Scope follows the
        // holder's memberships (platform = system-wide, school roles =
        // school-wide, branch roles = their branch); teachers reach the same
        // reports through attendance.view_own, homeroom-scoped.
        'attendance.reports.view',
        // RFID attendance hardware. Terminals and card ISSUANCE are Temari.et
        // platform territory (devices.manage / cards.manage) — schools view
        // their fleet and cards, and report lost cards for replacement
        // (cards.report drives the card_requests fulfilment pipeline).
        'devices.view', 'devices.manage', 'cards.view', 'cards.manage', 'cards.report',
        'fees.view', 'fees.manage', 'payments.record',
        // Inventory & school property. manage = the storekeeper's authority
        // (item master, receive/issue/adjust, stock takes, POs); approve =
        // countersign requisitions and purchase orders — never one's own;
        // request = any staff member asking the store for items; view =
        // supervisory read over stock, the ledger and requisitions.
        'inventory.view', 'inventory.manage', 'inventory.approve', 'inventory.request',
        // Receivables analytics (aging, defaulters, daily collections) and
        // the cashbook-style books (expenses, other income, budgets).
        // books.approve = countersign expenses above the recorder's pay grade.
        'fees.reports.view',
        'finance.books.view', 'finance.books.manage', 'finance.books.approve',
        'subjects.view', 'subjects.manage',
        'timetable.view', 'timetable.manage',
        // grades.manage = supervisory continuous assessment authority (also grade books,
        // grading scales + policies); grades.manage_own = teachers on their
        // own assignments; grades.approve = countersign/reopen marklists.
        'grades.view', 'grades.manage', 'grades.manage_own', 'grades.approve',
        // Lesson planning: manage_own = teachers author their annual + weekly
        // plans; review = approve/decline them (director AND principal each
        // hold it independently); view = supervisory read (pacing dashboard).
        'lesson_plans.view', 'lesson_plans.review', 'lesson_plans.manage_own',
        'reports.view',
        // LMS (ADR-016). lms.view = supervisory read over materials /
        // assignments / quizzes + attempt monitoring; lms.manage = supervisory
        // write (any class in scope, question banks included); lms.manage_own =
        // teachers on their own classes and their own banks. exam_prep.manage =
        // Temari.et staff curating the national bank + platform mock exams.
        'lms.view', 'lms.manage', 'lms.manage_own',
        'exam_prep.manage',
        // Chat (ADR-019). Plain participation needs NO permission — DMs and
        // channel membership derive from memberships / guardian links /
        // ownership. chat.announce = create channels + post in admin-posted
        // (announcement) channels + send emergency notices; chat.moderate =
        // the communication-book approval inbox + read-only audit access to
        // teacher↔parent threads in scope (every audit open is activity-logged).
        'chat.announce', 'chat.moderate',
        'users.view', 'users.create', 'users.update', 'users.delete', 'users.impersonate',
        'users.export', 'users.status', 'users.reset_password',
        // assign_branch = add a user to a branch (cross-branch assignment).
        // manage_branch_access = activate/deactivate/remove an existing membership,
        // always scope-limited to branches the actor controls (see MembershipPolicy).
        'users.assign_branch', 'users.manage_branch_access',
        // Tutoring marketplace (platform lane). tutors.review = vet
        // applications, suspend/reinstate; marketplace.manage = the money
        // console (escrow releases, refunds, payout desk, dispute
        // resolution, commission overrides); gateways.manage = the payment
        // gateway matrix + platform payment settings.
        'tutors.review', 'marketplace.manage', 'gateways.manage',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        // super_admin is granted everything via Gate::before — listed for clarity.
        RoleEnum::SuperAdmin->value => ['*'],

        RoleEnum::SupportAgent->value => [
            'platform.access', 'schools.view', 'branches.view', 'branches.view_geo', 'employees.view',
            'academic_years.view', 'sections.view', 'students.view', 'fees.view',
            'employee_attendance.view', 'leave.view', 'hr.reports.view',
            'attendance.reports.view',
            'devices.view', 'devices.manage', 'cards.view', 'cards.manage',
            'inventory.view',
            'users.view', 'users.export',
            'tutors.review',
        ],
        RoleEnum::FinanceAdmin->value => [
            'platform.access', 'schools.view', 'branches.view', 'branches.view_geo', 'fees.view',
            'fees.reports.view', 'finance.books.view',
            'marketplace.manage', 'gateways.manage',
        ],
        RoleEnum::SalesAgent->value => [
            'platform.access', 'schools.view', 'schools.create', 'branches.view', 'branches.view_geo',
        ],
        RoleEnum::ContentAdmin->value => [
            'platform.access', 'catalogs.manage', 'exam_prep.manage',
        ],

        // School scope — principal and school_admin share the same permissions.
        // No `schools.view`: school management (the schools list + CRUD) belongs
        // to Temari.et platform staff only. A principal still sees their own
        // school profile via SchoolPolicy@view (managesSchool), never the list.
        RoleEnum::Principal->value => [
            'branches.view', 'branches.create', 'branches.update', 'branches.delete',
            'evaluations.view', 'evaluations.manage', 'evaluations.view_own',
            'employees.view', 'employees.create', 'employees.update', 'employees.delete',
            'payroll.view', 'payroll.manage', 'payroll.approve',
            'employee_attendance.view', 'employee_attendance.record',
            'leave.view', 'leave.manage', 'leave.request_own',
            'hr.settings.manage', 'hr.reports.view',
            'academic_years.view', 'academic_years.create', 'academic_years.update', 'academic_years.delete',
            'sections.view', 'sections.create', 'sections.update', 'sections.delete',
            'students.view', 'students.create', 'students.update', 'students.delete', 'enrollments.create',
            'promotion.manage', 'transfers.manage',
            'branch_settings.manage',
            'guardians.view', 'guardians.manage',
            'attendance.view', 'attendance.record', 'attendance.reports.view',
            'devices.view', 'cards.view', 'cards.report',
            'fees.view', 'fees.manage', 'payments.record',
            'fees.reports.view',
            'finance.books.view', 'finance.books.manage', 'finance.books.approve',
            'inventory.view', 'inventory.manage', 'inventory.approve', 'inventory.request',
            'subjects.view', 'subjects.manage',
            'timetable.view', 'timetable.manage',
            'grades.view', 'grades.manage', 'grades.approve',
            'lesson_plans.view', 'lesson_plans.review',
            'lms.view', 'lms.manage',
            'chat.announce', 'chat.moderate',
            'reports.view',
            'users.view', 'users.export', 'users.assign_branch', 'users.manage_branch_access',
        ],
        RoleEnum::SchoolAdmin->value => [
            'branches.view', 'branches.create', 'branches.update', 'branches.delete',
            'evaluations.view', 'evaluations.manage', 'evaluations.view_own',
            'employees.view', 'employees.create', 'employees.update', 'employees.delete',
            'payroll.view', 'payroll.manage', 'payroll.approve',
            'employee_attendance.view', 'employee_attendance.record',
            'leave.view', 'leave.manage', 'leave.request_own',
            'hr.settings.manage', 'hr.reports.view',
            'academic_years.view', 'academic_years.create', 'academic_years.update', 'academic_years.delete',
            'sections.view', 'sections.create', 'sections.update', 'sections.delete',
            'students.view', 'students.create', 'students.update', 'students.delete', 'enrollments.create',
            'promotion.manage', 'transfers.manage',
            'branch_settings.manage',
            'guardians.view', 'guardians.manage',
            'attendance.view', 'attendance.record', 'attendance.reports.view',
            'devices.view', 'cards.view', 'cards.report',
            'fees.view', 'fees.manage', 'payments.record',
            'fees.reports.view',
            'finance.books.view', 'finance.books.manage', 'finance.books.approve',
            'inventory.view', 'inventory.manage', 'inventory.approve', 'inventory.request',
            'subjects.view', 'subjects.manage',
            'timetable.view', 'timetable.manage',
            'grades.view', 'grades.manage', 'grades.approve',
            'lesson_plans.view', 'lesson_plans.review',
            'lms.view', 'lms.manage',
            'chat.announce', 'chat.moderate',
            'reports.view',
            'users.view', 'users.export', 'users.assign_branch', 'users.manage_branch_access',
        ],

        // Branch scope. A director operates *inside* one branch — they never see
        // the Branch Management module (branches list / create / edit / delete),
        // so no `branches.view`. Their finance permissions below (fees.manage,
        // payments.record, finance.books.*) are CONDITIONAL: the kernel
        // (User::permissionsForScope) strips them unless the school's
        // `director_finance_access` setting is on — in Ethiopia the director is
        // the academic head; money is the finance officer's / principal's
        // domain. They also cannot ASSIGN users to branches
        // (`users.assign_branch`, a cross-branch action) — that stays with the
        // school-scope roles above. They CAN manage access for people already in
        // their own branch (`users.manage_branch_access`: activate/deactivate/
        // remove), which MembershipPolicy scope-limits to their branch.
        RoleEnum::Director->value => [
            'evaluations.view', 'evaluations.manage', 'evaluations.view_own',
            'employees.view', 'employees.create', 'employees.update',
            'payroll.view', 'payroll.manage', 'payroll.approve',
            'employee_attendance.view', 'employee_attendance.record',
            'leave.view', 'leave.manage', 'leave.request_own',
            'hr.settings.manage', 'hr.reports.view',
            'academic_years.view', 'academic_years.create', 'academic_years.update', 'academic_years.delete',
            'sections.view', 'sections.create', 'sections.update', 'sections.delete',
            'students.view', 'students.create', 'students.update', 'students.delete', 'enrollments.create',
            'promotion.manage', 'transfers.manage',
            'branch_settings.manage',
            'guardians.view', 'guardians.manage',
            'attendance.view', 'attendance.record', 'attendance.reports.view',
            'devices.view', 'cards.view', 'cards.report',
            'fees.view', 'fees.manage', 'payments.record',
            'fees.reports.view',
            'finance.books.view', 'finance.books.manage', 'finance.books.approve',
            'inventory.view', 'inventory.manage', 'inventory.approve', 'inventory.request',
            'subjects.view', 'subjects.manage',
            'timetable.view', 'timetable.manage',
            'grades.view', 'grades.manage', 'grades.approve',
            'lesson_plans.view', 'lesson_plans.review',
            'lms.view', 'lms.manage',
            'chat.announce', 'chat.moderate',
            'reports.view',
            'users.view', 'users.export', 'users.manage_branch_access',
        ],
        RoleEnum::Registrar->value => [
            'employees.view',
            'leave.request_own', 'evaluations.view_own',
            'inventory.request',
            'academic_years.view',
            'sections.view', 'sections.create', 'sections.update',
            'students.view', 'students.create', 'students.update', 'students.delete', 'enrollments.create',
            'promotion.manage', 'transfers.manage',
            'guardians.view', 'guardians.manage',
            'attendance.view', 'attendance.record', 'attendance.reports.view',
            'cards.view', 'cards.report',
            'fees.view',
            // Registrars compile and print report cards from approved marklists.
            'grades.view', 'reports.view',
        ],
        RoleEnum::FinanceOfficer->value => [
            'academic_years.view', 'sections.view', 'students.view',
            'fees.view', 'fees.manage', 'payments.record',
            'fees.reports.view',
            'finance.books.view', 'finance.books.manage',
            // Stock has money in it: finance reads the ledger and valuation.
            'inventory.view', 'inventory.request',
            'payroll.view', 'payroll.manage',
            // Unpaid-leave days feed payroll decisions; reports carry pay cost.
            'leave.view', 'leave.request_own', 'hr.reports.view',
            'evaluations.view_own',
        ],
        // Teachers get NO branch-wide reads: no student/parent registers, no
        // academic-year management, no whole-branch grading. Everything they
        // see flows through the ownership lane — their own sections (homeroom
        // or active teaching assignment), their own marklists, their own
        // timetable. Semester metadata reaches them via timetable.view
        // (TermController) — never through academic_years.view.
        RoleEnum::Teacher->value => [
            'sections.view_own',
            'attendance.view_own', 'attendance.record_own',
            'leave.request_own', 'evaluations.view_own',
            'inventory.request',
            'subjects.view',
            'timetable.view',
            'grades.manage_own',
            'lesson_plans.manage_own',
            'lms.manage_own',
        ],

        // The storekeeper (position-driven like teacher/registrar): runs the
        // branch store — item master, receiving, issuing, stock takes, POs —
        // but never approves requisitions (that is the director's/principal's
        // countersignature via inventory.approve).
        RoleEnum::Storekeeper->value => [
            'inventory.view', 'inventory.manage', 'inventory.request',
            'leave.request_own', 'evaluations.view_own',
        ],

        // Context-derived roles get no admin permissions in this slice.
        RoleEnum::Student->value => [],
        RoleEnum::Parent->value => [],
        RoleEnum::Tutor->value => [],
        RoleEnum::Vendor->value => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName);

            $role->syncPermissions(
                $permissions === ['*'] ? self::PERMISSIONS : $permissions
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
