<?php

namespace App\Contracts;

interface SmsGateway
{
    /**
     * Send a single SMS message.
     *
     * @return bool Whether the message was accepted by the gateway.
     */
    public function send(string $phone, string $message): bool;

    /**
     * Send the same SMS to multiple recipients.
     *
     * @param  array<string>  $phones
     * @return int Number of messages accepted.
     */
    public function sendBulk(array $phones, string $message): int;
}
