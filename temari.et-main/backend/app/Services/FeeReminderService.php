<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Mail\InvoiceNoticeMail;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Services\Notify\Notifier;
use App\Services\Sms\SmsClient;
use App\Support\NotificationCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The automated fee-reminder ladder. Every open invoice with a due date
 * walks: `upcoming` (N days before due) → `due` (on the due date) →
 * `overdue_1..M` (every K days past due), with N/K/M configurable per school
 * and overridable per branch. Audiences come from the fee's own
 * notify_parents / notify_students flags; channels from each recipient's
 * preferences (InvoiceRecipients). The invoice_reminders ledger guarantees
 * one message per (invoice, recipient, stage, channel) no matter how often
 * the scheduler re-runs — a parent is never spammed twice for the same rung.
 */
class FeeReminderService
{
    public function __construct(
        private readonly SmsClient $sms,
        private readonly Notifier $notifier,
    ) {}

    /**
     * Send every matured, unsent reminder for one branch.
     *
     * @return array{sms: int, email: int}
     */
    public function runForBranch(Branch $branch, CarbonImmutable $today): array
    {
        $sent = ['sms' => 0, 'email' => 0];

        if (! $branch->effectiveFeeRemindersEnabled()) {
            return $sent;
        }

        $daysBefore = $branch->effectiveFeeReminderDaysBefore();
        $overdueEvery = $branch->effectiveFeeReminderOverdueEvery();
        $overdueMax = $branch->effectiveFeeReminderOverdueMax();

        Invoice::query()
            ->where('branch_id', $branch->id)
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->whereNotNull('due_date')
            ->whereHas('feeStructure', fn ($q) => $q
                ->where(fn ($qq) => $qq->where('notify_parents', true)->orWhere('notify_students', true)))
            ->with([
                'feeStructure:id,name,notify_parents,notify_students',
                'student.user',
                'student.guardians.parentProfile.user',
                'branch.school:id,name',
            ])
            ->chunkById(200, function ($invoices) use ($today, $daysBefore, $overdueEvery, $overdueMax, &$sent): void {
                $stages = [];
                foreach ($invoices as $invoice) {
                    $stage = $this->stageFor($invoice, $today, $daysBefore, $overdueEvery, $overdueMax);
                    if ($stage !== null) {
                        $stages[$invoice->id] = $stage;
                    }
                }

                if ($stages === []) {
                    return;
                }

                // One ledger prefetch per chunk: everything already sent for
                // these invoices at their current stage.
                $logged = InvoiceReminder::query()
                    ->whereIn('invoice_id', array_keys($stages))
                    ->get(['invoice_id', 'user_id', 'stage', 'channel'])
                    ->mapWithKeys(fn (InvoiceReminder $r) => ["{$r->invoice_id}:{$r->user_id}:{$r->stage}:{$r->channel}" => true])
                    ->all();

                foreach ($invoices as $invoice) {
                    $stage = $stages[$invoice->id] ?? null;
                    if ($stage !== null) {
                        $this->remind($invoice, $stage, $logged, $sent);
                    }
                }
            });

        return $sent;
    }

    /**
     * The reminder rung this invoice sits on as of $today; null when nothing
     * is due to go out (too early, mid-rung, or the ladder is exhausted).
     */
    public function stageFor(Invoice $invoice, CarbonImmutable $today, int $daysBefore, int $overdueEvery, int $overdueMax): ?string
    {
        $due = $invoice->due_date->toImmutable()->startOfDay();
        $today = $today->startOfDay();

        if ($today->lessThan($due)) {
            $until = (int) $today->diffInDays($due);

            return ($daysBefore > 0 && $until <= $daysBefore) ? 'upcoming' : null;
        }

        $past = (int) $due->diffInDays($today);
        if ($past === 0) {
            return 'due';
        }

        $rung = intdiv($past, max(1, $overdueEvery));
        if ($rung === 0) {
            // Missed the due-day run — the due notice is still the right one.
            return 'due';
        }

        return $rung <= $overdueMax ? "overdue_{$rung}" : null;
    }

    /**
     * @param  array<string, true>  $logged
     * @param  array{sms: int, email: int}  $sent
     */
    private function remind(Invoice $invoice, string $stage, array $logged, array &$sent): void
    {
        $fee = $invoice->feeStructure;
        $recipients = InvoiceRecipients::for(
            $invoice,
            parents: (bool) $fee?->notify_parents,
            students: (bool) $fee?->notify_students,
        );

        // SMS is metered: the ladder's SMS rungs obey the platform whitelist.
        $smsAllowed = NotificationCatalog::smsAllowed('finance.fee_reminder');

        foreach ($recipients as $recipient) {
            $user = $recipient['user'];
            $locale = $user->preferred_language ?: 'en';
            $message = $this->message($invoice, $stage, $locale);

            // In-app row per rung (the ledger key makes this once-per-stage;
            // dedupe folds an unread earlier rung into the newest one).
            if (! isset($logged["{$invoice->id}:{$user->id}:{$stage}:sms"])
                && ! isset($logged["{$invoice->id}:{$user->id}:{$stage}:email"])) {
                $this->notifier->inApp($user, 'finance.fee_reminder', [
                    'student' => $invoice->student->full_name,
                    'amount' => (string) $invoice->balance,
                ], [
                    'link' => '/me/payments',
                    'schoolId' => $invoice->school_id,
                    'branchId' => $invoice->branch_id,
                    'dedupeKey' => "fee_reminder:{$invoice->id}",
                ]);
            }

            foreach (['sms', 'email'] as $channel) {
                if (! $recipient[$channel] || isset($logged["{$invoice->id}:{$user->id}:{$stage}:{$channel}"])) {
                    continue;
                }

                if ($channel === 'sms' && ! $smsAllowed) {
                    continue;
                }

                $result = 'sent';

                try {
                    if ($channel === 'sms') {
                        $this->sms->send($user->phone, $message);
                    } else {
                        Mail::to($user->email)->send(new InvoiceNoticeMail(
                            feeName: $invoice->title,
                            studentName: $invoice->student->full_name,
                            message: $message,
                            language: $locale,
                        ));
                    }
                } catch (\Throwable $e) {
                    $result = 'failed';
                    Log::warning('Fee reminder failed.', [
                        'invoice_id' => $invoice->id,
                        'user_id' => $user->id,
                        'stage' => $stage,
                        'channel' => $channel,
                        'error' => $e->getMessage(),
                    ]);
                }

                InvoiceReminder::create([
                    'school_id' => $invoice->school_id,
                    'branch_id' => $invoice->branch_id,
                    'invoice_id' => $invoice->id,
                    'student_id' => $invoice->student_id,
                    'user_id' => $user->id,
                    'audience' => $recipient['audience'] === 'parents' ? 'parent' : 'student',
                    'stage' => $stage,
                    'channel' => $channel,
                    'recipient' => $channel === 'sms' ? $user->phone : $user->email,
                    'result' => $result,
                ]);

                if ($result === 'sent') {
                    $sent[$channel]++;
                }
            }
        }
    }

    private function message(Invoice $invoice, string $stage, string $locale): string
    {
        $key = match (true) {
            $stage === 'upcoming' => 'fees.reminder_upcoming',
            $stage === 'due' => 'fees.reminder_due',
            default => 'fees.reminder_overdue',
        };

        return Lang::get($key, [
            'school' => $invoice->branch?->school?->name ?? 'Temari.et',
            'fee' => $invoice->title,
            'amount' => $invoice->balance,
            'student' => $invoice->student->full_name,
            'date' => $invoice->due_date->toDateString(),
        ], $locale);
    }
}
