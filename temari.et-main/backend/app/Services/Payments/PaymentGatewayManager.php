<?php

namespace App\Services\Payments;

use App\Enums\GatewayPurpose;
use App\Enums\GatewayTransactionStatus;
use App\Models\GatewayTransaction;
use App\Models\User;
use App\Services\Payments\Contracts\GatewayDriver;
use App\Services\Payments\Contracts\GatewayPayable;
use App\Services\Payments\Drivers\CbeBirrDriver;
use App\Services\Payments\Drivers\ChapaDriver;
use App\Services\Payments\Drivers\FakeDriver;
use App\Services\Payments\Drivers\TelebirrDriver;
use App\Support\ActivityLogger;
use App\Support\PaymentGateways;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * THE authority over gateway money. Drivers only talk protocols; every state
 * transition happens here: checkout() creates the transaction + hosted
 * checkout, settle() is the single writer of `paid` (fulfilling the payable
 * exactly once, row-locked), webhooks are re-verified server-side before
 * anything settles. Purpose gating (which gateways may collect what) comes
 * from the operator matrix in PaymentGateways.
 */
class PaymentGatewayManager
{
    public function driver(string $code): GatewayDriver
    {
        return match ($code) {
            'chapa' => app(ChapaDriver::class),
            'telebirr' => app(TelebirrDriver::class),
            'cbebirr' => app(CbeBirrDriver::class),
            'fake' => app(FakeDriver::class),
            default => throw new InvalidArgumentException("Unknown gateway [{$code}]."),
        };
    }

    /**
     * Start a hosted checkout. The payable model must implement
     * GatewayPayable; $gateway must be enabled for $purpose.
     */
    public function checkout(
        GatewayPurpose $purpose,
        Model&GatewayPayable $payable,
        User $payer,
        string $amount,
        string $gateway,
        string $returnUrl,
    ): GatewayTransaction {
        if (! in_array($gateway, PaymentGateways::availableFor($purpose), true)) {
            throw new HttpException(422, 'This payment method is not available right now.');
        }

        if (! is_numeric($amount) || (float) $amount <= 0) {
            throw new HttpException(422, 'Nothing to pay.');
        }

        $transaction = GatewayTransaction::create([
            'tx_ref' => GatewayTransaction::allocateRef(),
            'gateway' => $gateway,
            'purpose' => $purpose->value,
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'user_id' => $payer->id,
            'amount' => $amount,
            'status' => GatewayTransactionStatus::Initiated->value,
        ]);

        // The return URL may template the allocated reference.
        $returnUrl = str_replace('{tx_ref}', $transaction->tx_ref, $returnUrl);

        try {
            $checkoutUrl = $this->driver($gateway)->initiate($transaction, $returnUrl);
        } catch (GatewayUnavailableException $e) {
            $transaction->update([
                'status' => GatewayTransactionStatus::Failed->value,
                'failure_reason' => $e->getMessage(),
            ]);

            throw new HttpException(422, $e->getMessage());
        }

        $transaction->update([
            'status' => GatewayTransactionStatus::Pending->value,
            'checkout_url' => $checkoutUrl,
        ]);

        return $transaction;
    }

    /**
     * Ask the gateway for the truth and settle accordingly. Safe to call any
     * number of times (return-page polling, webhooks, ops retries).
     */
    public function verifyNow(GatewayTransaction $transaction): GatewayTransaction
    {
        if ($transaction->status->isTerminal()) {
            return $transaction;
        }

        $verdict = $this->driver($transaction->gateway)->verify($transaction);

        return match ($verdict->outcome) {
            'paid' => $this->settle($transaction, $verdict),
            'failed' => tap($transaction)->update([
                'status' => GatewayTransactionStatus::Failed->value,
                'failure_reason' => $verdict->reason,
                'raw' => $verdict->raw ?: $transaction->raw,
            ]),
            default => $transaction,
        };
    }

    /**
     * Webhook entry: validate via the driver, then re-verify server-side —
     * the webhook body is a doorbell, never evidence.
     */
    public function handleWebhook(string $gateway, Request $request): void
    {
        $txRef = $this->driver($gateway)->webhookTxRef($request);

        if ($txRef === null) {
            return;
        }

        $transaction = GatewayTransaction::query()
            ->where('tx_ref', $txRef)
            ->where('gateway', $gateway)
            ->first();

        if ($transaction !== null) {
            $this->verifyNow($transaction);
        }
    }

    /**
     * The single writer of `paid`: row-locked, idempotent, fulfils the
     * payable exactly once inside the same transaction.
     */
    private function settle(GatewayTransaction $transaction, GatewayVerdict $verdict): GatewayTransaction
    {
        return DB::transaction(function () use ($transaction, $verdict): GatewayTransaction {
            $locked = GatewayTransaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === GatewayTransactionStatus::Paid) {
                return $locked;
            }

            $locked->update([
                'status' => GatewayTransactionStatus::Paid->value,
                'gateway_ref' => $verdict->gatewayRef ?? $locked->gateway_ref,
                'raw' => $verdict->raw ?: $locked->raw,
                'paid_at' => now(),
            ]);

            $payable = $locked->payable;

            if ($payable instanceof GatewayPayable) {
                $payable->gatewayPaid($locked);
            }

            ActivityLogger::log(null, 'gateway_transaction.paid', $locked, [
                'gateway' => $locked->gateway,
                'purpose' => $locked->purpose->value,
                'amount' => (string) $locked->amount,
            ]);

            return $locked;
        });
    }
}
