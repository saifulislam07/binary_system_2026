<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The member's in-app notifications (database channel).
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $page = $this->user($request)->notifications()->paginate(20)->withQueryString();

        return Inertia::render('notifications/Index', [
            'notifications' => $page->through(fn (DatabaseNotification $n) => $this->present($n)),
            'unread' => $this->user($request)->unreadNotifications()->count(),
        ]);
    }

    /**
     * JSON for the header bell: unread count + the latest few.
     */
    public function recent(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->limit(8)->get()->map(fn (DatabaseNotification $n) => $this->present($n))->values(),
        ]);
    }

    /**
     * Mark one read and go where it points (our own relative paths only).
     */
    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = $this->user($request)->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        return is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? redirect($url)
            : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->user($request)->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    /**
     * @return array{id: string, kind: string, title: string, message: string, url: string|null, read: bool, created_at: string|null}
     */
    private function present(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'kind' => (string) ($data['kind'] ?? 'general'),
            'title' => (string) ($data['title'] ?? 'Notification'),
            'message' => (string) ($data['message'] ?? ''),
            'url' => isset($data['url']) && is_string($data['url']) ? $data['url'] : null,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
