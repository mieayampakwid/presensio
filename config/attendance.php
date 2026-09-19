<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scanner device keys
    |--------------------------------------------------------------------------
    |
    | Hardware scanners authenticate on POST /api/attendance/scan with one of
    | these shared keys in the X-Scanner-Key header. Comma-separated so a key
    | rotates with zero downtime: add the new key, redeploy scanners, then
    | drop the old one (spec 03 §Decisions "Scanner Authentication").
    |
    */

    'scanner_keys' => array_filter(explode(',', (string) env('ATTENDANCE_SCANNER_KEYS', env('ATTENDANCE_SCANNER_KEY', '')))),

    /*
    |--------------------------------------------------------------------------
    | Dynamic QR tokens
    |--------------------------------------------------------------------------
    |
    | Server-issued, HMAC-signed tokens (spec 03 §Decisions "Server-Issued QR
    | Tokens"). The signing key falls back to APP_KEY when unset. Freshness
    | window is symmetric: |now - issued_at| beyond the TTL rejects.
    |
    */

    'qr' => [
        'ttl_seconds' => (int) env('ATTENDANCE_QR_TTL', 30),
        'signing_key' => env('ATTENDANCE_QR_SIGNING_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Holiday feed
    |--------------------------------------------------------------------------
    |
    | One-way source for non_school_days sync rows (spec 03 §Requirements 7).
    | Nager.Date is free and keyless; country_code ID = Indonesia.
    |
    */

    'holiday_feed' => [
        'base_url' => env('HOLIDAY_FEED_BASE_URL', 'https://date.nager.at/api/v3'),
        'country_code' => 'ID',
    ],

];
