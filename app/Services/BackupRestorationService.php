<?php

namespace App\Services;

use App\Models\SystemBackup;
use App\Services\Contracts\BackupRestorationServiceInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Exception;

class BackupRestorationService implements BackupRestorationServiceInterface
{
    /**
     * Restore from backup.
     */
    public function restoreBackup(SystemBackup $backup): void
    {
        if (!$backup->canBeRestored()) {
            throw new Exception('Backup cannot be restored');
        }

        $backupPath = Storage::disk('backups')->path($backup->filename);

        try {
            // Extract backup
            $zip = new ZipArchive();

            if ($zip->open($backupPath) !== true) {
                throw new Exception('Failed to open backup file');
            }

            $tempDir = sys_get_temp_dir() . '/restore_' . uniqid();
            $zip->extractTo($tempDir);
            $zip->close();

            // Restore database
            $this->restoreDatabase($tempDir . '/database.sql');

            // Restore storage files
            $this->restoreStorageFiles($tempDir . '/storage');

            // Restore config files
            $this->restoreConfigFiles($tempDir);

            // Clean up temp directory
            $this->removeDirectory($tempDir);

            Log::info('Backup restored successfully', ['backup_id' => $backup->id]);

        } catch (\Exception $e) {
            ExceptionHandlerService::handleServiceException($e, 'BackupRestorationService');
        }
    }

    /**
     * Restore database from SQL file.
     */
    protected function restoreDatabase(string $sqlFile): void
    {
        if (!file_exists($sqlFile)) {
            throw new Exception('Database dump file not found in backup');
        }

        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');

        $command = [
            'mysql',
            "--host={$host}",
            "--user={$username}",
            "--password={$password}",
            $database,
        ];

        $process = new \Symfony\Component\Process\Process($command);
        $process->setInput(file_get_contents($sqlFile));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new Exception('Database restore failed: ' . $process->getErrorOutput());
        }
    }

    /**
     * Restore storage files.
     */
    protected function restoreStorageFiles(string $storageDir): void
    {
        if (!is_dir($storageDir)) {
            return;
        }

        $targetDir = storage_path();

        // Remove existing storage directory (except excluded)
        $this->removeDirectory($targetDir, ['cache', 'logs', 'framework']);

        // Copy restored files
        $this->copyDirectory($storageDir, $targetDir);
    }

    /**
     * Restore configuration files.
     */
    protected function restoreConfigFiles(string $tempDir): void
    {
        $configFiles = [
            '.env',
            'composer.json',
            'package.json',
            'vite.config.js',
        ];

        foreach ($configFiles as $file) {
            $sourceFile = $tempDir . '/' . $file;
            $targetFile = base_path($file);

            if (file_exists($sourceFile)) {
                copy($sourceFile, $targetFile);
            }
        }
    }

    /**
     * Remove directory recursively.
     */
    protected function removeDirectory(string $dir, array $exclude = []): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $relativePath = str_replace($dir, '', $file->getPathname());
            $relativePath = ltrim($relativePath, '/\\');
            $pathParts = explode('/', str_replace('\\', '/', $relativePath));

            // Skip excluded directories
            if (in_array($pathParts[0] ?? '', $exclude)) {
                continue;
            }

            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        if (!in_array(basename($dir), $exclude)) {
            rmdir($dir);
        }
    }

    /**
     * Copy directory recursively.
     */
    protected function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $sourcePath = $file->getPathname();
            $destinationPath = $destination . '/' . str_replace($source, '', $sourcePath);

            if ($file->isDir()) {
                mkdir($destinationPath, 0755, true);
            } else {
                copy($sourcePath, $destinationPath);
            }
        }
    }
}
