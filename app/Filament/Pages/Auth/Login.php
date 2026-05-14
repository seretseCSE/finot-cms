<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label('Phone Number')
            ->placeholder('912345678')
            ->required()
            ->autocomplete('tel')
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1])
            ->helperText('Enter 9 digits after '.config('finot.phone_prefix', '+251'))
            ->tel()
            ->prefix(config('finot.phone_prefix', '+251'))
            ->regex('/^[0-9]{9}$/')
            ->maxLength(9);
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (\DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();
        $credentials = $this->getCredentialsFromFormData($data);

        /** @var \Illuminate\Auth\SessionGuard $authGuard */
        $authGuard = Filament::auth();

        if (! $authGuard->attemptWhen($credentials, function (Authenticatable $user): bool {
            if (! ($user instanceof FilamentUser)) {
                return true;
            }

            return $user->canAccessPanel(Filament::getCurrentOrDefaultPanel());
        }, $data['remember'] ?? false)) {
            $this->throwFailureValidationException();
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $phone = $data['phone'] ?? null;

        return [
            'phone' => $phone ? config('finot.phone_prefix', '+251').$phone : null,
            'password' => $data['password'] ?? null,
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.phone' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }

    public function mount(): void
    {
        parent::mount();

        // Check for session expired message
        if (session('session_expired')) {
            $this->notify('warning', session('session_expired'));
        }
    }
}
