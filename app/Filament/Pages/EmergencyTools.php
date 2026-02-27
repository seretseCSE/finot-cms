<?php

namespace App\Filament\Pages;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms;
use Illuminate\Support\Carbon;

class EmergencyTools extends Page
{
    protected static ?string $title = 'Emergency Tools';

    public $purgeDate;

    public static function canAccess(): bool
    {
        return Auth::user()->hasRole('Superadmin');
    }

    public function getView(): string
    {
        return 'filament.pages.emergency-tools';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('system_status')
                ->label('System Status')
                ->icon('heroicon-o-heart-pulse')
                ->color('info')
                ->action(function () {
                    $this->checkSystemStatus();
                }),
        ];
    }

    /**
     * Force logout all users.
     */
    public function forceLogoutAllUsers(): void
    {
        try {
            // Clear all sessions
            Artisan::call('session:table');
            
            // Truncate sessions table
            \DB::table('sessions')->truncate();
            
            // Clear session files
            $sessionPath = storage_path('framework/sessions');
            if (is_dir($sessionPath)) {
                $files = glob($sessionPath . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }

            // Log action
            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'action' => 'force_logout_all_users',
                    'timestamp' => now(),
                ])
                ->log('emergency_force_logout_all_users');

            Notification::make()
                ->title('All Users Logged Out')
                ->body('All user sessions have been terminated successfully.')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Logout Failed')
                ->body('Failed to logout all users: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Clear all caches.
     */
    public function clearAllCaches(): void
    {
        try {
            $cachesCleared = [];
            
            // Clear application cache
            Artisan::call('cache:clear');
            $cachesCleared[] = 'Application Cache';
            
            // Clear configuration cache
            Artisan::call('config:clear');
            $cachesCleared[] = 'Configuration Cache';
            
            // Clear route cache
            Artisan::call('route:clear');
            $cachesCleared[] = 'Route Cache';
            
            // Clear view cache
            Artisan::call('view:clear');
            $cachesCleared[] = 'View Cache';
            
            // Clear compiled files
            Artisan::call('clear-compiled');
            $cachesCleared[] = 'Compiled Files';
            
            // Clear opcode cache if available
            if (function_exists('opcache_reset')) {
                opcache_reset();
                $cachesCleared[] = 'OPcache';
            }

            // Log action
            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'action' => 'clear_all_caches',
                    'caches_cleared' => $cachesCleared,
                    'timestamp' => now(),
                ])
                ->log('emergency_clear_all_caches');

            Notification::make()
                ->title('All Caches Cleared')
                ->body('Successfully cleared: ' . implode(', ', $cachesCleared))
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Cache Clear Failed')
                ->body('Failed to clear caches: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Run database optimization.
     */
    public function runDatabaseOptimization(): void
    {
        try {
            $optimizations = [];
            
            // Optimize database
            Artisan::call('db:optimize');
            $optimizations[] = 'Database Optimization';
            
            // Cache configuration
            Artisan::call('config:cache');
            $optimizations[] = 'Configuration Cache';
            
            // Cache routes
            Artisan::call('route:cache');
            $optimizations[] = 'Route Cache';
            
            // Cache views
            Artisan::call('view:cache');
            $optimizations[] = 'View Cache';

            // Get database size before and after
            $dbName = config('database.connections.mysql.database');
            $sizeQuery = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size FROM information_schema.tables WHERE table_schema = '{$dbName}'";
            $dbSize = \DB::select($sizeQuery)[0]->size ?? 0;

            // Log action
            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'action' => 'database_optimization',
                    'optimizations' => $optimizations,
                    'database_size_mb' => $dbSize,
                    'timestamp' => now(),
                ])
                ->log('emergency_database_optimization');

            Notification::make()
                ->title('Database Optimized')
                ->body('Optimizations completed: ' . implode(', ', $optimizations) . ". Database size: {$dbSize}MB")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Optimization Failed')
                ->body('Failed to optimize database: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Purge old logs.
     */
    public function purgeOldLogs(string $date): void
    {
        try {
            $purgeDate = Carbon::parse($date);
            $filesDeleted = 0;
            $totalSize = 0;

            // Purge Laravel logs
            $logPath = storage_path('logs');
            if (is_dir($logPath)) {
                $files = glob($logPath . '/*.log');
                foreach ($files as $file) {
                    if (filemtime($file) < $purgeDate->timestamp) {
                        $totalSize += filesize($file);
                        unlink($file);
                        $filesDeleted++;
                    }
                }
            }

            // Purge custom logs if they exist
            $customLogPath = storage_path('app/logs');
            if (is_dir($customLogPath)) {
                $files = glob($customLogPath . '/*');
                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) < $purgeDate->timestamp) {
                        $totalSize += filesize($file);
                        unlink($file);
                        $filesDeleted++;
                    }
                }
            }

            // Log action
            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'action' => 'purge_old_logs',
                    'purge_date' => $date,
                    'files_deleted' => $filesDeleted,
                    'size_freed' => $this->formatBytes($totalSize),
                    'timestamp' => now(),
                ])
                ->log('emergency_purge_old_logs');

            Notification::make()
                ->title('Logs Purged')
                ->body("Deleted {$filesDeleted} log files, freed {$this->formatBytes($totalSize)}")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Log Purge Failed')
                ->body('Failed to purge logs: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Toggle maintenance mode.
     */
    public function toggleMaintenanceMode(bool $enable): void
    {
        try {
            if ($enable) {
                Artisan::call('down');
                $status = 'enabled';
                $message = 'Maintenance mode enabled';
            } else {
                Artisan::call('up');
                $status = 'disabled';
                $message = 'Maintenance mode disabled';
            }

            // Log action
            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'action' => 'toggle_maintenance_mode',
                    'status' => $status,
                    'timestamp' => now(),
                ])
                ->log('emergency_toggle_maintenance_mode');

            Notification::make()
                ->title('Maintenance Mode Updated')
                ->body($message)
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Maintenance Mode Failed')
                ->body('Failed to toggle maintenance mode: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Check system status.
     */
    protected function checkSystemStatus(): void
    {
        $status = [];

        // Check if maintenance mode is on
        $status['maintenance'] = file_exists(storage_path('framework/down'));

        // Check cache status
        $status['cache'] = Cache::get('test_key') !== null;
        Cache::put('test_key', 'test', 60);
        $status['cache'] = Cache::get('test_key') === 'test';
        Cache::forget('test_key');

        // Check database connection
        try {
            \DB::select('SELECT 1');
            $status['database'] = true;
        } catch (\Exception $e) {
            $status['database'] = false;
        }

        // Check storage permissions
        $status['storage'] = is_writable(storage_path());

        $allGood = array_filter($status);

        Notification::make()
            ->title('System Status Check')
            ->body(count($allGood) === count($status) ? 'All systems operational' : 'Some issues detected')
            ->color(count($allGood) === count($status) ? 'success' : 'warning')
            ->send();
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
