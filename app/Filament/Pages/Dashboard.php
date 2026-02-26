<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardWidgetFactory;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class Dashboard extends Page
{
    protected string $view = 'filament.pages.dashboard';

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

    /**
     * Get the page's middleware.
     */
    public function getMiddleware(): array
    {
        return ['auth'];
    }
}

