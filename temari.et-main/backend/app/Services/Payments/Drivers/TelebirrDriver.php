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
 * Telebirr direct — Ethio Telecom's Fabric payment gateway (H5 C2B web
 * checkout): apply a fabric token, create a signed pre-order, then hand the
 * payer to the Telebirr web paygate. Requests are RSA-SHA256 signed with our
 * merchant private key. Webhook notifies are NEVER trusted alone — the
 * manager re-verifies via queryOrder before settling, so a forged notify can
 * only trigger a verification, never a fulfilment.
 */
class TelebirrDriver implements GatewayDriver
{
    public function initiate(GatewayTransaction $transaction, string $returnUrl): string
    {
        $config = $this->config();

        $biz = [
            'notify_url' => route('webhooks.payments', ['gateway' => 'telebirr']),
            'redirect_url' => $returnUrl,
            'appid' => $config['merchant_app_id'],
            'merch_code' => $config['short_code'],
            'merch_order_id' => $transaction->tx_ref,
            'trade_type' => 'Checkout',
            'title' => mb_substr($transaction->payable?->gatewayDescription() ?? 'Temari.et payment', 0, 64),
            'total_amount' => (string) $transaction->amount,
            'trans_currency' => $transaction->currency,
            'timeout_express' => '120m',
            'payee_identifier' => $config['short_code'],
            'payee_identifier_type' => '04',
            'payee_type' => '5000',
        ];

        $payload = $this->signedRequest('payment.preorder', $biz, $config);
        $response = $this->http($config)->post('/payment/v1/merchant/preOrder', $payload);
        $prepayId = $response->json('biz_content.prepay_id');

        if (! $response->successful() || ! is_string($prepayId)) {
            throw new GatewayUnavailableException($response->json('msg') ?? 'Telebirr could not start this payment.');
        }

        // The paygate URL carries the SAME signed param set plus the prepay id.
        $raw = [
            'appid' => $config['merchant_app_id'],
            'merch_code' => $config['short_code'],
            'nonce_str' => $payload['nonce_str'],
            'prepay_id' => $prepayId,
            'timestamp' => $payload['timestamp'],
        ];
        $raw['sign'] = $this->sign($raw, $config['private_key']);
        $raw['sign_type'] = 'SHA256WithRSA';

        return rtrim((string) $config['web_base_url'], '/').'/payment/web/paygate?'.http_build_query([
            'appid' => $raw['appid'],
            'merch_code' => $raw['merch_code'],
            'nonce_str' => $raw['nonce_str'],
            'prepay_id' => $raw['prepay_id'],
            'timestamp' => $raw['timestamp'],
            'sign' => $raw['sign'],
            'sign_type' => $raw['sign_type'],
            'version' => '1.0',
            'trade_type' => 'Checkout',
            'language' => 'en',
        ]);
    }

    public function verify(GatewayTransaction $transaction): GatewayVerdict
    {
        $config = $this->config();

        $payload = $this->signedRequest('payment.queryorder', [
            'appid' => $config['merchant_app_id'],
            'merch_code' => $config['short_code'],
            'merch_order_id' => $transaction->tx_ref,
        ], $config);

        $response = $this->http($config)->post('/payment/v1/merchant/queryOrder', $payload);
        $biz = (array) ($response->json('biz_content') ?? []);
        $state = strtoupper((string) ($biz['order_status'] ?? $biz['trade_status'] ?? ''));

        return match (true) {
            in_array($state, ['COMPLETED', 'SUCCESS', 'PAY_SUCCESS'], true) => GatewayVerdict::paid($biz['payment_order_id'] ?? $biz['trade_no'] ?? null, $biz),
            in_array($state, ['FAILED', 'CANCELLED', 'CLOSED', 'EXPIRED'], true) => GatewayVerdict::failed($state, $biz),
            default => GatewayVerdict::pending($biz),
        };
    }

    public function webhookTxRef(Request $request): ?string
    {
        // The notify carries merch_order_id (our tx_ref). Its signature uses
        // Telebirr's public key; since the manager re-verifies via queryOrder
        // regardless, the notify only needs to point at a transaction we own.
        $ref = $request->json('merch_order_id')
            ?? data_get($request->json()->all(), 'biz_content.merch_order_id');

        return is_string($ref) ? $ref : null;
    }

    /**
     * Fabric envelope: token header + timestamp/nonce + RSA-SHA256 signature
     * over the flattened sorted params (biz_content keys inlined).
     *
     * @param  array<string, mixed>  $biz
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function signedRequest(string $method, array $biz, array $config): array
    {
        $payload = [
            'timestamp' => (string) now()->getTimestamp(),
            'nonce_str' => bin2hex(random_bytes(16)),
            'method' => $method,
            'version' => '1.0',
            'biz_content' => $biz,
        ];

        $payload['sign'] = $this->sign(array_merge(
            collect($payload)->except('biz_content')->all(),
            $biz,
        ), $config['private_key']);
        $payload['sign_type'] = 'SHA256WithRSA';

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function sign(array $params, string $privateKey): string
    {
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }
            $pairs[] = $key.'='.$value;
        }

        $key = openssl_pkey_get_private($this->pemify($privateKey));

        if ($key === false || ! openssl_sign(implode('&', $pairs), $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new GatewayUnavailableException('Telebirr signing key is invalid.');
        }

        return base64_encode($signature);
    }

    /** Accept both a full PEM and a bare base64 key in env. */
    private function pemify(string $key): string
    {
        if (str_contains($key, 'BEGIN')) {
            return str_replace('\n', "\n", $key);
        }

        return "-----BEGIN PRIVATE KEY-----\n".chunk_split($key, 64, "\n").'-----END PRIVATE KEY-----';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function http(array $config): PendingRequest
    {
        return Http::baseUrl((string) $config['base_url'])
            ->withHeaders(['X-APP-Key' => $config['fabric_app_id']])
            ->withToken($this->fabricToken($config))
            ->acceptJson()
            ->timeout(20);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function fabricToken(array $config): string
    {
        $response = Http::baseUrl((string) $config['base_url'])
            ->withHeaders(['X-APP-Key' => $config['fabric_app_id']])
            ->acceptJson()
            ->timeout(20)
            ->post('/payment/v1/token', ['appSecret' => $config['app_secret']]);

        $token = $response->json('token');

        if (! is_string($token)) {
            throw new GatewayUnavailableException('Telebirr token request failed.');
        }

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        $config = (array) config('services.telebirr');

        if (blank($config['fabric_app_id'] ?? null) || blank($config['private_key'] ?? null)) {
            throw new GatewayUnavailableException('Telebirr is not configured.');
        }

        return $config;
    }
}
