<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Platform' }} · MedEMS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white font-sans antialiased text-slate-900">
    <div class="min-h-screen md:flex">
        <aside class="flex w-full shrink-0 flex-col bg-[#171719] text-white md:sticky md:top-0 md:h-screen md:w-64">
            <a href="{{ route('global-admin.dashboard') }}" class="flex items-center gap-3 border-b border-white/10 px-6 py-5">
                <span class="grid h-10 w-10 place-items-center rounded-lg bg-[#e51d2a] text-xl font-black">M</span>
                <span class="text-xl font-extrabold tracking-tight">Med<span class="text-[#ff4651]">EMS</span><span class="mt-1 block text-[10px] font-semibold uppercase tracking-[.18em] text-white/50">Platform admin</span></span>
            </a>
            <nav class="flex gap-1 overflow-x-auto px-3 py-4 md:flex-1 md:flex-col md:overflow-visible">
                @php($navItems = [
                    ['route' => 'global-admin.dashboard', 'label' => 'Overview', 'match' => 'global-admin.dashboard'],
                    ['route' => 'global-admin.hospitals.index', 'label' => 'Hospitals', 'match' => 'global-admin.hospitals.*'],
                    ['route' => 'global-admin.hospitals.create', 'label' => 'Add new hospital', 'match' => 'global-admin.hospitals.create'],
                    ['route' => 'global-admin.configuration', 'label' => 'Configuration', 'match' => 'global-admin.configuration'],
                    ['route' => 'global-admin.admins.index', 'label' => 'Platform Admins', 'match' => 'global-admin.admins.*'],
                ])
                @foreach ($navItems as $item)
                    @php($isActive = request()->routeIs($item['match']) && !($item['route'] === 'global-admin.hospitals.index' && request()->routeIs('global-admin.hospitals.create')))
                    <a href="{{ route($item['route']) }}" class="whitespace-nowrap rounded-lg px-4 py-3 text-sm font-semibold transition {{ $isActive ? 'bg-[#e51d2a] text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>
            <div class="hidden border-t border-white/10 px-6 py-4 text-xs text-white/50 md:block">Platform workspace</div>
        </aside>
        <div class="min-w-0 flex-1">
            <header class="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-5 sm:px-8">
                <div class="text-xs font-semibold uppercase tracking-[.16em] text-slate-500">Platform management</div>
                <div x-data="{ userMenu: false }" class="relative">
                    <button type="button" @click="userMenu = ! userMenu" @keydown.escape.window="userMenu = false" :aria-expanded="userMenu.toString()" aria-label="Open account menu" class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-sm hover:border-[#e51d2a] hover:text-[#c81020] focus:outline-none focus:ring-2 focus:ring-[#e51d2a]/30">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM4.5 20a7.5 7.5 0 0 1 15 0" /></svg>
                    </button>
                    <div x-cloak x-show="userMenu" @click.outside="userMenu = false" x-transition class="absolute right-0 z-50 mt-2 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white py-2 shadow-xl">
                        <div class="border-b border-slate-100 px-4 py-3"><p class="truncate text-sm font-bold text-slate-900">{{ auth()->user()->name }}</p><p class="mt-0.5 truncate text-xs text-slate-500">{{ auth()->user()->email }}</p></div>
                        <a href="{{ route('global-admin.profile.edit') }}" class="block px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Profile</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="block w-full px-4 py-2.5 text-left text-sm font-semibold text-[#c81020] hover:bg-red-50">Sign out</button></form>
                    </div>
                </div>
            </header>
            <main class="mx-auto max-w-[1500px] px-5 py-7 sm:px-8 sm:py-9">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
