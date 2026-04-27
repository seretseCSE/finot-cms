<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Livewire Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure Livewire's core behavior. If you find yourself
    | needing to change a default behavior, you're in the right place.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Class Namespace
    |--------------------------------------------------------------------------
    |
    | This value controls the default class namespace for Livewire components.
    | By default, Livewire will search for components in the `App\Livewire`
    | namespace. If you have a different namespace, you can change it here.
    |
    */

    'class_namespace' => 'App\\Livewire',

    /*
    |--------------------------------------------------------------------------
    | Layout View
    |--------------------------------------------------------------------------
    |
    | This value controls the default layout view that will be used when rendering
    | Livewire components. By default, Livewire will use the `layouts.app`
    | view. If you have a different layout, you can change it here.
    |
    */

    'layout' => 'layouts.app',

    /*
    |--------------------------------------------------------------------------
    | Livewire Endpoint Middleware Group
    |--------------------------------------------------------------------------
    |
    | This value controls the default middleware group that will be applied to
    | all Livewire endpoints. By default, Livewire will use the `web` middleware
    | group. If you have a different middleware group, you can change it here.
    |
    */

    'middleware_group' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Livewire Route Middleware
    |--------------------------------------------------------------------------
    |
    | This value controls the default middleware that will be applied to all
    | Livewire routes. By default, Livewire will use the `web` middleware.
    | If you have a different middleware, you can change it here.
    |
    */

    'middleware' => [
        'web',
        \Livewire\Middleware\HandleComponents::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Livewire Temporary File Uploads
    |--------------------------------------------------------------------------
    |
    | This value controls the temporary file upload directory for Livewire.
    | By default, Livewire will use the `livewire-tmp` directory in the storage
    | path. If you have a different directory, you can change it here.
    |
    */

    'tmp_dir' => storage_path('app/livewire-tmp'),

    /*
    |--------------------------------------------------------------------------
    | Livewire Asset Middleware
    |--------------------------------------------------------------------------
    |
    | This value controls the default middleware that will be applied to all
    | Livewire asset routes. By default, Livewire will use the `web` middleware.
    | If you have a different middleware, you can change it here.
    |
    */

    'asset_middleware' => [
        'web',
    ],

    /*
    |--------------------------------------------------------------------------
    | Livewire Script Route
    |--------------------------------------------------------------------------
    |
    | This value controls the default route that will be used to serve Livewire
    | JavaScript. By default, Livewire will use the `/livewire/livewire.js` route.
    | If you have a different route, you can change it here.
    |
    */

    'script_route' => '/livewire/livewire.js',

    /*
    |--------------------------------------------------------------------------
    | Livewire Update Route
    |--------------------------------------------------------------------------
    |
    | This value controls the default route that will be used to handle Livewire
    | updates. By default, Livewire will use the `/livewire/update` route.
    | If you have a different route, you can change it here.
    |
    */

    'update_route' => '/livewire/update',

    /*
    |--------------------------------------------------------------------------
    | Livewire Lazy Loading
    |--------------------------------------------------------------------------
    |
    | This value controls whether Livewire components should be lazy loaded.
    | By default, Livewire will lazy load components. If you want to disable
    | lazy loading, you can change it here.
    |
    */

    'lazy_loading' => true,

    /*
    |--------------------------------------------------------------------------
    | Livewire Render On Save
    |--------------------------------------------------------------------------
    |
    | This value controls whether Livewire components should render on save.
    | By default, Livewire will render on save. If you want to disable
    | render on save, you can change it here.
    |
    */

    'render_on_save' => true,

    /*
    |--------------------------------------------------------------------------
    | Livewire Eager Loading
    |--------------------------------------------------------------------------
    |
    | This value controls whether Livewire components should be eager loaded.
    | By default, Livewire will eager load components. If you want to disable
    | eager loading, you can change it here.
    |
    */

    'eager_loading' => true,

    /*
    |--------------------------------------------------------------------------
    | Livewire Manifest
    |--------------------------------------------------------------------------
    |
    | This value controls whether Livewire should generate a manifest file.
    | By default, Livewire will generate a manifest file. If you want to disable
    | manifest generation, you can change it here.
    |
    */

    'manifest' => true,

    /*
    |--------------------------------------------------------------------------
    | Livewire Artisan Commands
    |--------------------------------------------------------------------------
    |
    | This value controls whether Livewire should register Artisan commands.
    | By default, Livewire will register Artisan commands. If you want to disable
    | Artisan command registration, you can change it here.
    |
    */

    'artisan_commands' => true,

    /*
    |--------------------------------------------------------------------------
    | Livewire Route
    |--------------------------------------------------------------------------
    |
    | This value controls whether Livewire should register routes.
    | By default, Livewire will register routes. If you want to disable
    | route registration, you can change it here.
    |
    */

    'routes' => true,

    /*
    |--------------------------------------------------------------------------
    | Livewire Views
    |--------------------------------------------------------------------------
    |
    | This value controls whether Livewire should register views.
    | By default, Livewire will register views. If you want to disable
    | view registration, you can change it here.
    |
    */

    'views' => true,

    /*
    |--------------------------------------------------------------------------
    | Livewire Components
    |--------------------------------------------------------------------------
    |
    | This value controls whether Livewire should register components.
    | By default, Livewire will register components. If you want to disable
    | component registration, you can change it here.
    |
    */

    'components' => true,

    /*
    |--------------------------------------------------------------------------
    | Livewire Testing
    |--------------------------------------------------------------------------
    |
    | This value controls whether Livewire should register testing utilities.
    | By default, Livewire will register testing utilities. If you want to disable
    | testing utility registration, you can change it here.
    |
    */

    'testing' => true,

    /*
    |--------------------------------------------------------------------------
    | Livewire Development Mode
    |--------------------------------------------------------------------------
    |
    | This value controls whether Livewire is in development mode.
    | By default, Livewire will be in development mode if the app is in debug mode.
    | If you want to force development mode, you can change it here.
    |
    */

    'development_mode' => env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Livewire On-Demand Validation
    |--------------------------------------------------------------------------
    |
    | This value controls whether Livewire should validate on-demand.
    | By default, Livewire will validate on-demand. If you want to disable
    | on-demand validation, you can change it here.
    |
    */

    'on_demand_validation' => true,

    /*
    |--------------------------------------------------------------------------
    | Livewire Legacy Route Registration
    |--------------------------------------------------------------------------
    |
    | This value controls whether Livewire should use legacy route registration.
    | By default, Livewire will use legacy route registration. If you want to disable
    | legacy route registration, you can change it here.
    |
    */

    'legacy_route_registration' => false,

    /*
    |--------------------------------------------------------------------------
    | Livewire Inject Assets
    |--------------------------------------------------------------------------
    |
    | This value controls whether Livewire should inject assets.
    | By default, Livewire will inject assets. If you want to disable
    | asset injection, you can change it here.
    |
    */

    'inject_assets' => true,

    /*
    |--------------------------------------------------------------------------
    | Livewire Render Path
    |--------------------------------------------------------------------------
    |
    | This value controls the path where Livewire will render components.
    | By default, Livewire will render components in the `livewire` directory.
    | If you have a different directory, you can change it here.
    |
    */

    'render_path' => null,

    /*
    |--------------------------------------------------------------------------
    | Livewire Solo Assets
    |--------------------------------------------------------------------------
    |
    | This value controls whether Livewire should include solo assets.
    | By default, Livewire will include solo assets. If you want to disable
    | solo assets, you can change it here.
    |
    */

    'solo_assets' => false,

    /*
    |--------------------------------------------------------------------------
    | Livewire Manifest Path
    |--------------------------------------------------------------------------
    |
    | This value controls the path where Livewire will store the manifest file.
    | By default, Livewire will store the manifest in the `bootstrap/cache` directory.
    | If you have a different directory, you can change it here.
    |
    */

    'manifest_path' => null,

    /*
    |--------------------------------------------------------------------------
    | Livewire Package Assets
    |--------------------------------------------------------------------------
    |
    | This value controls whether Livewire should include package assets.
    | By default, Livewire will include package assets. If you want to disable
    | package assets, you can change it here.
    |
    */

    'package_assets' => true,
];
