<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentVerificationStatus;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\PaymentVerification;
use App\Models\User;
use App\Services\CheckEt\CheckEtClient;
use App\Services\CheckEt\CheckEtResult;
use App\Services\Notify\Notifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Verifies a parent's payment proof against bank records (check.et) and posts
 * the payment when everything lines up. Every attempt is recorded:
 *
 *  - VERIFIED     transaction real, completed, lands in a school account,
 *                 amount within the outstanding balance → Payment recorded.
 *  - FAILED       not found, not completed, or the receipt was already used.
 *  - NEEDS_REVIEW real transaction but something finance must eyeball:
 *                 receiver account not recognisably the school's, amount
 *                 above the balance, or the provider was unreachable.
 */
class VerifyInvoicePaymentAction
{
    public function __construct(
        private readonly CheckEtClient $checkEt,
        private readonly RecordPaymentAction $recordPayment,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @param  array{
     *     bank?: ?string,
     *     transaction_number?: ?string,
     *     receipt_url?: ?string,
     *     receipt_file?: ?UploadedFile,
     *     receipt_path?: ?string,
     * }  $input
     */
    public function execute(Invoice $invoice, array $input, User $submitter): PaymentVerification
    {
        if (in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Scholarship, InvoiceStatus::Void], true)) {
            throw ValidationException::withMessages([
                'invoice' => ['This invoice has nothing left to pay.'],
            ]);
        }

        $accounts = $this->collectionAccounts($invoice);
        $claimedBank = isset($input['bank'])
            ? (CheckEtClient::LOCAL_CODE_MAP[$input['bank']] ?? $input['bank'])
            : null;

        // Passing the receiving account makes check.et confirm the money
        // landed with the school — only when the claimed bank pins it down.
        $matching = $claimedBank === null
            ? collect()
            : $accounts->filter(fn (BankAccount $account) => $this->checkEtCode($account) === $claimedBank);

        $result = $this->checkEt->verify([
            'bank' => $claimedBank,
            'transaction_number' => $input['transaction_number'] ?? null,
            'account_number' => $matching->count() === 1 ? $matching->first()->account_number : null,
            'receipt_url' => $input['receipt_url'] ?? null,
            'receipt_file' => $input['receipt_file'] ?? null,
        ]);

        $method = match (true) {
            isset($input['receipt_file']) => 'file',
            isset($input['receipt_url']) => 'link',
            default => 'reference',
        };

        $verification = new PaymentVerification([
            'invoice_id' => $invoice->id,
            'student_id' => $invoice->student_id,
            'submitted_by' => $submitter->id,
            'method' => $method,
            'bank_code' => $result->bankCode() ?? $claimedBank,
            'transaction_number' => $result->transactionNumber() ?? ($input['transaction_number'] ?? null),
            'receipt_url' => $input['receipt_url'] ?? null,
            'receipt_path' => $input['receipt_path'] ?? null,
            'response' => $result->raw,
        ]);

        [$status, $reason] = $this->judge($invoice, $result, $accounts);
        $verification->status = $status;
        $verification->failure_reason = $reason;

        if ($status === PaymentVerificationStatus::Verified) {
            $receiverAccount = $this->matchReceiverAccount($result, $accounts);

            $payment = $this->recordPayment->execute($invoice, [
                'amount' => $result->amount(),
                'method' => $this->paymentMethodFor($verification->bank_code),
                'bank_account_id' => $receiverAccount?->id,
                'reference' => $verification->transaction_number,
                'note' => 'Verified via check.et by the guardian.',
            ], null);

            $verification->payment_id = $payment->id;
        }

        $verification->save();

        // Verified → the receipt flow (RecordPaymentAction → DocumentNotifier)
        // already tells the family; here we cover the other two outcomes.
        if ($status === PaymentVerificationStatus::NeedsReview) {
            $this->notifier->toStaff(
                $invoice->school_id,
                $invoice->branch_id,
                'payments.record',
                'finance.payment_submitted',
                [
                    'student' => $invoice->student?->full_name ?? '',
                    'amount' => number_format((float) ($result->amount() ?? $invoice->balance), 2),
                ],
                ['link' => '/invoices'],
            );
        } elseif ($status === PaymentVerificationStatus::Failed) {
            $this->notifier->toUser($submitter, 'finance.payment_rejected', [
                'student' => $invoice->student?->full_name ?? '',
                'amount' => number_format((float) ($result->amount() ?? $invoice->balance), 2),
            ], ['link' => '/me/payments']);
        }

        return $verification;
    }

    /**
     * @param  Collection<int, BankAccount>  $accounts
     * @return array{0: PaymentVerificationStatus, 1: ?string}
     */
    private function judge(Invoice $invoice, CheckEtResult $result, Collection $accounts): array
    {
        if (! $result->available) {
            return [PaymentVerificationStatus::NeedsReview, $result->message ?? 'Verification service unavailable.'];
        }

        if ($result->duplicate) {
            return [PaymentVerificationStatus::Failed, 'This receipt has already been used.'];
        }

        if (! $result->success || ! $result->exists) {
            return [PaymentVerificationStatus::Failed, 'The transaction could not be found in bank records.'];
        }

        if ($result->receiptStatus() !== null && $result->receiptStatus() !== 'completed') {
            return [PaymentVerificationStatus::Failed, 'The transaction has not completed.'];
        }

        $amount = $result->amount();
        if ($amount === null || $amount <= 0) {
            return [PaymentVerificationStatus::NeedsReview, 'The receipt amount could not be read.'];
        }

        // The money must recognisably land in one of the school's accounts.
        if ($accounts->isNotEmpty()
            && $result->receiverAccount() !== null
            && $this->matchReceiverAccount($result, $accounts) === null) {
            return [PaymentVerificationStatus::NeedsReview, 'The receiving account does not match the school\'s accounts.'];
        }

        $balance = round($invoice->totalDue() - (float) $invoice->amount_paid, 2);
        if ($amount > $balance) {
            return [PaymentVerificationStatus::NeedsReview, 'The receipt amount exceeds the outstanding balance.'];
        }

        return [PaymentVerificationStatus::Verified, null];
    }

    /**
     * The fee's collection accounts, falling back to every active account of
     * the school when the fee doesn't pin any down.
     *
     * @return Collection<int, BankAccount>
     */
    private function collectionAccounts(Invoice $invoice): Collection
    {
        $feeAccounts = $invoice->feeStructure?->bankAccounts()->with('bank')->get() ?? collect();

        if ($feeAccounts->isNotEmpty()) {
            return $feeAccounts->filter(fn (BankAccount $account) => $account->is_active)->values();
        }

        return BankAccount::query()
            ->where('school_id', $invoice->school_id)
            ->where('is_active', true)
            ->with('bank')
            ->get();
    }

    /**
     * Match check.et's receiver account against the school's accounts —
     * tolerant of masked digits (e.g. 1000***3218) by comparing tail digits.
     *
     * @param  Collection<int, BankAccount>  $accounts
     */
    private function matchReceiverAccount(CheckEtResult $result, Collection $accounts): ?BankAccount
    {
        $receiver = $result->receiverAccount();
        if ($receiver === null) {
            return null;
        }

        $receiverDigits = preg_replace('/\D/', '', $receiver) ?? '';

        return $accounts->first(function (BankAccount $account) use ($receiver, $receiverDigits): bool {
            $number = (string) $account->account_number;
            if ($number === '') {
                return false;
            }
            if ($number === $receiver) {
                return true;
            }

            // Masked receipts keep the tail — 4+ matching tail digits will do.
            $tail = substr($receiverDigits, -4);

            return strlen($tail) === 4 && str_ends_with(preg_replace('/\D/', '', $number) ?? '', $tail);
        });
    }

    private function checkEtCode(BankAccount $account): ?string
    {
        $code = $account->bank?->code;
        if ($code === null) {
            return null;
        }

        $mapped = CheckEtClient::LOCAL_CODE_MAP[$code] ?? $code;

        return in_array($mapped, CheckEtClient::SUPPORTED_BANKS, true) ? $mapped : null;
    }

    private function paymentMethodFor(?string $bankCode): string
    {
        return match ($bankCode) {
            'telebirr', 'cbebirr', 'mpesa' => PaymentMethod::Wallet->value,
            default => PaymentMethod::BankTransfer->value,
        };
    }
}
