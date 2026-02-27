<?php

namespace App\Services;

use App\Models\SystemBackup;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Exception;

class BackupService
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
            
        } catch (Exception $e) {
            $backup->markAsFailed('Backup failed: ' . $e->getMessage());
            
            Log::error('Backup creation failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
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
            
        } catch (Exception $e) {
            // Clean up temp directory on error
            if (isset($tempDir) && is_dir($tempDir)) {
                $this->removeDirectory($tempDir);
            }
            
            Log::error('Backup restoration failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
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
