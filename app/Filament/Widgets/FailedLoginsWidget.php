<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class FailedLoginsWidget extends Widget
{
    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        $failedCount = DB::table('audit_logs')
            ->where('action_type', 'login_failed')
            ->where('created_at', '>', now()->subHour())
            ->count();
        
        return [
            Stat::make('Failed Logins', $failedCount)
                ->description('Failed login attempts in last hour')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($failedCount > 10 ? 'danger' : 'warning'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()->role === 'superadmin';
    }
}

