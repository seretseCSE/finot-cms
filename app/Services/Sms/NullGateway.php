<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

class NullGateway implements SmsGateway
{
    public function send(string $phone, string $message): bool
    {
        Log::debug('NullGateway: SMS not sent (no provider configured)', compact('phone'));

        return true;
    }

    public function sendBulk(array $phones, string $message): int
    {
        Log::debug('NullGateway: bulk SMS not sent (no provider configured)', [
            'count' => count($phones),
        ]);

        return count($phones);
    }
}
