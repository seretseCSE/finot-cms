<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Send a push notification to a specific user.
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): bool
    {
        $subscriptions = PushSubscription::query()
            ->where('user_id', $userId)
            ->get();

        if ($subscriptions->isEmpty()) {
            return false;
        }

        $payload = json_encode([
            'notification' => [
                'title' => $title,
                'body' => $body,
                'icon' => asset('storage/logo.png'),
                'badge' => asset('storage/logo.png'),
                'data' => $data,
            ],
        ]);

        foreach ($subscriptions as $subscription) {
            $this->sendPush($subscription, $payload);
        }

        return true;
    }

    /**
     * Send a push notification to multiple users.
     *
     * @param  array<int>  $userIds
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): int
    {
        $subscriptions = PushSubscription::query()
            ->whereIn('user_id', $userIds)
            ->get();

        $payload = json_encode([
            'notification' => [
                'title' => $title,
                'body' => $body,
                'icon' => asset('storage/logo.png'),
                'badge' => asset('storage/logo.png'),
                'data' => $data,
            ],
        ]);

        $sent = 0;
        foreach ($subscriptions as $subscription) {
            if ($this->sendPush($subscription, $payload)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Send push notification using Web Push protocol.
     */
    protected function sendPush(PushSubscription $subscription, string $payload): bool
    {
        try {
            // Basic VAPID-less push for demonstration.
            // In production, use minishlink/web-push with VAPID keys.
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'TTL' => '60',
            ])->post($subscription->endpoint, [
                'payload' => $payload,
            ]);

            if ($response->status() === 410 || $response->status() === 404) {
                // Subscription expired or invalid
                $subscription->delete();

                return false;
            }

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Push notification failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Subscribe a user to push notifications.
     */
    public function subscribe(int $userId, string $endpoint, string $p256dh, string $authKey): PushSubscription
    {
        // Remove existing subscription with same endpoint
        PushSubscription::query()
            ->where('endpoint', $endpoint)
            ->delete();

        return PushSubscription::create([
            'user_id' => $userId,
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth_key' => $authKey,
        ]);
    }

    /**
     * Unsubscribe a user from push notifications.
     */
    public function unsubscribe(int $userId, string $endpoint): bool
    {
        return PushSubscription::query()
            ->where('user_id', $userId)
            ->where('endpoint', $endpoint)
            ->delete() > 0;
    }
}
