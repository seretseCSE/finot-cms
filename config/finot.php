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
];
