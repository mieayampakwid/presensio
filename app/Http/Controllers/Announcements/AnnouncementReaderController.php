<?php

namespace App\Http\Controllers\Announcements;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Announcements\AnnouncementAudience;
use App\Services\Announcements\AnnouncementReadModel;
use App\Services\Announcements\AnnouncementService;
use App\Services\Announcements\MarkdownRenderer;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnnouncementReaderController extends Controller
{
    public function __construct(private readonly AnnouncementAudience $audience, private readonly AnnouncementReadModel $readModel) {}

    public function index(Request $request, SchoolSettings $settings): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])]]);
        $query = $this->audience->visibleTo($request->user())->with(['author', 'receipts' => fn ($q) => $q->where('user_id', $request->user()->id)]);
        if (! empty($filters['q'])) {
            $query->where(fn ($q) => $q->whereLike('title', '%'.$filters['q'].'%')->orWhereLike('body', '%'.$filters['q'].'%'));
        }
        if (! empty($filters['from'])) {
            $query->where('published_at', '>=', Carbon::parse($filters['from'], $settings->timezone())->startOfDay()->utc());
        }
        if (! empty($filters['to'])) {
            $query->where('published_at', '<=', Carbon::parse($filters['to'], $settings->timezone())->endOfDay()->utc());
        }

        return Inertia::render('announcements/index', ['announcements' => $query->orderByDesc('is_pinned')->orderByDesc('published_at')->orderByDesc('id')->paginate(10)->withQueryString()->through(fn (Announcement $item) => $this->readModel->row($item, $request->user())), 'filters' => array_merge(['q' => '', 'from' => '', 'to' => ''], $filters), 'mode' => 'feed', 'classContext' => null, 'canManage' => $this->audience->manager($request->user()), 'canCreate' => false]);
    }

    public function show(Request $request, Announcement $announcement, AnnouncementService $service, MarkdownRenderer $markdown): Response
    {
        $service->read($request->user(), $announcement);

        return Inertia::render('announcements/show', ['announcement' => $this->readModel->row($announcement, $request->user(), true), 'bodyHtml' => $markdown->render($announcement->body)]);
    }

    public function acknowledge(Request $request, Announcement $announcement, AnnouncementService $service): RedirectResponse
    {
        $service->read($request->user(), $announcement, true);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Konfirmasi membaca tersimpan.']);

        return to_route('announcements.show', $announcement);
    }

    public function attachment(Request $request, Announcement $announcement): StreamedResponse
    {
        abort_unless($this->audience->visibleTo($request->user(), false)->whereKey($announcement->id)->exists(), 403);
        abort_if($announcement->attachment_path === null || ! Storage::disk('local')->exists($announcement->attachment_path), 404);

        return Storage::disk('local')->download($announcement->attachment_path, $announcement->attachment_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function recipients(Request $request, Announcement $announcement): Response
    {
        abort_unless($this->audience->canManage($request->user(), $announcement), 403);
        $request->validate(['status' => ['nullable', 'in:unread,unacknowledged']]);

        return Inertia::render('announcements/recipients', ['announcement' => $this->readModel->row($announcement, $request->user()), 'recipientReport' => $this->readModel->recipients($announcement, $request)]);
    }
}
