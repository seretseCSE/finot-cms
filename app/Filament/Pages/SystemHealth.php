<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Filament\Widgets\ServerUptimeWidget;
use App\Filament\Widgets\DatabaseResponseTimeWidget;
use App\Filament\Widgets\StorageUsageWidget;
use App\Filament\Widgets\ActiveSessionsWidget;
use App\Filament\Widgets\FailedLoginsWidget;
use App\Filament\Widgets\ErrorRateWidget;

class SystemHealth extends Page
{
    public static function getNavigationIcon(): ?string { return 'heroicon-o-cog-6-tooth'; }

    public static function getNavigationLabel(): string { return 'System Health'; }

    protected static ?string $title = 'System Health';

    protected static ?string $slug = 'system-health';

    protected static array $widgets = [
        ServerUptimeWidget::class,
        DatabaseResponseTimeWidget::class,
        StorageUsageWidget::class,
        ActiveSessionsWidget::class,
        FailedLoginsWidget::class,
        ErrorRateWidget::class,
    ];

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()->role === 'superadmin';
    }

    public function getWidgets(): array
    {
        return static::$widgets;
    }
}

