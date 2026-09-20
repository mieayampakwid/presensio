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

    /*
    |--------------------------------------------------------------------------
    | Excuse attachments
    |--------------------------------------------------------------------------
    |
    | Proof uploads on guardian excuse submissions (spec 04 §Requirements 1):
    | photos or PDFs such as a doctor's note. Stored on the private local
    | disk under excuses/ and served through an authorized download route.
    |
    */

    'excuses' => [
        'attachment_mimes' => env('EXCUSE_ATTACHMENT_MIMES', 'jpg,jpeg,png,pdf'),
        'attachment_max_kb' => (int) env('EXCUSE_ATTACHMENT_MAX_KB', 5120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Guardian absence notifications (spec 05)
    |--------------------------------------------------------------------------
    |
    | Attendance statuses that notify every linked guardian when a record
    | is CREATED. Default: absences only — a rare, high-signal safety
    | alert. Schools may add present/late/sick/leave (comma-separated), but
    | be aware of the cost before doing so: message volume grows from a
    | handful per day to one per student per status per day, which on the
    | WAHA (unofficial WhatsApp protocol) gateway is a phone-number ban
    | risk, and on paid gateways (Fonnte/Twilio) a per-message bill. Also
    | note the fatigue trade-off: guardians who receive a message every
    | morning tend to mute the number — and with it the absence alert
    | that exists to protect their child.
    |
    */

    'notifications' => [
        'statuses' => array_values(array_filter(explode(',', (string) env('ATTENDANCE_NOTIFICATION_STATUSES', 'absent')))),
    ],

];
