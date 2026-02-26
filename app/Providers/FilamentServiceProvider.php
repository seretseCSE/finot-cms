<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentColor;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class FilamentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configure default avatar provider
        FilamentView::registerRenderHook(
            'panels::user-menu.start',
            fn (): string => Blade::render('
                <x-filament::user-menu.account 
                    :name="$this->getUser()->name"
                    :email="$this->getUser()->email"
                    :avatar-url="$this->getUser()->avatar_url ?? null"
                />
            ')
        );

        // Customize Filament colors
        FilamentColor::register([
            'primary' => '#3B82F6',
            'secondary' => '#6B7280',
            'success' => '#10B981',
            'warning' => '#F59E0B',
            'danger' => '#EF4444',
            'info' => '#06B6D4',
        ]);

        // Customize Filament icons
        FilamentIcon::register([
            'panels::user-menu.account' => 'heroicon-o-user-circle',
            'panels::sidebar.group.collapse-button' => 'heroicon-o-chevron-down',
            'panels::sidebar.navigation-item' => 'heroicon-o-chevron-right',
        ]);
    }
}
