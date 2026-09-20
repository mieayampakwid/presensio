<?php

namespace App\Enums;

/**
 * Lifecycle of one guardian notification for one attendance record
 * (spec 05 §Requirements 3). Rows are written before the send attempt;
 * Sent is terminal, Failed is retried by the queue.
 */
enum NotificationDeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}
