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
    <div x-data="{ mobileNavOpen: false }" @keydown.escape.window="mobileNavOpen = false" class="min-h-screen xl:flex">
        <div x-cloak x-show="mobileNavOpen" x-transition.opacity @click="mobileNavOpen = false" class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-[2px] xl:hidden" aria-hidden="true"></div>
        <aside id="global-admin-navigation" :class="mobileNavOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 flex h-screen w-72 max-w-[calc(100vw-3rem)] min-w-0 flex-col bg-[#171719] text-white shadow-2xl transition-transform duration-200 ease-out xl:sticky xl:top-0 xl:z-auto xl:h-screen xl:w-64 xl:max-w-none xl:shrink-0 xl:translate-x-0 xl:shadow-none">
            <a href="{{ route('global-admin.dashboard') }}" class="flex items-center gap-3 border-b border-white/10 px-4 py-4 sm:px-6 xl:py-5">
                <span class="grid h-10 w-10 place-items-center rounded-lg bg-[#e51d2a] text-xl font-black">M</span>
                <span class="text-xl font-extrabold tracking-tight">Med<span class="text-[#ff4651]">EMS</span><span class="mt-1 block text-[10px] font-semibold uppercase tracking-[.18em] text-white/50">Platform admin</span></span>
            </a>
            <button type="button" @click="mobileNavOpen = false" class="absolute right-3 top-4 grid h-10 w-10 place-items-center rounded-lg text-white/70 hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/70 xl:hidden" aria-label="Close navigation menu">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M18 6 6 18" /></svg>
            </button>
            <nav aria-label="Platform administration" class="flex flex-1 flex-col gap-1 overflow-y-auto px-3 py-4">
                @php($navItems = [
                    ['route' => 'global-admin.dashboard', 'label' => 'Overview', 'match' => 'global-admin.dashboard'],
                    ['route' => 'global-admin.hospitals.index', 'label' => 'Hospitals', 'match' => 'global-admin.hospitals.*'],
                    ['route' => 'global-admin.hospitals.create', 'label' => 'Add new hospital', 'match' => 'global-admin.hospitals.create'],
                    ['route' => 'global-admin.configuration', 'label' => 'Configuration', 'match' => 'global-admin.configuration'],
                    ['route' => 'global-admin.admins.index', 'label' => 'Platform Admins', 'match' => 'global-admin.admins.*'],
                ])
                @foreach ($navItems as $item)
                    @php($isActive = request()->routeIs($item['match']) && !($item['route'] === 'global-admin.hospitals.index' && request()->routeIs('global-admin.hospitals.create')))
                    <a href="{{ route($item['route']) }}" @click="mobileNavOpen = false" class="whitespace-nowrap rounded-lg px-4 py-3 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-white/70 {{ $isActive ? 'bg-[#e51d2a] text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>
            <div class="border-t border-white/10 px-6 py-4 text-xs text-white/50">Platform workspace</div>
        </aside>
        <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between border-b border-white/10 bg-[#171719] px-4 py-3 text-white xl:hidden">
                <a href="{{ route('global-admin.dashboard') }}" class="flex min-w-0 items-center gap-2.5">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[#e51d2a] text-lg font-black">M</span>
                    <span class="truncate text-lg font-extrabold tracking-tight">Med<span class="text-[#ff4651]">EMS</span></span>
                </a>
                <button type="button" @click="mobileNavOpen = true" :aria-expanded="mobileNavOpen.toString()" aria-controls="global-admin-navigation" aria-label="Open navigation menu" class="grid h-10 w-10 shrink-0 place-items-center rounded-lg text-white/80 hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/70">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
            </div>
            <header class="flex min-h-16 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3 sm:px-6 lg:px-8">
                <div class="min-w-0 truncate text-[10px] font-semibold uppercase tracking-[.14em] text-slate-500 sm:text-xs sm:tracking-[.16em]">Platform management</div>
                <div class="flex items-center gap-3">
                    <x-notification-bell />
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
                </div>
            </header>
            <main class="mx-auto w-full max-w-[1500px] min-w-0 px-4 py-6 sm:px-6 sm:py-8 lg:px-8 lg:py-9">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
