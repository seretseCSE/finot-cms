<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class EditProfile extends Page
{
    protected static ?string $title = 'Edit Profile';

    public function getTitle(): string
    {
        return 'Edit Profile';
    }

    public function getHeading(): string
    {
        return 'My Profile';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->check();
    }
}

