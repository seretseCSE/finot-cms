<?php

namespace App\Providers\Filament;

use App\Filament\Resources\MediaResource;
use App\Http\Middleware\ForcePasswordChange;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Navigation\MenuItem;
use App\Filament\Pages\EditProfile;
use App\Filament\Pages\ManageActiveSessions;
use App\Http\Middleware\TrackUserSessions;
use App\Http\Middleware\SessionTimeoutMiddleware;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->brandName('FINOTE TSIDIK')
            ->brandLogo(fn () => asset('storage/logo.png'))
            ->brandLogoHeight('80px')
            ->colors([
                'primary' => '#1941F5',
                'danger' => '#C0392B',
                'success' => '#1E8449',
                'warning' => '#D4AC0D',
            ])
            ->font('Noto Sans Ethiopic', 'Noto Sans')
            ->topNavigation(false)
            ->collapsibleNavigationGroups(true)
            ->navigationGroups([
                'Membership Management',
                'Education Management',
                'Contributions',
                'Financial Reports',
                'Revenue & Banking',
                'Charity Management',
                'Inventory Management',
                'Tour Management',
                'Content Management',
                'System',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->favicon(asset('storage/logo.png'))
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
                    ->visible(fn (): bool => auth()->user()?->hasAnyRole(['superadmin', 'admin'])),
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
                TrackUserSessions::class,
                SessionTimeoutMiddleware::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<link rel="manifest" href="/manifest.json"><meta name="theme-color" content="#1B4F72"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><meta name="apple-mobile-web-app-title" content="FINOTE">',
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.components.pwa-admin-scripts'),
            )
            ->authMiddleware([
                Authenticate::class,
                ForcePasswordChange::class,
            ]);
    }
}
