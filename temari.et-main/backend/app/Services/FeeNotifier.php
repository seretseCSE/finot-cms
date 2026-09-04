<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Mail\InvoiceNoticeMail;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Notify\Notifier;
use App\Services\Sms\SmsClient;
use App\Support\NotificationCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * On-demand payment notices for a fee's OPEN invoices — the "notifications
 * were off when this fee was billed" catch-up lane. Guardians are gated by
 * their channel preferences (notify_via_sms + the guardian link's
 * can_receive_sms, notify_via_email); students by their own user's
 * preferences. preview() and send() walk the exact same recipient set, so the
 * confirmation counts always match what actually goes out.
 */
class FeeNotifier
{
    public function __construct(
        private readonly SmsClient $sms,
        private readonly Notifier $notifier,
    ) {
    }

    /**
     * Recipient counts per audience and channel, for the confirmation dialog.
     *
     * @return array{invoices: int, parents: array{recipients: int, sms: int, email: int}, students: array{recipients: int, sms: int, email: int}}
     */
    public function preview(FeeStructure $feeStructure, bool $parents, bool $students): array
    {
        $invoices = $this->openInvoices($feeStructure);

        $tally = [
            'parents' => ['users' => [], 'sms' => [], 'email' => []],
            'students' => ['users' => [], 'sms' => [], 'email' => []],
        ];

        foreach ($invoices as $invoice) {
            foreach ($this->recipientsFor($invoice, $parents, $students) as $recipient) {
                $bucket = &$tally[$recipient['audience']];
                $bucket['users'][$recipient['user']->id] = true;
                if ($recipient['sms']) {
                    $bucket['sms'][$recipient['user']->id] = true;
                }
                if ($recipient['email']) {
                    $bucket['email'][$recipient['user']->id] = true;
                }
                unset($bucket);
            }
        }

        return [
            'invoices' => $invoices->count(),
            'parents' => [
                'recipients' => count($tally['parents']['users']),
                'sms' => count($tally['parents']['sms']),
                'email' => count($tally['parents']['email']),
            ],
            'students' => [
                'recipients' => count($tally['students']['users']),
                'sms' => count($tally['students']['sms']),
                'email' => count($tally['students']['email']),
            ],
        ];
    }

    /**
     * Send the notice for every open invoice — one message per invoice per
     * recipient (a parent with two billed children hears about each). Never
     * throws: a dead phone number must not block the rest of the batch.
     *
     * @return array{sms: int, email: int}
     */
    public function send(FeeStructure $feeStructure, bool $parents, bool $students): array
    {
        $sent = ['sms' => 0, 'email' => 0];

        // SMS is metered: on-demand notices obey the platform whitelist.
        $smsAllowed = NotificationCatalog::smsAllowed('finance.fee_notice');

        foreach ($this->openInvoices($feeStructure) as $invoice) {
            foreach ($this->recipientsFor($invoice, $parents, $students) as $recipient) {
                $user = $recipient['user'];
                $locale = $user->preferred_language ?: 'en';
                $message = $this->message($invoice, $feeStructure, $locale);

                $this->notifier->inApp($user, 'finance.fee_notice', [
                    'student' => $invoice->student->full_name,
                    'fee' => $feeStructure->name,
                    'amount' => (string) $invoice->balance,
                ], [
                    'link' => '/me/payments',
                    'schoolId' => $invoice->school_id,
                    'branchId' => $invoice->branch_id,
                    'dedupeKey' => "fee_notice:{$invoice->id}",
                ]);

                try {
                    if ($recipient['sms'] && $smsAllowed) {
                        $this->sms->send($user->phone, $message);
                        $sent['sms']++;
                    }
                    if ($recipient['email']) {
                        Mail::to($user->email)->send(new InvoiceNoticeMail(
                            feeName: $feeStructure->name,
                            studentName: $invoice->student->full_name,
                            message: $message,
                            language: $locale,
                        ));
                        $sent['email']++;
                    }
                } catch (\Throwable $e) {
                    Log::warning('Fee notification failed.', [
                        'user_id' => $user->id,
                        'invoice_id' => $invoice->id,
                        'fee_structure_id' => $feeStructure->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $sent;
    }

    /**
     * @return Collection<int, Invoice>
     */
    private function openInvoices(FeeStructure $feeStructure): Collection
    {
        return $feeStructure->invoices()
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->with([
                'student.user',
                'student.guardians.parentProfile.user',
                'branch.school:id,name',
            ])
            ->get();
    }

    /**
     * Recipient resolution is shared with the automated reminder ladder —
     * see InvoiceRecipients.
     *
     * @return list<array{user: User, audience: 'parents'|'students', sms: bool, email: bool}>
     */
    private function recipientsFor(Invoice $invoice, bool $parents, bool $students): array
    {
        return InvoiceRecipients::for($invoice, $parents, $students);
    }

    private function message(Invoice $invoice, FeeStructure $feeStructure, string $locale): string
    {
        $due = $invoice->due_date !== null
            ? Lang::get('fees.invoice_due_suffix', ['date' => $invoice->due_date->toDateString()], $locale)
            : '';

        return Lang::get('fees.invoice_sms', [
            'school' => $invoice->branch?->school?->name ?? 'Temari.et',
            'fee' => $feeStructure->name,
            'amount' => $invoice->balance,
            'student' => $invoice->student->full_name,
            'due' => $due,
        ], $locale);
    }
}
