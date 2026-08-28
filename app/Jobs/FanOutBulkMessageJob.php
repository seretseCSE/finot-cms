<?php

namespace App\Jobs;

use App\Enums\BulkMessageStatus;
use App\Enums\MessageCategoryKey;
use App\Models\BulkMessage;
use App\Models\BulkMessageRecipient;
use App\Models\User;
use App\Services\Messages\RecipientResolver;
use App\Services\Notifications\Notifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FanOutBulkMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $messageId)
    {
    }

    public function handle(RecipientResolver $resolver, Notifier $notifier): void
    {
        $message = BulkMessage::query()->with('category')->findOrFail($this->messageId);
        $sender = $message->sender;

        if (! app()->environment('testing') && $this->inQuietHours() && ! $message->quiet_hours_bypassed && $message->category?->key !== MessageCategoryKey::Emergency->value) {
            $message->update([
                'status' => BulkMessageStatus::Queued,
                'scheduled_at' => now('Africa/Addis_Ababa')->startOfDay()->addHours(7)->utc(),
            ]);
            $this->release(now('Africa/Addis_Ababa')->startOfDay()->addHours(7)->diffInSeconds(now()));

            return;
        }

        $message->update(['status' => BulkMessageStatus::Sending]);

        $members = $resolver->resolve($sender, $message->audience ?? []);
        $event = $message->category?->key === MessageCategoryKey::Emergency->value
            ? 'messages.emergency'
            : 'messages.broadcast';

        foreach ($members as $member) {
            $user = User::query()->where('member_id', $member->id)->first();

            BulkMessageRecipient::query()->updateOrCreate(
                [
                    'bulk_message_id' => $message->id,
                    'member_id' => $member->id,
                    'channel' => 'in_app',
                ],
                [
                    'user_id' => $user?->id,
                    'status' => $user ? 'sent' : 'skipped',
                    'sent_at' => $user ? now() : null,
                ]
            );

            if ($user) {
                $notifier->toUser($user, $event, [
                    'body' => $message->body,
                    'sender' => $sender->name,
                ]);
            }
        }

        $message->update([
            'status' => BulkMessageStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    protected function inQuietHours(): bool
    {
        $hour = now('Africa/Addis_Ababa')->hour;

        return $hour >= 21 || $hour < 7;
    }
}
