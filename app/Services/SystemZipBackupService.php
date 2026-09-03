<?php

namespace App\Services;

use Carbon\Carbon;
use Symfony\Component\Process\Process;
use ZipArchive;

class SystemZipBackupService
{
    public function directory(): string
    {
        $path = storage_path('app/backups');

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    /**
     * @return array{filename: string, path: string, size: int}
     */
    public function create(string $type = 'manual'): array
    {
        $type = in_array($type, ['manual', 'auto'], true) ? $type : 'manual';
        $timestamp = now()->format('Ymd_His');
        $filename = "backup_{$type}_{$timestamp}.zip";
        $path = $this->directory().DIRECTORY_SEPARATOR.$filename;
        $dumpPath = $this->directory().DIRECTORY_SEPARATOR."db_dump_{$timestamp}.sql";

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create backup archive.');
        }

        try {
            $this->dumpDatabase($dumpPath);
            $zip->addFile($dumpPath, 'database.sql');

            $uploadPath = public_path('storage');
            if (is_dir($uploadPath)) {
                $this->addFolderToZip($zip, $uploadPath, 'storage');
            }

            $zip->addFromString('.env', $this->sanitizedEnv());
            $zip->close();
        } catch (\Throwable $e) {
            $zip->close();
            if (is_file($path)) {
                unlink($path);
            }
            throw $e;
        } finally {
            if (is_file($dumpPath)) {
                unlink($dumpPath);
            }
        }

        $this->retainNewest(30);

        return [
            'filename' => $filename,
            'path' => $path,
            'size' => filesize($path) ?: 0,
        ];
    }

    public function retainNewest(int $keep = 30): int
    {
        $files = glob($this->directory().DIRECTORY_SEPARATOR.'*.zip') ?: [];
        usort($files, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        $deleted = 0;
        foreach (array_slice($files, $keep) as $file) {
            if (is_file($file) && unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @return list<array{filename: string, path: string, size: string, created_at: string, type: string}>
     */
    public function list(int $limit = 30): array
    {
        $files = glob($this->directory().DIRECTORY_SEPARATOR.'*.zip') ?: [];
        $backups = [];

        foreach ($files as $file) {
            $filename = basename($file);
            $backups[] = [
                'filename' => $filename,
                'path' => $file,
                'size' => $this->formatSize((int) filesize($file)),
                'created_at' => Carbon::createFromTimestamp($this->timestampFromFilename($filename))->format('Y-m-d H:i:s'),
                'type' => $this->typeFromFilename($filename),
            ];
        }

        usort($backups, fn (array $a, array $b): int => strtotime($b['created_at']) <=> strtotime($a['created_at']));

        return array_slice($backups, 0, $limit);
    }

    public function lastAutomaticAt(): ?Carbon
    {
        foreach ($this->list(30) as $backup) {
            if ($backup['type'] === 'Automatic') {
                return Carbon::parse($backup['created_at']);
            }
        }

        return null;
    }

    public function nextAutomaticAt(): Carbon
    {
        $today = now()->copy()->setTime(1, 30);

        return now()->lessThan($today) ? $today : $today->addDay();
    }

    protected function dumpDatabase(string $path): void
    {
        $connection = (string) config('database.default');
        $db = config("database.connections.{$connection}");

        if (is_array($db) === false) {
            throw new \RuntimeException('Database connection is not configured.');
        }

        $host = $this->safeDumpToken((string) ($db['host'] ?? '127.0.0.1'), 'host');
        $port = (string) ((int) ($db['port'] ?? 3306));
        $user = $this->safeDumpToken((string) ($db['username'] ?? 'root'), 'username');
        $database = $this->safeDumpToken((string) ($db['database'] ?? ''), 'database');

        $process = new Process([
            $this->mysqldumpBinary(),
            '--host='.$host,
            '--port='.$port,
            '--user='.$user,
            '--single-transaction',
            '--routines',
            '--triggers',
            $database,
        ]);
        $process->setTimeout(300);
        $env = [];
        foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
            if (is_string($key) === true && is_string($value) === true) {
                $env[$key] = $value;
            }
        }
        $env['MYSQL_PWD'] = (string) ($db['password'] ?? '');
        $process->setEnv($env);

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Could not write the database dump file.');
        }

        try {
            $process->run(function (string $type, string $buffer) use ($handle): void {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);
                }
            });
        } finally {
            fclose($handle);
        }

        if ($process->isSuccessful() === false || is_file($path) === false || filesize($path) === 0) {
            if (is_file($path) === true) {
                unlink($path);
            }

            throw new \RuntimeException('Database dump failed. Check that mysqldump is available.');
        }
    }

    protected function mysqldumpBinary(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $xampp = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
            if (is_file($xampp) === true) {
                return $xampp;
            }
        }

        return 'mysqldump';
    }

    protected function safeDumpToken(string $value, string $label): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9._:\\[\\]-]+$/', $value) !== 1) {
            throw new \RuntimeException("Invalid database {$label}.");
        }

        return $value;
    }

    protected function addFolderToZip(ZipArchive $zip, string $folder, string $zipFolder): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                continue;
            }

            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $relativePath = substr($filePath, strlen(realpath($folder)) + 1);
            $zip->addFile($filePath, $zipFolder.'/'.str_replace('\\', '/', $relativePath));
        }
    }

    protected function sanitizedEnv(): string
    {
        $envFile = base_path('.env');
        if (! is_file($envFile)) {
            return '';
        }

        $content = (string) file_get_contents($envFile);
        $sensitiveKeys = ['DB_PASSWORD', 'MAIL_PASSWORD', 'AWS_SECRET_ACCESS_KEY', 'STRIPE_SECRET_KEY'];

        foreach ($sensitiveKeys as $key) {
            $content = preg_replace("/^{$key}=.*$/m", "{$key}=*****", $content) ?? $content;
        }

        return $content;
    }

    protected function timestampFromFilename(string $filename): int
    {
        if (preg_match('/(\d{8})_(\d{6})/', $filename, $matches)) {
            return Carbon::createFromFormat('YmdHis', $matches[1].$matches[2])->timestamp;
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$filename;

        return is_file($path) ? (int) filemtime($path) : time();
    }

    protected function typeFromFilename(string $filename): string
    {
        if (str_contains($filename, 'manual')) {
            return 'Manual';
        }

        if (str_contains($filename, 'auto')) {
            return 'Automatic';
        }

        return 'Unknown';
    }

    protected function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = $bytes > 0 ? (int) floor(log($bytes) / log(1024)) : 0;
        $pow = min($pow, count($units) - 1);

        return round($bytes / (1 << (10 * $pow)), 2).' '.$units[$pow];
    }
}
