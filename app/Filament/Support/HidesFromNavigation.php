<?php

namespace App\Filament\Support;

trait HidesFromNavigation
{
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
