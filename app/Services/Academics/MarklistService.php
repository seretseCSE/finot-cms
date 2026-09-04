<?php

namespace App\Services\Academics;

use App\Enums\MarklistStatus;
use App\Models\Marklist;
use App\Models\MarklistItem;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\TermGate;
use Illuminate\Support\Facades\DB;

class MarklistService
{
    public function __construct(private RankingService $ranking)
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

        $this->syncRoster($marklist, $actor);

        return $marklist->fresh(['items', 'subject', 'term']);
    }

    public function saveItems(Marklist $marklist, array $rows, User $actor): Marklist
    {
        $term = $marklist->term ?? $marklist->term()->first();
        if ($term) {
            TermGate::assertWritable($term, $actor);
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
                    || ($item->remarks ?? null) !== ($row['remarks'] ?? null)
                    || (string) ($item->score ?? '') !== (string) ($row['score'] ?? '')
                    || (string) ($item->max_score ?? '') !== (string) ($row['max_score'] ?? '');

                $maxScore = isset($row['max_score']) && $row['max_score'] !== '' && $row['max_score'] !== null
                    ? (int) $row['max_score']
                    : ($item->max_score ?: $marklist->subject?->max_score ?: 100);

                $item->fill([
                    'conduct' => $row['conduct'] ?? $item->conduct,
                    'memorization' => $row['memorization'] ?? $item->memorization,
                    'participation' => $row['participation'] ?? $item->participation,
                    'remarks' => $row['remarks'] ?? $item->remarks,
                    'score' => array_key_exists('score', $row) && $row['score'] !== '' && $row['score'] !== null
                        ? (float) $row['score']
                        : $item->score,
                    'max_score' => $maxScore,
                ]);

                if ($changed) {
                    $item->recorded_by = $actor->id;
                }
                $item->save();
            }

            $this->ranking->recalculateMarklist($marklist->fresh(['items', 'subject']));
        });

        activity()->causedBy($actor)->performedOn($marklist)->log('marklist.saved');

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
                [
                    'recorded_by' => $actor->id,
                    'max_score' => $marklist->subject?->max_score ?: 100,
                ]
            );
        }
    }
}
