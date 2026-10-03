<?php

namespace App\Http\Controllers\Excuses;

use App\Enums\ExcuseStatus;
use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Excuses\StoreExcuseRequest;
use App\Models\Excuse;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\Notifications\Message;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\Recipient;
use App\Services\Notifications\RecipientResolver;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Guardian portal: submit absence excuses for linked children and track
 * their review status. One child and one date range per submission so the
 * attachment binds accurately (spec 04 §Out of scope).
 */
class GuardianExcuseController extends Controller
{
    private const DISK = 'local';

    private const DIRECTORY = 'excuses';

    public function __construct(
        private readonly SchoolSettings $settings,
        private readonly NotificationDispatcher $dispatcher,
        private readonly RecipientResolver $resolver,
    ) {}

    public function index(Request $request): Response
    {
        $guardian = $request->user()->guardian;

        if ($guardian === null) {
            return Inertia::render('excuses/my-excuses', [
                'children' => [],
                'excuses' => null,
            ]);
        }

        $children = $guardian->students()
            ->with('currentEnrollment.schoolClass:id,name')
            ->orderBy('full_name')
            ->get();

        /** @var LengthAwarePaginator<int, Excuse> $excuses */
        $excuses = Excuse::query()
            ->whereIn('student_id', $children->modelKeys())
            ->with('student.currentEnrollment.schoolClass:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('excuses/my-excuses', [
            'children' => $children->map(fn ($student): array => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'class_name' => $student->currentEnrollment?->schoolClass?->name,
            ]),
            'excuses' => $excuses->through(fn (Excuse $excuse) => $this->row($excuse)),
        ]);
    }

    public function store(StoreExcuseRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $guardian = Guardian::where('user_id', $request->user()->id)->first();
        $validated['submitted_by_guardian_id'] = $guardian?->id;
        $validated['attachment_path'] = $this->storeAttachment($request);
        $validated['status'] = ExcuseStatus::Pending;

        $excuse = Excuse::create($validated);

        /** @var Student|null $student */
        $student = Student::with('currentEnrollment.schoolClass.teacher.user')->find($validated['student_id']);
        $recipients = $this->resolver->forAdmins();

        $schoolClass = $student instanceof Student ? $student->currentEnrollment?->schoolClass : null;
        if ($schoolClass !== null) {
            $homeroom = $this->resolver->forHomeroomTeacher($schoolClass);
            if ($homeroom !== null) {
                $recipients->push($homeroom);
            }
        }

        $uniqueRecipients = $recipients->unique(fn (Recipient $r) => $r->key())->values();
        $studentName = $student instanceof Student ? $student->full_name : 'Siswa';
        $message = new Message(
            title: 'Pengajuan Izin Baru',
            body: "Pengajuan izin untuk {$studentName} menunggu peninjauan.",
            route: 'excuses.index',
        );

        $this->dispatcher->dispatch(
            type: NotificationType::ExcuseSubmitted,
            recipients: $uniqueRecipients,
            message: $message,
            dedupeBase: "excuse:{$excuse->id}:submitted",
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Excuse submitted — awaiting review.']);

        return to_route('excuses.my');
    }

    /**
     * Proof upload on the private local disk under an opaque token name —
     * served back only through the authorized attachment route.
     */
    private function storeAttachment(StoreExcuseRequest $request): ?string
    {
        $file = $request->file('attachment');

        if ($file === null) {
            return null;
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $token = bin2hex(random_bytes(20));
        $stored = $file->storeAs(self::DIRECTORY, $token.'.'.$extension, self::DISK);

        if ($stored === false) {
            throw new RuntimeException('Could not persist the excuse attachment.');
        }

        return $stored;
    }

    /**
     * @return array{id: int, child_name: string, class_name: string|null, type: string, start_date: string, end_date: string, reason: string, has_attachment: bool, attachment_url: string|null, status: string, review_note: string|null, submitted_at: string}
     */
    private function row(Excuse $excuse): array
    {
        return [
            'id' => $excuse->id,
            'child_name' => $excuse->student->full_name,
            'class_name' => $excuse->student->currentEnrollment?->schoolClass?->name,
            'type' => $excuse->type->value,
            'start_date' => $excuse->start_date->toDateString(),
            'end_date' => $excuse->end_date->toDateString(),
            'reason' => $excuse->reason,
            'has_attachment' => $excuse->attachment_path !== null,
            'attachment_url' => $excuse->attachment_path !== null
                ? route('excuses.attachment', ['excuse' => $excuse->id])
                : null,
            'status' => $excuse->status->value,
            'review_note' => $excuse->review_note,
            'submitted_at' => $excuse->created_at?->tz($this->settings->timezone())->format('Y-m-d H:i'),
        ];
    }
}
