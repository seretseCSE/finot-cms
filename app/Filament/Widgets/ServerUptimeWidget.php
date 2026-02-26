<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Process;

class ServerUptimeWidget extends Widget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $uptime = 0;
        
        // Try to read from /proc/uptime (Linux)
        if (file_exists('/proc/uptime')) {
            $uptime = trim(file_get_contents('/proc/uptime'));
        }
        
        // Fallback to exec command (cross-platform)
        if (empty($uptime)) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $uptime = shell_exec('net stats -s');
            } else {
                $uptime = shell_exec('uptime 2>/dev/null');
            }
        }
        
        $uptimeSeconds = floatval($uptime);
        
        return [
            Stat::make('Server Uptime', $uptimeSeconds . ' seconds')
                ->description('Current server uptime')
                ->icon('heroicon-o-server')
                ->color('success'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()->role === 'superadmin';
    }
}

