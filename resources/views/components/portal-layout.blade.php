@php
    $hospital = \App\Models\HospitalSetting::current();
    $user     = auth()->user();
    $hospitalModules = $hospital->hospital?->modules()->where('enabled', true)->pluck('module')->all() ?? [];
    $menu     = collect(config('portal_menu'))->filter(fn ($item) => ! isset($item['module']) || in_array($item['module'], $hospitalModules, true));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $hospital->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-hospital-theme :hospital="$hospital" />
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-800">

<div x-data="{ open: false }" class="min-h-screen lg:flex">

    {{-- Mobile overlay --}}
    <div x-show="open" x-cloak @click="open = false" class="fixed inset-0 z-30 bg-black/40 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside
        class="fixed inset-y-0 left-0 z-40 w-64 shrink-0 transform bg-teal-900 text-teal-50 transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
        :class="open ? 'translate-x-0' : '-translate-x-full'">

        <div class="flex min-h-16 items-center gap-3 border-b border-teal-800 px-6 py-3">
            @if ($hospital->logo_url)
                <img src="{{ $hospital->logo_url }}" alt="" class="h-9 w-9 shrink-0 rounded bg-white object-contain p-0.5">
            @else
                <span class="text-2xl">✚</span>
            @endif
            <span class="line-clamp-2 break-words text-sm font-semibold leading-tight tracking-wide" title="{{ $hospital->name }}">
                {{ $hospital->name }}
            </span>
        </div>

        <nav class="max-h-[calc(100vh-4rem)] space-y-1 overflow-y-auto p-4">
            @foreach ($menu as $item)
                @php
                    $isLink = $item['route'] !== '#';
                    $active = $isLink && request()->routeIs(...(array) ($item['active'] ?? $item['route']));
                @endphp

                @if ($isLink)
                    <a href="{{ route($item['route']) }}"
                       class="block rounded-lg px-4 py-2.5 text-sm font-medium transition {{ $active ? 'bg-teal-700 text-white' : 'text-teal-100 hover:bg-teal-800' }}">
                        {{ $item['label'] }}
                    </a>
                @else
                    <span class="flex cursor-not-allowed items-center justify-between rounded-lg px-4 py-2.5 text-sm font-medium text-teal-100/50">
                        {{ $item['label'] }}
                        <span class="rounded bg-teal-800 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-teal-200">Soon</span>
                    </span>
                @endif
            @endforeach
        </nav>
    </aside>

    {{-- Main --}}
    <div class="flex min-h-screen flex-1 flex-col">
        <header class="flex h-16 items-center justify-between border-b bg-white px-4 md:px-8">
            <div class="flex items-center gap-3">
                <button @click="open = true" aria-label="Open navigation menu" :aria-expanded="open.toString()" class="rounded-lg p-2 text-xl leading-none text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-600 lg:hidden">☰</button>
                @isset($header)
                    <h1 class="truncate text-base font-semibold sm:text-lg">{{ $header }}</h1>
                @endisset
            </div>

            <div x-data="{ menu: false }" class="relative">
                <button @click="menu = !menu" class="flex items-center gap-3">
                    <span class="hidden text-right sm:block">
                        <span class="block text-sm font-medium leading-tight">{{ $user->name }}</span>
                        <span class="block text-xs text-teal-700">Patient</span>
                    </span>
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-teal-700 text-sm font-semibold text-white">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </span>
                </button>

                <div x-show="menu" x-cloak @click.outside="menu = false"
                     class="absolute right-0 mt-2 w-44 rounded-lg border bg-white py-1 shadow-lg">
                    <a href="{{ route('portal.settings') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        Settings
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-slate-50">
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="min-w-0 flex-1 p-4 sm:p-5 lg:p-8">
            @if ($user->patient && ! $user->patient->registration_paid_at)
                @php($registrationFee = (float) $hospital->registration_fee)
                <div role="alert" class="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <p class="font-semibold">Registration fee outstanding</p>
                    <p class="mt-1">Your registration fee of ₦{{ number_format($registrationFee, 2) }} has not been paid. Please pay at the billing desk. You can continue using your patient portal.</p>
                </div>
            @endif

            @foreach (['success' => 'border-green-200 bg-green-50 text-green-800', 'error' => 'border-red-200 bg-red-50 text-red-800'] as $key => $classes)
                @if (session($key))
                    <div class="mb-6 rounded-lg border px-4 py-3 text-sm {{ $classes }}">{{ session($key) }}</div>
                @endif
            @endforeach

            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
