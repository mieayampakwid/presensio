<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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
    |----------------------------------------------------------------
    | WAHA — self-hosted WhatsApp HTTP API (spec 05 §Decisions)
    |----------------------------------------------------------------
    |
    | The absence-alert WhatsApp channel. Point WAHA_BASE_URL at the
    | WAHA container (e.g. http://waha:3000); set WAHA_API_KEY only if
    | the WAHA server itself enforces one (its WAHA_API_KEY). An empty
    | base URL makes the client throw — a loud misconfiguration that
    | surfaces as failed jobs, never a silently skipped notification.
    |
    */

    'waha' => [
        'base_url' => env('WAHA_BASE_URL'),
        'api_key' => env('WAHA_API_KEY'),
        'session' => env('WAHA_SESSION', 'default'),
    ],

];
