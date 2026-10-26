<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ request()->routeIs('login') ? 'Sign in | MedEMS' : config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-hospital-theme :hospital="\App\Models\HospitalSetting::current()" />
    </head>
    <body class="font-sans text-gray-900 antialiased {{ request()->routeIs('login') ? 'medems-login' : '' }}">
        @if (request()->routeIs('login'))
            <main class="login-shell">
                <section class="login-aside">
                    <a class="login-aside__back" href="{{ url('/') }}"><span aria-hidden="true">←</span> Back to MedEMS</a>
                    <div class="login-aside__content">
                        <a class="login-brand" href="{{ url('/') }}" aria-label="MedEMS home">
                            <img src="{{ asset('images/expert-media-logo.png') }}" alt="Expert Media Solutions Ltd">
                            <span class="login-brand__name">Med<span>EMS</span></span>
                        </a>
                        <p class="eyebrow eyebrow--light">A clearer way to care</p>
                        <h1>Care teams,<br>connected.</h1>
                        <p class="login-aside__description">Your hospital’s day-to-day work, in one place. Pick up right where your team left off.</p>
                    </div>
                    <div class="login-aside__footer"><span class="login-aside__pulse"></span> A connected workspace for better care <span>© {{ date('Y') }} MedEMS</span></div>
                    <div class="login-aside__pattern" aria-hidden="true"></div>
                </section>
                <section class="login-main">
                    <div class="login-card">{{ $slot }}</div>
                    <p class="login-main__footnote">Secure access for your hospital team</p>
                </section>
            </main>
        @else
            <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
                <div>
                    <a href="/">
                        <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
                    </a>
                </div>
                <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                    {{ $slot }}
                </div>
            </div>
        @endif
    </body>
</html>
