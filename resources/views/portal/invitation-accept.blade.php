<x-guest-layout>
    @php $input = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600'; @endphp

    <h1 class="text-xl font-semibold">Activate your patient portal</h1>
    <p class="mt-1 text-sm text-slate-500">Choose a password. Your email is verified when you continue.</p>

    <form method="POST" action="{{ route('portal.invitations.accept.store', $token) }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-slate-700">Name</label>
            <input type="text" value="{{ $invitation->patient->full_name }}" disabled class="{{ $input }} bg-slate-100 text-slate-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Email</label>
            <input type="email" value="{{ $invitation->email }}" disabled class="{{ $input }} bg-slate-100 text-slate-500">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
            <input id="password" name="password" type="password" required autofocus autocomplete="new-password" class="{{ $input }}">
            @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="{{ $input }}">
        </div>

        @unless ($invitation->patient->registration_paid_at)
            <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">
                You can log in once your registration fee has been paid at the cashier.
            </p>
        @endunless

        <button class="w-full rounded-lg bg-teal-700 py-2.5 text-sm font-medium text-white hover:bg-teal-800">
            Verify email and create account
        </button>
    </form>
</x-guest-layout>   