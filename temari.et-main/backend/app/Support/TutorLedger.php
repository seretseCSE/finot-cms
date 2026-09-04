<?php

namespace App\Support;

use App\Models\TutorLedgerEntry;
use App\Models\TutorProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The ONLY writer of tutor wallet money. post() row-locks the profile,
 * appends the ledger entry with its running balance and mirrors
 * wallet_balance — so a release and a payout can never race each other
 * into a wrong balance. Debits (negative amounts) refuse to overdraw.
 */
class TutorLedger
{
    public static function post(
        TutorProfile $profile,
        string $entryType,
        float $amount,
        ?Model $reference = null,
        ?string $memo = null,
        ?int $createdBy = null,
    ): TutorLedgerEntry {
        return DB::transaction(function () use ($profile, $entryType, $amount, $reference, $memo, $createdBy): TutorLedgerEntry {
            $locked = TutorProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();

            $balance = round((float) $locked->wallet_balance + $amount, 2);

            if ($balance < 0) {
                throw new \RuntimeException('Insufficient wallet balance.');
            }

            $entry = TutorLedgerEntry::create([
                'tutor_profile_id' => $locked->id,
                'entry_type' => $entryType,
                'amount' => $amount,
                'balance_after' => $balance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'memo' => $memo,
                'created_by' => $createdBy,
            ]);

            // Aggregates live OUTSIDE fillable on purpose (no mass-assignment
            // path may touch money) — the single writer forceFills.
            $locked->forceFill(['wallet_balance' => $balance])->save();
            $profile->wallet_balance = $locked->wallet_balance;

            return $entry;
        });
    }
}
