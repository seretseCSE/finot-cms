<?php

namespace App\Services\Tutoring;

use App\Enums\PayoutStatus;
use App\Models\TutorLedgerEntry;
use App\Models\TutorPayout;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\Payments\Drivers\ChapaDriver;
use App\Services\Payments\GatewayUnavailableException;
use App\Support\ActivityLogger;
use App\Support\TutorLedger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Tutor withdrawals. request() snapshots the payout account; approve()
 * debits the wallet (funds reserved — an overdraw is impossible, the ledger
 * refuses); payViaChapa()/markPaidManually() finish; fail()/cancel() credit
 * the reservation back. Approval and payment are Temari.et staff actions
 * (`marketplace.manage`).
 */
class PayoutService
{
    public function request(TutorProfile $profile, float $amount): TutorPayout
    {
        if ($amount <= 0 || $amount > (float) $profile->wallet_balance) {
            throw new HttpException(422, 'Amount exceeds your available balance.');
        }

        if (blank($profile->payout_account_number)) {
            throw new HttpException(422, 'Add your payout account first.');
        }

        $open = TutorPayout::query()
            ->where('tutor_profile_id', $profile->id)
            ->whereIn('status', [PayoutStatus::Pending->value, PayoutStatus::Approved->value])
            ->exists();

        if ($open) {
            throw new HttpException(422, 'You already have a payout in progress.');
        }

        return TutorPayout::create([
            'tutor_profile_id' => $profile->id,
            'amount' => $amount,
            'method' => 'chapa',
            'bank_code' => $profile->payout_bank_code,
            'bank_name' => $profile->payout_bank_name,
            'account_number' => $profile->payout_account_number,
            'account_name' => $profile->payout_account_name,
            'status' => PayoutStatus::Pending->value,
        ]);
    }

    /** Reserve the funds: wallet debit happens HERE, not at payment. */
    public function approve(TutorPayout $payout, User $actor): TutorPayout
    {
        return DB::transaction(function () use ($payout, $actor): TutorPayout {
            $locked = TutorPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PayoutStatus::Pending) {
                throw new HttpException(422, 'Only pending payouts can be approved.');
            }

            TutorLedger::post(
                $locked->tutorProfile,
                TutorLedgerEntry::PAYOUT,
                -(float) $locked->amount,
                $locked,
                'Payout reserved',
                $actor->id,
            );

            $locked->update([
                'status' => PayoutStatus::Approved->value,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            ActivityLogger::log($actor, 'tutor_payout.approved', $locked);

            return $locked;
        });
    }

    public function payViaChapa(TutorPayout $payout, User $actor, ChapaDriver $chapa): TutorPayout
    {
        if ($payout->status !== PayoutStatus::Approved) {
            throw new HttpException(422, 'Approve the payout first.');
        }

        try {
            $ref = $chapa->transfer(
                'PAYOUT-'.$payout->id.'-'.now()->getTimestamp(),
                (string) $payout->account_name,
                (string) $payout->account_number,
                (string) $payout->bank_code,
                (string) $payout->amount,
            );
        } catch (GatewayUnavailableException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        $payout->update([
            'status' => PayoutStatus::Paid->value,
            'method' => 'chapa',
            'gateway_ref' => $ref,
            'paid_at' => now(),
        ]);

        ActivityLogger::log($actor, 'tutor_payout.paid', $payout, ['method' => 'chapa']);

        return $payout;
    }

    /** The escape hatch: money moved outside Chapa (manual bank transfer). */
    public function markPaidManually(TutorPayout $payout, User $actor, ?string $note): TutorPayout
    {
        if ($payout->status !== PayoutStatus::Approved) {
            throw new HttpException(422, 'Approve the payout first.');
        }

        $payout->update([
            'status' => PayoutStatus::Paid->value,
            'method' => 'manual',
            'paid_at' => now(),
            'note' => $note ?? $payout->note,
        ]);

        ActivityLogger::log($actor, 'tutor_payout.paid', $payout, ['method' => 'manual']);

        return $payout;
    }

    /** Failed transfer / operator cancel: the reservation flows back. */
    public function reverse(TutorPayout $payout, User $actor, string $status, ?string $reason): TutorPayout
    {
        return DB::transaction(function () use ($payout, $actor, $status, $reason): TutorPayout {
            $locked = TutorPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [PayoutStatus::Pending, PayoutStatus::Approved], true)) {
                throw new HttpException(422, 'This payout can no longer be reversed.');
            }

            if ($locked->status === PayoutStatus::Approved) {
                TutorLedger::post(
                    $locked->tutorProfile,
                    TutorLedgerEntry::PAYOUT_REVERSAL,
                    (float) $locked->amount,
                    $locked,
                    'Payout reversed',
                    $actor->id,
                );
            }

            $locked->update([
                'status' => $status,
                'failure_reason' => $reason,
            ]);

            ActivityLogger::log($actor, 'tutor_payout.reversed', $locked, ['to' => $status]);

            return $locked;
        });
    }
}
