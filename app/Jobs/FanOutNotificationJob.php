<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FanOutNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public array $userIds,
        public string $event,
        public array $data = [],
        public ?string $link = null,
        public ?string $dedupeKey = null,
    ) {
    }

    public function handle(Notifier $notifier): void
    {
        foreach (array_chunk($this->userIds, 200) as $chunk) {
            User::query()->whereIn('id', $chunk)->each(function (User $user) use ($notifier): void {
                $notifier->toUser($user, $this->event, $this->data, $this->link, $this->dedupeKey);
            });
        }
    }
}
