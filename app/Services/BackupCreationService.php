<?php

namespace App\Services;

use App\Models\SystemBackup;
use App\Services\Contracts\BackupCreationServiceInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Exception;

class BackupCreationService implements BackupCreationServiceInterface
{
    /**
     * Create a new system backup.
     */
    public function createBackup(int $userId): SystemBackup
    {
        $backup = SystemBackup::create([
            'filename' => SystemBackup::generateFilename(),
            'path' => 'backups/',
            'size' => 0,
            'status' => 'pending',
            'created_by' => $userId,
        ]);

        try {
            $backup->update(['status' => 'in_progress']);

            // Create backup file
            $this->performBackup($backup);

            // Get file size
            $size = Storage::disk('backups')->size($backup->filename);

            // Mark as completed
            $backup->update([
                'size' => $size,
                'status' => 'completed',
                'completed_at' => now(),
                'log_message' => 'Backup completed successfully',
            ]);

            Log::info('Backup created successfully', ['backup_id' => $backup->id]);

        } catch (\Exception $e) {
            ExceptionHandlerService::handleServiceException($e, 'BackupCreationService');
        }

        return $backup;
    }

    /**
     * Perform the actual backup process.
     */
    protected function performBackup(SystemBackup $backup): void
    {
        $backupPath = Storage::disk('backups')->path($backup->filename);

        // Create ZIP archive
        $zip = new ZipArchive();

        if ($zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Failed to create backup ZIP file');
        }

        try {
            // Add database dump
            $this->addDatabaseDump($zip);

            // Add storage files
            $this->addStorageFiles($zip);

            // Add important configuration files
            $this->addConfigFiles($zip);

            $zip->close();

        } catch (Exception $e) {
            $zip->close();
            throw $e;
        }
    }

    /**
     * Add database dump to backup.
     */
    protected function addDatabaseDump(ZipArchive $zip): void
    {
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');

        $dumpFile = tempnam(sys_get_temp_dir(), 'db_dump_');

        try {
            // Create database dump
            $command = [
                'mysqldump',
                "--host={$host}",
                "--user={$username}",
                "--password={$password}",
                "--single-transaction",
                "--routines",
                "--triggers",
                $database,
            ];

            $process = new \Symfony\Component\Process\Process($command);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new Exception('Database dump failed: ' . $process->getErrorOutput());
            }

            // Add to ZIP
            $zip->addFromString('database.sql', $process->getOutput());

        } finally {
            if (file_exists($dumpFile)) {
                unlink($dumpFile);
            }
        }
    }

    /**
     * Add storage files to backup.
     */
    protected function addStorageFiles(ZipArchive $zip): void
    {
        $storagePath = storage_path();

        // Add app/storage files (excluding cache, logs, framework)
        $excludeDirs = ['cache', 'logs', 'framework', 'testing'];

        $this->addDirectoryToZip($zip, $storagePath, 'storage', $excludeDirs);
    }

    /**
     * Add configuration files to backup.
     */
    protected function addConfigFiles(ZipArchive $zip): void
    {
        $configFiles = [
            '.env',
            'composer.json',
            'composer.lock',
            'package.json',
            'package-lock.json',
            'vite.config.js',
        ];

        foreach ($configFiles as $file) {
            $filePath = base_path($file);
            if (file_exists($filePath)) {
                $zip->addFile($filePath, $file);
            }
        }
    }

    /**
     * Add directory to ZIP recursively.
     */
    protected function addDirectoryToZip(ZipArchive $zip, string $sourcePath, string $zipPath, array $excludeDirs = []): void
    {
        if (!is_dir($sourcePath)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $relativePath = str_replace($sourcePath, '', $file->getPathname());
            $relativePath = ltrim($relativePath, '/\\');

            // Skip excluded directories
            $pathParts = explode('/', str_replace('\\', '/', $relativePath));
            if (in_array($pathParts[0] ?? '', $excludeDirs)) {
                continue;
            }

            if ($file->isDir()) {
                $zip->addEmptyDir($zipPath . '/' . $relativePath);
            } else {
                $zip->addFile($file->getPathname(), $zipPath . '/' . $relativePath);
            }
        }
    }
}
