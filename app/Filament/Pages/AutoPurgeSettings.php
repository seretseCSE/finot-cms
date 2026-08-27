<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use Filament\Actions;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;

class AutoPurgeSettings extends Page
{
    protected static ?string $title = 'Auto-Purge Settings';

    public function getView(): string
    {
        return 'filament.pages.auto-purge-settings';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-trash';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->can('page.settings.auto-purge') ?? false;
    }

    public function mount(): void
    {
        // Load current settings for display
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('test_purge')
                ->label('Test Purge Configuration')
                ->action('testPurgeConfiguration')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Test Purge Configuration')
                ->modalDescription('This will run a dry-run purge to test your configuration. No data will be deleted.'),

            Actions\Action::make('run_manual_purge')
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

    public function testPurgeConfiguration(): void
    {
        // Get current settings
        $errorLogsDays = SiteSetting::get('error_logs_retention_days', 60);
        $securityAuditDays = SiteSetting::get('security_audit_retention_days', 30);
        $sessionLogsDays = SiteSetting::get('session_logs_retention_days', 90);
        $mediaYears = SiteSetting::get('media_files_retention_years', 5);

        // Calculate what would be purged
        $results = [
            'error_logs_older_than' => now()->subDays($errorLogsDays)->format('Y-m-d'),
            'security_audit_older_than' => now()->subDays($securityAuditDays)->format('Y-m-d'),
            'session_logs_older_than' => now()->subDays($sessionLogsDays)->format('Y-m-d'),
            'media_older_than' => now()->subYears($mediaYears)->format('Y-m-d'),
        ];

        Notification::make()
            ->title('Purge Test Results')
            ->body('Dry-run completed. This is what would be purged:' . "\n\n" .
                "• Error logs older than: {$results['error_logs_older_than']}\n" .
                "• Security audit logs older than: {$results['security_audit_older_than']}\n" .
                "• Session logs older than: {$results['session_logs_older_than']}\n" .
                "• Media files older than: {$results['media_older_than']}")
            ->info()
            ->send();
    }

    public function runManualPurge(): void
    {
        // Get current settings
        $errorLogsDays = SiteSetting::get('error_logs_retention_days', 60);
        $securityAuditDays = SiteSetting::get('security_audit_retention_days', 30);
        $sessionLogsDays = SiteSetting::get('session_logs_retention_days', 90);
        try {
            // Run the actual purge commands
            $commands = [
                'logs:purge-error' => "--days={$errorLogsDays}",
                'logs:purge-security-audit' => "--days={$securityAuditDays}",
                'logs:purge-session' => "--days={$sessionLogsDays}",
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
