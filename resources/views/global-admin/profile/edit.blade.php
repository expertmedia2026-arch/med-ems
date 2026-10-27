@extends('layouts.global-admin', ['title' => 'Profile'])

@section('content')
<div class="w-full space-y-7">
    <div><p class="text-xs font-bold uppercase tracking-[.16em] text-[#c81020]">Account</p><h1 class="mt-2 text-2xl font-bold tracking-tight">Profile</h1><p class="mt-1 text-sm text-slate-600">Update your platform admin name, email, and password.</p></div>
    @if (session('success')) <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-green-800">{{ session('success') }}</div> @endif
    @if ($errors->any()) <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
    <div class="grid gap-6 xl:grid-cols-2">
    <form method="POST" action="{{ route('global-admin.profile.update') }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf @method('PUT')
        <h2 class="text-lg font-bold">Profile details</h2>
        <label class="block text-sm font-semibold">Name<input name="name" value="{{ old('name', $user->name) }}" required class="mt-2 block w-full rounded-md border-slate-300 font-normal"></label>
        <label class="block text-sm font-semibold">Email<input type="email" name="email" value="{{ old('email', $user->email) }}" required class="mt-2 block w-full rounded-md border-slate-300 font-normal"></label>
        <div class="flex justify-end"><button class="rounded-md bg-[#e51d2a] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#c81020]">Save profile</button></div>
    </form>
    <form method="POST" action="{{ route('global-admin.profile.password') }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf @method('PUT')
        <h2 class="text-lg font-bold">Change password</h2>
        <label class="block text-sm font-semibold">Current password<input type="password" name="current_password" required autocomplete="current-password" class="mt-2 block w-full rounded-md border-slate-300 font-normal"></label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-semibold">New password<input type="password" name="password" required minlength="8" autocomplete="new-password" class="mt-2 block w-full rounded-md border-slate-300 font-normal"></label>
            <label class="block text-sm font-semibold">Confirm new password<input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="mt-2 block w-full rounded-md border-slate-300 font-normal"></label>
        </div>
        <div class="flex justify-end"><button class="rounded-md border border-slate-300 px-4 py-2.5 text-sm font-bold hover:bg-slate-50">Change password</button></div>
    </form>
    </div>
</div>
@endsection
