<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Rules\PasswordHistoryRule;
use App\Rules\PasswordStrengthRule;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ChangeInitialPassword extends Page
{
    protected static ?string $title = 'Change Password';

    protected static ?string $slug = 'change-password';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament-panels::pages.simple';

    protected static string $layout = 'filament-panels::components.layout.simple';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function hasLogo(): bool
    {
        return true;
    }

    public function mount(): void
    {
        throw new HttpResponseException(new RedirectResponse(route('change-initial-password')));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('current_password')
                    ->label('Current Password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->currentPassword()
                    ->autocomplete('current-password')
                    ->helperText('Enter your current password to continue'),

                TextInput::make('new_password')
                    ->label('New Password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->autocomplete('new-password')
                    ->confirmed()
                    ->rules([
                        new PasswordStrengthRule(),
                        new PasswordHistoryRule(Auth::user(), 3),
                    ])
                    ->helperText('At least 8 characters, with uppercase, lowercase, and a number. Must be different from your current password.'),

                TextInput::make('new_password_confirmation')
                    ->label('Confirm New Password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->autocomplete('new-password')
                    ->dehydrated(false)
                    ->helperText('Re-enter your new password to confirm'),
            ])
            ->statePath('data');
    }

    public function changePassword(): void
    {
        $data = $this->form->getState();

        $user = Auth::user();

        if (! $user instanceof User) {
            $this->redirect(route('login'), navigate: false);

            return;
        }

        $user->updatePassword($data['new_password'], 3);
        Auth::login($user->fresh());
        $user->persistAuthPasswordHashInSession();

        if (! $user->temp_password_changed) {
            throw ValidationException::withMessages([
                'data.new_password' => 'Password could not be updated. Please try again.',
            ]);
        }

        session()->forget('url.intended');

        Notification::make()
            ->title('Password changed')
            ->body('Your password has been updated. You can now use the admin panel.')
            ->success()
            ->send();

        $this->redirect(Filament::getUrl(), navigate: false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    protected function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('changePassword')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment(Alignment::Center)
                    ->fullWidth()
                    ->key('form-actions'),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('changePassword')
                ->label('Change Password')
                ->submit('changePassword')
                ->color('primary'),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return 'Change Your Password';
    }

    public function getHeading(): string
    {
        return 'Welcome! Please Change Your Password';
    }

    public function getSubheading(): string
    {
        return 'For your security, you must change your temporary password before continuing to the admin panel.';
    }
}
