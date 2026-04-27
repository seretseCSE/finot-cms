<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?int $navigationSort = -2;

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    public function getHeading(): string
    {
        return __('Welcome, :name', ['name' => filament()->auth()->user()->name]);
    }

    public function getWidgets(): array
    {
        return [];
    }
}
