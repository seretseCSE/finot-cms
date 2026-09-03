<?php

namespace App\Support;

class LaravelLogFiles
{
    /**
     * Laravel application log paths, newest first.
     *
     * Includes daily files (`laravel-YYYY-MM-DD.log`) and the legacy
     * single-file `laravel.log` when it still exists.
     *
     * @return list<string>
     */
    public static function paths(?int $days = null): array
    {
        $logsDir = storage_path('logs');
        $paths = [];

        foreach (glob($logsDir.DIRECTORY_SEPARATOR.'laravel-*.log') ?: [] as $path) {
            if (is_file($path)) {
                $paths[] = $path;
            }
        }

        rsort($paths);

        if ($days !== null) {
            $cutoff = now()->subDays($days)->format('Y-m-d');
            $paths = array_values(array_filter($paths, function (string $path) use ($cutoff): bool {
                if (preg_match('/laravel-(\d{4}-\d{2}-\d{2})\.log$/', basename($path), $matches)) {
                    return $matches[1] >= $cutoff;
                }

                return true;
            }));
        }

        $single = $logsDir.DIRECTORY_SEPARATOR.'laravel.log';
        if (is_file($single)) {
            $paths[] = $single;
        }

        return $paths;
    }
}
