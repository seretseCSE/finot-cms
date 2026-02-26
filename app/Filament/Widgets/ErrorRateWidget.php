<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class ErrorRateWidget extends Widget
{
    protected static ?int $sort = 6;

    protected function getStats(): array
    {
        $errorCount = DB::table('error_logs')
            ->where('created_at', '>', now()->subHour())
            ->count();
        
        $totalRequests = DB::table('error_logs')
            ->where('created_at', '>', now()->subHour())
            ->count();
        
        $errorRate = $totalRequests > 0 ? round(($errorCount / $totalRequests) * 100, 2) : 0;
        
        return [
            Stat::make('Error Rate', $errorRate . '/hr')
                ->description('Errors per hour')
                ->icon('heroicon-o-exclamation-circle')
                ->color($errorRate > 10 ? 'danger' : 'warning'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()->role === 'superadmin';
    }
}

