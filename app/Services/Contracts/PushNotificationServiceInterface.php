<?php

namespace App\Services\Contracts;

use App\Models\PushSubscription;

interface PushNotificationServiceInterface
{
    /**
     * Send a push notification to a specific user.
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): bool;

    /**
     * Send a push notification to multiple users.
     *
     * @param  array<int>  $userIds
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): int;

    /**
     * Subscribe a user to push notifications.
     */
    public function subscribe(int $userId, string $endpoint, string $p256dh, string $authKey): PushSubscription;

    /**
     * Unsubscribe a user from push notifications.
     */
    public function unsubscribe(int $userId, string $endpoint): bool;
}
