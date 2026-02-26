<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardWidgetFactory;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard';

    public static function getNavigationLabel(): string { return 'Dashboard'; }

    public static function getNavigationIcon(): ?string { return 'heroicon-o-home'; }

    protected static ?int $navigationSort = -2;

    /**
     * Get widgets for page.
     */
    public function getWidgets(): array
    {
        return DashboardWidgetFactory::getWidgets();
    }

}

