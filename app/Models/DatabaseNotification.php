<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;

/**
 * Custom DatabaseNotification supporting unique dedupe_key (spec 17 §Schema 1).
 *
 * @property string $dedupe_key
 */
class DatabaseNotification extends BaseDatabaseNotification
{
    protected static function booted(): void
    {
        static::creating(function (DatabaseNotification $notification) {
            if (empty($notification->dedupe_key) && isset($notification->data['key'])) {
                $notification->dedupe_key = (string) $notification->data['key'];
            }
        });
    }
}
