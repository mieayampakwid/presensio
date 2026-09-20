<?php

namespace App\Enums;

/**
 * Delivery medium used for an absence notification (spec 05 §Decisions).
 * WhatsApp is primary; email is the fallback when the guardian has no
 * phone number on file or the WhatsApp send exhausts its retries.
 */
enum NotificationChannel: string
{
    case WhatsApp = 'whatsapp';
    case Email = 'email';
}
