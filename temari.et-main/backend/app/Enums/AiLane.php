<?php

namespace App\Enums;

use App\Models\User;

/**
 * The seven AI capability lanes — one per audience. A lane decides which
 * prompt module + tool set contributes to the composed assistant, and
 * mirrors the platform's access-lane model exactly: student/parent are
 * relationship-lane hats (ADR-012, guardian-link/self gated), the staff
 * lanes ride the membership kernel (ADR-010), platform is Temari.et staff.
 *
 * The user never PICKS a lane: the workspace decides the surface
 * (AiSurface), and the backend composes every lane the user holds there
 * into one assistant. A conversation stores its PRIMARY lane and stays on
 * its surface for life.
 */
enum AiLane: string
{
    case Student = 'student';
    case Parent = 'parent';
    case Teacher = 'teacher';
    case Leadership = 'leadership';
    case Registrar = 'registrar';
    case Finance = 'finance';
    case Platform = 'platform';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student tutor',
            self::Parent => 'Parent assistant',
            self::Teacher => 'Teacher copilot',
            self::Leadership => 'School analytics',
            self::Registrar => 'Registrar assistant',
            self::Finance => 'Finance assistant',
            self::Platform => 'Platform assistant',
        };
    }

    public function isFamilyLane(): bool
    {
        return $this === self::Student || $this === self::Parent;
    }

    /** Which assistant surface this lane composes into. */
    public function surface(): AiSurface
    {
        return match ($this) {
            self::Student, self::Parent => AiSurface::Family,
            self::Platform => AiSurface::Platform,
            default => AiSurface::School,
        };
    }

    /**
     * The assistant surfaces this user can open HERE, each with the lane set
     * that composes it — priority-ordered so the FIRST lane of a surface is
     * the stored/primary one (leadership > teacher > registrar > finance;
     * parent > student). Surface order = the default the UI falls back to:
     * a staff workspace answers as the school assistant first.
     *
     * @return array<string, non-empty-list<self>> keyed by AiSurface value
     */
    public static function surfacesFor(User $user, ?int $schoolId, ?int $branchId): array
    {
        $priority = [self::Leadership, self::Teacher, self::Registrar, self::Finance, self::Platform, self::Parent, self::Student];
        $lanes = self::availableFor($user, $schoolId, $branchId);
        usort($lanes, fn (self $a, self $b): int => array_search($a, $priority, true) <=> array_search($b, $priority, true));

        $surfaces = [];
        foreach ($lanes as $lane) {
            $surfaces[$lane->surface()->value][] = $lane;
        }

        // Default order: school (staff workspace), then platform, then family.
        return collect([AiSurface::School->value, AiSurface::Platform->value, AiSurface::Family->value])
            ->mapWithKeys(fn (string $key): array => isset($surfaces[$key]) ? [$key => $surfaces[$key]] : [])
            ->all();
    }

    /**
     * Every lane this user may open a NEW conversation in for the given
     * workspace, mirroring how the rest of the app decides surface access:
     * relationship hats from the student/guardian links, staff lanes from
     * effective permissions in the active school/branch context.
     *
     * @return list<self>
     */
    public static function availableFor(User $user, ?int $schoolId, ?int $branchId): array
    {
        $lanes = [];

        // A student row grants the tutor lane; so does a pure B2C learner
        // account (no staff memberships, no parent profile) — exam prep is
        // open to any authenticated user (ADR-016), and the tutor is its
        // paid companion. Personal-data tools deny gracefully for them.
        if ($user->studentProfile()->exists()
            || (! $user->parentProfile()->exists() && $user->memberships()->doesntExist())) {
            $lanes[] = self::Student;
        }

        if ($user->parentProfile()->exists()) {
            $lanes[] = self::Parent;
        }

        if ($user->isPlatformUser()) {
            $lanes[] = self::Platform;
        }

        if ($schoolId !== null) {
            $has = fn (string $permission): bool => $user->hasPermissionForScope($permission, $schoolId, $branchId);

            // Supervisory academic authority = the leadership analytics lane.
            if ($has('reports.view') || $has('grades.manage') || $has('lesson_plans.review')) {
                $lanes[] = self::Leadership;
            }

            if ($has('grades.manage_own') || $has('lesson_plans.manage_own') || $has('lms.manage_own')) {
                $lanes[] = self::Teacher;
            }

            if ($has('students.create') || $has('transfers.manage')) {
                $lanes[] = self::Registrar;
            }

            if ($has('fees.reports.view') || $has('finance.books.view')) {
                $lanes[] = self::Finance;
            }
        }

        return array_values(array_unique($lanes, SORT_REGULAR));
    }
}
