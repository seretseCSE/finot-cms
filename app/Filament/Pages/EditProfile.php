<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Hash;

class EditProfile extends Page
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.edit-profile';

    protected static ?string $title = 'Edit Profile';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'name' => auth()->user()->name,
            'email' => auth()->user()->email,
            'phone' => auth()->user()->phone,
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $schema->components([
                // Personal Information Section
                Section::make('Personal Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255)
                            ->autofocus(),

                        TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        \Filament\Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->nullable()
                            ->prefix(config('finot.phone_prefix', '+251'))
                            ->regex('/^[0-9]{9}$/')
                            ->placeholder('912345678')
                            ->helperText('Enter 9 digits after '.config('finot.phone_prefix', '+251'))
                            ->maxLength(9)
                            ->formatStateUsing(function ($state) {
                                $prefix = config('finot.phone_prefix', '+251');

                                return $state ? preg_replace('/^(' . preg_quote($prefix, '/') . '|0)/', '', $state) : null;
                            }),
                    ])
                    ->columns(2),

                // Password Change Section
                Section::make('Change Password')
                    ->description('Leave empty if you don\'t want to change your password')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Current Password')
                            ->password()
                            ->requiredWith('password')
                            ->currentPassword()
                            ->label('Current Password'),

                        TextInput::make('password')
                            ->label('New Password')
                            ->password()
                            ->confirmed()
                            ->nullable()
                            ->minLength(8)
                            ->helperText('Must be at least 8 characters'),

                        TextInput::make('password_confirmation')
                            ->label('Confirm New Password')
                            ->password()
                            ->dehydrated(false)
                            ->requiredWith('password'),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Update basic profile information
        auth()->user()->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => isset($data['phone']) && $data['phone'] ? config('finot.phone_prefix', '+251').$data['phone'] : null,
        ]);

        // Update password only if provided
        if (! empty($data['password'])) {
            auth()->user()->update([
                'password' => Hash::make($data['password']),
            ]);
        }

        // Show success notification
        \Filament\Notifications\Notification::make()
            ->title('Profile Updated')
            ->body('Your profile has been successfully updated.')
            ->success()
            ->send();

        // Refresh form data
        $this->form->fill([
            'name' => auth()->user()->name,
            'email' => auth()->user()->email,
            'phone' => auth()->user()->phone,
        ]);
    }

    public function getTitle(): string
    {
        return 'Edit Profile';
    }

    public function getHeading(): string
    {
        return 'My Profile';
    }

    public static function getNavigationIcon(): ?string
    {
        return null; // Hide from navigation
    }

    public static function getNavigationLabel(): string
    {
        return 'Edit Profile';
    }

    public static function getNavigationGroup(): ?string
    {
        return null; // Show in main navigation, not in a group
    }

    public static function getNavigationVisibility(): bool
    {
        return false; // Completely hide from navigation
    }

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->check();
    }

    public static function getLabel(): string
    {
        return 'My Profile';
    }
}
