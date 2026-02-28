<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

class GlobalChurchSettings extends Page
{
    use WithFileUploads;

    protected static ?string $title = 'Global Church Settings';

    protected static ?int $navigationSort = 3;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public function getView(): string
    {
        return 'filament.pages.global-church-settings';
    }

    public static function canAccess(): bool
    {
        return Auth::user()->hasRole('superadmin');
    }

    public function mount(): void
    {
        $this->form->fill([
            'church_name_en' => SiteSetting::get('church_name_en', 'FINOTE TSIDIK'),
            'church_name_am' => SiteSetting::get('church_name_am', 'ፊኖተ ጽዲክ'),
            'church_address' => SiteSetting::get('church_address', ''),
            'church_phone' => SiteSetting::get('church_phone', ''),
            'church_email' => SiteSetting::get('church_email', ''),
            'default_language' => SiteSetting::get('default_language', 'am'),
            'maintenance_mode' => SiteSetting::get('maintenance_mode', false),
            'logo' => SiteSetting::get('logo'),
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            \Filament\Schemas\Components\Section::make('Church Information')
                ->description('Basic church information and contact details')
                ->schema([
                    Forms\Components\TextInput::make('church_name_en')
                        ->label('Church Name (English)')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('church_name_am')
                        ->label('Church Name (Amharic)')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Textarea::make('church_address')
                        ->label('Church Address')
                        ->rows(3),

                    Forms\Components\TextInput::make('church_phone')
                        ->label('Phone Number')
                        ->tel()
                        ->regex('/^\+?[0-9]{10,15}$/'),

                    Forms\Components\TextInput::make('church_email')
                        ->label('Email Address')
                        ->email()
                        ->maxLength(255),
                ])
                ->columns(2),

            \Filament\Schemas\Components\Section::make('System Settings')
                ->description('System-wide configuration')
                ->schema([
                    Forms\Components\Select::make('default_language')
                        ->label('Default Language')
                        ->options([
                            'en' => 'English',
                            'am' => 'አማርኛ (Amharic)',
                        ])
                        ->required(),

                    Forms\Components\Toggle::make('maintenance_mode')
                        ->label('Maintenance Mode')
                        ->helperText('When enabled, only Superadmin can access the system')
                        ->reactive(),
                ])
                ->columns(2),

            \Filament\Schemas\Components\Section::make('Logo Management')
                ->description('Upload and manage church logo')
                ->schema([
                    Forms\Components\FileUpload::make('logo')
                        ->label('Church Logo')
                        ->image()
                        ->imageEditor()
                        ->directory('logos')
                        ->visibility('public')
                        ->maxSize(2048) // 2MB
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/jpg', 'image/svg'])
                        ->helperText('Recommended: PNG or SVG, max 2MB')
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $set('logo_preview', $state);
                            }
                        }),
                ]),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->action('saveSettings')
                ->icon('heroicon-o-check')
                ->color('success'),

            Action::make('reset')
                ->label('Reset to Defaults')
                ->action('resetToDefaults')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reset to Default Settings')
                ->modalDescription('This will reset all settings to their default values. This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, Reset Settings'),
        ];
    }

    public function saveSettings(): void
    {
        $data = $this->form->getState();

        // Handle logo upload
        if (isset($data['logo']) && $data['logo']) {
            // Delete old logo if exists
            $oldLogo = SiteSetting::get('logo');
            if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
                Storage::disk('public')->delete($oldLogo);
            }
            
            // Store new logo
            $logoPath = $data['logo']->store('logos', 'public');
            $data['logo'] = $logoPath;
        } else {
            // Keep existing logo if not changed
            unset($data['logo']);
        }

        // Save all settings
        foreach ($data as $key => $value) {
            SiteSetting::set($key, $value);
        }

        // Log the action
        activity()
            ->causedBy(Auth::user())
            ->performedOn(new SiteSetting())
            ->withProperties([
                'action' => 'update_global_settings',
                'settings_updated' => array_keys($data),
            ])
            ->log('Updated global church settings');

        Notification::make()
            ->title('Settings Saved')
            ->body('Global church settings have been updated successfully.')
            ->success()
            ->send();

        $this->redirect($this->getRedirectUrl());
    }

    public function resetToDefaults(): void
    {
        // Delete current logo if exists
        $oldLogo = SiteSetting::get('logo');
        if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
            Storage::disk('public')->delete($oldLogo);
        }

        // Reset all settings to defaults
        $defaults = [
            'church_name_en' => 'FINOTE TSIDIK',
            'church_name_am' => 'ፊኖተ ጽዲክ',
            'church_address' => '',
            'church_phone' => '',
            'church_email' => '',
            'default_language' => 'am',
            'maintenance_mode' => false,
            'logo' => null,
        ];

        foreach ($defaults as $key => $value) {
            SiteSetting::set($key, $value);
        }

        // Log the action
        activity()
            ->causedBy(Auth::user())
            ->performedOn(new SiteSetting())
            ->withProperties([
                'action' => 'reset_global_settings',
            ])
            ->log('Reset global church settings to defaults');

        Notification::make()
            ->title('Settings Reset')
            ->body('Global church settings have been reset to default values.')
            ->warning()
            ->send();

        // Remount the form with defaults
        $this->mount();
    }

    public function getRedirectUrl(): string
    {
        return $this->getUrl();
    }
}

