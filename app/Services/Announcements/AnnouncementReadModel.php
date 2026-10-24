<?php

namespace App\Services\Announcements;

use App\Models\Announcement;
use App\Models\AnnouncementReceipt;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolSettings;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class AnnouncementReadModel
{
    public function __construct(private readonly AnnouncementAudience $audience, private readonly SchoolSettings $settings) {}

    /** @return array<string,mixed> */
    public function row(Announcement $item, User $user, bool $detail = false): array
    {
        $item->loadMissing('author');
        $receipt = $item->receipts->firstWhere('user_id', $user->id);

        return [
            'id' => $item->id, 'title' => $item->title, 'excerpt' => Str::limit(trim(preg_replace('/\\s+/', ' ', strip_tags(app(MarkdownRenderer::class)->render($item->body))) ?? ''), 180), 'body' => $detail ? $item->body : null,
            'author' => $item->author->username, 'published_at' => $item->published_at?->timezone($this->settings->timezone())->format('Y-m-d H:i:s'),
            'updated_at' => $item->updated_at->timezone($this->settings->timezone())->format('Y-m-d H:i:s'),
            'is_updated' => $item->updated_at->gt($item->created_at),
            'status' => $item->published_at === null ? 'Draf' : ($item->isLive() ? 'Terbit' : 'Terjadwal'),
            'audience_type' => $item->audience_type->value, 'audience_role' => $item->audience_role, 'audience_grade_level' => $item->audience_grade_level, 'class_id' => $item->class_id,
            'is_pinned' => $item->is_pinned, 'requires_acknowledgement' => $item->requires_acknowledgement, 'notify_urgent' => $item->notify_urgent,
            'attachment_name' => $item->attachment_name, 'read_at' => $receipt?->read_at?->toIso8601String(), 'acknowledged_at' => $receipt?->acknowledged_at?->toIso8601String(),
            'can_manage' => $this->audience->canManage($user, $item), 'can_report' => $this->audience->canManage($user, $item), 'can_acknowledge' => $item->isLive() && $item->requires_acknowledgement && $receipt?->acknowledged_at === null,
        ];
    }

    /** @return array<string,mixed> */
    public function summary(User $user): array
    {
        $query = $this->audience->visibleTo($user);
        $count = (clone $query)->where('requires_acknowledgement', true)->whereDoesntHave('receipts', fn ($q) => $q->where('user_id', $user->id)->whereNotNull('acknowledged_at'))->count();

        return ['items' => $query->with(['author', 'receipts' => fn ($q) => $q->where('user_id', $user->id)])->orderByDesc('is_pinned')->orderByDesc('published_at')->orderByDesc('id')->limit(3)->get()->map(fn (Announcement $item) => $this->row($item, $user))->all(), 'unacknowledged_count' => $count];
    }

    /** @return array<string,mixed> */
    public function recipients(Announcement $item, Request $request): array
    {
        $receipts = AnnouncementReceipt::where('announcement_id', $item->id)->get()->keyBy('user_id');
        $isClass = in_array($item->audience_type->value, ['class', 'grade_level'], true);
        if ($isClass) {
            $classes = $this->audience->targetClasses($item)->pluck('id');
            $students = Student::whereHas('enrollments', fn ($q) => $q->activeOn($this->settings->todayDate())->whereIn('class_id', $classes))->with('guardians.user')->orderBy('full_name')->get()->toBase();
            $rows = $students->map(function (Student $student) use ($receipts): array {
                $guardians = $student->guardians->toBase()->map(function (Guardian $guardian) use ($receipts): array {
                    $receipt = $guardian->user?->is_active ? $receipts->get($guardian->user_id) : null;

                    return ['name' => $guardian->name, 'has_account' => $guardian->user !== null && $guardian->user->is_active, 'read' => $receipt !== null, 'acknowledged' => $receipt?->acknowledged_at !== null];
                });

                return ['id' => $student->id, 'name' => $student->full_name, 'guardians' => $guardians->all(), 'read' => $guardians->contains('read', true), 'acknowledged' => $guardians->contains('acknowledged', true)];
            });
        } else {
            $rows = $this->audience->recipientUsers($item)->orderBy('username')->get()->map(function (User $user) use ($receipts): array {
                $receipt = $receipts->get($user->id);

                return ['id' => $user->id, 'name' => $user->username, 'guardians' => [], 'read' => $receipt !== null, 'acknowledged' => $receipt?->acknowledged_at !== null];
            });
        }
        $totals = ['total' => $rows->count(), 'read' => $rows->where('read', true)->count(), 'acknowledged' => $rows->where('acknowledged', true)->count(), 'accountless_guardians' => $isClass ? $rows->sum(fn (array $row) => collect($row['guardians'])->where('has_account', false)->count()) : $this->audience->accountlessGuardians($item)->count()];
        $filter = $request->string('status')->toString();
        if ($filter === 'unread') {
            $rows = $rows->where('read', false);
        }
        if ($filter === 'unacknowledged') {
            $rows = $rows->where('acknowledged', false);
        }
        $page = max(1, $request->integer('page', 1));
        $paginator = new LengthAwarePaginator($rows->values()->forPage($page, 30)->values(), $rows->count(), 30, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return ['kind' => $isClass ? 'students' : 'users', 'as_of' => $this->settings->todayDate(), 'totals' => $totals, 'rows' => $paginator, 'filter' => $filter];
    }
}
