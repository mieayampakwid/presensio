<?php

namespace App\Http\Controllers\Announcements;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Announcements\SaveAnnouncementRequest;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\SchoolClass;
use App\Services\Announcements\AnnouncementAudience;
use App\Services\Announcements\AnnouncementReadModel;
use App\Services\Announcements\AnnouncementService;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementAudience $audience, private readonly AnnouncementReadModel $readModel) {}

    public function manage(Request $request): Response
    {
        abort_unless($this->audience->manager($request->user()), 403);

        return $this->listing($request);
    }

    public function classIndex(Request $request, SchoolClass $schoolClass): Response
    {
        abort_unless($this->audience->canManage($request->user(), classId: $schoolClass->id), 403);

        return $this->listing($request, $schoolClass);
    }

    private function listing(Request $request, ?SchoolClass $schoolClass = null): Response
    {
        $query = $this->audience->visibleTo($request->user(), false)->with(['author', 'receipts' => fn ($q) => $q->where('user_id', $request->user()->id)])
            ->when($schoolClass !== null, fn ($q) => $q->where('class_id', $schoolClass->id));

        return Inertia::render('announcements/index', [
            'announcements' => $query->orderByDesc('is_pinned')->orderByDesc('published_at')->orderByDesc('id')->paginate(10)->through(fn (Announcement $item) => $this->readModel->row($item, $request->user())),
            'filters' => ['q' => '', 'from' => '', 'to' => ''], 'mode' => 'manage', 'classContext' => $schoolClass?->only(['id', 'name']),
            'canManage' => $this->audience->manager($request->user()), 'canCreate' => true,
        ]);
    }

    public function create(Request $request, ?SchoolClass $schoolClass = null): Response
    {
        abort_unless($this->audience->canManage($request->user(), classId: $schoolClass?->id), 403);

        return $this->form($request, null, $schoolClass);
    }

    public function edit(Request $request, Announcement $announcement): Response
    {
        abort_unless($this->audience->canManage($request->user(), $announcement), 403);

        return $this->form($request, $announcement);
    }

    private function form(Request $request, ?Announcement $item = null, ?SchoolClass $schoolClass = null): Response
    {
        $manager = $this->audience->manager($request->user());

        return Inertia::render('announcements/form', [
            'announcement' => $item ? $this->readModel->row($item, $request->user(), true) : null,
            'classContext' => $schoolClass?->only(['id', 'name']), 'canManage' => $manager,
            'classes' => SchoolClass::where('academic_year_id', AcademicYear::active()?->id)->when(! $manager, fn ($q) => $q->whereIn('id', $this->audience->teachingClassIds($request->user())))->orderBy('name')->get(['id', 'name']),
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role) => $role->value),
            'timezone' => app(SchoolSettings::class)->timezone(),
            'schoolNow' => app(SchoolSettings::class)->now()->format('Y-m-d\\TH:i:s'),
        ]);
    }

    public function store(SaveAnnouncementRequest $request, AnnouncementService $service, ?SchoolClass $schoolClass = null): RedirectResponse
    {
        $item = $service->save($request->user(), $request->validated(), attachment: $request->file('attachment'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengumuman disimpan.']);

        return to_route('announcements.show', $item);
    }

    public function update(SaveAnnouncementRequest $request, Announcement $announcement, AnnouncementService $service): RedirectResponse
    {
        $item = $service->save($request->user(), $request->validated(), $announcement, $request->file('attachment'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengumuman diperbarui.']);

        return to_route('announcements.show', $item);
    }

    public function destroy(Request $request, Announcement $announcement, AnnouncementService $service): RedirectResponse
    {
        $service->delete($request->user(), $announcement);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengumuman dihapus.']);

        return to_route('announcements.index');
    }
}
