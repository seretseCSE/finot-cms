<?php

return [
    /*
    |--------------------------------------------------------------------------
    | File Size Limits
    |--------------------------------------------------------------------------
    |
    | This configuration defines maximum file sizes for different media types.
    | Sizes are specified in kilobytes (KB).
    |
    */

    'max_sizes' => [
        'photos' => 10240,      // 10MB in KB
        'videos' => 51200,      // 50MB in KB
        'audio' => 20480,       // 20MB in KB
        'documents' => null,    // Unlimited (null)
    ],

    /*
    |--------------------------------------------------------------------------
    | File Extensions by Type
    |--------------------------------------------------------------------------
    |
    | Define allowed file extensions for each media type.
    |
    */

    'allowed_extensions' => [
        'photos' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'],
        'videos' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv'],
        'audio' => ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'wma'],
        'documents' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'odt', 'ods', 'odp'],
    ],

    /*
    |--------------------------------------------------------------------------
    | MIME Types by Type
    |--------------------------------------------------------------------------
    |
    | Define allowed MIME types for each media type.
    |
    */

    'mime_types' => [
        'photos' => [
            'image/jpeg',
            'image/png', 
            'image/gif',
            'image/bmp',
            'image/webp',
            'image/svg+xml',
        ],
        'videos' => [
            'video/mp4',
            'video/avi',
            'video/quicktime',
            'video/x-ms-wmv',
            'video/x-flv',
            'video/webm',
            'video/x-matroska',
        ],
        'audio' => [
            'audio/mpeg',
            'audio/wav',
            'audio/ogg',
            'audio/flac',
            'audio/aac',
            'audio/mp4',
            'audio/x-ms-wma',
        ],
        'documents' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/rtf',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.presentation',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Helper Functions
    |--------------------------------------------------------------------------
    |
    | These functions can be used throughout the application to validate
    | file sizes and types.
    |
    */

    'helpers' => [
        'get_max_size_kb' => function (string $type): ?int {
            return config('file_sizes.max_sizes.' . $type);
        },
        
        'get_max_size_mb' => function (string $type): ?float {
            $kb = config('file_sizes.max_sizes.' . $type);
            return $kb ? round($kb / 1024, 2) : null;
        },
        
        'get_max_size_bytes' => function (string $type): ?int {
            $kb = config('file_sizes.max_sizes.' . $type);
            return $kb ? $kb * 1024 : null;
        },
        
        'is_allowed_extension' => function (string $type, string $extension): bool {
            return in_array(strtolower($extension), config('file_sizes.allowed_extensions.' . $type, []));
        },
        
        'is_allowed_mime' => function (string $type, string $mimeType): bool {
            return in_array($mimeType, config('file_sizes.mime_types.' . $type, []));
        },
    ],
];
