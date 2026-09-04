<?php

namespace App\Services;

use App\Models\AssetUnit;
use App\Models\Assignment;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Conversation;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Employee;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Models\Section;
use App\Models\Student;
use App\Models\SubjectAssignment;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\Chat\ConversationAccess;
use App\Support\PublicId;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The ⌘K palette's engine: one query fanned out across everything the user may
 * see in their ACTIVE context — students, parents, employees, users, sections
 * and invoices — capped per group and ranked by relevance.
 *
 * Performance contract: every people table carries a generated `search_text`
 * column (names, phone, email, public/national IDs flattened into one
 * haystack) behind a GIN trigram index, so partial, multi-word and fuzzy
 * matches stay index-backed at scale. Visibility mirrors each list endpoint's
 * scoping (branch context, school-wide context, platform), so the palette can
 * never surface a record the corresponding table would hide.
 */
class GlobalSearchService
{
    private const LIMIT = 6;

    /** @return array<string, list<array<string, mixed>>> */
    public function search(User $user, string $raw, ?Branch $branch, ?int $schoolScopeId): array
    {
        // Names are short, so pg_trgm's default 0.6 word-similarity threshold
        // rejects a single-letter typo ("Abeba" for "Abebe"). 0.45 keeps those
        // while still discarding noise. Session-scoped; drives `%>` below.
        DB::statement('SET pg_trgm.word_similarity_threshold = 0.45');

        $groups = [];

        if ($user->hasContextPermission('students.view')) {
            $groups['students'] = $this->students($raw, $branch, $schoolScopeId);
        }
        if ($user->hasContextPermission('employees.view')) {
            $groups['employees'] = $this->employees($raw, $branch, $schoolScopeId);
        }
        if ($user->hasContextPermission('guardians.view')) {
            $groups['parents'] = $this->parents($raw, $branch, $schoolScopeId);
        }
        // Accounts are hierarchy-scoped on the Users page; only platform staff
        // may sweep them globally — school actors find people via the scoped
        // student/employee/parent groups above.
        if ($user->isPlatformUser() && $user->hasContextPermission('users.view')) {
            $groups['users'] = $this->users($raw);
        }
        if ($user->hasContextPermission('sections.view')) {
            $groups['sections'] = $this->sections($raw, $branch, $schoolScopeId, $user);
        }
        if ($user->hasContextPermission('fees.view')) {
            $groups['invoices'] = $this->invoices($raw, $branch, $schoolScopeId, $user);
            $groups['payments'] = $this->payments($raw, $branch, $schoolScopeId, $user);
            $groups['accounts'] = $this->bankAccounts($raw, $branch, $schoolScopeId, $user);
        }

        // Inventory item master + tagged asset units — mirrors the list
        // endpoints' scoping (the school's own catalog, store staff +
        // supervisors). Asset tags are the code written on the thing itself.
        if ($user->hasContextPermission('inventory.view') || $user->hasContextPermission('inventory.manage')) {
            $groups['inventory_items'] = $this->inventoryItems($raw, $branch, $schoolScopeId, $user);
            $groups['assets'] = $this->assetUnits($raw, $branch, $schoolScopeId);
        }

        // LMS content — mirrors each list endpoint's scoping: supervisors
        // (lms.view) sweep the school/branch, teachers (lms.manage_own) only
        // what touches their own classes or what they authored.
        if ($lms = $this->lmsScope($user, $branch, $schoolScopeId)) {
            [$schoolId, $ownIds] = $lms;
            $groups['exams'] = $this->quizzes($raw, $branch, $schoolId, $ownIds);
            $groups['lms_assignments'] = $this->assignments($raw, $branch, $schoolId, $ownIds, $user);
            $groups['courses'] = $this->courses($raw, $branch, $schoolId, $ownIds, $user);
            $groups['materials'] = $this->materials($raw, $branch, $schoolId, $ownIds, $user);
            $groups['question_banks'] = $this->questionBanks($raw, $branch, $schoolId);
        }

        // Tutoring marketplace register — Temari.et reviewers only (the
        // public directory has its own search; this finds ANY status).
        if ($user->hasPlatformPermission('tutors.review')) {
            $groups['tutors'] = $this->tutors($raw);
        }

        // Chat (ADR-019): titled conversations the user is a MEMBER of —
        // visibility mirrors the /chat list exactly (the access kernel).
        $groups['conversations'] = $this->conversations($user, $raw);

        // Drop empty groups so the palette renders only what matched.
        return array_filter($groups, fn (array $rows): bool => $rows !== []);
    }

    /** @return list<array<string, mixed>> */
    private function tutors(string $raw): array
    {
        $term = trim($raw);

        return TutorProfile::query()
            ->with('user:id,name,phone')
            ->where(fn ($q) => $q
                ->where('headline', 'ilike', self::contains($term))
                ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', self::contains($term))
                    ->orWhere('phone', 'ilike', self::contains($term))))
            ->latest('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (TutorProfile $t): array => [
                'id' => $t->id,
                'label' => (string) $t->user?->name,
                'sublabel' => trim(($t->headline ?? '').' · '.$t->status->value, ' ·'),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function conversations(User $user, string $raw): array
    {
        $ids = app(ConversationAccess::class)->accessibleIds($user);

        if ($ids === []) {
            return [];
        }

        return Conversation::query()
            ->whereIn('id', $ids)
            ->whereNotNull('title')
            ->where('title', 'ilike', self::contains(trim($raw)))
            ->latest('last_message_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Conversation $conversation): array => [
                'id' => $conversation->id,
                'label' => $conversation->title,
                'sublabel' => ucfirst($conversation->kind),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function students(string $raw, ?Branch $branch, ?int $schoolScopeId): array
    {
        return Student::query()
            ->when($branch, fn ($q) => $q->where(fn ($inner) => $inner
                ->where('branch_id', $branch->id)
                ->orWhereHas('enrollments', fn ($e) => $e->where('branch_id', $branch->id))))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->where(fn ($inner) => $inner
                ->where('school_id', $schoolScopeId)
                ->orWhereHas('enrollments', fn ($e) => $e->where('school_id', $schoolScopeId))))
            ->where(fn ($q) => $this->whereMatches($q, $raw))
            ->tap(fn ($q) => $this->rank($q, $raw))
            ->with('currentEnrollment.gradeLevel:id,name', 'currentEnrollment.section:id,name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Student $s): array => [
                'id' => $s->id,
                'label' => $s->full_name,
                'sublabel' => trim(implode(' · ', array_filter([
                    $s->public_id,
                    $s->currentEnrollment?->gradeLevel?->name,
                    $s->currentEnrollment?->section?->name,
                ]))),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function employees(string $raw, ?Branch $branch, ?int $schoolScopeId): array
    {
        return Employee::query()
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->where('school_id', $schoolScopeId))
            ->where(function ($q) use ($raw): void {
                $this->whereMatches($q, $raw);
                // Login identifiers live on the linked user.
                $q->orWhereHas('user', fn ($u) => $u
                    ->where('public_id', PublicId::normalize($raw))
                    ->orWhere('phone', 'ilike', self::contains($raw)));
            })
            ->tap(fn ($q) => $this->rank($q, $raw))
            ->with('positions:id,employee_id,job_title,ended_on')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Employee $e): array => [
                'id' => $e->id,
                'label' => trim("{$e->first_name} {$e->father_name}"),
                'sublabel' => $e->positions->whereNull('ended_on')->pluck('job_title')->map(
                    fn (string $d) => ucwords(str_replace('_', ' ', $d)),
                )->implode(', ') ?: null,
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function parents(string $raw, ?Branch $branch, ?int $schoolScopeId): array
    {
        return ParentProfile::query()
            ->when($branch, fn ($q) => $q->whereHas('guardianships.student', fn ($s) => $s
                ->where(fn ($inner) => $inner
                    ->where('branch_id', $branch->id)
                    ->orWhereHas('enrollments', fn ($e) => $e->where('branch_id', $branch->id)))))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->whereHas('guardianships.student', fn ($s) => $s
                ->where(fn ($inner) => $inner
                    ->where('school_id', $schoolScopeId)
                    ->orWhereHas('enrollments', fn ($e) => $e->where('school_id', $schoolScopeId)))))
            ->where(function ($q) use ($raw): void {
                $this->whereMatches($q, $raw);
                // Login phone/email/public_id live on the linked user (its own
                // trigram indexes carry these).
                $q->orWhereHas('user', fn ($u) => $u->where(fn ($w) => $w
                    ->where('phone', 'ilike', self::contains($raw))
                    ->orWhere('email', 'ilike', self::contains($raw))
                    ->orWhere('public_id', PublicId::normalize($raw))));
            })
            ->tap(fn ($q) => $this->rank($q, $raw))
            ->with('user:id,phone')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (ParentProfile $p): array => [
                'id' => $p->id,
                'label' => trim("{$p->first_name} {$p->father_name}"),
                'sublabel' => $p->user?->phone,
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function users(string $raw): array
    {
        return User::query()
            ->where(fn ($q) => $this->whereMatches($q, $raw))
            ->tap(fn ($q) => $this->rank($q, $raw))
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'phone', 'public_id'])
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'label' => $u->name,
                'sublabel' => trim(implode(' · ', array_filter([$u->public_id, $u->phone]))),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function sections(string $raw, ?Branch $branch, ?int $schoolScopeId, User $user): array
    {
        return Section::query()
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->where('school_id', $schoolScopeId))
            ->when(! $branch && ! $schoolScopeId && ! $user->isPlatformUser(), fn ($q) => $q->whereRaw('1 = 0'))
            ->where(fn ($q) => $q
                ->where('name', 'ilike', self::contains($raw))
                ->orWhereHas('gradeLevel', fn ($g) => $g->where('name', 'ilike', self::contains($raw))))
            ->with('gradeLevel:id,name', 'branch:id,name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Section $s): array => [
                'id' => $s->id,
                'label' => trim(($s->gradeLevel?->name ?? '')." — {$s->name}"),
                'sublabel' => $s->branch?->name,
            ])
            ->values()
            ->all();
    }

    /**
     * The invoice id a query names, if any: bare digits ("3266", "003266")
     * or the printed number with optional #/INV prefix ("INV-003266").
     */
    private static function invoiceNumber(string $raw): ?int
    {
        return preg_match('/^#?(?:inv[-\s]?)?0*(\d+)$/i', trim($raw), $m)
            ? (int) $m[1]
            : null;
    }

    /** @return list<array<string, mixed>> */
    private function invoices(string $raw, ?Branch $branch, ?int $schoolScopeId, User $user): array
    {
        return Invoice::query()
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->where('school_id', $schoolScopeId))
            ->when(! $branch && ! $schoolScopeId && ! $user->isPlatformUser(), fn ($q) => $q->whereRaw('1 = 0'))
            ->where(function ($q) use ($raw): void {
                $q->whereHas('student', fn ($s) => $this->whereMatches($s, $raw))
                    ->orWhere('title', 'ilike', self::contains($raw));

                // The invoice number is id-derived (INV-003266) — an indexed
                // pk lookup, zero extra cost: "3266", "003266", "INV-003266",
                // "inv 3266" and "#3266" all resolve to invoice #3266.
                if (($invoiceId = self::invoiceNumber($raw)) !== null) {
                    $q->orWhere('id', $invoiceId);
                }
            })
            ->with('student:id,first_name,father_name')
            ->latest()
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Invoice $i): array => [
                'id' => $i->id,
                // The palette deep-links to the student's fees tab.
                'student_id' => $i->student_id,
                'label' => $i->title,
                'sublabel' => trim(implode(' · ', array_filter([
                    sprintf('INV-%06d', $i->id),
                    $i->student ? trim("{$i->student->first_name} {$i->student->father_name}") : null,
                    number_format((float) $i->amount, 2).' ETB',
                    ucfirst($i->status->value),
                ]))),
            ])
            ->values()
            ->all();
    }

    /**
     * Bank transaction references pasted straight from a receipt or a bank
     * statement ("FT26193K8Q…") AND official receipt numbers as printed on
     * the receipt itself ("RCT-12-000123"). Contains-match behind the
     * trigram indexes on both columns, so a partial code still lands.
     *
     * @return list<array<string, mixed>>
     */
    private function payments(string $raw, ?Branch $branch, ?int $schoolScopeId, User $user): array
    {
        // References are opaque codes — two characters would only pull noise.
        if (strlen(trim($raw)) < 4) {
            return [];
        }

        return Payment::query()
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->where('school_id', $schoolScopeId))
            ->when(! $branch && ! $schoolScopeId && ! $user->isPlatformUser(), fn ($q) => $q->whereRaw('1 = 0'))
            ->where(fn ($q) => $q
                ->where('reference', 'ilike', self::contains(trim($raw)))
                ->orWhere('receipt_number', 'ilike', self::contains(trim($raw))))
            ->with('student:id,first_name,father_name', 'invoice:id,title')
            ->latest('paid_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Payment $p): array => [
                'id' => $p->id,
                // The palette deep-links to the student's fees tab.
                'student_id' => $p->student_id,
                'label' => (string) ($p->reference ?: $p->receipt_number),
                'sublabel' => trim(implode(' · ', array_filter([
                    $p->reference ? $p->receipt_number : null,
                    $p->student ? trim("{$p->student->first_name} {$p->student->father_name}") : null,
                    $p->invoice?->title,
                    number_format((float) $p->amount, 2).' ETB',
                ]))),
            ])
            ->values()
            ->all();
    }

    /**
     * The staff LMS lane's visibility, resolved once: `[schoolId, ownIds]`
     * where `ownIds` is NULL for supervisors (lms.view — whole school/branch)
     * and the teacher's own subject-assignment ids for lms.manage_own.
     * Returns null when the user has no LMS access in this context.
     *
     * @return array{0: int, 1: list<int>|null}|null
     */
    private function lmsScope(User $user, ?Branch $branch, ?int $schoolScopeId): ?array
    {
        $schoolId = $branch?->school_id ?? $schoolScopeId;

        if ($schoolId === null) {
            return null;
        }

        if ($user->hasPermissionForScope('lms.view', $schoolId, $branch?->id)) {
            return [$schoolId, null];
        }

        if ($user->hasPermissionForScope('lms.manage_own', $schoolId, $branch?->id)) {
            return [$schoolId, SubjectAssignment::query()
                ->whereHas('employee', fn ($q) => $q->where('user_id', $user->id))
                ->pluck('id')
                ->all()];
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function quizzes(string $raw, ?Branch $branch, int $schoolId, ?array $ownIds): array
    {
        return Quiz::query()
            ->where('is_platform', false)
            ->when($ownIds === null, fn ($q) => $q
                ->where('school_id', $schoolId)
                ->when($branch, fn ($qq) => $qq->where('branch_id', $branch->id)))
            ->when($ownIds !== null, fn ($q) => $q
                ->whereHas('targets', fn ($t) => $t->whereIn('subject_assignment_id', $ownIds ?? [])))
            ->where('title', 'ilike', self::contains(trim($raw)))
            ->with('subject:id,name', 'gradeLevel:id,name')
            ->latest('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Quiz $quiz): array => [
                'id' => $quiz->id,
                'label' => $quiz->title,
                'sublabel' => trim(implode(' · ', array_filter([
                    ucfirst((string) $quiz->kind),
                    $quiz->subject?->name,
                    $quiz->gradeLevel?->name,
                ]))),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function assignments(string $raw, ?Branch $branch, int $schoolId, ?array $ownIds, User $user): array
    {
        return Assignment::query()
            ->when($ownIds === null, fn ($q) => $q
                ->where('school_id', $schoolId)
                ->when($branch, fn ($qq) => $qq->where('branch_id', $branch->id)))
            ->when($ownIds !== null, fn ($q) => $q
                ->whereHas('subjectAssignment.employee', fn ($e) => $e->where('user_id', $user->id)))
            ->where('title', 'ilike', self::contains(trim($raw)))
            ->with('subjectAssignment.subject:id,name', 'subjectAssignment.section:id,name')
            ->latest('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Assignment $a): array => [
                'id' => $a->id,
                'label' => $a->title,
                'sublabel' => trim(implode(' · ', array_filter([
                    $a->subjectAssignment?->subject?->name,
                    $a->subjectAssignment?->section?->name,
                ]))),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function courses(string $raw, ?Branch $branch, int $schoolId, ?array $ownIds, User $user): array
    {
        return Course::query()
            ->where('school_id', $schoolId)
            ->when($ownIds === null && $branch, fn ($q) => $q->where(
                fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $branch->id),
            ))
            ->when($ownIds !== null, fn ($q) => $q->where(fn ($w) => $w
                ->where('created_by', $user->id)
                ->orWhereHas('subjectAssignment.employee', fn ($e) => $e->where('user_id', $user->id))
                ->orWhereHas('targets.subjectAssignment.employee', fn ($e) => $e->where('user_id', $user->id))))
            ->where('title', 'ilike', self::contains(trim($raw)))
            ->with('subject:id,name')
            ->latest('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Course $c): array => [
                'id' => $c->id,
                'label' => $c->title,
                'sublabel' => $c->subject?->name,
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function materials(string $raw, ?Branch $branch, int $schoolId, ?array $ownIds, User $user): array
    {
        return CourseMaterial::query()
            ->where('school_id', $schoolId)
            ->when($ownIds === null && $branch, fn ($q) => $q->where(
                fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $branch->id),
            ))
            ->when($ownIds !== null, fn ($q) => $q->where(fn ($w) => $w
                ->where('created_by', $user->id)
                ->orWhereHas('targets', fn ($t) => $t->whereIn('subject_assignment_id', $ownIds ?? []))))
            ->where('title', 'ilike', self::contains(trim($raw)))
            ->with('subject:id,name')
            ->latest('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (CourseMaterial $m): array => [
                'id' => $m->id,
                'label' => $m->title,
                'sublabel' => trim(implode(' · ', array_filter([
                    ucfirst((string) $m->type),
                    $m->subject?->name,
                ]))),
            ])
            ->values()
            ->all();
    }

    /**
     * School question banks — teachers see the whole school's catalog on the
     * banks page (mirrored here), so no ownership narrowing.
     *
     * @return list<array<string, mixed>>
     */
    private function questionBanks(string $raw, ?Branch $branch, int $schoolId): array
    {
        return QuestionBank::query()
            ->where('school_id', $schoolId)
            ->when($branch, fn ($q) => $q->where(
                fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $branch->id),
            ))
            ->where('name', 'ilike', self::contains(trim($raw)))
            ->with('subject:id,name', 'gradeLevel:id,name')
            ->latest('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (QuestionBank $b): array => [
                'id' => $b->id,
                'label' => $b->name,
                'sublabel' => trim(implode(' · ', array_filter([
                    $b->subject?->name,
                    $b->gradeLevel?->name,
                ]))),
            ])
            ->values()
            ->all();
    }

    /**
     * Collection accounts by number or name ("which account is 1000234…?").
     * School-owned rows; the table is small per school so plain ILIKE is fine.
     *
     * @return list<array<string, mixed>>
     */
    private function bankAccounts(string $raw, ?Branch $branch, ?int $schoolScopeId, User $user): array
    {
        $schoolId = $branch?->school_id ?? $schoolScopeId;

        if ($schoolId === null && ! $user->isPlatformUser()) {
            return [];
        }

        return BankAccount::query()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->where(fn ($q) => $q
                ->where('account_number', 'ilike', self::contains(trim($raw)))
                ->orWhere('account_name', 'ilike', self::contains(trim($raw)))
                ->orWhereHas('bank', fn ($b) => $b->where('name', 'ilike', self::contains(trim($raw)))))
            ->with('bank:id,name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (BankAccount $a): array => [
                'id' => $a->id,
                'label' => $a->account_name,
                'sublabel' => trim(implode(' · ', array_filter([
                    $a->bank?->name,
                    $a->account_number,
                ]))),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function inventoryItems(string $raw, ?Branch $branch, ?int $schoolScopeId, User $user): array
    {
        $schoolId = $branch?->school_id ?? $schoolScopeId;

        if ($schoolId === null) {
            return [];
        }

        $term = trim($raw);

        return InventoryItem::query()
            ->where('school_id', $schoolId)
            ->where(fn ($q) => $q
                ->where('name', 'ilike', self::contains($term))
                ->orWhere('code', 'ilike', self::contains($term)))
            ->with('category:id,name')
            ->orderByRaw('word_similarity(?, name) desc', [$term])
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (InventoryItem $i): array => [
                'id' => $i->id,
                'label' => $i->name,
                'sublabel' => trim(implode(' · ', array_filter([
                    $i->category?->name,
                    $i->code,
                ]))),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function assetUnits(string $raw, ?Branch $branch, ?int $schoolScopeId): array
    {
        $schoolId = $branch?->school_id ?? $schoolScopeId;

        if ($schoolId === null) {
            return [];
        }

        $term = trim($raw);

        return AssetUnit::query()
            ->where('school_id', $schoolId)
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->where(fn ($q) => $q
                ->where('tag', PublicId::normalize($term))
                ->orWhere('serial_number', 'ilike', self::contains($term)))
            ->with(['item:id,name', 'openAssignment'])
            ->latest('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (AssetUnit $unit): array => [
                'id' => $unit->id,
                'label' => "{$unit->tag} · ".($unit->item?->name ?? ''),
                'sublabel' => trim(implode(' · ', array_filter([
                    $unit->serial_number,
                    $unit->status->label(),
                ]))),
            ])
            ->values()
            ->all();
    }

    /**
     * Index-backed matching against `search_text`: every word the user typed
     * must appear somewhere in the haystack (so "abebe kebede" spans name
     * parts, "abebe 0911" mixes name and phone), OR the whole query
     * fuzzy-matches one word (typo tolerance via pg_trgm word similarity),
     * OR — when the query carries digits — its bare digits appear (so phones
     * typed with spaces/dashes still hit).
     */
    private function whereMatches(Builder $q, string $raw): void
    {
        $tokens = preg_split('/\s+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        $q->where(function (Builder $outer) use ($tokens, $digits, $raw): void {
            $outer->where(function (Builder $all) use ($tokens): void {
                foreach ($tokens as $token) {
                    $all->where('search_text', 'ilike', self::contains($token));
                }
            });
            $outer->orWhereRaw('search_text %> ?', [$raw]);
            if (strlen($digits) >= 5 && $digits !== $raw) {
                $outer->orWhere('search_text', 'ilike', "%{$digits}%");
            }
        });
    }

    /** Best matches first: how close the query is to the closest word in the haystack. */
    private function rank(Builder $q, string $raw): void
    {
        $q->orderByRaw('word_similarity(?, search_text) desc', [$raw]);
    }

    /** A `%…%` ILIKE needle with the user's wildcards neutralised. */
    private static function contains(string $value): string
    {
        return '%'.addcslashes($value, '\%_').'%';
    }
}
