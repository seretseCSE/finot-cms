<?php

namespace App\Services\Chat;

use App\Models\Branch;
use App\Models\ChatMessage;
use App\Models\ChatMessageTemplate;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\ConversationTarget;
use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\Membership;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\SectionHomeroom;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\SubjectAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * THE single access gate of the chat engine (ADR-019) — visibility, posting
 * rights, the communication-book approval gate and audience resolution all
 * live here; controllers and events never re-derive any of it.
 *
 * Membership is two mechanisms: explicit conversation_participants rows for
 * direct/group/context, and rule-derived audiences for channels
 * (conversation_targets × live memberships/enrollments/positions). Family
 * access to a student-anchored direct thread ALWAYS derives from the LIVE
 * guardian link — a revoked guardian loses the thread, a newly-linked one
 * gains it, custody-correct with no participant syncing.
 *
 * Everything is memoized per request; instantiate via the container
 * (singleton) so one HTTP request resolves each identity facet once.
 */
class ConversationAccess
{
    public const MODE_MEMBER = 'member';

    public const MODE_AUDIT = 'audit';

    /** @var array<int, list<int>> */
    private array $childIdsCache = [];

    /** @var array<int, ?int> */
    private array $ownStudentIdCache = [];

    /** @var array<int, list<array{school_id: int, branch_id: ?int}>> */
    private array $staffScopesCache = [];

    /** @var array<int, list<int>> */
    private array $accessibleIdsCache = [];

    /** @var array<int, bool> */
    private array $studentsEnabledCache = [];

    /*
    |--------------------------------------------------------------------------
    | Identity facets
    |--------------------------------------------------------------------------
    */

    /**
     * Students the user actively guards (the relationship lane, ADR-012).
     *
     * @return list<int>
     */
    public function childIds(User $user): array
    {
        return $this->childIdsCache[$user->id] ??= StudentGuardian::query()
            ->where('is_active', true)
            ->whereIn('parent_id', ParentProfile::query()->where('user_id', $user->id)->select('id'))
            ->pluck('student_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function ownStudentId(User $user): ?int
    {
        if (! array_key_exists($user->id, $this->ownStudentIdCache)) {
            $this->ownStudentIdCache[$user->id] = Student::query()
                ->where('user_id', $user->id)
                ->value('id');
        }

        return $this->ownStudentIdCache[$user->id] === null
            ? null
            : (int) $this->ownStudentIdCache[$user->id];
    }

    /**
     * The user's active STAFF scopes (school/branch memberships — platform
     * memberships deliberately excluded: chat is school-operational).
     *
     * @return list<array{school_id: int, branch_id: ?int}>
     */
    public function staffScopes(User $user): array
    {
        return $this->staffScopesCache[$user->id] ??= Membership::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNotNull('school_id')
            ->get(['school_id', 'branch_id'])
            ->map(fn (Membership $m): array => [
                'school_id' => (int) $m->school_id,
                'branch_id' => $m->branch_id === null ? null : (int) $m->branch_id,
            ])
            ->all();
    }

    /** Whether the user is staff-side at the conversation's scope. */
    public function isStaffAt(User $user, Conversation $conversation): bool
    {
        foreach ($this->staffScopes($user) as $scope) {
            if ($scope['school_id'] !== (int) $conversation->school_id) {
                continue;
            }

            if ($scope['branch_id'] === null
                || $conversation->branch_id === null
                || $scope['branch_id'] === (int) $conversation->branch_id) {
                return true;
            }
        }

        return false;
    }

    /** Student chat participation, decided by the branch (school default). */
    public function studentsEnabled(?int $branchId, ?int $schoolId): bool
    {
        $key = $branchId ?? (-1 * ($schoolId ?? 0));

        return $this->studentsEnabledCache[$key] ??= $branchId !== null
            ? (bool) Branch::query()->find($branchId)?->effectiveChatStudentsEnabled()
            : (bool) School::query()->find($schoolId)?->chatStudentsEnabled();
    }

    /*
    |--------------------------------------------------------------------------
    | Visibility
    |--------------------------------------------------------------------------
    */

    /**
     * Every conversation id the user is a MEMBER of (audit access excluded) —
     * explicit participation, live guardian/student derivation, and channel
     * target matching. Memoized per request.
     *
     * @return list<int>
     */
    public function accessibleIds(User $user): array
    {
        if (isset($this->accessibleIdsCache[$user->id])) {
            return $this->accessibleIdsCache[$user->id];
        }

        $ids = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->pluck('conversation_id');

        // Family directs derive from the LIVE guardian link.
        $childIds = $this->childIds($user);
        if ($childIds !== []) {
            $ids = $ids->merge(
                Conversation::query()
                    ->where('kind', 'direct')
                    ->whereIn('student_id', $childIds)
                    ->pluck('id'),
            );
        }

        // A student's own family threads — only where the branch allows it.
        $ownStudentId = $this->ownStudentId($user);
        if ($ownStudentId !== null) {
            $ids = $ids->merge(
                Conversation::query()
                    ->where('kind', 'direct')
                    ->where('student_id', $ownStudentId)
                    ->get(['id', 'school_id', 'branch_id'])
                    ->filter(fn (Conversation $c): bool => $this->studentsEnabled($c->branch_id, $c->school_id))
                    ->pluck('id'),
            );
        }

        $ids = $ids->merge($this->channelIdsFor($user));

        return $this->accessibleIdsCache[$user->id] = $ids
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * member / audit / null. Audit: chat.moderate holders read (never write)
     * family-facing threads in their scope — the caller must activity-log it.
     */
    public function accessMode(User $user, Conversation $conversation): ?string
    {
        if (in_array($conversation->id, $this->accessibleIds($user), true)) {
            return self::MODE_MEMBER;
        }

        if ($this->canModerate($user, $conversation)) {
            return self::MODE_AUDIT;
        }

        return null;
    }

    public function canModerate(User $user, Conversation $conversation): bool
    {
        return $user->hasPermissionForScope(
            'chat.moderate',
            (int) $conversation->school_id,
            $conversation->branch_id === null ? null : (int) $conversation->branch_id,
        );
    }

    /**
     * Who may pin/unpin in a conversation: supervisors (chat.moderate)
     * anywhere they oversee; otherwise a member per kind — either side of a
     * direct/family thread, a group owner/moderator, a channel announcer.
     */
    public function canManagePins(User $user, Conversation $conversation): bool
    {
        if ($this->canModerate($user, $conversation)) {
            return true;
        }

        if ($this->accessMode($user, $conversation) !== self::MODE_MEMBER) {
            return false;
        }

        return match ($conversation->kind) {
            'direct', 'context' => true,
            'group' => $conversation->participants()
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->whereIn('role', ['owner', 'moderator'])
                ->exists(),
            'channel' => $user->hasPermissionForScope(
                'chat.announce',
                (int) $conversation->school_id,
                $conversation->branch_id === null ? null : (int) $conversation->branch_id,
            ),
            default => false,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Posting + the communication-book gate
    |--------------------------------------------------------------------------
    */

    public function canPost(User $user, Conversation $conversation): bool
    {
        if ($conversation->isArchived()) {
            return false;
        }

        if ($this->accessMode($user, $conversation) !== self::MODE_MEMBER) {
            return false;
        }

        // Departed group members keep history, lose the composer.
        if (in_array($conversation->kind, ['group', 'context'], true)) {
            $participant = $conversation->participants()->where('user_id', $user->id)->first();

            if ($participant !== null && ! $participant->isActive()) {
                return false;
            }
        }

        if ($conversation->kind === 'channel' && $conversation->adminPostedOnly()) {
            return $user->hasPermissionForScope(
                'chat.announce',
                (int) $conversation->school_id,
                $conversation->branch_id === null ? null : (int) $conversation->branch_id,
            );
        }

        return true;
    }

    /**
     * The digital communication book: does THIS author's message wait for a
     * director? Staff-side authors without chat.moderate, in family-facing
     * conversations, per the branch (or school default) mode: 'all' gates
     * every message; 'first' gates only until the director has APPROVED one
     * of the author's messages in this conversation (first contact reviewed,
     * then the lane is open); 'off' never gates. A teacher writing as the
     * PARENT of the thread's child is family-side and never gated.
     */
    public function requiresApproval(User $author, Conversation $conversation): bool
    {
        if (! $this->reachesFamily($conversation)) {
            return false;
        }

        if ($conversation->kind === 'direct' && $conversation->student_id !== null
            && in_array((int) $conversation->student_id, $this->childIds($author), true)) {
            return false;
        }

        if (! $this->isStaffAt($author, $conversation)) {
            return false;
        }

        if ($this->canModerate($author, $conversation)) {
            return false;
        }

        $mode = $conversation->branch_id !== null
            ? ($conversation->branch?->effectiveChatApprovalMode() ?? 'all')
            : ($conversation->school?->chatApprovalMode() ?? 'all');

        if ($mode === 'off') {
            return false;
        }

        if ($mode === 'first') {
            return ! $conversation->messages()
                ->where('user_id', $author->id)
                ->where('status', ChatMessage::STATUS_SENT)
                ->whereNotNull('reviewed_by')
                ->exists();
        }

        return true;
    }

    /**
     * Enforce the 'required' template mode on a body about to be written.
     *
     * Lives here rather than in a controller because BOTH send and edit must
     * obey it — an edit that skipped this check would be a trivial way to slip
     * free text past the gate.
     */
    public function assertTemplateCompliance(User $user, Conversation $conversation, ?string $body): void
    {
        $text = trim((string) $body);

        if ($text === '' || ! $this->requiresTemplate($user, $conversation)) {
            return;
        }

        $templates = ChatMessageTemplate::query()
            ->where('school_id', $conversation->school_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('branch_id')
                ->when($conversation->branch_id !== null, fn ($qq) => $qq->orWhere('branch_id', $conversation->branch_id)))
            ->get();

        foreach ($templates as $template) {
            foreach (['en', 'am', 'om'] as $language) {
                if ($template->resolveFor($conversation, $user, $language) === $text) {
                    return;
                }
            }
        }

        abort(422, 'Your school requires preset messages when writing to families — pick one from the template list.');
    }

    /**
     * Whether this author's family-reaching text must BE a school template
     * ('required' template mode). Same exemptions as the approval gate — the
     * parent hat and moderators write freely — plus a safety valve: with no
     * active template in scope the gate never engages (a school that empties
     * its library must not brick its teachers).
     */
    public function requiresTemplate(User $author, Conversation $conversation): bool
    {
        if (! $this->reachesFamily($conversation)) {
            return false;
        }

        if ($conversation->kind === 'direct' && $conversation->student_id !== null
            && in_array((int) $conversation->student_id, $this->childIds($author), true)) {
            return false;
        }

        if (! $this->isStaffAt($author, $conversation)) {
            return false;
        }

        if ($this->canModerate($author, $conversation)) {
            return false;
        }

        $mode = $conversation->branch_id !== null
            ? ($conversation->branch?->effectiveChatTemplateMode() ?? 'suggested')
            : ($conversation->school?->chatTemplateMode() ?? 'suggested');

        if ($mode !== 'required') {
            return false;
        }

        return ChatMessageTemplate::query()
            ->where('school_id', $conversation->school_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('branch_id')
                ->when($conversation->branch_id !== null, fn ($qq) => $qq->orWhere('branch_id', $conversation->branch_id)))
            ->exists();
    }

    /** Whether parents/students are part of the conversation's audience. */
    public function reachesFamily(Conversation $conversation): bool
    {
        if ($conversation->kind === 'direct') {
            return $conversation->student_id !== null;
        }

        if ($conversation->kind === 'channel') {
            return $conversation->targets()
                ->whereIn('audience', ['parents', 'students'])
                ->exists();
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Audience resolution (notifications + membership meta)
    |--------------------------------------------------------------------------
    */

    /**
     * Every user currently in the conversation's audience. Small for
     * direct/group/context; potentially thousands for channels — call from a
     * queued job for channel fan-out, never inline.
     *
     * @return Collection<int, User>
     */
    public function audienceUsers(Conversation $conversation): Collection
    {
        if ($conversation->kind === 'channel') {
            return $this->channelAudienceUsers($conversation);
        }

        $users = User::query()
            ->whereIn('id', $conversation->participants()->whereNull('left_at')->select('user_id'))
            ->where('status', 'active')
            ->get();

        if ($conversation->kind === 'direct' && $conversation->student_id !== null) {
            $student = $conversation->student()->with(['user', 'guardians.parentProfile.user'])->first();

            if ($student !== null) {
                foreach ($student->guardians as $link) {
                    $guardianUser = $link->is_active ? $link->parentProfile?->user : null;
                    if ($guardianUser !== null && $guardianUser->isActive()) {
                        $users->push($guardianUser);
                    }
                }

                if ($student->user !== null
                    && $student->user->isActive()
                    && $this->studentsEnabled($conversation->branch_id, $conversation->school_id)) {
                    $users->push($student->user);
                }
            }
        }

        return $users->unique('id')->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Channel target matching
    |--------------------------------------------------------------------------
    */

    /**
     * Channel conversation ids whose target rules match the user.
     *
     * @return Collection<int, int>
     */
    private function channelIdsFor(User $user): Collection
    {
        $ids = collect();

        // — Staff audience: memberships × (branch narrows) × (job title
        //   narrows via positions) × (section narrows to owned sections;
        //   chat.moderate supervisors match section rows anywhere in scope).
        foreach ($this->staffScopes($user) as $scope) {
            $branchIds = $scope['branch_id'] !== null
                ? [$scope['branch_id']]
                : Branch::query()->where('school_id', $scope['school_id'])->pluck('id')->map(fn ($id): int => (int) $id)->all();

            $jobTitles = $this->jobTitles($user, $branchIds);
            $supervises = $user->hasPermissionForScope('chat.moderate', $scope['school_id'], $scope['branch_id']);

            $query = ConversationTarget::query()
                ->where('audience', 'staff')
                ->whereHas('conversation', fn ($q) => $q->where('school_id', $scope['school_id']))
                ->where(function ($q) use ($branchIds): void {
                    $q->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
                })
                ->where(function ($q) use ($jobTitles): void {
                    $q->whereNull('job_title');
                    if ($jobTitles !== []) {
                        $q->orWhereIn('job_title', $jobTitles);
                    }
                });

            if (! $supervises) {
                $ownedSectionIds = [];
                foreach ($branchIds as $branchId) {
                    $ownedSectionIds = [...$ownedSectionIds, ...$user->ownedSectionIds($branchId)];
                }

                $query->where(function ($q) use ($ownedSectionIds): void {
                    $q->whereNull('section_id');
                    if ($ownedSectionIds !== []) {
                        $q->orWhereIn('section_id', $ownedSectionIds);
                    }
                });
            }

            $ids = $ids->merge($query->pluck('conversation_id'));
        }

        // — Parents audience: the children's ACTIVE enrollments.
        $ids = $ids->merge($this->familyChannelIds($this->childIds($user), 'parents'));

        // — Students audience: the user's own active enrollment, where the
        //   branch allows student chat.
        $ownStudentId = $this->ownStudentId($user);
        if ($ownStudentId !== null) {
            $ids = $ids->merge(
                $this->familyChannelIds([$ownStudentId], 'students')
                    ->filter(function (int $conversationId): bool {
                        $c = Conversation::query()->find($conversationId, ['id', 'school_id', 'branch_id']);

                        return $c !== null && $this->studentsEnabled($c->branch_id, $c->school_id);
                    }),
            );
        }

        return $ids->map(fn ($id): int => (int) $id);
    }

    /**
     * Channel ids whose parent/student targets match the given students'
     * active enrollments.
     *
     * @param  list<int>  $studentIds
     * @return Collection<int, int>
     */
    private function familyChannelIds(array $studentIds, string $audience): Collection
    {
        if ($studentIds === []) {
            return collect();
        }

        $enrollments = StudentEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', 'active')
            ->get(['school_id', 'branch_id', 'grade_level_id', 'section_id']);

        if ($enrollments->isEmpty()) {
            return collect();
        }

        $ids = collect();

        foreach ($enrollments as $enrollment) {
            $ids = $ids->merge(
                ConversationTarget::query()
                    ->where('audience', $audience)
                    ->whereHas('conversation', fn ($q) => $q->where('school_id', $enrollment->school_id))
                    ->where(function ($q) use ($enrollment): void {
                        $q->whereNull('branch_id')->orWhere('branch_id', $enrollment->branch_id);
                    })
                    ->where(function ($q) use ($enrollment): void {
                        $q->whereNull('grade_level_id')->orWhere('grade_level_id', $enrollment->grade_level_id);
                    })
                    ->where(function ($q) use ($enrollment): void {
                        $q->whereNull('section_id');
                        if ($enrollment->section_id !== null) {
                            $q->orWhere('section_id', $enrollment->section_id);
                        }
                    })
                    ->pluck('conversation_id'),
            );
        }

        return $ids->map(fn ($id): int => (int) $id)->unique()->values();
    }

    /**
     * Resolve a channel's full audience to users (queued-job territory for
     * big scopes).
     *
     * @return Collection<int, User>
     */
    private function channelAudienceUsers(Conversation $conversation): Collection
    {
        $users = collect();

        foreach ($conversation->targets as $target) {
            $users = match ($target->audience) {
                'staff' => $users->merge($this->staffAudience($conversation, $target)),
                'parents' => $users->merge($this->familyAudience($conversation, $target, 'parents')),
                'students' => $this->studentsEnabled($target->branch_id ?? $conversation->branch_id, $conversation->school_id)
                    ? $users->merge($this->familyAudience($conversation, $target, 'students'))
                    : $users,
                default => $users,
            };
        }

        return $users->unique('id')->values();
    }

    /** @return Collection<int, User> */
    private function staffAudience(Conversation $conversation, ConversationTarget $target): Collection
    {
        $query = User::query()
            ->where('status', 'active')
            ->whereHas('memberships', function ($q) use ($conversation, $target): void {
                $q->where('is_active', true)
                    ->where('school_id', $conversation->school_id)
                    ->when(
                        $target->branch_id !== null,
                        fn ($scope) => $scope->where(function ($b) use ($target): void {
                            $b->whereNull('branch_id')->orWhere('branch_id', $target->branch_id);
                        }),
                    );
            });

        if ($target->job_title !== null || $target->section_id !== null) {
            $query->whereIn('id', $this->positionUserIds($conversation, $target));
        }

        return $query->get();
    }

    /**
     * User ids narrowed by job title / section ownership (classroom staff =
     * the section's active subject teachers + homeroom).
     *
     * @return list<int>
     */
    private function positionUserIds(Conversation $conversation, ConversationTarget $target): array
    {
        if ($target->section_id !== null) {
            $teacherEmployeeIds = SubjectAssignment::query()
                ->where('section_id', $target->section_id)
                ->where('is_active', true)
                ->pluck('employee_id')
                ->merge(
                    SectionHomeroom::query()
                        ->where('section_id', $target->section_id)
                        ->whereHas('academicYear', fn ($q) => $q->where('status', 'active'))
                        ->pluck('employee_id'),
                );

            return Employee::query()
                ->whereIn('id', $teacherEmployeeIds)
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return Employee::query()
            ->where('school_id', $conversation->school_id)
            ->when($target->branch_id !== null, fn ($q) => $q->where('branch_id', $target->branch_id))
            ->whereNotNull('user_id')
            ->whereHas('positions', function ($q) use ($target): void {
                $q->whereNull('ended_on');
                if ($target->job_title !== null) {
                    $q->where('job_title', $target->job_title);
                }
            })
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @return Collection<int, User> */
    private function familyAudience(Conversation $conversation, ConversationTarget $target, string $audience): Collection
    {
        $enrollments = StudentEnrollment::query()
            ->where('school_id', $conversation->school_id)
            ->where('status', 'active')
            ->when($target->branch_id !== null, fn ($q) => $q->where('branch_id', $target->branch_id))
            ->when($target->grade_level_id !== null, fn ($q) => $q->where('grade_level_id', $target->grade_level_id))
            ->when($target->section_id !== null, fn ($q) => $q->where('section_id', $target->section_id))
            ->pluck('student_id');

        if ($enrollments->isEmpty()) {
            return collect();
        }

        if ($audience === 'students') {
            return User::query()
                ->where('status', 'active')
                ->whereIn('id', Student::query()->whereIn('id', $enrollments)->whereNotNull('user_id')->select('user_id'))
                ->get();
        }

        return User::query()
            ->where('status', 'active')
            ->whereIn('id', ParentProfile::query()
                ->whereIn('id', StudentGuardian::query()
                    ->whereIn('student_id', $enrollments)
                    ->where('is_active', true)
                    ->select('parent_id'))
                ->whereNotNull('user_id')
                ->select('user_id'))
            ->get();
    }

    /**
     * Active job titles the user holds in the given branches.
     *
     * @param  list<int>  $branchIds
     * @return list<string>
     */
    private function jobTitles(User $user, array $branchIds): array
    {
        if ($branchIds === []) {
            return [];
        }

        return EmployeePosition::query()
            ->whereNull('ended_on')
            ->whereIn('employee_id', Employee::query()
                ->where('user_id', $user->id)
                ->whereIn('branch_id', $branchIds)
                ->select('id'))
            ->pluck('job_title')
            ->unique()
            ->values()
            ->all();
    }

    /** Reset per-request memos (tests). */
    public function flush(): void
    {
        $this->childIdsCache = [];
        $this->ownStudentIdCache = [];
        $this->staffScopesCache = [];
        $this->accessibleIdsCache = [];
        $this->studentsEnabledCache = [];
    }
}
