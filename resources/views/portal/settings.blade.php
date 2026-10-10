<x-portal-layout>
    <x-slot name="header">Settings</x-slot>

    <div class="max-w-3xl rounded-xl border bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-lg font-semibold">Change password</h2>
        <p class="mt-1 text-sm text-slate-500">Enter your current password, then choose a new one.</p>

        <div class="mt-6 max-w-xl">
            @include('profile.partials.update-password-form')
        </div>
    </div>
</x-portal-layout>