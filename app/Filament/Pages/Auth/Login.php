<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $user = Filament::auth()->user();
            if ($user instanceof User && $user->isStudentOnly()) {
                throw new HttpResponseException(new RedirectResponse($user->postLoginUrl()));
            }

            parent::mount();

            return;
        }

        throw new HttpResponseException(new RedirectResponse(route('login')));
    }

    public function authenticate(): ?LoginResponse
    {
        throw new HttpResponseException(new RedirectResponse(route('login')));
    }

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
}
