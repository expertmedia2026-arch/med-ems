@php
    $notificationUser = auth()->user();
    $unreadNotificationCount = $notificationUser?->unreadNotifications()->count() ?? 0;
    $recentNotifications = $notificationUser?->notifications()->latest()->limit(5)->get() ?? collect();
@endphp

<div x-data="{ notificationsOpen: false }" class="relative">
    <button type="button" @click="notificationsOpen = !notificationsOpen" @keydown.escape.window="notificationsOpen = false"
            :aria-expanded="notificationsOpen.toString()" aria-label="Notifications"
            class="relative flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm hover:border-teal-600 hover:text-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-600/30">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-4.5-5.8V4a1.5 1.5 0 0 0-3 0v1.2A6 6 0 0 0 6 11v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 0 1-6 0m6 0H9" />
        </svg>
        @if ($unreadNotificationCount > 0)
            <span class="absolute -right-1 -top-1 min-w-5 rounded-full bg-rose-600 px-1 text-center text-[10px] font-bold leading-5 text-white">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
        @endif
    </button>

    <div x-cloak x-show="notificationsOpen" @click.outside="notificationsOpen = false" x-transition
         class="absolute right-0 z-50 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <div><h2 class="text-sm font-bold text-slate-900">Notifications</h2><p class="text-xs text-slate-500">{{ $unreadNotificationCount }} unread</p></div>
            <a href="{{ route('notifications.index') }}" class="text-xs font-semibold text-teal-700 hover:underline">View all</a>
        </div>
        <div class="max-h-96 overflow-y-auto">
            @forelse ($recentNotifications as $notification)
                <a href="{{ route('notifications.open', $notification->id) }}" class="flex gap-3 border-b border-slate-50 px-4 py-3 last:border-0 hover:bg-slate-50 {{ $notification->unread() ? 'bg-teal-50/50' : '' }}">
                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-teal-100 text-teal-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 0 1-6 0m6 0H9" /></svg>
                    </span>
                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-slate-900">{{ $notification->data['title'] ?? 'Notification' }}</span><span class="mt-0.5 block text-xs leading-5 text-slate-600">{{ $notification->data['message'] ?? '' }}</span><span class="mt-1 block text-[11px] text-slate-400">{{ $notification->created_at->diffForHumans() }}</span></span>
                    @if ($notification->unread())<span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-teal-600"></span>@endif
                </a>
            @empty
                <p class="px-4 py-8 text-center text-sm text-slate-500">You’re all caught up.</p>
            @endforelse
        </div>
        @if ($unreadNotificationCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}" class="border-t border-slate-100 p-2">
                @csrf @method('PATCH')
                <button class="w-full rounded-lg px-3 py-2 text-xs font-semibold text-teal-700 hover:bg-teal-50">Mark all as read</button>
            </form>
        @endif
    </div>
</div>
