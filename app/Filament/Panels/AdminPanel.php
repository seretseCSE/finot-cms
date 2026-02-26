<?php

namespace App\Filament\Panels;

use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Support\Str;

class AdminPanel extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'))
            ->discoverPages(in: app_path('Filament/Pages'))
            ->middleware([
                'web',
                'throttle:60,1',
            ])
            ->authMiddleware([
                // 'auth',
            ], isPersistent: false)
            ->renderHook(
                'panels::login.form.before',
                fn (): string => view('filament.custom-login-fields'),
            )
            ->brandName('Finotetsidik')
            ->brandLogo(asset('images/logo.png'))
            ->darkModeBrandLogo(asset('images/logo-dark.png'));
    }
}

