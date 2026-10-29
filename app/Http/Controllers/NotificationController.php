<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * Presents notifications for the signed-in user and handles notification acknowledgements.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'user' => $request->user(),
            'notifications' => $request->user()->notifications()->latest()->paginate(20),
        ]);
    }

    public function latest(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->limit(5)->get();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications->map(fn ($notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Notification',
                'message' => $notification->data['message'] ?? '',
                'created_at' => $notification->created_at->diffForHumans(),
                'unread' => $notification->unread(),
                'open_url' => route('notifications.open', $notification->id),
            ])->values(),
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return redirect()->to($this->safeDestination($item));
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    private function safeDestination(DatabaseNotification $notification): string
    {
        $url = (string) ($notification->data['url'] ?? '');
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($path) || ! str_starts_with($path, '/')) {
            return route('notifications.index');
        }

        return $path.($query ? '?'.$query : '');
    }
}
