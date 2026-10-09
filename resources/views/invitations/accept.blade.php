<x-guest-layout>
    @php $input = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600'; @endphp

    <h1 class="text-xl font-semibold">Join as {{ $invitation->role->label() }}</h1>
    <p class="mt-1 text-sm text-slate-500">Set your name and password to activate your account.</p>

    <form method="POST" action="{{ route('invitations.accept.store', $token) }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-slate-700">Email</label>
            <input type="email" value="{{ $invitation->email }}" disabled class="{{ $input }} bg-slate-100 text-slate-500">
        </div>

        <div>
            <label for="name" class="block text-sm font-medium text-slate-700">Full name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus class="{{ $input }}">
            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="{{ $input }}">
            @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="{{ $input }}">
        </div>

        <button class="w-full rounded-lg bg-teal-700 py-2.5 text-sm font-medium text-white hover:bg-teal-800">
            Activate account
        </button>
    </form>
</x-guest-layout>