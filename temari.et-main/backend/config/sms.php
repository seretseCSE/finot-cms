<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Sending Toggle
    |--------------------------------------------------------------------------
    |
    | When false, outgoing SMS messages are written to the log instead of being
    | dispatched to the provider. Keep this false locally and true everywhere
    | else so real messages are only sent from staging/production.
    |
    */

    'enabled' => env('SMS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Safaricom Ethiopia Numbers
    |--------------------------------------------------------------------------
    |
    | The current SMS provider only delivers to Ethio Telecom (09…) numbers —
    | Safaricom (07…) recipients make the whole batch fail with a 400. While
    | this is false, 07… numbers are rejected EVERYWHERE a phone is accepted
    | (App\Support\PhoneNumber is the single gate) and skipped from SMS sends.
    | Flip to true once the provider can deliver to Safaricom. Keep in sync
    | with the frontend's NEXT_PUBLIC_ALLOW_SAFARICOM.
    |
    */

    'allow_safaricom' => env('SMS_ALLOW_SAFARICOM', false),

    /*
    |--------------------------------------------------------------------------
    | Tiltek Provider Credentials
    |--------------------------------------------------------------------------
    */

    'base_url' => env('SMS_BASE_URL', 'https://tiltek.et'),
    'account_id' => env('SMS_ACCOUNT_ID'),
    'token' => env('SMS_TOKEN'),
    'code_id' => env('SMS_CODE_ID'),

    /*
    |--------------------------------------------------------------------------
    | Frontend URL
    |--------------------------------------------------------------------------
    |
    | Base URL used when building links sent over SMS (e.g. set-password links).
    |
    */

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

];
