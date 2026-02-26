<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        Log::info('Login page accessed', [
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'method' => Request::method(),
        ]);

        return TextInput::make('phone')
            ->label('Phone Number')
            ->placeholder('+251XXXXXXXXX')
            ->required()
            ->autocomplete('tel')
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1])
            ->helperText('ስልክ ቁጥር')
            ->tel();
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        Log::info('Processing login credentials', [
            'phone_provided' => isset($data['phone']),
            'data_phone_provided' => isset($data['data']['phone']),
            'password_provided' => isset($data['password']),
            'data_password_provided' => isset($data['data']['password']),
        ]);

        $credentials = [
            'phone' => $data['phone'] ?? $data['data']['phone'] ?? null,
            'password' => $data['password'] ?? $data['data']['password'] ?? null,
        ];

        Log::info('Credentials extracted', [
            'phone' => $credentials['phone'],
            'has_password' => !empty($credentials['password']),
        ]);

        return $credentials;
    }

    protected function throwFailureValidationException(): never
    {
        Log::warning('Login validation failed', [
            'ip' => Request::ip(),
        ]);

        throw ValidationException::withMessages([
            'phone' => __('filament-panels::auth/pages/login/messages.failed'),
            'data.phone' => __('filament-panels::auth/pages/login/messages.failed'),
        ]);
    }

    public function mount(): void
    {
        Log::info('Login page mounting', [
            'session_expired' => session('session_expired'),
        ]);

        parent::mount();

        // Check for session expired message
        if (session('session_expired')) {
            $this->notify('warning', session('session_expired'));
        }
    }
}

