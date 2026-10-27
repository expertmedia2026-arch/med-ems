<div class="w-full space-y-5">
    <div class="flex items-end justify-between gap-4">
        <div><h2 class="text-xl font-bold text-slate-900">Your notifications</h2><p class="mt-1 text-sm text-slate-500">Updates that need your attention.</p></div>
        @if ($user->unreadNotifications()->exists())
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf @method('PATCH')<button class="text-sm font-semibold text-teal-700 hover:underline">Mark all as read</button></form>
        @endif
    </div>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @forelse ($notifications as $notification)
            <a href="{{ route('notifications.open', $notification->id) }}" class="flex gap-4 border-b border-slate-100 p-4 last:border-0 hover:bg-slate-50 sm:p-5 {{ $notification->unread() ? 'bg-teal-50/40' : '' }}">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-teal-100 text-teal-700">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 0 1-6 0m6 0H9" /></svg>
                </span>
                <span class="min-w-0 flex-1"><span class="flex items-center gap-2 text-sm font-semibold text-slate-900">{{ $notification->data['title'] ?? 'Notification' }}@if ($notification->unread())<span class="h-2 w-2 rounded-full bg-teal-600"></span>@endif</span><span class="mt-1 block text-sm text-slate-600">{{ $notification->data['message'] ?? '' }}</span><span class="mt-2 block text-xs text-slate-400">{{ $notification->created_at->format('d M Y, g:i A') }} · {{ $notification->created_at->diffForHumans() }}</span></span>
            </a>
        @empty
            <p class="p-10 text-center text-sm text-slate-500">No notifications yet.</p>
        @endforelse
    </div>
    {{ $notifications->links() }}
</div>
