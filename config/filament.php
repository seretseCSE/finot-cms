<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Panel
    |--------------------------------------------------------------------------
    |
    | This option controls the default filament panel that should be used.
    |
    */

    'default' => 'admin',

    /*
    |--------------------------------------------------------------------------
    | Panels
    |--------------------------------------------------------------------------
    |
    | This option registers the filament panels for your application.
    |
    */

    'panels' => [
        'admin' => [
            'id' => 'admin',
            'path' => '/admin',
            'login' => \App\Filament\Pages\Auth\Login::class,
            'brand' => [
                'name' => 'FINOTE TSIDIK',
                'logo' => 'storage/logo.png',
                'logoHeight' => '80px',
            ],
            'colors' => [
                'primary' => '#1941F5',
                'danger' => '#C0392B',
                'success' => '#1E8449',
                'warning' => '#D4AC0D',
            ],
            'font' => 'Noto Sans Ethiopic',
            'defaultAvatarProvider' => null,
            'topNavigation' => false,
            'collapsibleNavigationGroups' => true,
            'globalSearch' => true,
            'navigationGroups' => [
                'Membership Management',
                'Education Management', 
                'Financial Management',
                'Inventory Management',
                'Tour Management',
                'Content Management',
                'System',
            ],
            'pages' => [
                \App\Filament\Pages\Auth\ChangeInitialPassword::class,
                \App\Filament\Pages\EditProfile::class,
                \App\Filament\Pages\ManageActiveSessions::class,
                \App\Filament\Pages\ManageCustomOptions::class,
            ],
            'middleware' => [
                'web',
            ],
            'authMiddleware' => [
                'auth',
                'force.password.change',
            ],
        ],
    ],
];
