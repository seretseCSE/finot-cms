<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class DatabaseResponseTimeWidget extends Widget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $startTime = microtime(true);
        $result = DB::select(DB::raw('1'))->first();
        $endTime = microtime(true);
        
        $responseTime = round(($endTime - $startTime) * 1000, 2);
        
        return [
            Stat::make('DB Response Time', $responseTime . 'ms')
                ->description('Average database query response time')
                ->icon('heroicon-o-clock')
                ->color($responseTime < 200 ? 'success' : 'warning'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()->role === 'superadmin';
    }
}

