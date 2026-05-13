<?php

namespace App\Filament\Pages\Auth;

use App\Rules\PasswordHistoryRule;
use Filament\Schemas\Schema;
use App\Rules\PasswordStrengthRule;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class ChangeInitialPassword extends Page
{
    protected static ?string $title = 'Change Password';

    protected static ?string $slug = 'change-password';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
                TextInput::make('current_password')
                    ->label('Current Password')
                    ->password()
                    ->required()
                    ->autocomplete('current-password')
                    ->helperText('Enter your current password to continue'),

                TextInput::make('new_password')
                    ->label('New Password')
                    ->password()
                    ->required()
                    ->autocomplete('new-password')
                    ->rules([
                        new PasswordStrengthRule(),
                        new PasswordHistoryRule(Auth::user(), 3),
                    ])
                    ->helperText('Password must be at least 8 characters with uppercase, lowercase, and numbers'),

                TextInput::make('new_password_confirmation')
                    ->label('Confirm New Password')
                    ->password()
                    ->required()
                    ->autocomplete('new-password')
                    ->same('new_password')
                    ->helperText('Re-enter your new password to confirm'),
            ])
            ->statePath('data');
    }

    public function changePassword(): void
    {
        $data = $this->form->getState();

        $user = Auth::user();

        // Verify current password
        if (!Hash::check($data['current_password'], $user->password)) {
            $this->addError('current_password', 'Current password is incorrect.');
            return;
        }

        // Update password with history tracking
        $user->updatePassword($data['new_password'], 3);

        // Mark temporary password as changed
        $user->update(['temp_password_changed' => true]);

        // Redirect to intended page or dashboard
        $intendedUrl = session()->pull('url.intended', route('filament.admin.pages.dashboard'));

        $this->redirect($intendedUrl);
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Never show in navigation - this is a forced flow page
        return false;
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
