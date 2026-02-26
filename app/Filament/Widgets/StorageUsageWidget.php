<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Storage;

class StorageUsageWidget extends Widget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $totalSpace = disk_total_space('/');
        $freeSpace = disk_free_space('/');
        $usedSpace = $totalSpace - $freeSpace;
        $usedPercentage = ($totalSpace > 0) ? round(($usedSpace / $totalSpace) * 100, 2) : 0;
        
        return [
            Stat::make('Storage Usage', $usedPercentage . '%')
                ->description($usedSpace . 'GB of ' . number_format($totalSpace / 1024, 2) . 'GB used')
                ->icon('heroicon-o-server')
                ->color($usedPercentage > 70 ? 'danger' : ($usedPercentage > 40 ? 'warning' : 'success')),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()->role === 'superadmin';
    }
}

