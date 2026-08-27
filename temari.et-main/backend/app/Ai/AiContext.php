<?php

namespace App\Ai;

use App\Enums\AiLane;
use App\Models\Branch;
use App\Models\School;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\Ethiopia;

/**
 * Everything a lane agent and its tools may know about WHO is asking and
 * WHERE. Built once per prompt by AiAgentFactory from the conversation row —
 * never from raw request input — so a session opened at School A keeps
 * answering for School A even after the user switches workspaces. Tools MUST
 * derive every query scope from this object plus the kernel/guardian checks;
 * a model-supplied id is a filter within that scope, never an authority.
 */
class AiContext
{
    public function __construct(
        public readonly User $user,
        public readonly AiLane $lane,
        public readonly ?School $school = null,
        public readonly ?Branch $branch = null,
        /** Parent lane: the focused child. Student lane: the user's own student row. */
        public readonly ?Student $student = null,
    ) {}

    public function schoolId(): ?int
    {
        return $this->school?->id;
    }

    public function branchId(): ?int
    {
        return $this->branch?->id;
    }

    /** Kernel check against this context's scope (ADR-010). */
    public function allows(string $permission): bool
    {
        if ($this->schoolId() === null) {
            return $this->user->isPlatformUser() && $this->user->hasPlatformPermission($permission);
        }

        return $this->user->hasPermissionForScope($permission, $this->schoolId(), $this->branchId());
    }

    /**
     * The current term of a branch (falling back to the context branch).
     * Leadership school-wide contexts pass an explicit branch id per query.
     */
    public function currentTerm(?int $branchId = null): ?Term
    {
        $branchId ??= $this->branchId();

        if ($branchId === null) {
            return Term::query()
                ->where('school_id', $this->schoolId())
                ->where('is_current', true)
                ->orderByDesc('id')
                ->first();
        }

        return Term::query()
            ->where('branch_id', $branchId)
            ->where('is_current', true)
            ->first();
    }

    /** Human context header injected into every agent's instructions. */
    public function describe(): string
    {
        $lines = [
            'Today: '.now()->toFormattedDateString().' (Ethiopian calendar: '.Ethiopia::today().').',
            'User: '.$this->user->name,
        ];

        if ($this->school !== null) {
            $lines[] = 'School: '.$this->school->name.($this->branch !== null ? ' — '.$this->branch->name.' branch' : ' (all branches)');
        }

        if ($this->student !== null) {
            $enrollment = $this->student->currentEnrollment;
            $lines[] = ($this->lane === AiLane::Parent ? 'Focused child: ' : 'Student: ')
                .$this->student->full_name
                .($enrollment !== null
                    ? ' — '.($enrollment->gradeLevel?->name ?? '').' '.($enrollment->section?->name ?? '').', '.($enrollment->branch?->school?->name ?? '')
                    : ' (no active enrollment)');
        }

        $language = $this->user->preferred_language ?? 'en';
        $lines[] = 'Preferred language: '.match ($language) {
            'am' => 'Amharic', 'om' => 'Afan Oromo', default => 'English',
        }.'. Reply in the language the user writes in; default to the preferred language when unclear.';

        return implode("\n", $lines);
    }
}
