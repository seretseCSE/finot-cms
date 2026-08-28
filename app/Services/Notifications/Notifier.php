<?php

namespace App\Services\Notifications;

use App\Models\InAppNotification;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class Notifier
{
    public const CATALOG = [
        'academics.marklist_submitted' => ['category' => 'approvals', 'importance' => 'important', 'sms' => false],
        'academics.marklist_decided' => ['category' => 'academics', 'importance' => 'important', 'sms' => false],
        'academics.marklist_assist' => ['category' => 'academics', 'importance' => 'important', 'sms' => false],
        'academics.term_closed_incomplete' => ['category' => 'academics', 'importance' => 'info', 'sms' => false],
        'movement.withdrawal' => ['category' => 'movement', 'importance' => 'important', 'sms' => false],
        'messages.broadcast' => ['category' => 'chat', 'importance' => 'info', 'sms' => false],
        'messages.emergency' => ['category' => 'chat', 'importance' => 'critical', 'sms' => false],
        'imports.committed' => ['category' => 'system', 'importance' => 'info', 'sms' => false],
        'bookings.requested' => ['category' => 'system', 'importance' => 'info', 'sms' => false],
        'exports.ready' => ['category' => 'system', 'importance' => 'info', 'sms' => false],
    ];

    public static function smsAllowed(string $event): bool
    {
        $meta = self::CATALOG[$event] ?? null;
        if (! $meta || ! ($meta['sms'] ?? false)) {
            return false;
        }

        $whitelist = PlatformSetting::getValue('notifications.sms_whitelist', []);

        return is_array($whitelist) && $whitelist !== [] && in_array($event, $whitelist, true);
    }

    public function toUser(User $user, string $event, array $data = [], ?string $link = null, ?string $dedupeKey = null): void
    {
        if (! isset(self::CATALOG[$event])) {
            throw new \InvalidArgumentException("Unknown notification event [{$event}]");
        }

        $write = function () use ($user, $event, $data, $link, $dedupeKey): void {
            $meta = self::CATALOG[$event];
            if ($dedupeKey) {
                $existing = InAppNotification::query()
                    ->where('user_id', $user->id)
                    ->where('dedupe_key', $dedupeKey)
                    ->whereNull('read_at')
                    ->first();
                if ($existing) {
                    $payload = $existing->data ?? [];
                    $payload['count'] = ($payload['count'] ?? 1) + 1;
                    $existing->update(['data' => array_merge($payload, $data)]);

                    return;
                }
            }

            InAppNotification::query()->create([
                'user_id' => $user->id,
                'event' => $event,
                'category' => $meta['category'],
                'data' => $data,
                'link' => $link,
                'dedupe_key' => $dedupeKey,
            ]);

            if (self::smsAllowed($event)) {
                $this->sendSmsNoop($user, $event, $data);
            }
        };

        if (app()->runningUnitTests()) {
            $write();
        } else {
            try {
                DB::afterCommit($write);
            } catch (\Throwable) {
                $write();
            }
        }
    }

    /**
     * @param  iterable<User|int>  $users
     */
    public function toUsers(iterable $users, string $event, array $data = [], ?string $link = null, ?string $dedupeKey = null): void
    {
        $ids = collect($users)->map(function ($user) {
            return $user instanceof User ? $user->id : (int) $user;
        })->filter()->unique()->values();

        if ($ids->count() > 50) {
            \App\Jobs\FanOutNotificationJob::dispatch($ids->all(), $event, $data, $link, $dedupeKey);

            return;
        }

        User::query()->whereIn('id', $ids)->each(function (User $user) use ($event, $data, $link, $dedupeKey): void {
            $this->toUser($user, $event, $data, $link, $dedupeKey);
        });
    }

    protected function sendSmsNoop(User $user, string $event, array $data): void
    {
        // Phase 1: no SMS provider.
    }
}
