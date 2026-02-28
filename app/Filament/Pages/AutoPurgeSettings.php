<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;

class AutoPurgeSettings extends Page
{
    protected static ?string $title = 'Auto-Purge Settings';

    protected static ?int $navigationSort = 5;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-trash';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public function getView(): string
    {
        return 'filament.pages.auto-purge-settings';
    }

    public static function canAccess(): bool
    {
        return Auth::user()->hasRole('superadmin');
    }

    public function mount(): void
    {
        $this->form->fill([
            'error_logs_retention_days' => SiteSetting::get('error_logs_retention_days', 60),
            'security_audit_retention_days' => SiteSetting::get('security_audit_retention_days', 30),
            'session_logs_retention_days' => SiteSetting::get('session_logs_retention_days', 90),
            'read_notifications_retention_days' => SiteSetting::get('read_notifications_retention_days', 90),
            'media_files_retention_years' => SiteSetting::get('media_files_retention_years', 5),
            'auto_purge_enabled' => SiteSetting::get('auto_purge_enabled', true),
            'purge_schedule' => SiteSetting::get('purge_schedule', 'daily'),
            'notify_before_purge' => SiteSetting::get('notify_before_purge', true),
            'purge_notification_days' => SiteSetting::get('purge_notification_days', 7),
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            \Filament\Schemas\Components\Section::make('Data Retention Settings')
                ->description('Configure how long to keep different types of data before automatic deletion')
                ->schema([
                    Forms\Components\Toggle::make('auto_purge_enabled')
                        ->label('Enable Auto-Purge')
                        ->helperText('Automatically delete old data based on retention periods')
                        ->reactive(),

                    Forms\Components\Select::make('purge_schedule')
                        ->label('Purge Schedule')
                        ->options([
                            'daily' => 'Daily (2:00 AM)',
                            'weekly' => 'Weekly (Sunday 2:00 AM)',
                            'monthly' => 'Monthly (1st day 2:00 AM)',
                        ])
                        ->required()
                        ->reactive(),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('error_logs_retention_days')
                                ->label('Error Logs Retention (Days)')
                                ->numeric()
                                ->min(1)
                                ->max(365)
                                ->helperText('Delete error logs older than this many days')
                                ->default(60),

                            Forms\Components\TextInput::make('security_audit_retention_days')
                                ->label('Security Audit Logs Retention (Days)')
                                ->numeric()
                                ->min(1)
                                ->max(365)
                                ->helperText('Delete security audit logs older than this many days')
                                ->default(30),
                        ]),
                    
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('session_logs_retention_days')
                                ->label('Session Logs Retention (Days)')
                                ->numeric()
                                ->min(1)
                                ->max(365)
                                ->helperText('Delete user session logs older than this many days')
                                ->default(90),

                            Forms\Components\TextInput::make('read_notifications_retention_days')
                                ->label('Read Notifications Retention (Days)')
                                ->numeric()
                                ->min(1)
                                ->max(365)
                                ->helperText('Delete read notifications older than this many days')
                                ->default(90),
                        ]),
                ])
                ->columns(2),

            \Filament\Schemas\Components\Section::make('Media & Files Settings')
                ->description('Configure retention for uploaded files and media')
                ->schema([
                    Forms\Components\TextInput::make('media_files_retention_years')
                        ->label('Media Files Retention (Years)')
                        ->numeric()
                        ->min(1)
                        ->max(20)
                        ->helperText('Automatically archive media files older than this many years')
                        ->default(5),
                ])
                ->columns(1),

            \Filament\Schemas\Components\Section::make('Purge Notifications')
                ->description('Configure warnings before automatic deletion')
                ->schema([
                    Forms\Components\Toggle::make('notify_before_purge')
                        ->label('Notify Before Purge')
                        ->helperText('Send notification before running auto-purge')
                        ->reactive(),

                    Forms\Components\TextInput::make('purge_notification_days')
                        ->label('Purge Warning Days')
                        ->numeric()
                        ->min(1)
                        ->max(30)
                        ->helperText('Send warning this many days before scheduled purge')
                        ->default(7)
                        ->reactive()
                        ->visible(fn (callable $get) => $get('notify_before_purge')),
                ])
                ->columns(2),
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

            Action::make('test_purge')
                ->label('Test Purge Configuration')
                ->action('testPurgeConfiguration')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Test Purge Configuration')
                ->modalDescription('This will run a dry-run purge to test your configuration. No data will be deleted.'),

            Action::make('run_manual_purge')
                ->label('Run Manual Purge Now')
                ->action('runManualPurge')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Run Manual Purge')
                ->modalDescription('This will immediately purge data based on current retention settings. This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, Purge Data'),
        ];
    }

    public function saveSettings(): void
    {
        $data = $this->form->getState();

        // Capture old values for audit
        $oldValues = [];
        $newValues = [];
        $settingsKeys = [
            'error_logs_retention_days', 'security_audit_retention_days', 'session_logs_retention_days',
            'read_notifications_retention_days', 'media_files_retention_years', 'auto_purge_enabled',
            'purge_schedule', 'notify_before_purge', 'purge_notification_days'
        ];

        foreach ($settingsKeys as $key) {
            if (isset($data[$key])) {
                $oldVal = SiteSetting::get($key);
                if ($oldVal !== $data[$key]) {
                    $oldValues[$key] = $oldVal;
                    $newValues[$key] = $data[$key];
                }
            }
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
                'action' => 'update_auto_purge_settings',
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'settings_updated' => array_keys($newValues),
            ])
            ->log('Updated auto-purge settings');

        // Clear cache
        Artisan::call('optimize:clear');

        Notification::make()
            ->title('Settings Saved')
            ->body('Auto-purge settings have been updated successfully.')
            ->success()
            ->send();
    }

    public function testPurgeConfiguration(): void
    {
        $data = $this->form->getState();
        
        // Calculate what would be purged
        $results = [
            'error_logs_older_than' => now()->subDays($data['error_logs_retention_days'])->format('Y-m-d'),
            'security_audit_older_than' => now()->subDays($data['security_audit_retention_days'])->format('Y-m-d'),
            'session_logs_older_than' => now()->subDays($data['session_logs_retention_days'])->format('Y-m-d'),
            'notifications_older_than' => now()->subDays($data['read_notifications_retention_days'])->format('Y-m-d'),
            'media_older_than' => now()->subYears($data['media_files_retention_years'])->format('Y-m-d'),
        ];

        Notification::make()
            ->title('Purge Test Results')
            ->body('Dry-run completed. This is what would be purged:' . "\n\n" . 
                "• Error logs older than: {$results['error_logs_older_than']}\n" .
                "• Security audit logs older than: {$results['security_audit_older_than']}\n" .
                "• Session logs older than: {$results['session_logs_older_than']}\n" .
                "• Read notifications older than: {$results['notifications_older_than']}\n" .
                "• Media files older than: {$results['media_older_than']}")
            ->info()
            ->send();
    }

    public function runManualPurge(): void
    {
        $data = $this->form->getState();
        
        try {
            // Run the actual purge commands
            $commands = [
                'logs:purge-error' => "--days={$data['error_logs_retention_days']}",
                'logs:purge-security-audit' => "--days={$data['security_audit_retention_days']}",
                'logs:purge-session' => "--days={$data['session_logs_retention_days']}",
                'notifications:purge-read' => "--days={$data['read_notifications_retention_days']}",
            ];

            $results = [];
            foreach ($commands as $command => $params) {
                try {
                    Artisan::call($command, explode(' ', $params));
                    $results[$command] = 'success';
                } catch (\Exception $e) {
                    $results[$command] = 'error: ' . $e->getMessage();
                }
            }

            // Log the manual purge
            activity()
                ->causedBy(Auth::user())
                ->performedOn(new SiteSetting())
                ->withProperties([
                    'action' => 'manual_purge',
                    'commands_run' => array_keys($commands),
                    'results' => $results,
                ])
                ->log('Ran manual data purge');

            Notification::make()
                ->title('Manual Purge Completed')
                ->body('Manual purge has been completed. Check system logs for detailed results.')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Purge Failed')
                ->body('Error during purge: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
