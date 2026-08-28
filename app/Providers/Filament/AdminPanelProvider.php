<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\EditProfile;
use App\Filament\Pages\ManageActiveSessions;
use App\Filament\Support\AdminNavigation;
use App\Http\Middleware\ForcePasswordChange;
use App\Services\ProductTour\ProductTourService;
use App\Support\RoleGate;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->default()
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->brandName('FINOTE TSIDIK')
            ->brandLogo(fn () => asset('images/logow.PNG'))
            ->darkModeBrandLogo(fn () => asset('images/logo2.png'))
            ->brandLogoHeight('2.75rem')
            ->colors([
                'primary' => '#1941F5',
                'danger' => '#C0392B',
                'success' => '#1E8449',
                'warning' => '#D4AC0D',
            ])
            ->font('Inter', provider: LocalFontProvider::class)
            ->topNavigation(false)
            ->collapsibleNavigationGroups(true)
            ->navigationGroups(AdminNavigation::groups())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->favicon(asset('images/logo2.png'))
            ->pages([
                Dashboard::class,
                \App\Filament\Pages\Auth\ChangeInitialPassword::class,
            ])
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label('Profile')
                    ->icon('heroicon-o-user-circle')
                    ->url(fn (): string => EditProfile::getUrl()),
                'sessions' => MenuItem::make()
                    ->label('Manage Sessions')
                    ->url(fn (): string => ManageActiveSessions::getUrl())
                    ->icon('heroicon-o-clock')
                    ->visible(fn (): bool => RoleGate::isAny(['superadmin', 'admin'])),
                'restart_tour' => MenuItem::make()
                    ->label('Restart Tour')
                    ->icon('heroicon-o-question-mark-circle')
                    ->url('#')
                    ->visible(fn (): bool => ProductTourService::isAvailableStatic()),
            ])
            ->widgets([])
            ->databaseNotifications()
            ->resources([
                // MediaResource is already discovered via discoverResources
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<link rel="manifest" href="/manifest.json"><meta name="theme-color" content="#1B4F72"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><meta name="apple-mobile-web-app-title" content="FINOTE">',
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => (string) view('filament.components.pwa-admin-scripts')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => (string) view('filament.components.tour-init')->render(),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => auth()->check() ? (string) view('filament.components.in-app-bell')->render() : '',
            )
            ->authMiddleware([
                Authenticate::class,
                ForcePasswordChange::class,
            ]);
    }
}
