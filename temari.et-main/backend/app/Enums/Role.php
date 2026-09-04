<?php

namespace App\Enums;

/**
 * The full role taxonomy for Temari.et. Each role has a fixed scope that
 * determines how it is granted:
 *
 *  - platform / school / branch roles are granted via `memberships` and are the
 *    ONLY roles memberships may hold;
 *  - relationship roles (student, parent, tutor, vendor) are never granted —
 *    they are derived from relationships (own enrollment, student_guardians
 *    link, tutoring engagement) and served through the /me access lane.
 */
enum Role: string
{
    // Platform — Temari.et itself
    case SuperAdmin = 'super_admin';
    case SupportAgent = 'support_agent';
    case FinanceAdmin = 'finance_admin';
    case SalesAgent = 'sales_agent';
    case ContentAdmin = 'content_admin';

    // School scope — see all branches of one school
    case Principal = 'principal';
    case SchoolAdmin = 'school_admin';

    // Branch scope — one branch only
    case Director = 'director';
    case Registrar = 'registrar';
    case FinanceOfficer = 'finance_officer';
    case Teacher = 'teacher';
    case Storekeeper = 'storekeeper';

    // Relationship-derived — never assignable, never membership-backed
    case Student = 'student';
    case Parent = 'parent';
    case Tutor = 'tutor';
    case Vendor = 'vendor';

    public function scope(): RoleScope
    {
        return match ($this) {
            self::SuperAdmin, self::SupportAgent, self::FinanceAdmin,
            self::SalesAgent, self::ContentAdmin => RoleScope::Platform,

            self::Principal, self::SchoolAdmin => RoleScope::School,

            self::Director, self::Registrar, self::FinanceOfficer,
            self::Teacher, self::Storekeeper => RoleScope::Branch,

            self::Student, self::Parent, self::Tutor, self::Vendor => RoleScope::Relationship,
        };
    }

    public function isPlatform(): bool
    {
        return $this->scope() === RoleScope::Platform;
    }

    public function isSchool(): bool
    {
        return $this->scope() === RoleScope::School;
    }

    public function isBranch(): bool
    {
        return $this->scope() === RoleScope::Branch;
    }

    public function isRelationship(): bool
    {
        return $this->scope() === RoleScope::Relationship;
    }

    /**
     * Whether this role may ever be stored on a membership row.
     */
    public function isAssignable(): bool
    {
        return ! $this->isRelationship();
    }

    /**
     * Management authority rank (lower = more authority). Drives the strict
     * Temari Admin > Principal > Director hierarchy: an actor may only act on
     * targets whose highest role ranks strictly below their own.
     *
     *  0 = platform (Temari staff)
     *  1 = school   (principal / school_admin)
     *  2 = branch admin (director)
     *  3 = branch staff / context roles (teacher, registrar, student, ...)
     */
    public function hierarchyLevel(): int
    {
        return match ($this) {
            self::SuperAdmin, self::SupportAgent, self::FinanceAdmin,
            self::SalesAgent, self::ContentAdmin => 0,

            self::Principal, self::SchoolAdmin => 1,

            self::Director => 2,

            self::Registrar, self::FinanceOfficer, self::Teacher, self::Storekeeper,
            self::Student, self::Parent, self::Tutor, self::Vendor => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::SupportAgent => 'Support Agent',
            self::FinanceAdmin => 'Finance Admin',
            self::SalesAgent => 'Sales Agent',
            self::ContentAdmin => 'Content Admin',
            self::Principal => 'Principal',
            self::SchoolAdmin => 'School Administrator',
            self::Director => 'Director',
            self::Registrar => 'Registrar',
            self::FinanceOfficer => 'Finance Officer',
            self::Teacher => 'Teacher',
            self::Storekeeper => 'Storekeeper',
            self::Student => 'Student',
            self::Parent => 'Parent',
            self::Tutor => 'Tutor',
            self::Vendor => 'Vendor',
        };
    }

    /**
     * Roles that operate on Temari.et itself (not tied to a school).
     *
     * @return list<self>
     */
    public static function platformRoles(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role): bool => $role->scope() === RoleScope::Platform,
        ));
    }
}
