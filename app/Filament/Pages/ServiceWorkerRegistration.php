<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ServiceWorkerRegistration extends Page
{
    protected string $view = 'filament.pages.service-worker-registration';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
