<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\PushNotificationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class SendNotification extends Page
{
    protected static ?string $title = 'Send Notification';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-bell';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Notifications';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public function getView(): string
    {
        return 'filament.pages.send-notification';
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['admin', 'superadmin']);
    }

    public ?string $recipient_type = 'all';

    public ?array $recipient_ids = [];

    public ?string $title_input = '';

    public ?string $body = '';

    public bool $send_push = false;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')
                ->label('Send Notification')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->action('sendNotification')
                ->requiresConfirmation()
                ->modalHeading('Send Notification')
                ->modalDescription('Are you sure you want to send this notification to the selected recipients?')
                ->modalSubmitActionLabel('Send'),
        ];
    }

    public function sendNotification(): void
    {
        $this->validate([
            'title_input' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:1000'],
            'recipient_type' => ['required', 'in:all,roles,users'],
            'recipient_ids' => ['required_if:recipient_type,roles,users', 'array'],
        ]);

        $recipients = $this->resolveRecipients();

        if ($recipients->isEmpty()) {
            Notification::make()
                ->title('No Recipients')
                ->body('No users match the selected criteria.')
                ->warning()
                ->send();

            return;
        }

        foreach ($recipients as $user) {
            Notification::make()
                ->title($this->title_input)
                ->body($this->body)
                ->sendToDatabase($user);
        }

        if ($this->send_push) {
            $pushService = new PushNotificationService();
            $pushService->sendToUsers(
                $recipients->pluck('id')->toArray(),
                $this->title_input,
                $this->body
            );
        }

        Notification::make()
            ->title('Notification Sent')
            ->body("Notification sent to {$recipients->count()} user(s).")
            ->success()
            ->send();

        $this->reset(['title_input', 'body', 'recipient_ids']);
    }

    protected function resolveRecipients()
    {
        return match ($this->recipient_type) {
            'all' => User::query()->where('is_active', true)->get(),
            'roles' => User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', $this->recipient_ids))
                ->get(),
            'users' => User::query()
                ->where('is_active', true)
                ->whereIn('id', $this->recipient_ids)
                ->get(),
            default => collect(),
        };
    }
}
