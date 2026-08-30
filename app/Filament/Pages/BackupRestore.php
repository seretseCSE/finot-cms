<?php

namespace App\Filament\Pages;

use App\Services\SystemZipBackupService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use ZipArchive;

class BackupRestore extends Page
{
    protected static ?string $title = 'Backup & Restore';

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-archive-box-arrow-down';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Settings & Logs';
    }

    public function getView(): string
    {
        return 'filament.pages.backup-restore';
    }

    public function getSubheading(): ?string
    {
        return 'Nightly automatic backups at 1:30 AM. You can also create one now.';
    }

    public static function canAccess(): bool
    {
        return \App\Support\RoleGate::can('system.backups');
    }

    public function getBackups(): array
    {
        return app(SystemZipBackupService::class)->list();
    }

    public function lastAutomaticAt(): ?string
    {
        return app(SystemZipBackupService::class)->lastAutomaticAt()?->format('M j, Y g:i A');
    }

    public function nextAutomaticAt(): string
    {
        return app(SystemZipBackupService::class)->nextAutomaticAt()->format('M j, Y g:i A');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_backup')
                ->label('Create Backup')
                ->action('createBackup')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Create System Backup')
                ->modalDescription('This will create a complete backup of the database and all uploaded files. The process may take several minutes.')
                ->modalSubmitActionLabel('Create Backup'),

            Action::make('cleanup_old_backups')
                ->label('Cleanup Old Backups')
                ->action('cleanupOldBackups')
                ->icon('heroicon-o-trash')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Cleanup Old Backups')
                ->modalDescription('This will delete backups older than 30 days, keeping only the 30 most recent backups.')
                ->modalSubmitActionLabel('Cleanup Backups'),
        ];
    }

    public function createBackup(): void
    {
        try {
            $result = app(SystemZipBackupService::class)->create('manual');

            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'action' => 'create_backup',
                    'backup_name' => $result['filename'],
                    'backup_size' => $result['size'],
                ])
                ->log('Created manual system backup');

            Notification::make()
                ->title('Backup Created')
                ->body("Saved {$result['filename']}.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Backup Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'action' => 'create_backup_failed',
                    'error' => $e->getMessage(),
                ])
                ->log('Failed to create manual backup');
        }
    }

    public function cleanupOldBackups(): void
    {
        try {
            $deletedCount = app(SystemZipBackupService::class)->retainNewest(30);

            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'action' => 'cleanup_backups',
                    'deleted_count' => $deletedCount,
                ])
                ->log('Cleaned up old backups');

            Notification::make()
                ->title('Cleanup Completed')
                ->body($deletedCount === 0
                    ? 'Nothing to remove. The 30 newest backups are already kept.'
                    : "Deleted {$deletedCount} old backup files.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Cleanup Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function restoreBackup(string $filename): void
    {
        try {
            $backupPath = storage_path("app/backups/{$filename}");

            if (!file_exists($backupPath)) {
                throw new \Exception('Backup file not found');
            }

            // Enable maintenance mode
            Artisan::call('down', ['--secret' => 'maintenance-' . time()]);

            try {
                // Extract backup
                $zip = new ZipArchive();
                if ($zip->open($backupPath) !== true) {
                    throw new \Exception('Failed to open backup file');
                }

                $tempPath = storage_path('app/temp_restore');
                if (is_dir($tempPath)) {
                    $this->deleteDirectory($tempPath);
                }
                mkdir($tempPath, 0755, true);

                $zip->extractTo($tempPath);
                $zip->close();

                // Restore database
                if (file_exists($tempPath . '/database.sql')) {
                    $this->restoreDatabase($tempPath . '/database.sql');
                }

                // Restore files
                if (is_dir($tempPath . '/storage')) {
                    $this->restoreFiles($tempPath . '/storage', public_path('storage'));
                }

                // Restore .env file
                if (file_exists($tempPath . '/.env')) {
                    copy($tempPath . '/.env', base_path('.env'));
                }

                // Clean up
                $this->deleteDirectory($tempPath);

                // Log the action
                activity()
                    ->causedBy(Auth::user())
                    ->withProperties([
                        'action' => 'restore_backup',
                        'backup_name' => $filename,
                    ])
                    ->log('Restored system from backup');

                Notification::make()
                    ->title('Restore Completed')
                    ->body("System has been restored from '{$filename}'. The application will be restarted.")
                    ->success()
                    ->send();

            } finally {
                // Disable maintenance mode
                Artisan::call('up');
            }

        } catch (\Exception $e) {
            // Ensure maintenance mode is disabled even if restore fails
            Artisan::call('up');

            Notification::make()
                ->title('Restore Failed')
                ->body('Failed to restore backup: ' . $e->getMessage())
                ->danger()
                ->send();

            // Log the error
            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'action' => 'restore_backup_failed',
                    'backup_name' => $filename,
                    'error' => $e->getMessage(),
                ])
                ->log('Failed to restore from backup');
        }
    }

    private function restoreDatabase(string $sqlFile): void
    {
        $dbHost = config('database.connections.mysql.host');
        $dbPort = config('database.connections.mysql.port');
        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPassword = config('database.connections.mysql.password');

        $command = "mysql --host={$dbHost} --port={$dbPort} --user={$dbUser} --password={$dbPassword} {$dbName} < {$sqlFile}";

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Database restore failed');
        }
    }

    private function restoreFiles(string $source, string $destination): void
    {
        if (is_dir($destination)) {
            $this->deleteDirectory($destination);
        }

        $this->copyDirectory($source, $destination);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $files = scandir($source);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $sourcePath = $source . '/' . $file;
                $destPath = $destination . '/' . $file;

                if (is_dir($sourcePath)) {
                    $this->copyDirectory($sourcePath, $destPath);
                } else {
                    copy($sourcePath, $destPath);
                }
            }
        }
    }

    public function downloadBackup(string $filename): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $backupPath = storage_path("app/backups/{$filename}");

        if (!file_exists($backupPath)) {
            abort(404, 'Backup file not found');
        }

        return response()->download($backupPath, $filename);
    }

    public function deleteBackup(string $filename): void
    {
        try {
            $backupPath = storage_path("app/backups/{$filename}");

            if (!file_exists($backupPath)) {
                throw new \Exception('Backup file not found');
            }

            if (unlink($backupPath)) {
                // Log the action
                activity()
                    ->causedBy(Auth::user())
                    ->withProperties([
                        'action' => 'delete_backup',
                        'backup_name' => $filename,
                    ])
                    ->log('Deleted backup file');

                Notification::make()
                    ->title('Backup Deleted')
                    ->body("Backup '{$filename}' has been deleted.")
                    ->success()
                    ->send();
            } else {
                throw new \Exception('Failed to delete backup file');
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title('Delete Failed')
                ->body('Failed to delete backup: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
