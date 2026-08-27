<?php

namespace App\Services\Chat;

use App\Models\Membership;
use App\Models\SectionHomeroom;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\SubjectAssignment;
use App\Models\User;
use App\Support\SearchTerm;
use Illuminate\Support\Collection;

/**
 * Who may I start a conversation with? The new-chat picker AND the
 * server-side validation both read this — the deny-by-default matrix:
 *
 *  staff lane
 *   - staff:    anyone with an active membership in my scope
 *   - families: teachers reach ONLY the students of their own sections
 *               (ownership lane); students.view holders reach any student
 *               at their scope. Starting a family chat = picking a STUDENT —
 *               the thread is with the child's guardians.
 *  family lane (/me)
 *   - a guardian reaches their child's subject teachers + homeroom + the
 *     branch's director/registrar. Parent↔parent NEVER exists.
 *   - a student (where enabled) reaches their own teachers.
 */
class ChatDirectory
{
    public function __construct(private readonly ConversationAccess $access) {}

    /**
     * Staff users reachable from the caller's active scope.
     *
     * @return Collection<int, User>
     */
    public function staffFor(User $user, int $schoolId, ?int $branchId, ?string $q = null): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->where('id', '!=', $user->id)
            ->whereHas('memberships', function ($m) use ($schoolId, $branchId): void {
                $m->where('is_active', true)
                    ->where('school_id', $schoolId)
                    ->when($branchId !== null, fn ($s) => $s->where(function ($b) use ($branchId): void {
                        $b->whereNull('branch_id')->orWhere('branch_id', $branchId);
                    }));
            })
            ->tap(fn ($query) => SearchTerm::apply($query, $q, fn ($w, string $n) => $w
                ->where('search_text', 'ilike', SearchTerm::contains($n))))
            ->orderBy('name')
            ->limit(20)
            ->get();
    }

    /**
     * Students whose family a staff member may open a thread with.
     *
     * @return Collection<int, Student>
     */
    public function studentsFor(User $user, int $schoolId, ?int $branchId, ?string $q = null): Collection
    {
        $supervisory = $user->hasPermissionForScope('students.view', $schoolId, $branchId)
            || $user->hasPermissionForScope('chat.moderate', $schoolId, $branchId);

        $enrollments = StudentEnrollment::query()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->when($branchId !== null, fn ($q2) => $q2->where('branch_id', $branchId));

        if (! $supervisory) {
            $sectionIds = $branchId !== null ? $user->ownedSectionIds($branchId) : [];

            if ($sectionIds === []) {
                return collect();
            }

            $enrollments->whereIn('section_id', $sectionIds);
        }

        return Student::query()
            ->whereIn('id', $enrollments->select('student_id'))
            ->tap(fn ($query) => SearchTerm::apply($query, $q, fn ($w, string $n) => $w
                ->where('search_text', 'ilike', SearchTerm::contains($n))))
            ->with(['guardians' => fn ($g) => $g->where('is_active', true)])
            ->orderBy('first_name')
            ->limit(20)
            ->get();
    }

    /**
     * The people a guardian/student may open a thread with, grouped per
     * child: subject teachers, homeroom, and the branch office
     * (director/registrar memberships).
     *
     * @return list<array<string, mixed>>
     */
    public function familyPartners(User $user): array
    {
        $studentIds = $this->access->childIds($user);

        $ownId = $this->access->ownStudentId($user);
        if ($ownId !== null) {
            $studentIds[] = $ownId;
        }

        if ($studentIds === []) {
            return [];
        }

        $out = [];

        $enrollments = StudentEnrollment::query()
            ->whereIn('student_id', array_unique($studentIds))
            ->where('status', 'active')
            ->with(['student:id,first_name,father_name,grandfather_name,user_id', 'branch:id,name'])
            ->get();

        foreach ($enrollments as $enrollment) {
            // A student sees only their own card, and only where enabled.
            if ($enrollment->student?->user_id === $user->id
                && ! $this->access->studentsEnabled($enrollment->branch_id, $enrollment->school_id)) {
                continue;
            }

            $teachers = collect();

            if ($enrollment->section_id !== null) {
                $teachers = SubjectAssignment::query()
                    ->where('section_id', $enrollment->section_id)
                    ->where('is_active', true)
                    ->with(['employee.user:id,name,avatar_path', 'subject:id,name'])
                    ->get()
                    ->map(fn (SubjectAssignment $sa): ?array => $sa->employee?->user === null ? null : [
                        'user_id' => $sa->employee->user->id,
                        'name' => $sa->employee->user->name,
                        'avatar_url' => $sa->employee->user->avatarUrl(),
                        'role' => 'teacher',
                        'subject' => $sa->subject?->name,
                    ])
                    ->filter()
                    ->unique('user_id')
                    ->values();

                $homeroom = SectionHomeroom::query()
                    ->where('section_id', $enrollment->section_id)
                    ->whereHas('academicYear', fn ($q) => $q->where('status', 'active'))
                    ->with('employee.user:id,name,avatar_path')
                    ->first()?->employee?->user;

                if ($homeroom !== null) {
                    $teachers = $teachers->map(fn (array $t): array => $t['user_id'] === $homeroom->id
                        ? [...$t, 'role' => 'homeroom']
                        : $t);

                    if (! $teachers->contains('user_id', $homeroom->id)) {
                        $teachers->push([
                            'user_id' => $homeroom->id,
                            'name' => $homeroom->name,
                            'avatar_url' => $homeroom->avatarUrl(),
                            'role' => 'homeroom',
                            'subject' => null,
                        ]);
                    }
                }
            }

            $office = User::query()
                ->where('status', 'active')
                ->whereIn('id', Membership::query()
                    ->where('is_active', true)
                    ->where('branch_id', $enrollment->branch_id)
                    ->whereIn('role', ['director', 'registrar'])
                    ->select('user_id'))
                ->get(['id', 'name', 'avatar_path'])
                ->map(fn (User $u): array => [
                    'user_id' => $u->id,
                    'name' => $u->name,
                    'avatar_url' => $u->avatarUrl(),
                    'role' => 'office',
                    'subject' => null,
                ]);

            $out[] = [
                'student_id' => $enrollment->student_id,
                'student_name' => $enrollment->student?->full_name,
                'branch_name' => $enrollment->branch?->name,
                'is_self' => $enrollment->student?->user_id === $user->id,
                'partners' => $teachers->merge($office)->unique('user_id')->values()->all(),
            ];
        }

        return $out;
    }

    /** Membership check behind staff↔staff directs and group membership. */
    public function isStaffReachable(User $target, int $schoolId, ?int $branchId): bool
    {
        return $target->isActive() && Membership::query()
            ->where('user_id', $target->id)
            ->where('is_active', true)
            ->where('school_id', $schoolId)
            ->when($branchId !== null, fn ($q) => $q->where(function ($b) use ($branchId): void {
                $b->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->exists();
    }

    /** May this staff user open the family thread of this student? */
    public function staffReachesStudent(User $user, Student $student): bool
    {
        $enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->first();

        if ($enrollment === null) {
            return false;
        }

        if ($user->hasPermissionForScope('students.view', (int) $enrollment->school_id, (int) $enrollment->branch_id)
            || $user->hasPermissionForScope('chat.moderate', (int) $enrollment->school_id, (int) $enrollment->branch_id)) {
            return true;
        }

        return $enrollment->section_id !== null
            && in_array((int) $enrollment->section_id, $user->ownedSectionIds((int) $enrollment->branch_id), true);
    }

    /** May this guardian/student user open a direct with this staff user? */
    public function familyReachesStaff(User $user, Student $student, User $staff): bool
    {
        $isGuardian = in_array($student->id, $this->access->childIds($user), true);
        $isSelf = $this->access->ownStudentId($user) === $student->id;

        if (! $isGuardian && ! $isSelf) {
            return false;
        }

        foreach ($this->familyPartners($user) as $card) {
            if ((int) $card['student_id'] === (int) $student->id
                && collect($card['partners'])->contains('user_id', $staff->id)) {
                return true;
            }
        }

        return false;
    }
}
