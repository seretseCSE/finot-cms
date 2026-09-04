<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | check.et — Ethiopian bank/wallet payment verification. The key is a
    | business-account secret (chk_…): env only, never committed. When
    | disabled (local dev without a key), the client reports verification
    | as unavailable instead of calling out.
    */
    'check_et' => [
        'key' => env('CHECK_ET_API_KEY'),
        'enabled' => env('CHECK_ET_ENABLED', true),
        'base_url' => env('CHECK_ET_BASE_URL', 'https://api.check.et/api/v1'),
    ],

    /*
    | Payment gateways — Temari.et's OWN collections only (tutoring escrow,
    | AI subscription, boosts, School Plan). School fees never touch these.
    | Which gateway is enabled / serves which purpose is a platform setting
    | (PaymentGateways); credentials live here, env-only.
    */
    'chapa' => [
        'secret_key' => env('CHAPA_SECRET_KEY'),
        'webhook_secret' => env('CHAPA_WEBHOOK_SECRET'),
        'base_url' => env('CHAPA_BASE_URL', 'https://api.chapa.co/v1'),
    ],

    'telebirr' => [
        'fabric_app_id' => env('TELEBIRR_FABRIC_APP_ID'),
        'app_secret' => env('TELEBIRR_APP_SECRET'),
        'merchant_app_id' => env('TELEBIRR_MERCHANT_APP_ID'),
        'short_code' => env('TELEBIRR_SHORT_CODE'),
        'private_key' => env('TELEBIRR_PRIVATE_KEY'),
        'base_url' => env('TELEBIRR_BASE_URL', 'https://196.188.120.3:38443/apiaccess/payment/gateway'),
        'web_base_url' => env('TELEBIRR_WEB_BASE_URL', 'https://196.188.120.3:38443'),
    ],

    'cbebirr' => [
        // Reserved: direct CBE Birr needs a merchant agreement with CBE.
        'api_key' => env('CBEBIRR_API_KEY'),
    ],

    /*
    | PostHog — product analytics + error tracking for the whole platform.
    | The project API key is a public write-only token. Everything no-ops
    | when the key is unset (local dev, tests), and every capture is queued
    | so no request ever waits on the analytics API.
    */
    'posthog' => [
        'key' => env('POSTHOG_KEY'),
        'host' => env('POSTHOG_HOST', 'https://us.i.posthog.com'),
    ],

    /*
    | Jitsi — online tutoring rooms (one unguessable room per session).
    | Point at a self-hosted instance later; meet.jit.si works out of the box.
    */
    'jitsi' => [
        'base_url' => env('JITSI_BASE_URL', 'https://meet.jit.si'),
    ],

    /*
    | Cloudflare Browser Rendering — official PDFs (receipts, letters,
    | transcripts, statements, payslips) render through its REST /pdf
    | endpoint, so no headless Chrome ever runs on our own servers. The
    | token needs the Browser Rendering permission.
    */
    'cloudflare' => [
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
    ],

];
