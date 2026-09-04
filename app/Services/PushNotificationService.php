<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Services\Contracts\PushNotificationServiceInterface;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService implements PushNotificationServiceInterface
{
    public function sendToUser(int $userId, string $title, string $body, array $data = []): bool
    {
        $subscriptions = PushSubscription::query()
            ->where('user_id', $userId)
            ->get();

        if ($subscriptions->isEmpty()) {
            return false;
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => asset('images/logo2.png'),
            'badge' => asset('images/logo2.png'),
            'data' => $data,
        ]);

        foreach ($subscriptions as $subscription) {
            $this->sendPush($subscription, $payload);
        }

        return true;
    }

    /**
     * @param  array<int>  $userIds
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): int
    {
        if ($userIds === []) {
            return 0;
        }

        $subscriptions = PushSubscription::query()
            ->whereIn('user_id', $userIds)
            ->get();

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => asset('images/logo2.png'),
            'badge' => asset('images/logo2.png'),
            'data' => $data,
        ]);

        $sent = 0;
        foreach ($subscriptions as $subscription) {
            if ($this->sendPush($subscription, $payload)) {
                $sent++;
            }
        }

        return $sent;
    }

    protected function sendPush(PushSubscription $subscription, string $payload): bool
    {
        $publicKey = config('finot.vapid.public_key');
        $privateKey = config('finot.vapid.private_key');

        if (! $publicKey || ! $privateKey) {
            Log::debug('VAPID keys not configured; skipping web push');

            return false;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('finot.vapid.subject', config('app.url')),
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ]);

            $report = $webPush->sendOneNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->p256dh,
                    'authToken' => $subscription->auth_key,
                ]),
                $payload
            );

            if ($report->isSubscriptionExpired()) {
                $subscription->delete();

                return false;
            }

            return $report->isSuccess();
        } catch (\Throwable $e) {
            ExceptionHandlerService::handleServiceException($e, 'PushNotificationService');
            Log::error('Push notification failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function subscribe(int $userId, string $endpoint, string $p256dh, string $authKey): PushSubscription
    {
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

    public function unsubscribe(int $userId, string $endpoint): bool
    {
        return PushSubscription::query()
            ->where('user_id', $userId)
            ->where('endpoint', $endpoint)
            ->delete() > 0;
    }
}
