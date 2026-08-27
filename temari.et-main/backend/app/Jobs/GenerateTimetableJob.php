<?php

namespace App\Jobs;

use App\Enums\TimetableVersionStatus;
use App\Models\TimetableVersion;
use App\Models\User;
use App\Services\Notify\Notifier;
use App\Services\Timetable\TimetableSolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs the solver over a draft version (status `generating` while queued).
 * Locked slots survive; everything else is regenerated. Failure returns the
 * version to draft with the error in `conflicts` — never a stuck state.
 */
class GenerateTimetableJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    /** @param  array{teacher_daily_max?: int}  $options */
    public function __construct(
        public readonly int $versionId,
        public readonly array $options = [],
        public readonly ?int $requestedBy = null,
    ) {}

    public function handle(TimetableSolver $solver, Notifier $notifier): void
    {
        $version = TimetableVersion::find($this->versionId);

        if ($version === null) {
            return;
        }

        try {
            $result = $solver->solve($version, $this->options);

            $version->update([
                'status' => TimetableVersionStatus::Draft,
                'score' => $result['score'],
                'conflicts' => $result['conflicts'],
                'generated_at' => now(),
            ]);

            // The requester is usually elsewhere in the app by the time the
            // solver finishes — the feed row brings them back.
            $version->loadMissing('term:id,school_id,branch_id,name');
            $notifier->toUser(User::find($this->requestedBy), 'system.timetable_generated', [
                'term' => $version->term?->name ?? '',
            ], [
                'link' => '/timetable',
                'schoolId' => $version->term?->school_id,
                'branchId' => $version->term?->branch_id,
            ]);
        } catch (Throwable $e) {
            $version->update([
                'status' => TimetableVersionStatus::Draft,
                'conflicts' => [['code' => 'solver_failed']],
            ]);

            report($e);
        }
    }

    public function failed(?Throwable $exception): void
    {
        TimetableVersion::query()
            ->whereKey($this->versionId)
            ->update(['status' => TimetableVersionStatus::Draft->value]);
    }
}
