<?php

namespace App\Http\Requests\Announcements;

use App\Enums\AnnouncementAudienceType;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\SchoolClass;
use App\Services\Announcements\AnnouncementAudience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        $audience = app(AnnouncementAudience::class);
        $item = $this->route('announcement');
        $class = $this->route('school_class');
        $classId = $class instanceof SchoolClass ? $class->id : $this->integer('class_id');
        if ($audience->manager($user)) {
            return true;
        }
        if ($this->boolean('notify_urgent') || $this->input('audience_type', 'class') !== 'class') {
            return false;
        }

        return $audience->canManage($user, $item instanceof Announcement ? $item : null, $classId ?: null);
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $type = $this->input('audience_type', 'class');

        return [
            'title' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:50000'],
            'audience_type' => ['required', Rule::enum(AnnouncementAudienceType::class)],
            'audience_role' => [$type === 'role' ? 'required' : 'prohibited', 'nullable', Rule::enum(UserRole::class)],
            'audience_grade_level' => [$type === 'grade_level' ? 'required' : 'prohibited', 'nullable', 'integer', 'between:1,12'],
            'class_id' => [$type === 'class' ? 'required' : 'prohibited', 'nullable', 'integer', Rule::exists('classes', 'id')->where('academic_year_id', ($this->route('announcement') instanceof Announcement && $this->route('announcement')->isLive() ? $this->route('announcement')->academic_year_id : AcademicYear::active()?->id) ?? 0)],
            'published_at' => ['nullable', 'date'], 'is_pinned' => ['required', 'boolean'], 'requires_acknowledgement' => ['required', 'boolean'], 'notify_urgent' => ['required', 'boolean'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], 'remove_attachment' => ['sometimes', 'boolean'],
            'academic_year_id' => ['prohibited'], 'created_by_user_id' => ['prohibited'], 'notification_recipients' => ['prohibited'], 'notifications_dispatched_at' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $class = $this->route('school_class');
        if ($class instanceof SchoolClass) {
            abort_if($this->filled('class_id') && $this->integer('class_id') !== $class->id, 403);
            abort_if($this->filled('audience_type') && $this->input('audience_type') !== 'class', 403);
            $this->merge(['class_id' => $class->id, 'audience_type' => 'class']);
        }
    }
}
