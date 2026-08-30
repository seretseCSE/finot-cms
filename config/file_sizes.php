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
            'audio/mp3',
            'audio/wav',
            'audio/x-wav',
            'audio/wave',
            'audio/ogg',
            'audio/flac',
            'audio/x-flac',
            'audio/aac',
            'audio/x-aac',
            'audio/mp4',
            'audio/m4a',
            'audio/x-m4a',
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

];
