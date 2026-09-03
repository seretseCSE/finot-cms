<?php

namespace App\Filament\Support;

use Filament\Widgets\StatsOverviewWidget\Stat;
use Throwable;

class ClickableStat
{
    public static function make(string $label, mixed $value, ?string $url = null): Stat
    {
        $stat = Stat::make($label, $value);

        if (filled($url)) {
            $stat->url($url)->extraAttributes([
                'class' => 'cursor-pointer transition hover:ring-2 hover:ring-primary-500/25',
            ]);
        }

        return $stat;
    }

    public static function resourceUrl(string $resourceClass, string $page = 'index'): ?string
    {
        try {
            return $resourceClass::getUrl($page);
        } catch (Throwable) {
            return null;
        }
    }

    public static function pageUrl(string $pageClass): ?string
    {
        try {
            return $pageClass::getUrl();
        } catch (Throwable) {
            return null;
        }
    }
}
