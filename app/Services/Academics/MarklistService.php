<?php

namespace App\Services\Academics;

use App\Enums\MarklistStatus;
use App\Models\Marklist;
use App\Models\MarklistItem;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarklistService
{
    public function __construct(private Notifier $notifier)
    {
    }

    public function ensure(int $classId, int $termId, ?int $subjectId, User $actor): Marklist
    {
        $marklist = Marklist::query()->firstOrCreate(
            [
                'class_id' => $classId,
                'term_id' => $termId,
                'subject_id' => $subjectId,
            ],
            [
                'status' => MarklistStatus::Draft,
                'assisted_by' => $actor->id,
                'assisted_at' => now(),
                'assist_reason' => 'Entered by staff (no teacher login)',
            ]
        );

        if (! $marklist->assisted_by) {
            $marklist->update([
                'assisted_by' => $actor->id,
                'assisted_at' => now(),
                'assist_reason' => $marklist->assist_reason ?: 'Entered by staff (no teacher login)',
            ]);
        }

        if ($marklist->status === MarklistStatus::Draft) {
            $this->syncRoster($marklist, $actor);
        }

        return $marklist->fresh(['items']);
    }

    public function saveItems(Marklist $marklist, array $rows, User $actor): Marklist
    {
        $this->assertTermActive($marklist, $actor);

        if ($marklist->status->isLocked()) {
            throw ValidationException::withMessages(['status' => 'Marklist is locked.']);
        }

        DB::transaction(function () use ($marklist, $rows, $actor): void {
            foreach ($rows as $row) {
                $item = MarklistItem::query()->where('marklist_id', $marklist->id)
                    ->where('member_id', $row['member_id'])
                    ->first();
                if (! $item) {
                    continue;
                }

                $changed = $item->conduct?->value !== ($row['conduct'] ?? null)
                    || $item->memorization?->value !== ($row['memorization'] ?? null)
                    || $item->participation?->value !== ($row['participation'] ?? null)
                    || ($item->remarks ?? null) !== ($row['remarks'] ?? null);

                $item->fill([
                    'conduct' => $row['conduct'] ?? $item->conduct,
                    'memorization' => $row['memorization'] ?? $item->memorization,
                    'participation' => $row['participation'] ?? $item->participation,
                    'remarks' => $row['remarks'] ?? $item->remarks,
                ]);

                if ($changed) {
                    $item->recorded_by = $actor->id;
                }
                $item->save();
            }
        });

        activity()->causedBy($actor)->performedOn($marklist)->log('marklist.saved');

        return $marklist->fresh(['items']);
    }

    public function submit(Marklist $marklist, User $actor): Marklist
    {
        $this->assertTermActive($marklist, $actor);

        if ($marklist->status !== MarklistStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'Only drafts can be submitted.']);
        }

        $filled = $marklist->items->contains(function (MarklistItem $item) {
            return $item->conduct || $item->memorization || $item->participation;
        });

        if (! $filled) {
            throw ValidationException::withMessages(['items' => 'At least one rubric dimension must be filled.']);
        }

        $marklist->update([
            'status' => MarklistStatus::Submitted,
            'submitted_at' => now(),
            'submitted_by' => $actor->id,
        ]);

        $approvers = User::permission('results.approve')->get();
        $this->notifier->toUsers($approvers, 'academics.marklist_submitted', [
            'class' => $marklist->class?->name,
            'term' => $marklist->term?->name,
            'actor' => $actor->name,
        ], null, 'marklist-submitted-'.$marklist->id);

        activity()->causedBy($actor)->performedOn($marklist)->log('marklist.submitted');

        return $marklist->fresh();
    }

    public function approve(Marklist $marklist, User $actor): Marklist
    {
        if ($marklist->status !== MarklistStatus::Submitted) {
            throw ValidationException::withMessages(['status' => 'Only submitted marklists can be approved.']);
        }

        if (! $actor->can('results.approve') && ! $actor->hasRole('superadmin')) {
            throw ValidationException::withMessages(['actor' => 'Not allowed to approve.']);
        }

        if ($actor->hasRole('education_head')) {
            // Allowed even if they assisted.
        } elseif (
            $actor->id === $marklist->submitted_by
            || $actor->id === $marklist->assisted_by
            || $marklist->items()->where('recorded_by', $actor->id)->exists()
        ) {
            throw ValidationException::withMessages([
                'actor' => 'You entered marks on this marklist — another supervisor must approve it.',
            ]);
        }

        $marklist->update([
            'status' => MarklistStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $actor->id,
        ]);

        activity()->causedBy($actor)->performedOn($marklist)->log('marklist.approved');

        return $marklist->fresh();
    }

    public function reopen(Marklist $marklist, User $actor, string $remarks): Marklist
    {
        if (strlen($remarks) < 10) {
            throw ValidationException::withMessages(['remarks' => 'Remarks must be at least 10 characters.']);
        }

        if ($marklist->status === MarklistStatus::Approved && ! $actor->can('results.approve') && ! $actor->hasRole(['admin', 'superadmin'])) {
            throw ValidationException::withMessages(['actor' => 'Only an approver can reopen an approved marklist.']);
        }

        $marklist->update([
            'status' => MarklistStatus::Draft,
            'submitted_at' => null,
            'submitted_by' => null,
            'approved_at' => null,
            'approved_by' => null,
            'remarks' => $remarks,
        ]);

        activity()->causedBy($actor)->performedOn($marklist)->log('marklist.reopened');

        return $marklist->fresh(['items']);
    }

    protected function syncRoster(Marklist $marklist, User $actor): void
    {
        $memberIds = StudentEnrollment::query()
            ->where('class_id', $marklist->class_id)
            ->whereNull('removed_at')
            ->where('status', 'Enrolled')
            ->pluck('member_id');

        foreach ($memberIds as $memberId) {
            MarklistItem::query()->firstOrCreate(
                ['marklist_id' => $marklist->id, 'member_id' => $memberId],
                ['recorded_by' => $actor->id]
            );
        }
    }

    protected function assertTermActive(Marklist $marklist, User $actor): void
    {
        $term = $marklist->term ?? $marklist->term()->first();
        if ($term && ! $term->is_active && ! $actor->hasRole(['admin', 'superadmin'])) {
            throw ValidationException::withMessages(['term' => 'Term is not active.']);
        }
    }
}
