<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'songs-audio' => [
            'driver' => 'local',
            'root' => storage_path('app/songs-audio'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage/songs-audio',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'songs-video' => [
            'driver' => 'local',
            'root' => storage_path('app/songs-video'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage/songs-video',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'media-photos' => [
            'driver' => 'local',
            'root' => storage_path('app/media/photos'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage/media/photos',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'media-videos' => [
            'driver' => 'local',
            'root' => storage_path('app/media/videos'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage/media/videos',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // Module-specific disks
        'members' => [
            'driver' => 'local',
            'root' => storage_path('app/public/members'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage/members',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'tours' => [
            'driver' => 'local',
            'root' => storage_path('app/public/tours'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage/tours',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'media' => [
            'driver' => 'local',
            'root' => storage_path('app/public/media'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage/media',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'documents' => [
            'driver' => 'local',
            'root' => storage_path('app/public/documents'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage/documents',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'library' => [
            'driver' => 'local',
            'root' => storage_path('app/public/library'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage/library',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'blog' => [
            'driver' => 'local',
            'root' => storage_path('app/public/blog'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage/blog',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'events' => [
            'driver' => 'local',
            'root' => storage_path('app/public/events'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage/events',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'fundraising' => [
            'driver' => 'local',
            'root' => storage_path('app/public/fundraising'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage/fundraising',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'songs' => [
            'driver' => 'local',
            'root' => storage_path('app/public/songs'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage/songs',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'inventory' => [
            'driver' => 'local',
            'root' => storage_path('app/public/inventory'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage/inventory',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
        public_path('storage/songs-audio') => storage_path('app/songs-audio'),
        public_path('storage/songs-video') => storage_path('app/songs-video'),
        public_path('storage/media/photos') => storage_path('app/media/photos'),
        public_path('storage/media/videos') => storage_path('app/media/videos'),
    ],

];
