<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use ZipArchive;

class BackupRestore extends Page
{
    protected static ?string $title = 'Backup & Restore';

    public static function getNavigationSort(): ?int { return 4; }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-archive-box-arrow-down';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public function getView(): string
    {
        return 'filament.pages.backup-restore';
    }

    public static function canAccess(): bool
    {
        return Auth::user()->hasRole('superadmin');
    }

    public function getBackups(): array
    {
        $backups = [];
        $backupPath = storage_path('app/backups');

        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $files = glob($backupPath . '/*.zip');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $timestamp = $this->extractTimestampFromFilename($filename);
            
            $backups[] = [
                'filename' => $filename,
                'path' => $file,
                'size' => $this->formatFileSize(filesize($file)),
                'created_at' => Carbon::createFromTimestamp($timestamp)->format('Y-m-d H:i:s'),
                'type' => $this->getBackupType($filename),
            ];
        }

        // Sort by creation date (newest first)
        usort($backups, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return array_slice($backups, 0, 30); // Keep only last 30 backups
    }

    private function extractTimestampFromFilename(string $filename): int
    {
        // Extract timestamp from filename like: backup_20240228_120000.zip
        if (preg_match('/(\d{8})_(\d{6})/', $filename, $matches)) {
            $date = $matches[1];
            $time = $matches[2];
            return Carbon::createFromFormat('YmdHis', $date . $time)->timestamp;
        }
        return filemtime(storage_path('app/backups/' . $filename));
    }

    private function getBackupType(string $filename): string
    {
        if (str_contains($filename, 'manual')) {
            return 'Manual';
        } elseif (str_contains($filename, 'auto')) {
            return 'Automatic';
        }
        return 'Unknown';
    }

    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
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
            $timestamp = now()->format('Ymd_His');
            $backupName = "backup_manual_{$timestamp}.zip";
            $backupPath = storage_path("app/backups/{$backupName}");

            // Ensure backup directory exists
            $backupDir = storage_path('app/backups');
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            // Create zip archive
            $zip = new ZipArchive();
            if ($zip->open($backupPath, ZipArchive::CREATE) === TRUE) {
                // Add database dump
                $dbDumpPath = storage_path("app/backups/db_dump_{$timestamp}.sql");
                $this->createDatabaseDump($dbDumpPath);
                $zip->addFile($dbDumpPath, "database.sql");

                // Add uploaded files
                $uploadPath = public_path('storage');
                if (is_dir($uploadPath)) {
                    $this->addFolderToZip($zip, $uploadPath, 'storage');
                }

                // Add .env file (without sensitive data)
                $envContent = $this->sanitizeEnvFile();
                $zip->addFromString('.env', $envContent);

                $zip->close();

                // Clean up temporary files
                unlink($dbDumpPath);

                // Log the action
                activity()
                    ->causedBy(Auth::user())
                    ->withProperties([
                        'action' => 'create_backup',
                        'backup_name' => $backupName,
                        'backup_size' => filesize($backupPath),
                    ])
                    ->log('Created manual system backup');

                Notification::make()
                    ->title('Backup Created')
                    ->body("System backup '{$backupName}' has been created successfully.")
                    ->success()
                    ->send();

            } else {
                throw new \Exception('Failed to create backup archive');
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title('Backup Failed')
                ->body('Failed to create backup: ' . $e->getMessage())
                ->danger()
                ->send();

            // Log the error
            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'action' => 'create_backup_failed',
                    'error' => $e->getMessage(),
                ])
                ->log('Failed to create manual backup');
        }
    }

    private function createDatabaseDump(string $path): void
    {
        $dbHost = config('database.connections.mysql.host');
        $dbPort = config('database.connections.mysql.port');
        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPassword = config('database.connections.mysql.password');

        $command = "mysqldump --host={$dbHost} --port={$dbPort} --user={$dbUser} --password={$dbPassword} --single-transaction --routines --triggers {$dbName} > {$path}";

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Database dump failed');
        }
    }

    private function addFolderToZip(ZipArchive $zip, string $folder, string $zipFolder): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($folder) + 1);
                $zip->addFile($filePath, $zipFolder . '/' . $relativePath);
            }
        }
    }

    private function sanitizeEnvFile(): string
    {
        $envFile = base_path('.env');
        if (!file_exists($envFile)) {
            return '';
        }

        $content = file_get_contents($envFile);
        
        // Remove sensitive values
        $sensitiveKeys = ['DB_PASSWORD', 'MAIL_PASSWORD', 'AWS_SECRET_ACCESS_KEY', 'STRIPE_SECRET_KEY'];
        
        foreach ($sensitiveKeys as $key) {
            $content = preg_replace("/^{$key}=.*$/m", "{$key}=*****", $content);
        }

        return $content;
    }

    public function cleanupOldBackups(): void
    {
        try {
            $backupPath = storage_path('app/backups');
            $files = glob($backupPath . '/*.zip');
            
            // Sort by creation time
            usort($files, function ($a, $b) {
                return filemtime($b) - filemtime($a);
            });

            // Keep only the 30 most recent
            $filesToDelete = array_slice($files, 30);
            $deletedCount = 0;

            foreach ($filesToDelete as $file) {
                if (unlink($file)) {
                    $deletedCount++;
                }
            }

            // Log the action
            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'action' => 'cleanup_backups',
                    'deleted_count' => $deletedCount,
                ])
                ->log('Cleaned up old backups');

            Notification::make()
                ->title('Cleanup Completed')
                ->body("Deleted {$deletedCount} old backup files.")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Cleanup Failed')
                ->body('Failed to cleanup old backups: ' . $e->getMessage())
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
                if ($zip->open($backupPath) !== TRUE) {
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
