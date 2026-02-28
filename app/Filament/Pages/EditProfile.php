<?php

namespace App\Filament\Pages;

use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;

class EditProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'Edit Profile';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'name' => Auth::user()->name,
            'email' => Auth::user()->email,
            'phone' => Auth::user()->phone,
            'language_preference' => Auth::user()->language_preference ?? 'am',
        ]);
    }

    public function getView(): string
    {
        return 'filament.pages.edit-profile';
    }

    public function getTitle(): string
    {
        return 'Edit Profile';
    }

    public function getHeading(): string
    {
        return 'My Profile';
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    // Remove from navigation panel
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function form(\Filament\Schemas\Schema $form): \Filament\Schemas\Schema
    {
        return $form
            ->schema([
                // Personal Information Header
                \Filament\Forms\Components\Placeholder::make('personal_info_header')
                    ->label('Personal Information')
                    ->content('Update your personal information and preferences')
                    ->columnSpanFull(),

                TextInput::make('name')
                    ->label('Full Name')
                    ->required()
                    ->maxLength(255)
                    ->autofocus()
                    ->placeholder('Enter your full name'),

                TextInput::make('email')
                    ->label('Email Address')
                    ->email()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                        $rule->where('id', '!=', Auth::id());
                    })
                    ->placeholder('your.email@example.com'),

                TextInput::make('phone')
                    ->label('Phone Number')
                    ->tel()
                    ->required()
                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                        $rule->where('id', '!=', Auth::id());
                    })
                    ->placeholder('+251 9X XXX XXXX')
                    ->regex('/^\+251[79]\d{8}$/')
                    ->helperText('Format: +251 followed by 9 digits (Ethiopian numbers only)'),

                Select::make('language_preference')
                    ->label('Preferred Language')
                    ->options([
                        'en' => 'English',
                        'am' => 'አማርኛ (Amharic)',
                    ])
                    ->required(),

                // Change Password Header
                \Filament\Forms\Components\Placeholder::make('password_header')
                    ->label('Change Password')
                    ->content('Leave blank if you don\'t want to change your password')
                    ->columnSpanFull(),

                TextInput::make('current_password')
                    ->label('Current Password')
                    ->password()
                    ->revealable()
                    ->required(fn ($get) => filled($get('new_password')))
                    ->currentPassword()
                    ->helperText('Enter your current password to confirm the change'),

                TextInput::make('new_password')
                    ->label('New Password')
                    ->password()
                    ->revealable()
                    ->confirmed()
                    ->required(fn ($get) => filled($get('current_password')))
                    ->rules([
                        Password::min(8)
                            ->mixedCase()
                            ->numbers()
                            ->uncompromised(3),
                    ])
                    ->helperText('Minimum 8 characters, must include uppercase, lowercase, and numbers'),

                TextInput::make('new_password_confirmation')
                    ->label('Confirm New Password')
                    ->password()
                    ->revealable()
                    ->required(fn ($get) => filled($get('new_password')))
                    ->dehydrated(false),

                // Hidden form field (customizable as needed)
                Hidden::make('hidden_field'),
            ])
            ->columns(2)
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Changes')
                ->action('save')
                ->icon('heroicon-o-check')
                ->color('success'),

            Action::make('cancel')
                ->label('Cancel')
                ->url('/admin')
                ->icon('heroicon-o-x-mark')
                ->color('gray'),
        ];
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();
            $user = Auth::user();

            // Update basic information
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->phone = $data['phone'];
            $user->language_preference = $data['language_preference'];

            // Update password if provided
            $passwordChanged = false;
            if (!empty($data['new_password'])) {
                $user->password = Hash::make($data['new_password']);
                $user->temp_password_changed = true;
                $passwordChanged = true;
            }

            $user->save();

            // If password was changed, re-authenticate to keep session active
            if ($passwordChanged) {
                Auth::login($user);
            }

            // Profile updated successfully
            Notification::make()
                ->title('Profile Updated')
                ->body('Your profile has been updated successfully.')
                ->success()
                ->send();

            // Redirect to dashboard
            $this->redirect('/admin');

        } catch (\Exception $e) {
            Notification::make()
                ->title('Update Failed')
                ->body('Failed to update profile: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}

