<?php

namespace App\Models;

use App\Enums\AnnouncementAudienceType;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $body
 * @property AnnouncementAudienceType $audience_type
 * @property string|null $audience_role
 * @property int|null $audience_grade_level
 * @property int|null $class_id
 * @property int|null $academic_year_id
 * @property bool $is_pinned
 * @property bool $requires_acknowledgement
 * @property bool $notify_urgent
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 * @property Carbon|null $published_at
 * @property list<array{type: string, id: int}>|null $notification_recipients
 * @property Carbon|null $notifications_dispatched_at
 * @property int $created_by_user_id
 * @property Carbon $updated_at
 * @property User $author
 */
#[Fillable(['title', 'body', 'audience_type', 'audience_role', 'audience_grade_level', 'class_id', 'academic_year_id', 'is_pinned', 'requires_acknowledgement', 'notify_urgent', 'attachment_path', 'attachment_name', 'published_at', 'created_by_user_id', 'notification_recipients', 'notifications_dispatched_at'])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<AnnouncementReceipt, $this> */
    public function receipts(): HasMany
    {
        return $this->hasMany(AnnouncementReceipt::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['audience_type' => AnnouncementAudienceType::class, 'is_pinned' => 'boolean', 'requires_acknowledgement' => 'boolean', 'notify_urgent' => 'boolean', 'published_at' => 'datetime', 'notification_recipients' => 'array', 'notifications_dispatched_at' => 'datetime'];
    }

    public function isLive(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }
}
