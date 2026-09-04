<?php

namespace App\Services\Tutoring;

use App\Enums\CycleStatus;
use App\Enums\TutoringSessionStatus;
use App\Models\TutoringCycle;
use App\Models\TutorLedgerEntry;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\TutorLedger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The escrow release — the marketplace's single money-out-of-escrow writer.
 * net = confirmed hours × rate − commission → tutor wallet (TutorLedger);
 * unfulfilled value (gross − confirmed) carries as credit into the next
 * cycle. Releases require the month to be over, every logged session
 * decided (confirmed/canceled — disputes block), and run row-locked.
 */
class CycleReleaser
{
    public function release(TutoringCycle $cycle, ?User $actor = null): TutoringCycle
    {
        return DB::transaction(function () use ($cycle, $actor): TutoringCycle {
            $locked = TutoringCycle::query()->whereKey($cycle->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== CycleStatus::Funded) {
                throw new HttpException(422, 'Only funded cycles can be released.');
            }

            if ($locked->ends_on->isFuture()) {
                throw new HttpException(422, 'The month is not over yet.');
            }

            $undecided = $locked->sessions()
                ->whereIn('status', [TutoringSessionStatus::Logged->value, TutoringSessionStatus::Disputed->value])
                ->count();

            if ($undecided > 0) {
                throw new HttpException(422, 'Sessions are still awaiting confirmation or dispute resolution.');
            }

            $confirmed = $locked->sessions()
                ->where('status', TutoringSessionStatus::Confirmed->value)
                ->get(['duration_hours']);

            $confirmedHours = round((float) $confirmed->sum('duration_hours'), 2);
            $confirmedValue = min(round($confirmedHours * (float) $locked->hourly_rate, 2), (float) $locked->gross_amount);
            $commission = round($confirmedValue * (float) $locked->commission_percent / 100, 2);
            $net = round($confirmedValue - $commission, 2);
            $creditCarried = round((float) $locked->gross_amount - $confirmedValue, 2);

            $locked->update([
                'status' => CycleStatus::Released->value,
                'confirmed_hours' => $confirmedHours,
                'confirmed_value' => $confirmedValue,
                'commission_amount' => $commission,
                'released_amount' => $net,
                'credit_carried' => $creditCarried,
                'released_at' => now(),
                'released_by' => $actor?->id,
            ]);

            $profile = $locked->engagement->tutorProfile;

            if ($net > 0) {
                TutorLedger::post(
                    $profile,
                    TutorLedgerEntry::EARNING,
                    $net,
                    $locked,
                    "Release — {$locked->label}",
                    $actor?->id,
                );
            }

            // Hours taught feed the public profile (single writer: here).
            $profile->increment('hours_taught', $confirmedHours);

            ActivityLogger::log($actor, 'tutoring_cycle.released', $locked, [
                'confirmed_hours' => $confirmedHours,
                'released_amount' => $net,
                'commission_amount' => $commission,
                'credit_carried' => $creditCarried,
            ]);

            return $locked;
        });
    }
}
