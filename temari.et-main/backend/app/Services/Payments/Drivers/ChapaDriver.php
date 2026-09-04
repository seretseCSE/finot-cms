<?php

namespace App\Services\Payments\Drivers;

use App\Models\GatewayTransaction;
use App\Services\Payments\Contracts\GatewayDriver;
use App\Services\Payments\GatewayUnavailableException;
use App\Services\Payments\GatewayVerdict;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Chapa hosted checkout (developer.chapa.co). One integration carries cards,
 * Telebirr, CBE Birr, M-Pesa and the banks — our launch gateway. Webhooks
 * are HMAC-SHA256 signed with the dashboard webhook secret; we still
 * re-verify by tx_ref before settling (manager rule). Also our payout rail:
 * transfers() sends tutor payouts to banks and Telebirr wallets.
 */
class ChapaDriver implements GatewayDriver
{
    public function initiate(GatewayTransaction $transaction, string $returnUrl): string
    {
        $payer = $transaction->user;

        $response = $this->http()->post('/transaction/initialize', array_filter([
            'amount' => (string) $transaction->amount,
            'currency' => $transaction->currency,
            'tx_ref' => $transaction->tx_ref,
            'first_name' => $payer?->first_name,
            'last_name' => $payer?->father_name,
            'phone_number' => $payer?->phone ? '0'.$payer->phone : null,
            'return_url' => $returnUrl,
            'callback_url' => route('webhooks.payments', ['gateway' => 'chapa']),
            'customization' => [
                'title' => mb_substr('Temari.et', 0, 16),
                'description' => mb_substr($transaction->payable?->gatewayDescription() ?? 'Temari.et payment', 0, 100),
            ],
        ]));

        $checkout = $response->json('data.checkout_url');

        if (! $response->successful() || ! is_string($checkout)) {
            throw new GatewayUnavailableException($response->json('message') ?? 'Chapa could not start this payment.');
        }

        return $checkout;
    }

    public function verify(GatewayTransaction $transaction): GatewayVerdict
    {
        $response = $this->http()->get('/transaction/verify/'.$transaction->tx_ref);
        $data = $response->json('data') ?? [];

        return match ($response->json('data.status')) {
            'success' => GatewayVerdict::paid($data['reference'] ?? null, $data),
            'failed', 'cancelled', 'canceled' => GatewayVerdict::failed($response->json('message'), $data),
            default => GatewayVerdict::pending($data),
        };
    }

    public function webhookTxRef(Request $request): ?string
    {
        $secret = (string) config('services.chapa.webhook_secret');

        if ($secret === '') {
            return null; // no secret configured → webhooks are ignored, polling still settles
        }

        $signature = $request->header('Chapa-Signature') ?? $request->header('x-chapa-signature');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! is_string($signature) || ! hash_equals($expected, $signature)) {
            return null;
        }

        $ref = $request->json('tx_ref') ?? $request->json('trx_ref');

        return is_string($ref) ? $ref : null;
    }

    /**
     * Send money OUT (tutor payouts) to a bank account or Telebirr wallet.
     *
     * @return string the transfer's gateway reference
     *
     * @throws GatewayUnavailableException
     */
    public function transfer(string $reference, string $accountName, string $accountNumber, string $bankCode, string $amount): string
    {
        $response = $this->http()->post('/transfers', [
            'account_name' => $accountName,
            'account_number' => $accountNumber,
            'amount' => $amount,
            'currency' => 'ETB',
            'reference' => $reference,
            'bank_code' => $bankCode,
        ]);

        if (! $response->successful() || $response->json('status') !== 'success') {
            throw new GatewayUnavailableException($response->json('message') ?? 'Chapa transfer failed.');
        }

        return (string) ($response->json('data') ?? $reference);
    }

    /** Chapa's bank list (id + name + code) for the payout-account picker. */
    public function banks(): array
    {
        $response = $this->http()->get('/banks');

        return $response->successful() ? (array) $response->json('data') : [];
    }

    private function http(): PendingRequest
    {
        $secret = (string) config('services.chapa.secret_key');

        if ($secret === '') {
            throw new GatewayUnavailableException('Chapa is not configured.');
        }

        return Http::baseUrl((string) config('services.chapa.base_url'))
            ->withToken($secret)
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(20);
    }
}
