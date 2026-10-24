<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** @property Carbon $read_at
 * @property Carbon|null $acknowledged_at
 * @property int $user_id
 */
#[Fillable(['announcement_id', 'user_id', 'read_at', 'acknowledged_at'])]
class AnnouncementReceipt extends Model
{
    public $timestamps = false;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    }
}
