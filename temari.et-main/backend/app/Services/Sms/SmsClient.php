<?php

namespace App\Services\Sms;

interface SmsClient
{
    /**
     * Send an SMS message to one or more recipients.
     *
     * @param  string|list<string>  $to
     */
    public function send(string|array $to, string $body): void;
}
