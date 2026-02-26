<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class ActiveSessionsWidget extends Widget
{
    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        $activeCount = DB::table('user_sessions')
            ->where('last_activity', '>', now()->subMinutes(30))
            ->count();
        
        return [
            Stat::make('Active Sessions', $activeCount)
                ->description('Sessions active in last 30 minutes')
                ->icon('heroicon-o-users')
                ->color('primary'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()->role === 'superadmin';
    }
}

