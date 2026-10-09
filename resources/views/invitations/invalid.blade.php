<x-guest-layout>
    <h1 class="text-xl font-semibold">This invitation is not valid</h1>
    <p class="mt-2 text-sm text-slate-600">
        The link may have expired, already been used, or been revoked. Ask your administrator to send a new invitation.
    </p>
    <a href="{{ route('login') }}" class="mt-6 inline-block text-sm text-teal-700 hover:underline">Go to login</a>
</x-guest-layout>