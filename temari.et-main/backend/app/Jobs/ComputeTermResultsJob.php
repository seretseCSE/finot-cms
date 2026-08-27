<?php

namespace App\Jobs;

use App\Actions\ComputeTermResultsAction;
use App\Enums\TermStatus;
use App\Models\Student;
use App\Models\StudentTermResult;
use App\Models\Term;
use App\Services\Notify\Notifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Freezes a term's report cards (student_term_results) — dispatched when a
 * term closes, and re-runnable any time by staff for an in-progress preview.
 * Families are told "report card ready" only for a CLOSED term — preview
 * recomputes stay silent (the numbers aren't final).
 */
class ComputeTermResultsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $termId) {}

    public function handle(ComputeTermResultsAction $action, Notifier $notifier): void
    {
        $term = Term::find($this->termId);

        if ($term === null) {
            return;
        }

        $action->execute($term);

        if ($term->status !== TermStatus::Closed) {
            return;
        }

        // Staff first: the frozen results unblock roster reports + promotion.
        $notifier->toStaff($term->school_id, $term->branch_id, 'grades.approve', 'system.term_results_computed', [
            'term' => $term->name,
        ], ['link' => '/marklists', 'dedupeKey' => "term_results:{$term->id}"]);

        // Then every family with a frozen row — chunked with eager loads so
        // a 2,000-student branch never lazy-loads its way through the job.
        Student::query()
            ->whereIn('id', StudentTermResult::query()->where('term_id', $term->id)->select('student_id'))
            ->with(['user', 'guardians' => fn ($q) => $q->where('is_active', true), 'guardians.parentProfile.user'])
            ->chunkById(100, function ($students) use ($notifier, $term): void {
                foreach ($students as $student) {
                    $notifier->toFamily($student, 'academics.term_results_published', [
                        'term' => $term->name,
                    ], [
                        'link' => '/me/children',
                        'schoolId' => $term->school_id,
                        'branchId' => $term->branch_id,
                        'dedupeKey' => "report_card:{$term->id}:{$student->id}",
                    ]);
                }
            });
    }
}
