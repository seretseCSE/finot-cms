<?php

namespace App\Filament\Actions;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Illuminate\Validation\ValidationException;

class FilamentPhoneLoginAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Sign in')
            ->action('authenticate')
            ->color('primary')
            ->submit('authenticate');
    }

    public function authenticate(array $data): ?LoginResponse
    {
        try {
            rate_limit(5);
        } catch (TooManyRequestsException $exception) {
            throw ValidationException::withMessages([
                'phone' => __('filament::login.messages.throttled', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => ceil($exception->secondsUntilAvailable / 60),
                ]),
            ]);
        }

        $credentials = [
            'phone' => $data['phone'],
            'password' => $data['password'],
        ];

        // Check if user exists and is active
        $user = \App\Models\User::where('phone', $data['phone'])->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'phone' => __('No account found with this phone number.'),
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'phone' => __('Your account has been deactivated. Please contact administrator.'),
            ]);
        }

        if ($user->isAccountLocked()) {
            throw ValidationException::withMessages([
                'phone' => $user->getLockStatusMessage(),
            ]);
        }

        if (!auth()->attempt($credentials, $data['remember'] ?? false)) {
            // Log failed login attempt
            $user->logFailedLogin('login_failed', [
                'reason' => 'invalid_credentials',
                'login_attempt' => $user->failed_login_attempts + 1,
            ]);
            
            // Increment failed login attempts
            $user->incrementFailedAttempts();

            throw ValidationException::withMessages([
                'phone' => __('filament::login.messages.failed'),
            ]);
        }

        // Reset failed login attempts on successful login
        if ($user->failed_login_attempts > 0) {
            // Log successful login after previous failures
            $user->logFailedLogin('login_success_after_failures', [
                'previous_failed_attempts' => $user->failed_login_attempts,
                'was_locked' => $user->is_locked,
            ]);
            
            $user->resetFailedAttempts();
        } else {
            // Log normal successful login
            $user->logFailedLogin('login_success', [
                'first_attempt' => true,
            ]);
        }

        return app(LoginResponse::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\TextInput::make('phone')
                    ->label('Phone Number / ስልክ ቁጥር')
                    ->placeholder('+251XXXXXXXXX')
                    ->required()
                    ->autocomplete('tel')
                    ->autofocus()
                    ->extraInputAttributes(['tabindex' => 1])
                    ->tel()
                    ->rules([new \App\Rules\EthiopianPhoneRule(), new \App\Rules\PasswordStrengthRule()]),

                \Filament\Forms\Components\TextInput::make('password')
                    ->label(__('filament::login.fields.password.label'))
                    ->hint(__('filament::login.fields.password.hint'))
                    ->password()
                    ->required()
                    ->autocomplete('current-password')
                    ->extraInputAttributes(['tabindex' => 2]),

                \Filament\Forms\Components\Checkbox::make('remember')
                    ->label(__('filament::login.fields.remember.label'))
                    ->extraInputAttributes(['tabindex' => 3]),
            ])
            ->statePath('data');
    }
}

