<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Carbon;

class SystemMonitoringService
{
    public function getSystemHealthMetrics(): array
    {
        return Cache::remember('system_health_metrics', 300, function () {
            return [
                'uptime' => $this->getServerUptime(),
                'storage_usage' => $this->getStorageUsage(),
                'db_query_time' => $this->getDatabaseQueryTime(),
                'active_sessions' => $this->getActiveSessionsCount(),
                'error_rate' => $this->getErrorRate(),
                'failed_logins' => $this->getFailedLoginsCount(),
                'memory_usage' => $this->getMemoryUsage(),
                'cpu_usage' => $this->getCpuUsage(),
            ];
        });
    }

    public function getSystemOverviewStats(): array
    {
        return Cache::remember('global_oversight_stats', 300, function () {
            return [
                'total_members' => \App\Models\Member::count(),
                'contributions_this_year' => \App\Models\Contribution::whereYear('created_at', now()->year)->sum('amount'),
                'active_tours' => \App\Models\Tour::where('status', 'active')->count(),
                'total_users' => \App\Models\User::count(),
                'students' => \App\Models\Student::count(),
                'teachers' => \App\Models\Teacher::count(),
                'parents' => \App\Models\Parent::count(),
                'departments' => \App\Models\Department::count(),
                'academic_years' => \App\Models\AcademicYear::count(),
                'enrollments_this_year' => \App\Models\StudentEnrollment::whereYear('created_at', now()->year)->count(),
                'attendance_sessions_today' => \App\Models\AttendanceSession::whereDate('created_at', today())->count(),
                'contributions_this_month' => \App\Models\Contribution::whereMonth('created_at', now()->month)->sum('amount'),
                'new_users_this_month' => \App\Models\User::whereMonth('created_at', now()->month)->count(),
                'active_users_today' => \App\Models\User::whereDate('last_login_at', today())->count(),
            ];
        });
    }

    public function getErrorLogs(int $limit = 100): array
    {
        $logFile = storage_path('logs/laravel.log');
        $logs = [];
        
        if (file_exists($logFile)) {
            // Get logs from the last 2 months
            $cutoffDate = now()->subMonths(2)->format('Y-m-d');
            $lines = file($logFile);
            
            $formattedLogs = [];
            foreach (array_reverse($lines) as $line) {
                $timestamp = $this->extractTimestamp($line);
                if ($timestamp && $timestamp >= $cutoffDate) {
                    if (strpos($line, 'ERROR') !== false || strpos($line, 'CRITICAL') !== false || strpos($line, 'WARNING') !== false) {
                        $formattedLogs[] = [
                            'timestamp' => $timestamp,
                            'level' => $this->extractLogLevel($line),
                            'message' => $this->extractLogMessage($line),
                            'context' => $this->extractLogContext($line),
                            'full_line' => trim($line),
                        ];
                    }
                }
            }
            
            return array_slice($formattedLogs, 0, $limit);
        }
        
        return [];
    }

    public function getChartData(): array
    {
        return [
            'user_registrations' => $this->getUserRegistrationsChart(),
            'contributions' => $this->getContributionsChart(),
            'system_load' => $this->getSystemLoadChart(),
            'error_trends' => $this->getErrorTrendsChart(),
        ];
    }

    protected function getServerUptime(): array
    {
        $uptime = shell_exec('uptime 2>/dev/null') ?: 'Unknown';
        $load = sys_getloadavg() ?: [0, 0, 0];
        
        return [
            'uptime' => $uptime,
            'load_average' => $load,
            'formatted' => $this->formatUptime($uptime),
            'status' => $load[0] > 2 ? 'critical' : ($load[0] > 1 ? 'warning' : 'good'),
        ];
    }

    protected function getStorageUsage(): array
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        $used = $total - $free;
        $percentage = ($used / $total) * 100;

        return [
            'total' => $this->formatBytes($total),
            'used' => $this->formatBytes($used),
            'free' => $this->formatBytes($free),
            'percentage' => round($percentage, 2),
            'status' => $percentage > 80 ? 'critical' : ($percentage > 60 ? 'warning' : 'good'),
        ];
    }

    protected function getMemoryUsage(): array
    {
        $memoryInfo = file_exists('/proc/meminfo') ? file('/proc/meminfo') : [];
        $totalMemory = 0;
        $freeMemory = 0;
        
        foreach ($memoryInfo as $line) {
            if (preg_match('/^MemTotal:\s+(\d+)/', $line, $matches)) {
                $totalMemory = $matches[1];
            }
            if (preg_match('/^MemAvailable:\s+(\d+)/', $line, $matches)) {
                $freeMemory = $matches[1];
            }
        }

        if ($totalMemory > 0) {
            $usedMemory = $totalMemory - $freeMemory;
            $percentage = ($usedMemory / $totalMemory) * 100;
            
            return [
                'total' => $this->formatBytes($totalMemory * 1024),
                'used' => $this->formatBytes($usedMemory * 1024),
                'free' => $this->formatBytes($freeMemory * 1024),
                'percentage' => round($percentage, 2),
                'status' => $percentage > 80 ? 'critical' : ($percentage > 60 ? 'warning' : 'good'),
            ];
        }

        return [
            'total' => 'Unknown',
            'used' => 'Unknown',
            'free' => 'Unknown',
            'percentage' => 0,
            'status' => 'unknown',
        ];
    }

    protected function getCpuUsage(): array
    {
        $load = sys_getloadavg();
        
        return [
            'load_1min' => $load[0],
            'load_5min' => $load[1] ?? 0,
            'load_15min' => $load[2] ?? 0,
            'status' => $load[0] > 2 ? 'critical' : ($load[0] > 1 ? 'warning' : 'good'),
        ];
    }

    protected function getDatabaseQueryTime(): float
    {
        $start = microtime(true);
        DB::select('SELECT 1');
        $end = microtime(true);
        
        return round(($end - $start) * 1000, 2); // Return in milliseconds
    }

    protected function getActiveSessionsCount(): int
    {
        return DB::table('sessions')->count();
    }

    protected function getErrorRate(): array
    {
        $logFile = storage_path('logs/laravel.log');
        $totalLogs = 0;
        $errorLogs = 0;
        
        if (file_exists($logFile)) {
            $logs = file($logFile);
            $totalLogs = count($logs);
            
            foreach ($logs as $log) {
                if (strpos($log, 'ERROR') !== false || strpos($log, 'CRITICAL') !== false) {
                    $errorLogs++;
                }
            }
        }

        $rate = $totalLogs > 0 ? ($errorLogs / $totalLogs) * 100 : 0;

        return [
            'total_logs' => $totalLogs,
            'error_logs' => $errorLogs,
            'rate' => round($rate, 2),
            'status' => $rate > 5 ? 'critical' : ($rate > 2 ? 'warning' : 'good'),
        ];
    }

    protected function getFailedLoginsCount(): int
    {
        return \App\Models\User::where('failed_login_attempts', '>', 0)->sum('failed_login_attempts');
    }

    protected function getUserRegistrationsChart(): array
    {
        $data = \App\Models\User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'labels' => $data->pluck('date')->map(fn($date) => Carbon::parse($date)->format('M j')),
            'data' => $data->pluck('count'),
        ];
    }

    protected function getContributionsChart(): array
    {
        $data = \App\Models\Contribution::selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'labels' => $data->pluck('date')->map(fn($date) => Carbon::parse($date)->format('M j')),
            'data' => $data->pluck('total'),
        ];
    }

    protected function getSystemLoadChart(): array
    {
        $load = sys_getloadavg();
        
        return [
            'labels' => ['1 min', '5 min', '15 min'],
            'data' => $load,
        ];
    }

    protected function getErrorTrendsChart(): array
    {
        $errorData = [];
        $labels = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $logFile = storage_path('logs/laravel-' . $date->format('Y-m-d') . '.log');
            
            $errorCount = 0;
            if (file_exists($logFile)) {
                $logs = file($logFile);
                foreach ($logs as $log) {
                    if (strpos($log, 'ERROR') !== false || strpos($log, 'CRITICAL') !== false) {
                        $errorCount++;
                    }
                }
            }
            
            $labels[] = $date->format('M j');
            $errorData[] = $errorCount;
        }

        return [
            'labels' => $labels,
            'data' => $errorData,
        ];
    }

    protected function extractTimestamp(string $line): string
    {
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            return $matches[1];
        }
        return '';
    }

    protected function extractLogLevel(string $line): string
    {
        if (strpos($line, 'CRITICAL') !== false) return 'CRITICAL';
        if (strpos($line, 'ERROR') !== false) return 'ERROR';
        if (strpos($line, 'WARNING') !== false) return 'WARNING';
        return 'INFO';
    }

    protected function extractLogMessage(string $line): string
    {
        if (preg_match('/\.(CRITICAL|ERROR|WARNING):\s*(.+?)(?:\s*\{|\s*$)/', $line, $matches)) {
            return trim($matches[2]);
        }
        return '';
    }

    protected function extractLogContext(string $line): string
    {
        if (preg_match('/\{.*\}/', $line, $matches)) {
            return $matches[0];
        }
        return '';
    }

    protected function formatUptime(string $uptime): string
    {
        if (preg_match('/up\s+(.+?),\s+\d+\s+user/', $uptime, $matches)) {
            return trim($matches[1]);
        }
        return 'Unknown';
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function clearCache(): void
    {
        Cache::forget('system_health_metrics');
        Cache::forget('global_oversight_stats');
        Cache::forget('global_oversight_error_logs');
    }
}
