<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Auth\ChangeInitialPassword;
use App\Filament\Pages\EditProfile;
use App\Filament\Pages\ManageActiveSessions;
use App\Filament\Pages\ManageCustomOptions;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\AvatarProviders\UiAvatarsProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('/admin')
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->brandName('FINOTE TSIDIK')
            ->brandLogo(fn() => asset('storage/logo.png'))
            ->brandLogoHeight('80px')
            ->colors([
                'primary' => '#1941F5',
                'danger' => '#C0392B',
                'success' => '#1E8449',
                'warning' => '#D4AC0D',
            ])
            ->font('Noto Sans Ethiopic', 'Noto Sans')
            ->defaultAvatarProvider(UiAvatarsProvider::class)
            ->topNavigation(false)
            ->collapsibleNavigationGroups(true)
            ->globalSearch()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->navigationGroups([
                NavigationGroup::make('Membership Management')
                    ->label('Membership Management')
                    ->icon('heroicon-o-users'),
                NavigationGroup::make('Education Management')
                    ->label('Education Management')
                    ->icon('heroicon-o-academic-cap'),
                NavigationGroup::make('Financial Management')
                    ->label('Financial Management')
                    ->icon('heroicon-o-currency-dollar'),
                NavigationGroup::make('Inventory Management')
                    ->label('Inventory Management')
                    ->icon('heroicon-o-cube'),
                NavigationGroup::make('Tour Management')
                    ->label('Tour Management')
                    ->icon('heroicon-o-map'),
                NavigationGroup::make('Content Management')
                    ->label('Content Management')
                    ->icon('heroicon-o-document-text'),
                NavigationGroup::make('System')
                    ->label('System'),
            ])
            ->pages([
                \App\Filament\Pages\Dashboard::class,
                ChangeInitialPassword::class,
                EditProfile::class,
                ManageActiveSessions::class,
                ManageCustomOptions::class,
            ])
            ->bootUsing(function () {
                foreach (\Filament\Facades\Filament::getResources() as $resourceClass) {
                    $pages = $resourceClass::getPages();
                    foreach ($pages as $key => $reg) {
                        if (is_string($reg)) {
                            \Illuminate\Support\Facades\Log::error("Bad string in getPages() - Resource: {$resourceClass}, Key: {$key}, Value: {$reg}");
                        }
                    }
                }
            })
            ->middleware([
                'web',
            ])
            ->authMiddleware([
                'auth',
                'force.password.change', // Apply only to authenticated routes
            ]);
    }
}
