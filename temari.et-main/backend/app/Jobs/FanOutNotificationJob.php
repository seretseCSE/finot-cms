<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Notify\Notifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued leg of the Notifier for large audiences (a published timetable, an
 * assignment to five sections): user ids are chunked and delivered off the
 * request cycle so a 10k-recipient event never blocks a controller. Only
 * queue-safe options travel (no closures — Notifier::queueableOptions).
 */
class FanOutNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    /**
     * @param  list<int>  $userIds
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public array $userIds,
        public string $event,
        public array $data,
        public array $options = [],
    ) {}

    public function handle(Notifier $notifier): void
    {
        User::query()
            ->whereIn('id', $this->userIds)
            ->where('status', 'active')
            ->chunkById(200, function ($users) use ($notifier): void {
                foreach ($users as $user) {
                    $notifier->deliver($user, $this->event, $this->data, $this->options);
                }
            });
    }
}
