@extends('layouts.global-admin', ['title' => 'Platform Admins'])

@section('content')
<div class="w-full space-y-7">
    <div><p class="text-xs font-bold uppercase tracking-[.16em] text-[#c81020]">Access management</p><h1 class="mt-2 text-2xl font-bold tracking-tight">Platform Admins</h1><p class="mt-1 text-sm text-slate-600">Add sub-admin accounts for trusted people who manage the MedEMS platform.</p></div>
    @if (session('success')) <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-green-800">{{ session('success') }}</div> @endif
    @if ($errors->any()) <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

    <form method="POST" action="{{ route('global-admin.admins.store') }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        <div><h2 class="text-lg font-bold">Add sub-admin</h2><p class="mt-1 text-sm text-slate-600">Sub-admins get platform-wide access. Set their initial password and share it with them directly.</p></div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <label class="block text-sm font-semibold">Full name<input name="name" value="{{ old('name') }}" required maxlength="255" class="mt-2 block w-full rounded-md border-slate-300 font-normal"></label>
            <label class="block text-sm font-semibold">Email address<input type="email" name="email" value="{{ old('email') }}" required maxlength="255" class="mt-2 block w-full rounded-md border-slate-300 font-normal"></label>
            <label class="block text-sm font-semibold">Initial password<input type="password" name="password" required minlength="8" autocomplete="new-password" class="mt-2 block w-full rounded-md border-slate-300 font-normal"></label>
            <label class="block text-sm font-semibold">Confirm password<input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="mt-2 block w-full rounded-md border-slate-300 font-normal"></label>
        </div>
        <div class="flex justify-end"><button class="rounded-md bg-[#e51d2a] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#c81020]">Add sub-admin</button></div>
    </form>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold">Platform admin accounts</h2><p class="mt-1 text-xs text-slate-500">Deactivated accounts can no longer sign in.</p></div>
        <div class="overflow-x-auto"><table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Admin</th><th class="px-5 py-3">Email</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($admins as $admin)
                    <tr><td class="px-5 py-4 font-semibold">{{ $admin->name }} @if ($admin->id === auth()->id())<span class="ml-1 text-xs font-normal text-slate-500">You</span>@endif</td><td class="px-5 py-4 text-slate-600">{{ $admin->email }}</td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $admin->is_active ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ $admin->is_active ? 'Active' : 'Inactive' }}</span></td><td class="px-5 py-4 text-right">
                        @if ($admin->id !== auth()->id())
                            <form method="POST" action="{{ route('global-admin.admins.toggle', $admin) }}">@csrf @method('PATCH')<button class="font-semibold {{ $admin->is_active ? 'text-[#c81020]' : 'text-green-700' }}">{{ $admin->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                        @else <span class="text-xs text-slate-400">Current account</span> @endif
                    </td></tr>
                @empty <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">No platform admins found.</td></tr> @endforelse
            </tbody>
        </table></div>
    </section>
</div>
@endsection
