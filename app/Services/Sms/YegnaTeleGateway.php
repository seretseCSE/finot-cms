<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YegnaTeleGateway implements SmsGateway
{
    protected string $baseUrl;

    protected string $apiKey;

    protected string $senderId;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('finot.sms.base_url', ''), '/');
        $this->apiKey = config('finot.sms.api_key', '');
        $this->senderId = config('finot.sms.sender_id', '');
        $this->timeout = (int) config('finot.sms.timeout', 15);
    }

    public function send(string $phone, string $message): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('YegnaTele SMS: gateway not configured, skipping send', compact('phone'));

            return false;
        }

        $phone = $this->normalizePhone($phone);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->headers())
                ->post("{$this->baseUrl}/api/sms/send", [
                    'to' => $phone,
                    'message' => $message,
                    'sender_id' => $this->senderId,
                ]);

            if ($response->successful()) {
                Log::info('YegnaTele SMS sent', [
                    'phone' => $this->maskPhone($phone),
                    'status' => $response->status(),
                ]);

                return true;
            }

            Log::error('YegnaTele SMS failed', [
                'phone' => $this->maskPhone($phone),
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (ConnectionException $e) {
            Log::error('YegnaTele SMS connection failed', [
                'phone' => $this->maskPhone($phone),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendBulk(array $phones, string $message): int
    {
        if (! $this->isConfigured()) {
            Log::warning('YegnaTele SMS: gateway not configured, skipping bulk send');

            return 0;
        }

        $sent = 0;

        foreach (array_chunk($phones, 50) as $batch) {
            $normalized = array_map([$this, 'normalizePhone'], $batch);

            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders($this->headers())
                    ->post("{$this->baseUrl}/api/sms/send-bulk", [
                        'recipients' => $normalized,
                        'message' => $message,
                        'sender_id' => $this->senderId,
                    ]);

                if ($response->successful()) {
                    $sent += count($batch);
                } else {
                    Log::error('YegnaTele bulk SMS failed', [
                        'batch_size' => count($batch),
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }
            } catch (ConnectionException $e) {
                Log::error('YegnaTele bulk SMS connection failed', [
                    'batch_size' => count($batch),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    protected function headers(): array
    {
        return [
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Strip the +251 prefix and re-add as 251 (no plus) for the gateway.
     */
    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }

        if (str_starts_with($phone, '0')) {
            $phone = '251' . substr($phone, 1);
        }

        if (! str_starts_with($phone, '251')) {
            $phone = '251' . $phone;
        }

        return $phone;
    }

    protected function maskPhone(string $phone): string
    {
        return substr($phone, 0, 5) . '****' . substr($phone, -2);
    }

    protected function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '';
    }
}
