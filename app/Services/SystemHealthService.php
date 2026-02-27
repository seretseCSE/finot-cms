<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Carbon\Carbon;

class SystemHealthService
{
    /**
     * Get comprehensive system health data.
     */
    public function getHealthData(): array
    {
        return [
            'server' => $this->getServerInfo(),
            'storage' => $this->getStorageInfo(),
            'database' => $this->getDatabaseInfo(),
            'users' => $this->getUserInfo(),
            'errors' => $this->getErrorInfo(),
            'system' => $this->getSystemInfo(),
            'alerts' => $this->getSystemAlerts(),
        ];
    }

    /**
     * Get server information.
     */
    protected function getServerInfo(): array
    {
        $uptime = $this->getServerUptime();
        
        return [
            'uptime' => $uptime,
            'uptime_text' => $this->formatUptime($uptime),
            'load_average' => $this->getLoadAverage(),
            'memory_usage' => $this->getMemoryUsage(),
            'cpu_usage' => $this->getCpuUsage(),
        ];
    }

    /**
     * Get storage usage information.
     */
    protected function getStorageInfo(): array
    {
        $total = $this->getDiskTotalSpace();
        $used = $this->getDiskUsedSpace();
        $free = $total - $used;
        $percentage = $total > 0 ? ($used / $total) * 100 : 0;

        return [
            'total' => $this->formatBytes($total),
            'used' => $this->formatBytes($used),
            'free' => $this->formatBytes($free),
            'percentage' => round($percentage, 2),
            'warning' => $percentage >= 80,
            'critical' => $percentage >= 95,
        ];
    }

    /**
     * Get database information.
     */
    protected function getDatabaseInfo(): array
    {
        try {
            // Get database size
            $dbName = config('database.connections.mysql.database');
            $sizeQuery = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size FROM information_schema.tables WHERE table_schema = '{$dbName}'";
            $size = DB::select($sizeQuery)[0]->size ?? 0;

            // Get average query time (cached)
            $avgQueryTime = Cache::remember('health_avg_query_time', 300, function () {
                $start = microtime(true);
                DB::select('SELECT 1');
                $end = microtime(true);
                
                return round(($end - $start) * 1000, 2); // Convert to milliseconds
            });

            // Get connection count
            $connections = Cache::remember('health_db_connections', 60, function () {
                return DB::select('SHOW STATUS LIKE "Threads_connected"')[0]->Value ?? 0;
            });

            return [
                'size' => $size . ' MB',
                'avg_query_time' => $avgQueryTime . ' ms',
                'connections' => $connections,
                'slow_queries' => $this->getSlowQueriesCount(),
            ];
        } catch (\Exception $e) {
            return [
                'size' => 'N/A',
                'avg_query_time' => 'N/A',
                'connections' => 'N/A',
                'slow_queries' => 'N/A',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get user and session information.
     */
    protected function getUserInfo(): array
    {
        try {
            // Get active users (last 5 minutes)
            $activeUsers = Cache::remember('health_active_users', 60, function () {
                return DB::table('sessions')
                    ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
                    ->count();
            });

            // Get total users
            $totalUsers = Cache::remember('health_total_users', 300, function () {
                return DB::table('users')->count();
            });

            // Get online users (last 15 minutes)
            $onlineUsers = Cache::remember('health_online_users', 60, function () {
                return DB::table('sessions')
                    ->where('last_activity', '>=', now()->subMinutes(15)->timestamp)
                    ->count();
            });

            return [
                'active_users' => $activeUsers,
                'online_users' => $onlineUsers,
                'total_users' => $totalUsers,
                'active_sessions' => $activeUsers,
            ];
        } catch (\Exception $e) {
            return [
                'active_users' => 0,
                'online_users' => 0,
                'total_users' => 0,
                'active_sessions' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get error information.
     */
    protected function getErrorInfo(): array
    {
        try {
            // Get error rate from logs (last 24 hours)
            $errorRate = Cache::remember('health_error_rate', 300, function () {
                $logFile = storage_path('logs/laravel.log');
                if (!file_exists($logFile)) {
                    return 0;
                }

                $yesterday = now()->subDay()->format('Y-m-d');
                $today = now()->format('Y-m-d');
                
                $errorCount = 0;
                $lines = file($logFile);
                
                foreach ($lines as $line) {
                    if (strpos($line, $yesterday) !== false || strpos($line, $today) !== false) {
                        if (strpos($line, 'ERROR') !== false || strpos($line, 'Exception') !== false) {
                            $errorCount++;
                        }
                    }
                }

                return $errorCount;
            });

            // Get recent exceptions from database (if you have an exceptions table)
            $recentExceptions = 0; // Implement based on your logging setup

            return [
                'error_rate_24h' => $errorRate,
                'recent_exceptions' => $recentExceptions,
                'critical_errors' => $this->getCriticalErrors(),
            ];
        } catch (\Exception $e) {
            return [
                'error_rate_24h' => 0,
                'recent_exceptions' => 0,
                'critical_errors' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get system information.
     */
    protected function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'os' => PHP_OS,
            'timezone' => config('app.timezone'),
            'environment' => config('app.env'),
            'debug_mode' => config('app.debug'),
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'queue_driver' => config('queue.default'),
        ];
    }

    /**
     * Get system alerts.
     */
    protected function getSystemAlerts(): array
    {
        $alerts = [];
        $healthData = $this->getHealthData();

        // Storage alerts
        if ($healthData['storage']['critical']) {
            $alerts[] = [
                'type' => 'critical',
                'title' => 'Critical Storage Warning',
                'message' => "Storage usage is at {$healthData['storage']['percentage']}%. Immediate action required.",
                'icon' => 'heroicon-o-exclamation-triangle',
            ];
        } elseif ($healthData['storage']['warning']) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Storage Warning',
                'message' => "Storage usage is at {$healthData['storage']['percentage']}%. Consider cleaning up.",
                'icon' => 'heroicon-o-exclamation-triangle',
            ];
        }

        // Error rate alerts
        if ($healthData['errors']['error_rate_24h'] > 50) {
            $alerts[] = [
                'type' => 'critical',
                'title' => 'High Error Rate',
                'message' => " {$healthData['errors']['error_rate_24h']} errors in the last 24 hours.",
                'icon' => 'heroicon-o-x-circle',
            ];
        } elseif ($healthData['errors']['error_rate_24h'] > 10) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Elevated Error Rate',
                'message' => " {$healthData['errors']['error_rate_24h']} errors in the last 24 hours.",
                'icon' => 'heroicon-o-exclamation-triangle',
            ];
        }

        // Database alerts
        if (isset($healthData['database']['error'])) {
            $alerts[] = [
                'type' => 'critical',
                'title' => 'Database Connection Issues',
                'message' => 'Unable to connect to database or retrieve statistics.',
                'icon' => 'heroicon-o-server',
            ];
        }

        // Memory alerts
        if ($healthData['server']['memory_usage']['percentage'] > 90) {
            $alerts[] = [
                'type' => 'critical',
                'title' => 'High Memory Usage',
                'message' => "Memory usage is at {$healthData['server']['memory_usage']['percentage']}%.",
                'icon' => 'heroicon-o-cpu-chip',
            ];
        }

        // Debug mode alert
        if ($healthData['system']['debug_mode'] && $healthData['system']['environment'] === 'production') {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Debug Mode in Production',
                'message' => 'Debug mode is enabled in production environment.',
                'icon' => 'heroicon-o-bug',
            ];
        }

        return $alerts;
    }

    /**
     * Get server uptime in seconds.
     */
    protected function getServerUptime(): int
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $uptime = file_get_contents('/proc/uptime');
            $uptime = explode(' ', $uptime);
            return (int) $uptime[0];
        }
        
        // Fallback for other systems
        return time() - filemtime(storage_path('framework/.gitignore'));
    }

    /**
     * Format uptime for display.
     */
    protected function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";

        return implode(' ', $parts) ?: 'Just started';
    }

    /**
     * Get system load average.
     */
    protected function getLoadAverage(): array
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $load = sys_getloadavg();
            return [
                '1min' => $load[0] ?? 0,
                '5min' => $load[1] ?? 0,
                '15min' => $load[2] ?? 0,
            ];
        }
        
        return ['1min' => 0, '5min' => 0, '15min' => 0];
    }

    /**
     * Get memory usage.
     */
    protected function getMemoryUsage(): array
    {
        $memoryLimit = $this->parseBytes(ini_get('memory_limit'));
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);

        return [
            'current' => $this->formatBytes($memoryUsage),
            'peak' => $this->formatBytes($memoryPeak),
            'limit' => $this->formatBytes($memoryLimit),
            'percentage' => $memoryLimit > 0 ? round(($memoryUsage / $memoryLimit) * 100, 2) : 0,
        ];
    }

    /**
     * Get CPU usage (simplified).
     */
    protected function getCpuUsage(): array
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $load = sys_getloadavg();
            $cpuCount = $this->getCpuCount();
            
            return [
                'percentage' => round(($load[0] / $cpuCount) * 100, 2),
                'cores' => $cpuCount,
            ];
        }
        
        return ['percentage' => 0, 'cores' => 1];
    }

    /**
     * Get CPU count.
     */
    protected function getCpuCount(): int
    {
        if (PHP_OS_FAMILY === 'Linux') {
            return (int) shell_exec('nproc');
        }
        
        return 1;
    }

    /**
     * Get disk total space.
     */
    protected function getDiskTotalSpace(): int
    {
        return disk_total_space('/');
    }

    /**
     * Get disk used space.
     */
    protected function getDiskUsedSpace(): int
    {
        return disk_total_space('/') - disk_free_space('/');
    }

    /**
     * Get slow queries count.
     */
    protected function getSlowQueriesCount(): int
    {
        try {
            $result = DB::select('SHOW GLOBAL STATUS LIKE "Slow_queries"');
            return (int) ($result[0]->Value ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get critical errors.
     */
    protected function getCriticalErrors(): int
    {
        try {
            $logFile = storage_path('logs/laravel.log');
            if (!file_exists($logFile)) {
                return 0;
            }

            $criticalCount = 0;
            $lines = file($logFile);
            
            foreach (array_slice($lines, -1000) as $line) { // Check last 1000 lines
                if (strpos($line, 'CRITICAL') !== false || strpos($line, 'FATAL') !== false) {
                    $criticalCount++;
                }
            }

            return $criticalCount;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Parse bytes from PHP ini value.
     */
    protected function parseBytes(string $value): int
    {
        $unit = strtolower(substr($value, -1));
        $value = (int) $value;
        
        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }

    /**
     * Format bytes for display.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
