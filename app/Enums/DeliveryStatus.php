<?php

namespace App\Enums;

/**
 * Status lifecycle of a notification delivery attempt (spec 17 §Schema 2).
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case SkippedQuota = 'skipped_quota';
    case SkippedOptOut = 'skipped_opt_out';
    case SkippedNoContact = 'skipped_no_contact';
}
