<x-guest-layout>
    <h1 class="text-xl font-semibold">This link is not valid</h1>
    <p class="mt-2 text-sm text-slate-600">
        It may have expired, already been used, or been replaced by a newer invitation.
        Please ask the front desk to send you a new one.
    </p>
    <a href="{{ route('login') }}" class="mt-6 inline-block text-sm text-teal-700 hover:underline">Go to login</a>
</x-guest-layout>