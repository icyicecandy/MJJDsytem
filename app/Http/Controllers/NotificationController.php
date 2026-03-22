<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->appNotifications()
            ->paginate(20)
            ->withQueryString();

        return view('notifications.index', compact('notifications'));
    }

    public function feed(Request $request): JsonResponse
    {
        $query = $request->user()->appNotifications();

        return response()->json([
            'unread_count' => (clone $query)->unread()->count(),
            'notifications' => (clone $query)
                ->take(8)
                ->get()
                ->map(fn (AppNotification $notification) => $this->serializeNotification($notification))
                ->values(),
            'index_url' => route('notifications.index'),
            'mark_all_read_url' => route('notifications.mark-all-read'),
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()
            ->appNotifications()
            ->unread()
            ->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok']);
        }

        return redirect()
            ->route('notifications.index')
            ->with('success', 'All notifications marked as read.');
    }

    public function redirect(Request $request, AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->markAsRead();

        return redirect()->to($notification->url ?: route('dashboard'));
    }

    private function serializeNotification(AppNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'time' => $notification->created_at?->diffForHumans(),
            'is_unread' => $notification->read_at === null,
            'url' => route('notifications.redirect', $notification),
        ];
    }
}
