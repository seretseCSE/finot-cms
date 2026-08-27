<?php

namespace App\Services\Sms;

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends SMS through the Tiltek gateway. When sms.enabled is false (local default)
 * messages are logged instead of dispatched so development never sends real SMS.
 */
class TiltekSmsClient implements SmsClient
{
    /**
     * @param  string|list<string>  $to
     */
    public function send(string|array $to, string $body): void
    {
        $recipients = array_values(array_filter((array) $to));

        // The gateway 400s the WHOLE batch when any recipient is on a network
        // it cannot deliver to (Safaricom 07… while sms.allow_safaricom is off,
        // e.g. numbers stored before the gate existed) — drop those instead of
        // failing everyone's message.
        $undeliverable = array_values(array_filter(
            $recipients,
            fn (string $phone): bool => PhoneNumber::normalize($phone) === null
        ));

        if ($undeliverable !== []) {
            Log::warning('[SMS] skipping undeliverable recipients', ['to' => $undeliverable]);
            $recipients = array_values(array_diff($recipients, $undeliverable));
        }

        if ($recipients === []) {
            return;
        }

        if (! config('sms.enabled')) {
            Log::info('[SMS:logged] message not sent (sms.enabled=false)', [
                'to' => $recipients,
                'body' => $body,
            ]);

            return;
        }

        $accountId = (string) config('sms.account_id');

        Http::withBasicAuth($accountId, (string) config('sms.token'))
            ->acceptJson()
            ->asJson()
            ->post(rtrim((string) config('sms.base_url'), '/')."/api/v1/customer/{$accountId}", [
                'to' => $recipients,
                'body' => $body,
                'codeId' => config('sms.code_id'),
            ])
            ->throw();
    }
}
