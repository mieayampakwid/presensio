<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Display a paginated listing of notifications for the authenticated user.
     */
    public function index(Request $request): Response
    {
        $unreadOnly = $request->boolean('unread');

        $query = $request->user()->notifications();

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate(20)->withQueryString()->through(fn ($notification) => [
            'id' => $notification->id,
            'data' => $notification->data,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->diffForHumans(),
        ]);

        return Inertia::render('notifications/index', [
            'notifications' => $notifications,
            'unread_only' => $unreadOnly,
        ]);
    }

    /**
     * Mark a single notification as read and redirect to its URL.
     */
    public function read(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        $url = (string) ($notification->data['url'] ?? '/dashboard');

        if ($request->wantsJson()) {
            return response()->json(['url' => $url]);
        }

        return redirect()->to($url);
    }

    /**
     * Mark all notifications as read for the authenticated user.
     */
    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
