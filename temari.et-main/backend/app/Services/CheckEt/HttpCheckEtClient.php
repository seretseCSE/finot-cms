<?php

namespace App\Services\CheckEt;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * check.et REST client (docs.check.et). Business outcomes (not found,
 * duplicate) come back as normal results; transport/auth/quota problems
 * become `unavailable` so the caller can park the claim for staff instead
 * of bouncing the parent.
 */
class HttpCheckEtClient implements CheckEtClient
{
    public function verify(array $payload): CheckEtResult
    {
        $key = (string) config('services.check_et.key');

        if (! config('services.check_et.enabled') || $key === '') {
            Log::info('check.et verification skipped (disabled or key missing).');

            return CheckEtResult::unavailable('Verification service is not configured.');
        }

        try {
            $request = Http::withToken($key)
                ->acceptJson()
                ->timeout(30);

            $file = $payload['receipt_file'] ?? null;

            if ($file instanceof UploadedFile) {
                $request = $request->attach(
                    'receipt_file',
                    fopen($file->getRealPath(), 'r'),
                    $file->getClientOriginalName(),
                );
            }

            $body = array_filter([
                'bank' => $payload['bank'] ?? null,
                'transaction_number' => $payload['transaction_number'] ?? null,
                'account_number' => $payload['account_number'] ?? null,
                'receipt_url' => $payload['receipt_url'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');

            $url = rtrim((string) config('services.check_et.base_url'), '/').'/verify';

            $response = $file instanceof UploadedFile
                ? $request->post($url, $body)
                : $request->asJson()->post($url, $body);

            // 404 is a real business answer ("transaction not found").
            if ($response->successful() || $response->status() === 404) {
                return CheckEtResult::fromResponse((array) $response->json());
            }

            Log::warning('check.et verification errored.', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            return CheckEtResult::unavailable(
                $response->status() === 402
                    ? 'Verification quota exhausted.'
                    : 'Verification service returned an error.',
            );
        } catch (Throwable $e) {
            Log::warning('check.et verification unreachable.', ['error' => $e->getMessage()]);

            return CheckEtResult::unavailable('Verification service is unreachable.');
        }
    }
}
