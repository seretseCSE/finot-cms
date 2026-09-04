<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ethiopian Phone Prefix
    |--------------------------------------------------------------------------
    |
    | The country code prefix used for Ethiopian phone numbers.
    |
    */
    'phone_prefix' => '+251',

    /*
    |--------------------------------------------------------------------------
    | Session Timeout
    |--------------------------------------------------------------------------
    |
    | Number of minutes of inactivity before a user session is considered
    | expired and subject to cleanup.
    |
    */
    'session_timeout_minutes' => 30,

    /*
    |--------------------------------------------------------------------------
    | Maximum Active Sessions Per User
    |--------------------------------------------------------------------------
    |
    | Maximum number of concurrent active sessions allowed per user.
    | When a user logs in and exceeds this limit, the oldest session will
    | be automatically invalidated to make room for the new session.
    | Set to null to disable session limiting.
    |
    */
    'max_active_sessions' => 3,

    /*
    |--------------------------------------------------------------------------
    | Failed Login Lockout Threshold
    |--------------------------------------------------------------------------
    |
    | Number of consecutive failed login attempts before the account is
    | temporarily locked.
    |
    */
    'failed_login_lockout_threshold' => 5,

    /*
    |--------------------------------------------------------------------------
    | Password History Count
    |--------------------------------------------------------------------------
    |
    | Number of previous passwords to retain and check against when a user
    | changes their password.
    |
    */
    'password_history_count' => 3,

    /*
    |--------------------------------------------------------------------------
    | Member Code Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix used when auto-generating member identification codes.
    |
    */
    'member_code_prefix' => 'M-',

    /*
    |--------------------------------------------------------------------------
    | Tour Passenger Code Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix used when auto-generating tour passenger identification codes.
    |
    */
    'tour_passenger_code_prefix' => 'TP-',

    /*
    |--------------------------------------------------------------------------
    | SMS Gateway (YegnaTele by Tiltek Technology)
    |--------------------------------------------------------------------------
    |
    | Configure your YegnaTele SMS gateway credentials here.
    | Set YEGNATELE_API_KEY and YEGNATELE_BASE_URL in .env once Tiltek
    | provides your production credentials.
    |
    */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'null'),
        'base_url' => env('YEGNATELE_BASE_URL', ''),
        'api_key' => env('YEGNATELE_API_KEY', ''),
        'sender_id' => env('YEGNATELE_SENDER_ID', 'FinotTsidik'),
        'timeout' => env('YEGNATELE_TIMEOUT', 15),
    ],

    'vapid' => [
        'subject' => env('VAPID_SUBJECT', env('APP_URL', 'mailto:admin@finot.local')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Promotion pass mark
    |--------------------------------------------------------------------------
    |
    | Minimum annual average (from computed term results) to suggest Pass on
    | the promotion board. Subject credits on fail transfer use the same mark.
    |
    */
    'promotion_pass_mark' => 50,
];
